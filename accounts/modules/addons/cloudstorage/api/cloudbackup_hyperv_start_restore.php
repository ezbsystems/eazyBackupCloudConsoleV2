<?php
/**
 * Hyper-V Start Restore API
 * 
 * Initiates a Hyper-V disk restore from a backup point.
 * Creates a restore run for tracking progress and queues the hyperv_restore command.
 * 
 * Flow:
 * 1. Validates backup point ownership and existence
 * 2. Creates a restore run for progress tracking
 * 3. Queues hyperv_restore command with disk manifests
 * 4. Returns restore_run_id for live progress redirect
 */

// Start output buffering to catch any stray output
ob_start();

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/MspController.php';
require_once __DIR__ . '/../lib/Client/UuidBinary.php';

if (!defined("WHMCS")) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['status' => 'fail', 'message' => 'This file cannot be accessed directly']);
    exit;
}

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\CloudBackupController;
use WHMCS\Module\Addon\CloudStorage\Client\MspController;
use WHMCS\Module\Addon\CloudStorage\Client\UuidBinary;

function respond(array $data, int $httpCode = 200): void
{
    // Clear any buffered output
    ob_end_clean();
    (new JsonResponse($data, $httpCode))->send();
    exit;
}

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    respond(['status' => 'fail', 'message' => 'Session timeout.']);
}

$clientId = $ca->getUserID();
$backupPointId = isset($_POST['backup_point_id']) ? (int) $_POST['backup_point_id'] : 0;
$targetPath = isset($_POST['target_path']) ? trim((string) $_POST['target_path']) : '';
$diskFilterRaw = $_POST['disk_filter'] ?? '';

// Validate inputs
if ($backupPointId <= 0) {
    respond(['status' => 'fail', 'message' => 'backup_point_id is required']);
}
if (empty($targetPath)) {
    respond(['status' => 'fail', 'message' => 'target_path is required']);
}

// Parse disk filter (JSON array of disk paths to restore, empty = all disks)
$diskFilter = [];
if (!empty($diskFilterRaw)) {
    // HTML decode in case WHMCS encoded the value
    $decoded = html_entity_decode($diskFilterRaw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $parsed = json_decode($decoded, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
        $diskFilter = $parsed;
    }
}

// Check if Hyper-V tables exist
if (!Capsule::schema()->hasTable('s3_hyperv_backup_points')) {
    respond(['status' => 'fail', 'message' => 'Hyper-V backup tables not initialized']);
}

$hasJobIdPk = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'job_id');
$hasRunIdCol = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'run_id');
$vmJobJoin = $hasJobIdPk ? ['v.job_id', '=', 'j.job_id'] : ['v.job_id', '=', 'j.id'];
$bpRunJoin = $hasRunIdCol ? ['bp.run_id', '=', 'r.run_id'] : ['bp.run_id', '=', 'r.id'];

$bpSelect = [
    'bp.*',
    'v.vm_name',
    'v.vm_guid',
    'j.name as job_name',
    'j.agent_id',
    'j.engine',
    'r.status as run_status',
];
$bpSelect[] = $hasJobIdPk ? Capsule::raw('BIN_TO_UUID(j.job_id) as job_id') : 'j.id as job_id';
$bpSelect[] = $hasRunIdCol ? Capsule::raw('BIN_TO_UUID(bp.run_id) as run_id_uuid') : Capsule::raw('bp.run_id as run_id_uuid');

// Get backup point and verify ownership through VM -> Job chain
$backupPoint = Capsule::table('s3_hyperv_backup_points as bp')
    ->join('s3_hyperv_vms as v', 'bp.vm_id', '=', 'v.id')
    ->join('s3_cloudbackup_jobs as j', $vmJobJoin[0], $vmJobJoin[1], $vmJobJoin[2])
    ->join('s3_cloudbackup_runs as r', $bpRunJoin[0], $bpRunJoin[1], $bpRunJoin[2])
    ->where('bp.id', $backupPointId)
    ->where('j.client_id', $clientId)
    ->select($bpSelect)
    ->first();

if (!$backupPoint) {
    respond(['status' => 'fail', 'message' => 'Backup point not found or access denied']);
}

// Verify the backup run was successful
if (!in_array($backupPoint->run_status, ['success', 'warning'], true)) {
    respond(['status' => 'fail', 'message' => 'Cannot restore from this backup point (run status: ' . $backupPoint->run_status . ')']);
}

// MSP tenant authorization check (job_id is UUID string when hasJobIdPk)
$jobIdForAccess = (string) ($backupPoint->job_id ?? '');
if ($hasJobIdPk && UuidBinary::isUuid($jobIdForAccess)) {
    $accessCheck = MspController::validateJobAccess($jobIdForAccess, $clientId);
    if (!$accessCheck['valid']) {
        respond(['status' => 'fail', 'message' => $accessCheck['message']]);
    }
}

// Parse disk manifests
$diskManifests = [];
if (!empty($backupPoint->disk_manifests)) {
    $decoded = json_decode($backupPoint->disk_manifests, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $diskManifests = $decoded;
    }
}

if (empty($diskManifests)) {
    respond(['status' => 'fail', 'message' => 'Backup point has no disk manifests. Cannot restore.']);
}

// Filter disks if requested
$disksToRestore = $diskManifests;
if (!empty($diskFilter)) {
    $disksToRestore = array_filter($diskManifests, function ($diskPath) use ($diskFilter) {
        return in_array($diskPath, $diskFilter, true);
    }, ARRAY_FILTER_USE_KEY);
}

if (empty($disksToRestore)) {
    respond(['status' => 'fail', 'message' => 'No disks selected for restore']);
}

// For incremental backups, calculate the restore chain
$restoreChain = [];
if ($backupPoint->backup_type === 'Incremental') {
    $restoreChain = calculateRestoreChainForPoint((int)$backupPoint->vm_id, $backupPointId);
    if (empty($restoreChain)) {
        respond(['status' => 'fail', 'message' => 'Cannot restore incremental backup: restore chain is broken or incomplete. Please select a Full backup point.']);
    }
} else {
    // Full backup - just use this backup point
    $restoreChain = [$backupPoint->manifest_id];
}

// Calculate estimated size
$estimatedSize = 0;
if ($backupPoint->total_size_bytes) {
    $estimatedSize = (int) $backupPoint->total_size_bytes;
}

// Check if commands table exists
if (!Capsule::schema()->hasTable('s3_cloudbackup_run_commands')) {
    respond(['status' => 'fail', 'message' => 'Restore commands not supported on this installation']);
}

try {
    $restoreRunId = null;
    $restoreRunUuid = null;
    $commandId = null;

    $hasCommandRunIdBinary = Capsule::schema()->hasTable('s3_cloudbackup_run_commands')
        && stripos((string) (Capsule::selectOne("SHOW COLUMNS FROM s3_cloudbackup_run_commands WHERE Field = 'run_id'")->Type ?? ''), 'binary') !== false;
    $hasEventsRunIdBinary = Capsule::schema()->hasTable('s3_cloudbackup_run_events')
        && stripos((string) (Capsule::selectOne("SHOW COLUMNS FROM s3_cloudbackup_run_events WHERE Field = 'run_id'")->Type ?? ''), 'binary') !== false;

    Capsule::connection()->transaction(function () use (
        $backupPoint, $backupPointId, $targetPath, $disksToRestore, $restoreChain, $estimatedSize,
        $hasJobIdPk, $hasRunIdCol, $hasCommandRunIdBinary, $hasEventsRunIdBinary,
        &$restoreRunId, &$restoreRunUuid, &$commandId
    ) {
        // Determine which columns exist for the runs table
        $hasAgentIdRuns = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'agent_id');
        $hasEngineColumn = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'engine');
        $hasRunTypeColumn = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'run_type');
        $hasRunUuidColumn = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'run_uuid');

        $restoreRunUuidGen = ($hasRunIdCol) ? CloudBackupController::generateUuid() : null;

        // Create a restore run for tracking progress
        $jobIdForRun = $hasJobIdPk && UuidBinary::isUuid((string) ($backupPoint->job_id ?? ''))
            ? Capsule::raw(UuidBinary::toDbExpr(UuidBinary::normalize((string) $backupPoint->job_id)))
            : $backupPoint->job_id;

        $runData = [
            'job_id' => $jobIdForRun,
            'status' => 'queued',
            'created_at' => Capsule::raw('NOW()'),
            'cancel_requested' => 0,
        ];

        if ($hasRunIdCol && $restoreRunUuidGen) {
            $runData['run_id'] = Capsule::raw(UuidBinary::toDbExpr($restoreRunUuidGen));
        }
        if ($hasAgentIdRuns && $backupPoint->agent_id) {
            $runData['agent_id'] = $backupPoint->agent_id;
        }
        if ($hasEngineColumn) {
            $runData['engine'] = 'hyperv';
        }
        if ($hasRunTypeColumn) {
            $runData['run_type'] = 'hyperv_restore';
        }
        if ($hasRunUuidColumn) {
            $runData['run_uuid'] = $restoreRunUuidGen ?? CloudBackupController::generateUuid();
        }

        $restoreMetadata = [
            'type' => 'hyperv_restore',
            'backup_point_id' => $backupPointId,
            'vm_name' => $backupPoint->vm_name,
            'vm_guid' => $backupPoint->vm_guid,
            'backup_type' => $backupPoint->backup_type,
            'target_path' => $targetPath,
            'disk_count' => count($disksToRestore),
            'estimated_size_bytes' => $estimatedSize,
            'restore_chain_length' => count($restoreChain),
        ];
        if (Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'stats_json')) {
            $runData['stats_json'] = json_encode($restoreMetadata);
        }
        if (Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'log_ref')) {
            $runData['log_ref'] = $backupPoint->manifest_id;
        }

        // Insert the restore run (no insertGetId when UUID schema)
        if ($hasRunIdCol && $restoreRunUuidGen) {
            Capsule::table('s3_cloudbackup_runs')->insert($runData);
            $restoreRunId = $restoreRunUuidGen;
            $restoreRunUuid = $restoreRunUuidGen;
        } else {
            $restoreRunId = Capsule::table('s3_cloudbackup_runs')->insertGetId($runData);
            $restoreRunUuid = $runData['run_uuid'] ?? null;
        }

        // Command run_id: backup run for job context. UUID schema: use run_id_uuid; legacy: run_id (int)
        $backupRunIdUuid = (string) ($backupPoint->run_id_uuid ?? '');
        $cmdRunIdValue = ($hasCommandRunIdBinary && UuidBinary::isUuid($backupRunIdUuid))
            ? Capsule::raw(UuidBinary::toDbExpr(UuidBinary::normalize($backupRunIdUuid)))
            : (isset($backupPoint->run_id) ? $backupPoint->run_id : null);

        $commandId = Capsule::table('s3_cloudbackup_run_commands')->insertGetId([
            'run_id' => $cmdRunIdValue,
            'type' => 'hyperv_restore',
            'payload_json' => json_encode([
                'backup_point_id' => $backupPointId,
                'vm_name' => $backupPoint->vm_name,
                'vm_guid' => $backupPoint->vm_guid,
                'target_path' => $targetPath,
                'disk_manifests' => $disksToRestore,
                'restore_chain' => $restoreChain,
                'backup_type' => $backupPoint->backup_type,
                'restore_run_id' => $restoreRunId,
                'restore_run_uuid' => $restoreRunUuid,
            ]),
            'status' => 'pending',
            'created_at' => Capsule::raw('NOW()'),
        ]);

        $eventRunIdValue = ($hasEventsRunIdBinary && $restoreRunUuid && UuidBinary::isUuid($restoreRunUuid))
            ? Capsule::raw(UuidBinary::toDbExpr(UuidBinary::normalize($restoreRunUuid)))
            : $restoreRunId;

        if (Capsule::schema()->hasTable('s3_cloudbackup_run_events')) {
            Capsule::table('s3_cloudbackup_run_events')->insert([
                'run_id' => $eventRunIdValue,
                'ts' => Capsule::raw('NOW()'),
                'type' => 'info',
                'level' => 'info',
                'code' => 'HYPERV_RESTORE_QUEUED',
                'message_id' => 'HYPERV_RESTORE_STARTING',
                'params_json' => json_encode([
                    'vm_name' => $backupPoint->vm_name,
                    'backup_type' => $backupPoint->backup_type,
                    'target_path' => $targetPath,
                    'disk_count' => count($disksToRestore),
                ]),
            ]);
        }
    });
    
    respond([
        'status' => 'success',
        'message' => 'Hyper-V restore started',
        'restore_run_id' => $restoreRunId,
        'restore_run_uuid' => $restoreRunUuid,
        'command_id' => $commandId,
        'job_id' => $hasJobIdPk ? (string) ($backupPoint->job_id ?? '') : (int) $backupPoint->job_id,
        'vm_name' => $backupPoint->vm_name,
        'disks_to_restore' => count($disksToRestore),
        'estimated_size_bytes' => $estimatedSize,
        'backup_type' => $backupPoint->backup_type,
        'restore_chain_length' => count($restoreChain),
    ]);
} catch (\Throwable $e) {
    logModuleCall('cloudstorage', 'cloudbackup_hyperv_start_restore', [
        'backup_point_id' => $backupPointId,
        'target_path' => $targetPath,
    ], $e->getMessage());
    respond(['status' => 'fail', 'message' => 'Failed to start Hyper-V restore: ' . $e->getMessage()]);
}

/**
 * Calculate the restore chain for an incremental backup point.
 * Returns an ordered list of manifest IDs from base full to target incremental.
 */
function calculateRestoreChainForPoint(int $vmId, int $targetBackupPointId): array
{
    $chain = [];
    $visited = [];
    $currentId = $targetBackupPointId;
    
    // Walk backwards through parent chain to find the base full backup
    while ($currentId !== null && !isset($visited[$currentId])) {
        $visited[$currentId] = true;
        
        $bp = Capsule::table('s3_hyperv_backup_points')
            ->where('id', $currentId)
            ->where('vm_id', $vmId)
            ->select('id', 'manifest_id', 'backup_type', 'parent_backup_id', 'disk_manifests')
            ->first();
        
        if (!$bp) {
            // Chain is broken - return empty
            return [];
        }
        
        // Prepend to chain (we're walking backwards)
        array_unshift($chain, [
            'backup_point_id' => (int) $bp->id,
            'manifest_id' => $bp->manifest_id,
            'backup_type' => $bp->backup_type,
            'disk_manifests' => json_decode($bp->disk_manifests ?? '{}', true),
        ]);
        
        if ($bp->backup_type === 'Full') {
            // Found the base - chain is complete
            return $chain;
        }
        
        // Move to parent
        $currentId = $bp->parent_backup_id ? (int) $bp->parent_backup_id : null;
    }
    
    // If we get here without finding a Full backup, chain is incomplete
    return [];
}

