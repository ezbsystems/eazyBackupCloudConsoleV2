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
use WHMCS\Module\Addon\CloudStorage\Client\UuidBinary;

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
$targetAgentUuid = trim((string) ($_POST['target_agent_uuid'] ?? ''));
$targetPath = isset($_POST['target_path']) ? trim((string) $_POST['target_path']) : '';
$mount = isset($_POST['mount']) && $_POST['mount'] === 'true';
$selectedPathsRaw = $_POST['selected_paths'] ?? null;
$selectedPaths = [];
if (is_array($selectedPathsRaw)) {
    $selectedPaths = array_values(array_filter(array_map('strval', $selectedPathsRaw), fn($p) => trim($p) !== ''));
} elseif (is_string($selectedPathsRaw) && trim($selectedPathsRaw) !== '') {
    $decoded = json_decode($selectedPathsRaw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $selectedPaths = array_values(array_filter(array_map('strval', $decoded), fn($p) => trim($p) !== ''));
    }
}

// Validate inputs
if (empty($backupRunIdentifier) && $restorePointId <= 0) {
    respond(['status' => 'fail', 'message' => 'backup_run_id or restore_point_id is required']);
}
if (empty($targetPath)) {
    respond(['status' => 'fail', 'message' => 'target_path is required']);
}
if (!empty($backupRunIdentifier) && !UuidBinary::isUuid(trim((string) $backupRunIdentifier))) {
    respond(['status' => 'fail', 'code' => 'invalid_identifier_format', 'message' => 'backup_run_id must be UUID'], 400);
}

$backupRun = null;
$backupRunId = null;
$backupRunIdentifierNorm = null;
$restorePoint = null;
$job = null;
$manifestId = '';
$targetAgent = null;
$restoreTenantId = null;
$restoreRepositoryId = '';
$hasJobTenantCol = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'tenant_id');
$hasJobRepositoryCol = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'repository_id');
$hasRunTenantCol = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'tenant_id');
$hasRunRepositoryCol = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'repository_id');
$hasRunIdCol = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'run_id');
$hasJobIdCol = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'job_id');
$hasCommandRunIdBinary = Capsule::schema()->hasColumn('s3_cloudbackup_run_commands', 'run_id')
    && stripos((string) (Capsule::selectOne("SHOW COLUMNS FROM s3_cloudbackup_run_commands WHERE Field = 'run_id'")->Type ?? ''), 'binary') !== false;
$hasEventsRunIdBinary = Capsule::schema()->hasTable('s3_cloudbackup_run_events')
    && Capsule::schema()->hasColumn('s3_cloudbackup_run_events', 'run_id')
    && stripos((string) (Capsule::selectOne("SHOW COLUMNS FROM s3_cloudbackup_run_events WHERE Field = 'run_id'")->Type ?? ''), 'binary') !== false;

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
    if (empty($restorePoint->agent_uuid)) {
        respond(['status' => 'fail', 'message' => 'Restore point is not linked to an agent.']);
    }

    $manifestId = (string) ($restorePoint->manifest_id ?? '');
    if ($manifestId === '') {
        respond(['status' => 'fail', 'message' => 'Restore point has no manifest ID. Cannot restore.']);
    }

    $jobIdRaw = $restorePoint->job_id ?? null;
    if (empty($jobIdRaw)) {
        respond(['status' => 'fail', 'message' => 'Restore point missing job reference. Cannot create restore run.']);
    }

    $jobSelect = $hasJobIdCol
        ? [Capsule::raw('BIN_TO_UUID(j.job_id) as job_id'), 'j.name as job_name', 'j.agent_uuid', 'j.engine', 'j.source_type']
        : ['j.id as job_id', 'j.name as job_name', 'j.agent_uuid', 'j.engine', 'j.source_type'];
    if ($hasJobTenantCol) {
        $jobSelect[] = 'j.tenant_id';
    }
    if ($hasJobRepositoryCol) {
        $jobSelect[] = 'j.repository_id';
    }
    $jobQuery = Capsule::table('s3_cloudbackup_jobs as j')->where('j.client_id', $clientId);
    if ($hasJobIdCol) {
        $jobQuery->whereRaw('j.job_id = ?', [$jobIdRaw]);
    } else {
        $jobQuery->where('j.id', $jobIdRaw);
    }
    $job = $jobQuery->select($jobSelect)->first();
    if (!$job) {
        respond(['status' => 'fail', 'message' => 'Backup job not found for this restore point']);
    }

    $originalAgentUuid = trim((string) ($restorePoint->agent_uuid ?? ''));
    $desiredAgentUuid = $targetAgentUuid !== '' ? $targetAgentUuid : $originalAgentUuid;
    if ($desiredAgentUuid === '') {
        respond(['status' => 'fail', 'message' => 'Destination agent is required.']);
    }

    $targetAgent = Capsule::table('s3_cloudbackup_agents')
        ->where('agent_uuid', $desiredAgentUuid)
        ->where('client_id', $clientId)
        ->first(['id', 'agent_uuid', 'status', 'tenant_id']);

    if (!$targetAgent || $targetAgent->status !== 'active') {
        if ($targetAgentUuid === '') {
            respond(['status' => 'fail', 'message' => 'Original agent is unavailable. Select a destination agent.']);
        }
        respond(['status' => 'fail', 'message' => 'Selected agent not found or inactive.']);
    }

    $restoreTenantId = $restorePoint->tenant_id ?? ($job->tenant_id ?? null);
    $restoreRepositoryId = trim((string) ($restorePoint->repository_id ?? ($job->repository_id ?? '')));
    if ($restoreTenantId) {
        if ((int) $targetAgent->tenant_id !== (int) $restoreTenantId) {
            respond(['status' => 'fail', 'message' => 'Destination agent does not belong to the same tenant.']);
        }
    } else {
        if (!empty($targetAgent->tenant_id)) {
            respond(['status' => 'fail', 'message' => 'Destination agent must be a direct (non-tenant) agent.']);
        }
    }
} else {
    $backupRunIdentifierNorm = UuidBinary::normalize(trim((string) $backupRunIdentifier));
    $backupRun = CloudBackupController::getRun($backupRunIdentifier, $clientId);
    if (!$backupRun) {
        respond(['status' => 'fail', 'message' => 'Backup run not found or access denied']);
    }
    $backupRunId = $hasRunIdCol ? $backupRunIdentifierNorm : ($backupRun['id'] ?? null);

    // Allow restore from success or warning status (warning = completed with some issues but data exists)
    if (!in_array(($backupRun['status'] ?? ''), ['success', 'warning'], true)) {
        respond(['status' => 'fail', 'message' => 'Cannot restore from this backup run (status: ' . ($backupRun['status'] ?? 'unknown') . '). Only successful or warning runs can be restored.']);
    }

    // Load job for additional context/fields
    $jobSelect = $hasJobIdCol
        ? [Capsule::raw('BIN_TO_UUID(j.job_id) as job_id'), 'j.name as job_name', 'j.agent_uuid', 'j.engine', 'j.source_type']
        : ['j.id as job_id', 'j.name as job_name', 'j.agent_uuid', 'j.engine', 'j.source_type'];
    if ($hasJobTenantCol) {
        $jobSelect[] = 'j.tenant_id';
    }
    if ($hasJobRepositoryCol) {
        $jobSelect[] = 'j.repository_id';
    }
    $jobQuery = Capsule::table('s3_cloudbackup_jobs as j')->where('j.client_id', $clientId);
    if ($hasJobIdCol) {
        $jobQuery->join('s3_cloudbackup_runs as r', 'r.job_id', '=', 'j.job_id')
            ->whereRaw('r.run_id = ' . UuidBinary::toDbExpr($backupRunIdentifierNorm));
    } else {
        $jobQuery->where('j.id', $backupRun['job_id'] ?? 0);
    }
    $job = $jobQuery->select($jobSelect)->first();

    if (!$job) {
        respond(['status' => 'fail', 'message' => 'Backup job not found for this run']);
    }

    // MSP tenant authorization check
    $accessCheck = MspController::validateJobAccess((string) ($job->job_id ?? ''), $clientId);
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

    $restoreTenantId = $hasRunTenantCol ? ($backupRun['tenant_id'] ?? null) : null;
    if ($restoreTenantId === null && isset($job->tenant_id)) {
        $restoreTenantId = $job->tenant_id;
    }
    $restoreRepositoryId = $hasRunRepositoryCol ? trim((string) ($backupRun['repository_id'] ?? '')) : '';
    if ($restoreRepositoryId === '' && isset($job->repository_id)) {
        $restoreRepositoryId = trim((string) $job->repository_id);
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

    Capsule::connection()->transaction(function () use ($backupRun, $backupRunId, $restorePoint, $manifestId, $targetPath, $mount, $selectedPaths, $job, $targetAgent, $targetAgentUuid, $restoreTenantId, $restoreRepositoryId, $hasRunIdCol, $hasJobIdCol, $hasCommandRunIdBinary, $hasEventsRunIdBinary, $backupRunIdentifierNorm, &$restoreRunId, &$restoreRunUuid, &$commandId) {
        // Determine which columns exist for the runs table
        $hasAgentUuidRuns = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'agent_uuid');
        $hasEngineColumn = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'engine');
        $hasRunTypeColumn = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'run_type');
        $hasRunUuidColumn = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'run_uuid');
        $hasRunTenantColumn = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'tenant_id');
        $hasRunRepositoryColumn = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'repository_id');

        $jobIdForRun = $job->job_id ?? ($backupRun['job_id'] ?? null);
        if ($hasJobIdCol && $jobIdForRun) {
            $jobIdForRun = UuidBinary::normalize((string) $jobIdForRun);
        }
        
        // Create a restore run for tracking progress
        $restoreRunUuidGen = $hasRunIdCol ? CloudBackupController::generateUuid() : null;
        $runData = [
            'job_id' => $hasJobIdCol && $jobIdForRun
                ? Capsule::raw(UuidBinary::toDbExpr($jobIdForRun))
                : ($jobIdForRun ?? 0),
            'status' => 'queued',
            'created_at' => Capsule::raw('NOW()'),
            'cancel_requested' => 0,
        ];
        
        // Add optional columns if they exist
        if ($hasAgentUuidRuns) {
            $agentUuid = $targetAgent ? ($targetAgent->agent_uuid ?? null) : ($job->agent_uuid ?? ($restorePoint ? $restorePoint->agent_uuid : null));
            if ($agentUuid) {
                $runData['agent_uuid'] = $agentUuid;
            }
        }
        if ($hasEngineColumn) {
            $runData['engine'] = $job->engine ?? ($restorePoint ? ($restorePoint->engine ?? 'kopia') : 'kopia');
        }
        if ($hasRunTypeColumn) {
            $runData['run_type'] = 'restore';
        }
        if ($hasRunUuidColumn) {
            $runData['run_uuid'] = $restoreRunUuidGen ?? CloudBackupController::generateUuid();
        }
        if ($hasRunIdCol && $restoreRunUuidGen) {
            $runData['run_id'] = Capsule::raw(UuidBinary::toDbExpr($restoreRunUuidGen));
        }
        if ($hasRunTenantColumn) {
            $runData['tenant_id'] = $restoreTenantId !== null ? (int) $restoreTenantId : null;
        }
        if ($hasRunRepositoryColumn) {
            $runData['repository_id'] = $restoreRepositoryId !== '' ? $restoreRepositoryId : null;
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
        if ($targetAgentUuid !== '') {
            $restoreMetadata['target_agent_uuid'] = $targetAgentUuid;
        }
        if (!empty($selectedPaths)) {
            $restoreMetadata['selected_paths'] = $selectedPaths;
        }
        
        if (Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'stats_json')) {
            $runData['stats_json'] = json_encode($restoreMetadata);
        }
        if (Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'log_ref')) {
            // Use log_ref to reference the backup manifest being restored
            $runData['log_ref'] = $manifestId;
        }
        
        // Insert the restore run (no insertGetId for UUID schema)
        if ($hasRunIdCol && $restoreRunUuidGen) {
            Capsule::table('s3_cloudbackup_runs')->insert($runData);
            $restoreRunId = $restoreRunUuidGen;
            $restoreRunUuid = $restoreRunUuidGen;
        } else {
            $restoreRunId = Capsule::table('s3_cloudbackup_runs')->insertGetId($runData);
            $restoreRunUuid = $runData['run_uuid'] ?? null;
        }

        $cmdRunIdValue = null;
        if ($backupRunId !== null && $backupRunId !== '' && $backupRunId !== 0) {
            $cmdRunIdValue = $hasCommandRunIdBinary && $backupRunIdentifierNorm
                ? Capsule::raw(UuidBinary::toDbExpr($backupRunIdentifierNorm))
                : $backupRunId;
        }

        // Queue the restore command (references the BACKUP run for job context when applicable)
        $commandId = Capsule::table('s3_cloudbackup_run_commands')->insertGetId([
            'run_id' => $cmdRunIdValue,
            'agent_uuid' => $targetAgent ? ($targetAgent->agent_uuid ?? null) : ($restorePoint ? ($restorePoint->agent_uuid ?? null) : null),
            'type' => 'restore',
            'payload_json' => json_encode([
                'restore_point_id' => $restorePoint ? ($restorePoint->id ?? null) : null,
                'manifest_id' => $manifestId,
                'target_path' => $targetPath,
                'mount' => $mount,
                'target_agent_uuid' => $targetAgent ? ($targetAgent->agent_uuid ?? null) : null,
                'selected_paths' => !empty($selectedPaths) ? $selectedPaths : null,
                'restore_run_id' => $restoreRunId,
                'restore_run_uuid' => $restoreRunUuid,
                'repository_id' => $restoreRepositoryId !== '' ? $restoreRepositoryId : null,
            ]),
            'status' => 'pending',
            'created_at' => Capsule::raw('NOW()'),
        ]);

        $eventRunIdValue = $hasEventsRunIdBinary && $restoreRunUuid
            ? Capsule::raw(UuidBinary::toDbExpr(UuidBinary::normalize($restoreRunUuid)))
            : $restoreRunId;

        // Insert a run event for the restore
        if (Capsule::schema()->hasTable('s3_cloudbackup_run_events')) {
            Capsule::table('s3_cloudbackup_run_events')->insert([
                'run_id' => $eventRunIdValue,
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

