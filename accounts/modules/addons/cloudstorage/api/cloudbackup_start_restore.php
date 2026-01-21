<?php
/**
 * Cloud Backup Start Restore API
 * 
 * Creates a restore run for tracking progress and queues the restore command.
 * This provides a proper restore flow where:
 * 1. A new "restore" run is created for progress tracking
 * 2. The restore command is queued referencing the original backup run
 * 3. The UI can redirect to cloudbackup_live.tpl to show restore progress
 * 4. Email notifications are sent on completion
 */

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/MspController.php';

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\CloudBackupController;
use WHMCS\Module\Addon\CloudStorage\Client\MspController;

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
$backupRunIdentifier = $_POST['backup_run_uuid'] ?? ($_POST['backup_run_id'] ?? null);
$restorePointId = isset($_POST['restore_point_id']) ? (int) $_POST['restore_point_id'] : 0;
$targetPath = isset($_POST['target_path']) ? trim((string) $_POST['target_path']) : '';
$mount = isset($_POST['mount']) && $_POST['mount'] === 'true';

// Validate inputs
if (empty($backupRunIdentifier) && $restorePointId <= 0) {
    respond(['status' => 'fail', 'message' => 'backup_run_id or restore_point_id is required']);
}
if (empty($targetPath)) {
    respond(['status' => 'fail', 'message' => 'target_path is required']);
}

$backupRun = null;
$backupRunId = 0;
$restorePoint = null;
$job = null;
$manifestId = '';

if ($restorePointId > 0) {
    if (!Capsule::schema()->hasTable('s3_cloudbackup_restore_points')) {
        respond(['status' => 'fail', 'message' => 'Restore points not supported on this installation']);
    }
    $restorePoint = Capsule::table('s3_cloudbackup_restore_points')
        ->where('id', $restorePointId)
        ->where('client_id', $clientId)
        ->first();
    if (!$restorePoint) {
        respond(['status' => 'fail', 'message' => 'Restore point not found or access denied']);
    }

    // MSP tenant authorization check (based on restore point tenant)
    if (MspController::isMspClient($clientId) && !empty($restorePoint->tenant_id)) {
        $tenant = MspController::getTenant((int) $restorePoint->tenant_id, $clientId);
        if (!$tenant) {
            respond(['status' => 'fail', 'message' => 'Tenant not found or access denied']);
        }
    }

    if (!in_array(($restorePoint->status ?? ''), ['success', 'warning'], true)) {
        respond(['status' => 'fail', 'message' => 'Cannot restore from this restore point (status: ' . ($restorePoint->status ?? 'unknown') . ').']);
    }
    if (!empty($restorePoint->hyperv_backup_point_id)) {
        respond(['status' => 'fail', 'message' => 'Hyper-V restore points must be restored using the Hyper-V restore flow.']);
    }
    if (empty($restorePoint->agent_id)) {
        respond(['status' => 'fail', 'message' => 'Restore point is not linked to an agent.']);
    }

    $manifestId = (string) ($restorePoint->manifest_id ?? '');
    if ($manifestId === '') {
        respond(['status' => 'fail', 'message' => 'Restore point has no manifest ID. Cannot restore.']);
    }

    $jobId = (int) ($restorePoint->job_id ?? 0);
    if ($jobId <= 0) {
        respond(['status' => 'fail', 'message' => 'Restore point missing job reference. Cannot create restore run.']);
    }

    $job = Capsule::table('s3_cloudbackup_jobs as j')
        ->where('j.id', $jobId)
        ->where('j.client_id', $clientId)
        ->select('j.id as job_id', 'j.name as job_name', 'j.agent_id', 'j.engine', 'j.source_type')
        ->first();
    if (!$job) {
        respond(['status' => 'fail', 'message' => 'Backup job not found for this restore point']);
    }
} else {
    $backupRun = CloudBackupController::getRun($backupRunIdentifier, $clientId);
    if (!$backupRun) {
        respond(['status' => 'fail', 'message' => 'Backup run not found or access denied']);
    }
    $backupRunId = (int) ($backupRun['id'] ?? 0);

    // Allow restore from success or warning status (warning = completed with some issues but data exists)
    if (!in_array(($backupRun['status'] ?? ''), ['success', 'warning'], true)) {
        respond(['status' => 'fail', 'message' => 'Cannot restore from this backup run (status: ' . ($backupRun['status'] ?? 'unknown') . '). Only successful or warning runs can be restored.']);
    }

    // Load job for additional context/fields
    $job = Capsule::table('s3_cloudbackup_jobs as j')
        ->where('j.id', $backupRun['job_id'] ?? 0)
        ->where('j.client_id', $clientId)
        ->select('j.id as job_id', 'j.name as job_name', 'j.agent_id', 'j.engine', 'j.source_type')
        ->first();

    if (!$job) {
        respond(['status' => 'fail', 'message' => 'Backup job not found for this run']);
    }

    // MSP tenant authorization check
    $accessCheck = MspController::validateJobAccess((int)$job->job_id, $clientId);
    if (!$accessCheck['valid']) {
        respond(['status' => 'fail', 'message' => $accessCheck['message']]);
    }

    // Get the manifest ID from the backup run
    $manifestId = $backupRun['log_ref'] ?? '';
    if (empty($manifestId)) {
        // Try to get from stats_json
        $statsJson = $backupRun['stats_json'] ?? '';
        if ($statsJson) {
            $stats = json_decode($statsJson, true);
            if (json_last_error() === JSON_ERROR_NONE && !empty($stats['manifest_id'])) {
                $manifestId = $stats['manifest_id'];
            }
        }
    }

    if (empty($manifestId)) {
        respond(['status' => 'fail', 'message' => 'This backup run has no manifest ID. Cannot restore.']);
    }
}

// Check if commands table exists
if (!Capsule::schema()->hasTable('s3_cloudbackup_run_commands')) {
    respond(['status' => 'fail', 'message' => 'Restore commands not supported on this installation']);
}

try {
    $restoreRunId = null;
    $restoreRunUuid = null;
    $commandId = null;

    Capsule::connection()->transaction(function () use ($backupRun, $backupRunId, $restorePoint, $manifestId, $targetPath, $mount, $job, &$restoreRunId, &$restoreRunUuid, &$commandId) {
        // Determine which columns exist for the runs table
        $hasAgentIdRuns = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'agent_id');
        $hasEngineColumn = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'engine');
        $hasRunTypeColumn = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'run_type');
        $hasRunUuidColumn = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'run_uuid');
        
        // Create a restore run for tracking progress
        $runData = [
            'job_id' => $job->job_id ?? ($backupRun['job_id'] ?? 0),
            'status' => 'queued',
            'created_at' => Capsule::raw('NOW()'),
            'cancel_requested' => 0,
        ];
        
        // Add optional columns if they exist
        if ($hasAgentIdRuns) {
            $agentId = $job->agent_id ?? ($restorePoint ? $restorePoint->agent_id : null);
            if ($agentId) {
                $runData['agent_id'] = $agentId;
            }
        }
        if ($hasEngineColumn) {
            $runData['engine'] = $job->engine ?? ($restorePoint ? ($restorePoint->engine ?? 'kopia') : 'kopia');
        }
        if ($hasRunTypeColumn) {
            $runData['run_type'] = 'restore';
        }
        if ($hasRunUuidColumn) {
            $runData['run_uuid'] = CloudBackupController::generateUuid();
        }
        
        // Store restore metadata in stats_json or progress_json
        $restoreMetadata = [
            'type' => 'restore',
            'backup_run_id' => $backupRunId ?: null,
            'manifest_id' => $manifestId,
            'target_path' => $targetPath,
            'mount' => $mount,
        ];
        if (!empty($restorePoint) && !empty($restorePoint->id)) {
            $restoreMetadata['restore_point_id'] = (int) $restorePoint->id;
        }
        
        if (Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'stats_json')) {
            $runData['stats_json'] = json_encode($restoreMetadata);
        }
        if (Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'log_ref')) {
            // Use log_ref to reference the backup manifest being restored
            $runData['log_ref'] = $manifestId;
        }
        
        // Insert the restore run
        $restoreRunId = Capsule::table('s3_cloudbackup_runs')->insertGetId($runData);
        $restoreRunUuid = $runData['run_uuid'] ?? null;
        
        // Queue the restore command (references the BACKUP run for job context)
        $commandId = Capsule::table('s3_cloudbackup_run_commands')->insertGetId([
            'run_id' => $backupRunId ?: null,
            'agent_id' => $restorePoint ? ($restorePoint->agent_id ?? null) : null,
            'type' => 'restore',
            'payload_json' => json_encode([
                'restore_point_id' => $restorePoint ? ($restorePoint->id ?? null) : null,
                'manifest_id' => $manifestId,
                'target_path' => $targetPath,
                'mount' => $mount,
                'restore_run_id' => $restoreRunId, // Reference to progress tracking run
                'restore_run_uuid' => $restoreRunUuid,
            ]),
            'status' => 'pending',
            'created_at' => Capsule::raw('NOW()'),
        ]);
        
        // Insert a run event for the restore
        if (Capsule::schema()->hasTable('s3_cloudbackup_run_events')) {
            Capsule::table('s3_cloudbackup_run_events')->insert([
                'run_id' => $restoreRunId,
                'ts' => Capsule::raw('NOW()'),
                'type' => 'info',
                'level' => 'info',
                'code' => 'RESTORE_QUEUED',
                'message_id' => 'RESTORE_STARTING',
                'params_json' => json_encode([
                    'manifest_id' => $manifestId,
                    'target_path' => $targetPath,
                ]),
            ]);
        }
    });
    
    respond([
        'status' => 'success',
        'message' => 'Restore started',
        'restore_run_id' => $restoreRunId,
        'restore_run_uuid' => $restoreRunUuid,
        'command_id' => $commandId,
        'job_id' => $job->job_id ?? ($backupRun['job_id'] ?? null),
        'manifest_id' => $manifestId,
    ]);
} catch (\Throwable $e) {
    logModuleCall('cloudstorage', 'cloudbackup_start_restore', [
        'backup_run_id' => $backupRunIdentifier,
        'target_path' => $targetPath,
    ], $e->getMessage());
    respond(['status' => 'fail', 'message' => 'Failed to start restore: ' . $e->getMessage()]);
}

