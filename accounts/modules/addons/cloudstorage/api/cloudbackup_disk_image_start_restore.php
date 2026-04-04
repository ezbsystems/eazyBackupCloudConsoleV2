<?php
/**
 * Start a disk image restore by queueing a disk_restore command for an agent.
 */

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/MspController.php';

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\CloudBackupController;
use WHMCS\Module\Addon\CloudStorage\Client\MspController;
use WHMCS\Module\Addon\CloudStorage\Client\UuidBinary;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function respond(array $data, int $httpCode = 200): void
{
    (new JsonResponse($data, $httpCode))->send();
    exit;
}

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    respond(['status' => 'fail', 'message' => 'Session timeout.'], 200);
}

$clientId = $ca->getUserID();
$restorePointId = isset($_POST['restore_point_id']) ? (int) $_POST['restore_point_id'] : 0;
$targetAgentUuid = trim((string) ($_POST['target_agent_uuid'] ?? ''));
$targetDisk = isset($_POST['target_disk']) ? trim((string) $_POST['target_disk']) : '';
$shrinkEnabled = isset($_POST['shrink_enabled']) && $_POST['shrink_enabled'] === 'true';

if ($restorePointId <= 0) {
    respond(['status' => 'fail', 'message' => 'restore_point_id is required']);
}
if ($targetAgentUuid === '') {
    respond(['status' => 'fail', 'message' => 'target_agent_uuid is required']);
}
if ($targetDisk === '') {
    respond(['status' => 'fail', 'message' => 'target_disk is required']);
}

$hasJobIdPk = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'job_id');
$hasRunIdPk = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'run_id');
$rpQuery = Capsule::table('s3_cloudbackup_restore_points')
    ->where('id', $restorePointId)
    ->where('client_id', $clientId);
if ($hasJobIdPk) {
    $rpQuery->addSelect(Capsule::raw('*, BIN_TO_UUID(job_id) as job_id_str'));
}
$restorePoint = $rpQuery->first();
if (!$restorePoint) {
    respond(['status' => 'fail', 'message' => 'Restore point not found']);
}

if (($restorePoint->status ?? '') === 'metadata_incomplete') {
    respond([
        'status' => 'fail',
        'message' => 'Restore metadata is incomplete for this restore point. Create a fresh disk image backup and try again.',
    ]);
}

if (!in_array(($restorePoint->status ?? ''), ['success', 'warning'], true)) {
    respond(['status' => 'fail', 'message' => 'Restore point is not available']);
}

$restoreEngine = strtolower((string) ($restorePoint->engine ?? ''));
$restoreLayout = trim((string) ($restorePoint->disk_layout_json ?? ''));
if ($restoreEngine === 'disk_image' && $restoreLayout === '') {
    respond([
        'status' => 'fail',
        'message' => 'Restore point is missing disk layout metadata. Create a new disk image backup and retry.',
    ]);
}

if (MspController::isMspClient($clientId) && !empty($restorePoint->tenant_id)) {
    $tenant = MspController::getTenant((int) $restorePoint->tenant_id, $clientId);
    if (!$tenant) {
        respond(['status' => 'fail', 'message' => 'Tenant not found or access denied']);
    }
}

$hasJobIdPk = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'job_id');
$hasRunIdPk = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'run_id');
$jobIdStr = $hasJobIdPk ? ($restorePoint->job_id_str ?? '') : ($restorePoint->job_id ?? 0);
if ($hasJobIdPk && !UuidBinary::isUuid($jobIdStr)) {
    respond(['status' => 'fail', 'message' => 'Restore point missing job reference']);
} elseif (!$hasJobIdPk && (int) $jobIdStr <= 0) {
    respond(['status' => 'fail', 'message' => 'Restore point missing job reference']);
}

$jobQuery = Capsule::table('s3_cloudbackup_jobs')
    ->where('client_id', $clientId);
if ($hasJobIdPk) {
    $jobQuery->whereRaw('job_id = ' . UuidBinary::toDbExpr(UuidBinary::normalize($jobIdStr)));
    $jobQuery->addSelect(Capsule::raw('*, BIN_TO_UUID(job_id) as job_id_str'));
} else {
    $jobQuery->where('id', (int) $jobIdStr);
}
$job = $jobQuery->first();
if (!$job) {
    respond(['status' => 'fail', 'message' => 'Backup job not found']);
}

// Validate target agent
$targetAgent = Capsule::table('s3_cloudbackup_agents')
    ->where('agent_uuid', $targetAgentUuid)
    ->where('client_id', $clientId)
    ->where('status', 'active')
    ->first();
if (!$targetAgent) {
    respond(['status' => 'fail', 'message' => 'Target agent not found or inactive']);
}

if (!Capsule::schema()->hasTable('s3_cloudbackup_run_commands')) {
    respond(['status' => 'fail', 'message' => 'Restore commands not supported']);
}

try {
    $restoreRunId = null;
    $restoreRunUuid = null;
    Capsule::connection()->transaction(function () use ($restorePoint, $job, $targetAgent, $targetDisk, $shrinkEnabled, &$restoreRunId, &$restoreRunUuid, $hasRunIdPk, $hasJobIdPk) {
        $hasRunUuidColumn = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'run_uuid');
        $hasRunTypeColumn = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'run_type');

        $runUuid = CloudBackupController::generateUuid();

        if ($hasRunIdPk) {
            $runData = [
                'run_id' => Capsule::raw(UuidBinary::toDbExpr($runUuid)),
                'job_id' => $hasJobIdPk
                    ? Capsule::raw(UuidBinary::toDbExpr(UuidBinary::normalize($job->job_id_str ?? '')))
                    : ($job->id ?? 0),
                'client_id' => $job->client_id,
                'agent_uuid' => $targetAgent->agent_uuid ?? null,
                'engine' => 'disk_image',
                'status' => 'queued',
                'progress_pct' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'stats_json' => json_encode(['type' => 'disk_restore']),
            ];
        } else {
            $runData = [
                'job_id' => $job->id ?? 0,
                'client_id' => $job->client_id,
                'agent_uuid' => $targetAgent->agent_uuid ?? null,
                'engine' => 'disk_image',
                'status' => 'queued',
                'progress_pct' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'stats_json' => json_encode(['type' => 'disk_restore']),
            ];
        }
        if ($hasRunTypeColumn) {
            $runData['run_type'] = 'disk_restore';
        }
        if ($hasRunUuidColumn) {
            $runData['run_uuid'] = $runUuid;
        }

        if ($hasRunIdPk) {
            Capsule::table('s3_cloudbackup_runs')->insert($runData);
            $restoreRunId = $runUuid;
        } else {
            $restoreRunId = Capsule::table('s3_cloudbackup_runs')->insertGetId($runData);
        }
        $restoreRunUuid = $runUuid;

        $payload = [
            'restore_point_id' => (int) $restorePoint->id,
            'restore_run_id' => $restoreRunId,
            'manifest_id' => $restorePoint->manifest_id ?? '',
            'target_disk' => $targetDisk,
            'disk_layout_json' => $restorePoint->disk_layout_json ?? null,
            'disk_total_bytes' => $restorePoint->disk_total_bytes ?? null,
            'disk_used_bytes' => $restorePoint->disk_used_bytes ?? null,
            'disk_boot_mode' => $restorePoint->disk_boot_mode ?? null,
            'disk_partition_style' => $restorePoint->disk_partition_style ?? null,
            'shrink_enabled' => $shrinkEnabled,
        ];

        Capsule::table('s3_cloudbackup_run_commands')->insert([
            'run_id' => null,
            'agent_uuid' => $targetAgent->agent_uuid ?? null,
            'type' => 'disk_restore',
            'payload_json' => json_encode($payload, JSON_UNESCAPED_SLASHES),
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    });

    respond([
        'status' => 'success',
        'restore_run_id' => $restoreRunId,
        'restore_run_uuid' => $restoreRunUuid,
    ]);
} catch (\Throwable $e) {
    respond(['status' => 'fail', 'message' => 'Failed to start disk restore'], 500);
}
