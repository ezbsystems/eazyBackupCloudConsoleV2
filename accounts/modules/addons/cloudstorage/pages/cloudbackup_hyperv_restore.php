<?php
/**
 * Hyper-V Restore Page
 * 
 * Displays backup points for a VM and allows initiating a restore.
 */

use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Client\MspController;
use Illuminate\Database\Capsule\Manager as Capsule;

$packageId = ProductConfig::$E3_PRODUCT_ID;
$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    header('Location: clientarea.php');
    exit;
}

$loggedInUserId = $ca->getUserID();
$product = DBController::getProduct($loggedInUserId, $packageId);
if (is_null($product) || is_null($product->username)) {
    header('Location: index.php?m=cloudstorage&page=s3storage');
    exit;
}

$vmId = isset($_GET['vm_id']) ? (int) $_GET['vm_id'] : 0;

if ($vmId <= 0) {
    // Redirect to hyperv page if no VM specified
    header('Location: index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_hyperv');
    exit;
}

// Check if Hyper-V tables exist
$tablesExist = Capsule::schema()->hasTable('s3_hyperv_vms') && 
               Capsule::schema()->hasTable('s3_hyperv_backup_points');

if (!$tablesExist) {
    return [
        'error' => 'Hyper-V backup tables not initialized',
        'vm' => null,
        'disks' => [],
        'backupPointCount' => 0,
        'fullBackupCount' => 0,
        'latestBackup' => null,
        'vmId' => $vmId,
    ];
}

// Get VM and verify ownership through job chain
$vm = null;
$disks = [];
$backupPointCount = 0;
$fullBackupCount = 0;
$latestBackup = null;

try {
    $vm = Capsule::table('s3_hyperv_vms as v')
        ->join('s3_cloudbackup_jobs as j', 'v.job_id', '=', 'j.id')
        ->where('v.id', $vmId)
        ->where('j.client_id', $loggedInUserId)
        ->select(
            'v.id',
            'v.vm_name',
            'v.vm_guid',
            'v.generation',
            'v.is_linux',
            'v.rct_enabled',
            'v.backup_enabled',
            'v.job_id',
            'j.name as job_name',
            'j.agent_id'
        )
        ->first();

    if (!$vm) {
        return [
            'error' => 'VM not found or access denied',
            'vm' => null,
            'disks' => [],
            'backupPointCount' => 0,
            'fullBackupCount' => 0,
            'latestBackup' => null,
            'vmId' => $vmId,
        ];
    }

    // MSP authorization check
    $accessCheck = MspController::validateJobAccess((int)$vm->job_id, $loggedInUserId);
    if (!$accessCheck['valid']) {
        return [
            'error' => $accessCheck['message'],
            'vm' => null,
            'disks' => [],
            'backupPointCount' => 0,
            'fullBackupCount' => 0,
            'latestBackup' => null,
            'vmId' => $vmId,
        ];
    }

    // Get disk info
    $disks = Capsule::table('s3_hyperv_vm_disks')
        ->where('vm_id', $vmId)
        ->select('id', 'disk_path', 'controller_type', 'vhd_format', 'size_bytes')
        ->get()
        ->map(function ($disk) {
            return [
                'id' => (int) $disk->id,
                'disk_path' => $disk->disk_path,
                'disk_name' => basename($disk->disk_path),
                'controller_type' => $disk->controller_type ?? 'SCSI',
                'vhd_format' => $disk->vhd_format ?? 'VHDX',
                'size_bytes' => $disk->size_bytes ? (int) $disk->size_bytes : null,
            ];
        })
        ->toArray();

    // Get backup point count for summary
    $backupPointCount = Capsule::table('s3_hyperv_backup_points as bp')
        ->join('s3_cloudbackup_runs as r', 'bp.run_id', '=', 'r.id')
        ->where('bp.vm_id', $vmId)
        ->whereIn('r.status', ['success', 'warning'])
        ->count();

    $fullBackupCount = Capsule::table('s3_hyperv_backup_points as bp')
        ->join('s3_cloudbackup_runs as r', 'bp.run_id', '=', 'r.id')
        ->where('bp.vm_id', $vmId)
        ->where('bp.backup_type', 'Full')
        ->whereIn('r.status', ['success', 'warning'])
        ->count();

    // Get latest backup point
    $latestBackupRow = Capsule::table('s3_hyperv_backup_points')
        ->where('vm_id', $vmId)
        ->orderBy('created_at', 'desc')
        ->first();

    if ($latestBackupRow) {
        $latestBackup = [
            'id' => (int) $latestBackupRow->id,
            'backup_type' => $latestBackupRow->backup_type,
            'created_at' => $latestBackupRow->created_at,
            'consistency_level' => $latestBackupRow->consistency_level ?? 'Application',
            'total_size_bytes' => $latestBackupRow->total_size_bytes ? (int) $latestBackupRow->total_size_bytes : null,
        ];
    }

} catch (\Throwable $e) {
    return [
        'error' => 'Error loading VM data: ' . $e->getMessage(),
        'vm' => null,
        'disks' => [],
        'backupPointCount' => 0,
        'fullBackupCount' => 0,
        'latestBackup' => null,
        'vmId' => $vmId,
    ];
}

// Return template variables
return [
    'vm' => [
        'id' => (int) $vm->id,
        'vm_name' => $vm->vm_name,
        'vm_guid' => $vm->vm_guid,
        'generation' => (int) ($vm->generation ?? 2),
        'is_linux' => (bool) $vm->is_linux,
        'rct_enabled' => (bool) $vm->rct_enabled,
        'backup_enabled' => (bool) $vm->backup_enabled,
        'job_id' => (int) $vm->job_id,
        'job_name' => $vm->job_name,
    ],
    'disks' => $disks,
    'backupPointCount' => $backupPointCount,
    'fullBackupCount' => $fullBackupCount,
    'latestBackup' => $latestBackup,
    'vmId' => $vmId,
];
