<?php
/**
 * e3 Cloud Backup - Hyper-V Restore Page
 * 
 * Displays backup points for a VM and allows initiating a restore.
 */

use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Client\MspController;
use WHMCS\Module\Addon\CloudStorage\Client\UuidBinary;
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
    header('Location: index.php?m=cloudstorage&page=e3backup&view=hyperv');
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

$hasJobIdPk = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'job_id');
$hasRunIdCol = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'run_id');
$vmJobJoin = $hasJobIdPk ? ['v.job_id', '=', 'j.job_id'] : ['v.job_id', '=', 'j.id'];

$vmSelect = [
    'v.id',
    'v.vm_name',
    'v.vm_guid',
    'v.generation',
    'v.is_linux',
    'v.rct_enabled',
    'v.backup_enabled',
    'j.name as job_name',
    'j.agent_id',
];
$vmSelect[] = $hasJobIdPk
    ? Capsule::raw('BIN_TO_UUID(v.job_id) as job_id')
    : 'v.job_id';

try {
    $vm = Capsule::table('s3_hyperv_vms as v')
        ->join('s3_cloudbackup_jobs as j', $vmJobJoin[0], $vmJobJoin[1], $vmJobJoin[2])
        ->where('v.id', $vmId)
        ->where('j.client_id', $loggedInUserId)
        ->select($vmSelect)
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

    // MSP authorization check (job_id is UUID string when hasJobIdPk)
    $jobIdForAccess = (string) ($vm->job_id ?? '');
    if ($hasJobIdPk && UuidBinary::isUuid($jobIdForAccess)) {
        $accessCheck = MspController::validateJobAccess($jobIdForAccess, $loggedInUserId);
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

    // If no disk records, derive from backup points
    if (empty($disks)) {
        $backupPointsWithManifests = Capsule::table('s3_hyperv_backup_points')
            ->where('vm_id', $vmId)
            ->whereNotNull('disk_manifests')
            ->pluck('disk_manifests');
        
        $derivedDiskPaths = [];
        foreach ($backupPointsWithManifests as $manifestsJson) {
            $manifests = json_decode($manifestsJson, true);
            if (is_array($manifests)) {
                foreach (array_keys($manifests) as $diskPath) {
                    if (!isset($derivedDiskPaths[$diskPath])) {
                        $derivedDiskPaths[$diskPath] = true;
                    }
                }
            }
        }
        
        $diskIndex = 1;
        foreach (array_keys($derivedDiskPaths) as $diskPath) {
            $ext = strtolower(pathinfo($diskPath, PATHINFO_EXTENSION));
            $vhdFormat = in_array($ext, ['vhd', 'avhd']) ? 'VHD' : 'VHDX';
            
            $disks[] = [
                'id' => -$diskIndex,
                'disk_path' => $diskPath,
                'disk_name' => basename($diskPath),
                'controller_type' => 'SCSI',
                'vhd_format' => $vhdFormat,
                'size_bytes' => null,
                'derived' => true,
            ];
            $diskIndex++;
        }
    }

    $bpRunJoin = $hasRunIdCol ? ['bp.run_id', '=', 'r.run_id'] : ['bp.run_id', '=', 'r.id'];
    $bpRunJoinArr = [$bpRunJoin[0], $bpRunJoin[1], $bpRunJoin[2]];

    // Get backup point count for summary
    $backupPointCount = Capsule::table('s3_hyperv_backup_points as bp')
        ->join('s3_cloudbackup_runs as r', $bpRunJoinArr[0], $bpRunJoinArr[1], $bpRunJoinArr[2])
        ->where('bp.vm_id', $vmId)
        ->whereIn('r.status', ['success', 'warning'])
        ->count();

    $fullBackupCount = Capsule::table('s3_hyperv_backup_points as bp')
        ->join('s3_cloudbackup_runs as r', $bpRunJoinArr[0], $bpRunJoinArr[1], $bpRunJoinArr[2])
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

// Return template variables (job_id as UUID string when UUID schema)
return [
    'vm' => [
        'id' => (int) $vm->id,
        'vm_name' => $vm->vm_name,
        'vm_guid' => $vm->vm_guid,
        'generation' => (int) ($vm->generation ?? 2),
        'is_linux' => (bool) $vm->is_linux,
        'rct_enabled' => (bool) $vm->rct_enabled,
        'backup_enabled' => (bool) $vm->backup_enabled,
        'job_id' => $hasJobIdPk ? (string) ($vm->job_id ?? '') : (int) $vm->job_id,
        'job_name' => $vm->job_name,
    ],
    'disks' => $disks,
    'backupPointCount' => $backupPointCount,
    'fullBackupCount' => $fullBackupCount,
    'latestBackup' => $latestBackup,
    'vmId' => $vmId,
];

