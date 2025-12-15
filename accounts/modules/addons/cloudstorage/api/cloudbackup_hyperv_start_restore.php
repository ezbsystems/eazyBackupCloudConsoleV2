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

// Get backup point and verify ownership through VM -> Job chain
$backupPoint = Capsule::table('s3_hyperv_backup_points as bp')
    ->join('s3_hyperv_vms as v', 'bp.vm_id', '=', 'v.id')
    ->join('s3_cloudbackup_jobs as j', 'v.job_id', '=', 'j.id')
    ->join('s3_cloudbackup_runs as r', 'bp.run_id', '=', 'r.id')
    ->where('bp.id', $backupPointId)
    ->where('j.client_id', $clientId)
    ->select(
        'bp.*',
        'v.vm_name',
        'v.vm_guid',
        'j.id as job_id',
        'j.name as job_name',
        'j.agent_id',
        'j.engine',
        'r.status as run_status'
    )
    ->first();

if (!$backupPoint) {
    respond(['status' => 'fail', 'message' => 'Backup point not found or access denied']);
}

// Verify the backup run was successful
if (!in_array($backupPoint->run_status, ['success', 'warning'], true)) {
    respond(['status' => 'fail', 'message' => 'Cannot restore from this backup point (run status: ' . $backupPoint->run_status . ')']);
}

// MSP tenant authorization check
$accessCheck = MspController::validateJobAccess((int)$backupPoint->job_id, $clientId);
if (!$accessCheck['valid']) {
    respond(['status' => 'fail', 'message' => $accessCheck['message']]);
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

    Capsule::connection()->transaction(function () use (
        $backupPoint, $backupPointId, $targetPath, $disksToRestore, $restoreChain, $estimatedSize,
        &$restoreRunId, &$restoreRunUuid, &$commandId
    ) {
        // Determine which columns exist for the runs table
        $hasAgentIdRuns = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'agent_id');
        $hasEngineColumn = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'engine');
        $hasRunTypeColumn = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'run_type');
        $hasRunUuidColumn = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'run_uuid');
        
        // Create a restore run for tracking progress
        $runData = [
            'job_id' => $backupPoint->job_id,
            'status' => 'queued',
            'created_at' => Capsule::raw('NOW()'),
            'cancel_requested' => 0,
        ];
        
        // Add optional columns if they exist
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
            $runData['run_uuid'] = CloudBackupController::generateUuid();
        }
        
        // Store restore metadata in stats_json
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
            // Use log_ref to reference the main backup manifest being restored
            $runData['log_ref'] = $backupPoint->manifest_id;
        }
        
        // Insert the restore run
        $restoreRunId = Capsule::table('s3_cloudbackup_runs')->insertGetId($runData);
        $restoreRunUuid = $runData['run_uuid'] ?? null;
        
        // Queue the hyperv_restore command
        // Note: run_id points to the original backup run for job context
        $commandId = Capsule::table('s3_cloudbackup_run_commands')->insertGetId([
            'run_id' => $backupPoint->run_id, // Points to backup run for job context
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
        
        // Insert a run event for the restore
        if (Capsule::schema()->hasTable('s3_cloudbackup_run_events')) {
            Capsule::table('s3_cloudbackup_run_events')->insert([
                'run_id' => $restoreRunId,
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
        'job_id' => (int) $backupPoint->job_id,
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

