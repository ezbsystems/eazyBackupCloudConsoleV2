<?php

/**
 * Cloud Backup Retention Policy Cleanup Cron
 * 
 * This cron job applies retention policies to backup jobs, deleting old backup data
 * based on job retention settings (keep_last_n or keep_days).
 * 
 * Should be run daily or every few hours.
 */

require_once __DIR__ . '/../init.php';

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\CloudBackupController;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Client\BucketController;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;

// Get module config
$module = DBController::getResult('tbladdonmodules', [
    ['module', '=', 'cloudstorage']
]);

if (count($module) == 0) {
    exit("Cloud Storage module not configured\n");
}

$s3Endpoint = $module->where('setting', 's3_endpoint')->pluck('value')->first();
$cephAdminUser = $module->where('setting', 'ceph_admin_user')->pluck('value')->first();
$cephAdminAccessKey = $module->where('setting', 'ceph_access_key')->pluck('value')->first();
$cephAdminSecretKey = $module->where('setting', 'ceph_secret_key')->pluck('value')->first();
$s3Region = $module->where('setting', 's3_region')->pluck('value')->first() ?? 'us-east-1';
$encryptionKey = $module->where('setting', 'encryption_key')->pluck('value')->first();

if (empty($s3Endpoint) || empty($cephAdminUser) || empty($cephAdminAccessKey) || empty($cephAdminSecretKey)) {
    exit("Cloud Storage module not fully configured\n");
}

// Get all active jobs with retention policies enabled
$jobs = Capsule::table('s3_cloudbackup_jobs')
    ->where('status', 'active')
    ->whereIn('retention_mode', ['keep_last_n', 'keep_days'])
    ->whereNotNull('retention_value')
    ->get();

if ($jobs->isEmpty()) {
    exit("No jobs with retention policies found.\n");
}

echo "Found " . $jobs->count() . " job(s) with retention policies.\n";

$processedCount = 0;
$errorCount = 0;

foreach ($jobs as $job) {
    try {
        echo "Processing job ID {$job->id} ({$job->name}) - Retention: {$job->retention_mode} = {$job->retention_value}\n";
        
        $result = CloudBackupController::applyRetentionPolicy(
            $job->id,
            $s3Endpoint,
            $cephAdminUser,
            $cephAdminAccessKey,
            $cephAdminSecretKey,
            $s3Region,
            $encryptionKey
        );
        
        if ($result['status'] === 'success') {
            $processedCount++;
            echo "  ✓ Applied retention policy: {$result['message']}\n";
        } else {
            $errorCount++;
            echo "  ✗ Failed: {$result['message']}\n";
        }
    } catch (\Exception $e) {
        $errorCount++;
        echo "  ✗ Error processing job {$job->id}: " . $e->getMessage() . "\n";
        logModuleCall('cloudstorage', 'retention_cleanup', ['job_id' => $job->id], $e->getMessage());
    }
}

echo "\nRetention cleanup completed. Processed: {$processedCount}, Errors: {$errorCount}\n";

