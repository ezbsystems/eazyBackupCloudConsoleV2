<?php
/**
 * e3 Cloud Backup - Hyper-V Management Page
 * 
 * Displays and manages Hyper-V VMs configured for backup.
 */

use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
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

$jobId = isset($_GET['job_id']) ? (int) $_GET['job_id'] : 0;

// Get Hyper-V jobs for this client
$hypervJobs = [];
try {
    $hypervJobs = Capsule::table('s3_cloudbackup_jobs')
        ->where('client_id', $loggedInUserId)
        ->where('engine', 'hyperv')
        ->select('id', 'name', 'source_display_name', 'status', 'hyperv_config', 'hyperv_enabled')
        ->orderBy('name', 'asc')
        ->get()
        ->map(function ($job) {
            return [
                'id' => $job->id,
                'name' => $job->name,
                'source_display_name' => $job->source_display_name,
                'status' => $job->status,
                'hyperv_enabled' => (bool) $job->hyperv_enabled,
                'hyperv_config' => json_decode($job->hyperv_config ?? '{}', true),
            ];
        })
        ->toArray();
} catch (\Throwable $e) {
    // Table may not exist yet
    $hypervJobs = [];
}

// If job_id provided, get VMs for that job
$vms = [];
$selectedJob = null;
if ($jobId > 0) {
    // Verify job belongs to client
    $job = Capsule::table('s3_cloudbackup_jobs')
        ->where('id', $jobId)
        ->where('client_id', $loggedInUserId)
        ->first();
    
    if ($job) {
        $selectedJob = [
            'id' => $job->id,
            'name' => $job->name,
            'hyperv_config' => json_decode($job->hyperv_config ?? '{}', true),
        ];
        
        try {
            $vms = Capsule::table('s3_hyperv_vms')
                ->where('job_id', $jobId)
                ->orderBy('vm_name', 'asc')
                ->get()
                ->map(function ($vm) {
                    // Get latest backup point
                    $lastBackup = Capsule::table('s3_hyperv_backup_points')
                        ->where('vm_id', $vm->id)
                        ->orderBy('created_at', 'desc')
                        ->first();
                    
                    // Get disk count
                    $diskCount = Capsule::table('s3_hyperv_vm_disks')
                        ->where('vm_id', $vm->id)
                        ->count();
                    
                    return [
                        'id' => $vm->id,
                        'vm_name' => $vm->vm_name,
                        'vm_guid' => $vm->vm_guid,
                        'generation' => $vm->generation,
                        'is_linux' => (bool) $vm->is_linux,
                        'rct_enabled' => (bool) $vm->rct_enabled,
                        'backup_enabled' => (bool) $vm->backup_enabled,
                        'disk_count' => $diskCount,
                        'last_backup' => $lastBackup ? [
                            'type' => $lastBackup->backup_type,
                            'created_at' => $lastBackup->created_at,
                            'consistency_level' => $lastBackup->consistency_level,
                            'total_size_bytes' => $lastBackup->total_size_bytes,
                            'changed_size_bytes' => $lastBackup->changed_size_bytes,
                        ] : null,
                    ];
                })
                ->toArray();
        } catch (\Throwable $e) {
            $vms = [];
        }
    }
}

// Return data for template
return [
    'hypervJobs' => $hypervJobs,
    'vms' => $vms,
    'selectedJob' => $selectedJob,
    'jobId' => $jobId,
];

