<?php
/**
 * Hyper-V Backup Points API
 * 
 * Lists available backup points (restore points) for a specific Hyper-V VM.
 * Returns backup points with disk manifests and restore chain information.
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
use WHMCS\Module\Addon\CloudStorage\Client\MspController;

function respond(array $data, int $httpCode = 200): void
{
    // Clear any buffered output
    ob_end_clean();
    (new JsonResponse($data, $httpCode))->send();
    exit;
}

try {
    $ca = new ClientArea();
    if (!$ca->isLoggedIn()) {
        respond(['status' => 'fail', 'message' => 'Session timeout.']);
    }

    $clientId = $ca->getUserID();
    $vmId = isset($_GET['vm_id']) ? (int) $_GET['vm_id'] : 0;
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
$offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;
$typeFilter = isset($_GET['type']) ? trim($_GET['type']) : ''; // 'Full', 'Incremental', or empty for all

if ($limit <= 0 || $limit > 200) {
    $limit = 50;
}
if ($offset < 0) {
    $offset = 0;
}

if ($vmId <= 0) {
    respond(['status' => 'fail', 'message' => 'vm_id is required']);
}

// Check if Hyper-V tables exist
if (!Capsule::schema()->hasTable('s3_hyperv_vms') || !Capsule::schema()->hasTable('s3_hyperv_backup_points')) {
    respond(['status' => 'fail', 'message' => 'Hyper-V backup tables not initialized']);
}

// Get VM and verify ownership through job
$vm = Capsule::table('s3_hyperv_vms as v')
    ->join('s3_cloudbackup_jobs as j', 'v.job_id', '=', 'j.id')
    ->where('v.id', $vmId)
    ->where('j.client_id', $clientId)
    ->select('v.*', 'j.id as job_id', 'j.name as job_name', 'j.agent_id')
    ->first();

if (!$vm) {
    respond(['status' => 'fail', 'message' => 'VM not found or access denied']);
}

// MSP tenant authorization check
$accessCheck = MspController::validateJobAccess((int)$vm->job_id, $clientId);
if (!$accessCheck['valid']) {
    respond(['status' => 'fail', 'message' => $accessCheck['message']]);
}

// Build query for backup points
$query = Capsule::table('s3_hyperv_backup_points as bp')
    ->join('s3_cloudbackup_runs as r', 'bp.run_id', '=', 'r.id')
    ->where('bp.vm_id', $vmId)
    ->whereIn('r.status', ['success', 'warning']); // Only restorable runs

if ($typeFilter !== '' && in_array($typeFilter, ['Full', 'Incremental'], true)) {
    $query->where('bp.backup_type', $typeFilter);
}

// Get total count
$total = (clone $query)->count();

// Get backup points with pagination
$backupPoints = $query
    ->orderBy('bp.created_at', 'desc')
    ->offset($offset)
    ->limit($limit)
    ->select(
        'bp.id',
        'bp.run_id',
        'bp.backup_type',
        'bp.manifest_id',
        'bp.parent_backup_id',
        'bp.disk_manifests',
        'bp.total_size_bytes',
        'bp.changed_size_bytes',
        'bp.duration_seconds',
        'bp.consistency_level',
        'bp.created_at',
        'bp.warnings_json',
        'bp.warning_code',
        'bp.has_warnings',
        'r.started_at as run_started_at',
        'r.finished_at as run_finished_at'
    )
    ->get();

// Process backup points and calculate restore chains
$result = [];
foreach ($backupPoints as $bp) {
    $diskManifests = [];
    if (!empty($bp->disk_manifests)) {
        $decoded = json_decode($bp->disk_manifests, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $diskManifests = $decoded;
        }
    }

    $warnings = [];
    if (!empty($bp->warnings_json)) {
        $decoded = json_decode($bp->warnings_json, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $warnings = $decoded;
        }
    }

    // Calculate restore chain for incremental backups
    $restoreChain = [];
    if ($bp->backup_type === 'Incremental' && $bp->parent_backup_id) {
        $restoreChain = calculateRestoreChain($vmId, $bp->id);
    } else {
        // Full backup - chain is just this backup
        $restoreChain = [$bp->manifest_id];
    }

    $result[] = [
        'id' => (int) $bp->id,
        'run_id' => (int) $bp->run_id,
        'backup_type' => $bp->backup_type,
        'manifest_id' => $bp->manifest_id,
        'parent_backup_id' => $bp->parent_backup_id ? (int) $bp->parent_backup_id : null,
        'disk_manifests' => $diskManifests,
        'disk_count' => count($diskManifests),
        'total_size_bytes' => $bp->total_size_bytes ? (int) $bp->total_size_bytes : null,
        'changed_size_bytes' => $bp->changed_size_bytes ? (int) $bp->changed_size_bytes : null,
        'duration_seconds' => $bp->duration_seconds ? (int) $bp->duration_seconds : null,
        'consistency_level' => $bp->consistency_level ?? 'Application',
        'created_at' => $bp->created_at,
        'run_started_at' => $bp->run_started_at,
        'run_finished_at' => $bp->run_finished_at,
        'has_warnings' => (bool) $bp->has_warnings,
        'warning_code' => $bp->warning_code,
        'warnings' => $warnings,
        'restore_chain' => $restoreChain,
        'restore_chain_length' => count($restoreChain),
        'is_restorable' => count($restoreChain) > 0 && count($diskManifests) > 0,
    ];
}

// Get VM disks for reference
$disks = Capsule::table('s3_hyperv_vm_disks')
    ->where('vm_id', $vmId)
    ->select('id', 'disk_path', 'controller_type', 'vhd_format', 'size_bytes')
    ->get()
    ->map(function ($disk) {
        return [
            'id' => (int) $disk->id,
            'disk_path' => $disk->disk_path,
            'controller_type' => $disk->controller_type,
            'vhd_format' => $disk->vhd_format ?? 'VHDX',
            'size_bytes' => $disk->size_bytes ? (int) $disk->size_bytes : null,
        ];
    })
    ->toArray();

// If no disk records exist, derive disk info from backup points
if (empty($disks) && !empty($result)) {
    $derivedDiskPaths = [];
    foreach ($result as $bp) {
        if (!empty($bp['disk_manifests']) && is_array($bp['disk_manifests'])) {
            foreach (array_keys($bp['disk_manifests']) as $diskPath) {
                if (!isset($derivedDiskPaths[$diskPath])) {
                    $derivedDiskPaths[$diskPath] = true;
                }
            }
        }
    }
    $diskIndex = 1;
    foreach (array_keys($derivedDiskPaths) as $diskPath) {
        // Derive VHD format from extension
        $ext = strtolower(pathinfo($diskPath, PATHINFO_EXTENSION));
        $vhdFormat = in_array($ext, ['vhd', 'avhd']) ? 'VHD' : 'VHDX';
        
        $disks[] = [
            'id' => -$diskIndex, // Negative ID to indicate derived, not from DB
            'disk_path' => $diskPath,
            'controller_type' => 'SCSI', // Default assumption
            'vhd_format' => $vhdFormat,
            'size_bytes' => null, // Unknown without discovery
            'derived' => true, // Flag to indicate this is derived from backup data
        ];
        $diskIndex++;
    }
}

respond([
    'status' => 'success',
    'vm' => [
        'id' => (int) $vm->id,
        'vm_name' => $vm->vm_name,
        'vm_guid' => $vm->vm_guid,
        'generation' => (int) ($vm->generation ?? 2),
        'rct_enabled' => (bool) ($vm->rct_enabled ?? false),
        'job_id' => (int) $vm->job_id,
        'job_name' => $vm->job_name,
    ],
    'disks' => $disks,
    'backup_points' => $result,
    'total' => $total,
    'limit' => $limit,
    'offset' => $offset,
]);

} catch (\Throwable $e) {
    respond(['status' => 'fail', 'message' => 'Error: ' . $e->getMessage()]);
}

/**
 * Calculate the restore chain for an incremental backup.
 * Returns an ordered list of manifest IDs from base full to target incremental.
 */
function calculateRestoreChain(int $vmId, int $targetBackupPointId): array
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
            ->select('id', 'manifest_id', 'backup_type', 'parent_backup_id')
            ->first();
        
        if (!$bp) {
            // Chain is broken - return empty
            return [];
        }
        
        // Prepend to chain (we're walking backwards)
        array_unshift($chain, $bp->manifest_id);
        
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

