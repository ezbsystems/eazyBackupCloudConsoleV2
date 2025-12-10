<?php
/**
 * Cloud NAS - Mount Snapshot (Time Machine)
 * Mounts a Kopia snapshot as a read-only drive for file browsing/recovery
 */

require_once __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;

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

$jobId = intval($input['job_id'] ?? 0);
$manifestId = trim($input['manifest_id'] ?? '');
$agentId = intval($input['agent_id'] ?? 0);

if ($jobId <= 0 || empty($manifestId) || $agentId <= 0) {
    (new JsonResponse(['status' => 'error', 'message' => 'Job ID, manifest ID, and agent ID are required'], 200))->send();
    exit;
}

try {
    // Verify job belongs to client
    $job = Capsule::table('s3_cloudbackup_jobs')
        ->where('id', $jobId)
        ->where('client_id', $clientId)
        ->first();

    if (!$job) {
        (new JsonResponse(['status' => 'error', 'message' => 'Job not found'], 200))->send();
        exit;
    }

    // Verify agent belongs to client
    $agent = Capsule::table('s3_cloudbackup_agents')
        ->where('id', $agentId)
        ->where('client_id', $clientId)
        ->first();

    if (!$agent) {
        (new JsonResponse(['status' => 'error', 'message' => 'Agent not found'], 200))->send();
        exit;
    }

    // Verify manifest exists in a completed run
    $run = Capsule::table('s3_cloudbackup_runs')
        ->where('job_id', $jobId)
        ->where('manifest_id', $manifestId)
        ->where('status', 'success')
        ->first();

    if (!$run) {
        (new JsonResponse(['status' => 'error', 'message' => 'Snapshot not found'], 200))->send();
        exit;
    }

    // Find an available drive letter for snapshot mount (use Y, X, W... as temp mounts)
    $usedLetters = Capsule::table('s3_cloudnas_mounts')
        ->where('client_id', $clientId)
        ->where('agent_id', $agentId)
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

    // Get bucket credentials
    $accessKey = Capsule::table('s3_access_keys')
        ->where('client_id', $clientId)
        ->where('status', 'active')
        ->first();

    if (!$accessKey) {
        (new JsonResponse(['status' => 'error', 'message' => 'No active access key found'], 200))->send();
        exit;
    }

    // Decrypt the secret key
    $encryptionKey = getenv('S3_ENCRYPTION_KEY') ?: 'default-key-change-me';
    $secretKey = openssl_decrypt($accessKey->secret_key_enc, 'AES-256-CBC', $encryptionKey, 0, substr($encryptionKey, 0, 16));

    if (!$secretKey) {
        (new JsonResponse(['status' => 'error', 'message' => 'Failed to decrypt credentials'], 200))->send();
        exit;
    }

    // Get S3 endpoint
    $s3Endpoint = Capsule::table('tbladdonmodules')
        ->where('module', 'cloudstorage')
        ->where('setting', 's3_endpoint')
        ->value('value') ?: 'https://s3.eazybackup.ca';

    // Queue snapshot mount command
    $commandId = Capsule::table('s3_cloudbackup_run_commands')->insertGetId([
        'run_id' => $run->id,
        'agent_id' => $agentId,
        'type' => 'nas_mount_snapshot',
        'payload_json' => json_encode([
            'job_id' => $jobId,
            'manifest_id' => $manifestId,
            'drive_letter' => $driveLetter,
            'bucket' => $job->dest_bucket,
            'prefix' => $job->dest_prefix,
            'endpoint' => $s3Endpoint,
            'access_key' => $accessKey->access_key,
            'secret_key' => $secretKey,
            'region' => 'us-east-1'
        ]),
        'status' => 'pending',
        'created_at' => Capsule::raw('NOW()')
    ]);

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

