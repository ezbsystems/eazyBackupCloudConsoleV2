<?php
/**
 * Cloud NAS - Mount Snapshot (Time Machine)
 * Mounts a Kopia snapshot as a read-only drive for file browsing/recovery
 */

require_once __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Client\UuidBinary;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Session timeout'], 200))->send();
    exit;
}
$clientId = $ca->getUserID();

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

$hasJobIdPk = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'job_id');
$hasRunIdPk = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'run_id');
$rawJobId = trim((string) ($input['job_id'] ?? ''));
$jobIdIsUuid = $hasJobIdPk && UuidBinary::isUuid($rawJobId);
$manifestId = trim($input['manifest_id'] ?? '');
$agentUuid = trim((string) ($input['agent_uuid'] ?? ''));

$jobIdEmpty = $jobIdIsUuid ? false : (intval($rawJobId) <= 0);
if ($jobIdEmpty || empty($manifestId) || $agentUuid === '') {
    (new JsonResponse(['status' => 'error', 'message' => 'Job ID, manifest ID, and agent UUID are required'], 200))->send();
    exit;
}

try {
    // Verify job belongs to client
    $jobQuery = Capsule::table('s3_cloudbackup_jobs')
        ->where('client_id', $clientId);
    if ($jobIdIsUuid) {
        $jobQuery->whereRaw('job_id = ' . UuidBinary::toDbExpr(UuidBinary::normalize($rawJobId)));
    } else {
        $jobQuery->where('id', intval($rawJobId));
    }
    $job = $jobQuery->first();

    if (!$job) {
        (new JsonResponse(['status' => 'error', 'message' => 'Job not found'], 200))->send();
        exit;
    }

    // Verify agent belongs to client
    $agent = Capsule::table('s3_cloudbackup_agents')
        ->where('agent_uuid', $agentUuid)
        ->where('client_id', $clientId)
        ->first();

    if (!$agent) {
        (new JsonResponse(['status' => 'error', 'message' => 'Agent not found'], 200))->send();
        exit;
    }

    // Verify manifest exists in a completed run
    $runQuery = Capsule::table('s3_cloudbackup_runs')
        ->where('manifest_id', $manifestId)
        ->where('status', 'success');
    if ($jobIdIsUuid) {
        $runQuery->whereRaw('job_id = ' . UuidBinary::toDbExpr(UuidBinary::normalize($rawJobId)));
    } else {
        $runQuery->where('job_id', intval($rawJobId));
    }
    if ($hasRunIdPk) {
        $runQuery->addSelect(Capsule::raw('*, BIN_TO_UUID(run_id) as run_id_str'));
    }
    $run = $runQuery->first();

    if (!$run) {
        (new JsonResponse(['status' => 'error', 'message' => 'Snapshot not found'], 200))->send();
        exit;
    }

    // Find an available drive letter for snapshot mount (use Y, X, W... as temp mounts)
    $usedLetters = Capsule::table('s3_cloudnas_mounts')
        ->where('client_id', $clientId)
        ->where('agent_id', (int) $agent->id)
        ->pluck('drive_letter')
        ->toArray();

    $snapshotLetters = ['Y', 'X', 'W', 'V', 'U', 'T', 'S', 'R', 'Q'];
    $driveLetter = null;
    foreach ($snapshotLetters as $letter) {
        if (!in_array($letter, $usedLetters)) {
            $driveLetter = $letter;
            break;
        }
    }

    if (!$driveLetter) {
        (new JsonResponse(['status' => 'error', 'message' => 'No available drive letters'], 200))->send();
        exit;
    }

    // Resolve the destination bucket name from the job's dest_bucket_id FK
    $destBucketName = '';
    $destPrefix = $job->dest_prefix ?? '';
    if (!empty($job->dest_bucket_id)) {
        $destBucket = Capsule::table('s3_buckets')->where('id', (int) $job->dest_bucket_id)->first(['name', 'user_id']);
        if ($destBucket) {
            $destBucketName = $destBucket->name;
        }
    }
    if ($destBucketName === '') {
        (new JsonResponse(['status' => 'error', 'message' => 'Cannot determine destination bucket for job'], 200))->send();
        exit;
    }

    // Resolve S3 credentials via AdminOps temp key (same approach as nas_mount)
    $settings = Capsule::table('tbladdonmodules')
        ->where('module', 'cloudstorage')
        ->whereIn('setting', ['s3_endpoint', 'cloudbackup_agent_s3_endpoint', 'cloudbackup_agent_s3_region', 'ceph_access_key', 'ceph_secret_key'])
        ->pluck('value', 'setting');

    $s3Endpoint     = $settings['s3_endpoint']     ?? 'https://s3.eazybackup.ca';
    $agentEndpoint  = trim($settings['cloudbackup_agent_s3_endpoint'] ?? '');
    if ($agentEndpoint === '') {
        $agentEndpoint = $s3Endpoint;
    }
    $agentRegion    = trim($settings['cloudbackup_agent_s3_region'] ?? '') ?: 'us-east-1';
    $adminAccessKey = $settings['ceph_access_key'] ?? '';
    $adminSecretKey = $settings['ceph_secret_key'] ?? '';

    $bucketOwner = Capsule::table('s3_users')->where('id', $destBucket->user_id ?? 0)->first();
    $cephUid = $bucketOwner ? \WHMCS\Module\Addon\CloudStorage\Client\HelperController::resolveCephQualifiedUid($bucketOwner) : '';

    $accessKeyPlain = '';
    $secretKeyPlain = '';
    if ($cephUid !== '' && $adminAccessKey !== '' && $adminSecretKey !== '') {
        $tmp = \WHMCS\Module\Addon\CloudStorage\Admin\AdminOps::createTempKey($s3Endpoint, $adminAccessKey, $adminSecretKey, $cephUid, null);
        if (is_array($tmp) && ($tmp['status'] ?? '') === 'success') {
            $accessKeyPlain = (string) ($tmp['access_key'] ?? '');
            $secretKeyPlain = (string) ($tmp['secret_key'] ?? '');
        }
    }

    if ($accessKeyPlain === '' || $secretKeyPlain === '') {
        (new JsonResponse(['status' => 'error', 'message' => 'Unable to provision S3 credentials for snapshot mount'], 200))->send();
        exit;
    }

    // Queue snapshot mount command
    $runIdValue = $hasRunIdPk ? ($run->run_id_str ?? $run->run_id) : ($run->id ?? null);
    $commandPayload = [
        'run_id' => $runIdValue,
        'type' => 'nas_mount_snapshot',
        'payload_json' => json_encode([
            'job_id' => $rawJobId,
            'manifest_id' => $manifestId,
            'drive_letter' => $driveLetter,
            'bucket' => $destBucketName,
            'prefix' => $destPrefix,
            'endpoint' => $agentEndpoint,
            'access_key' => $accessKeyPlain,
            'secret_key' => $secretKeyPlain,
            'region' => $agentRegion,
        ]),
        'status' => 'pending',
        'created_at' => Capsule::raw('NOW()'),
    ];
    if (Capsule::schema()->hasColumn('s3_cloudbackup_run_commands', 'agent_uuid')) {
        $commandPayload['agent_uuid'] = $agentUuid;
    } elseif (Capsule::schema()->hasColumn('s3_cloudbackup_run_commands', 'agent_id')) {
        $commandPayload['agent_id'] = (int) $agent->id;
    }
    $commandId = Capsule::table('s3_cloudbackup_run_commands')->insertGetId($commandPayload);

    (new JsonResponse([
        'status' => 'success',
        'message' => 'Snapshot mount command queued',
        'drive_letter' => $driveLetter,
        'command_id' => $commandId
    ], 200))->send();

} catch (Exception $e) {
    error_log("cloudnas_mount_snapshot error: " . $e->getMessage());
    (new JsonResponse(['status' => 'error', 'message' => 'Failed to mount snapshot'], 200))->send();
}
exit;

