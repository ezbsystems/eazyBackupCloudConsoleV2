<?php

require __DIR__ . '/../init.php';

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\BucketController;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;

// Get module configuration
$module = DBController::getResult('tbladdonmodules', [
    ['module', '=', 'cloudstorage']
]);

if (count($module) == 0) {
    logModuleCall(
        'cloudstorage',
        'Historical Stats Update',
        [],
        'Error: CloudStorage addon module is not enabled'
    );
    exit();
}

$s3Endpoint = $module->where('setting', 's3_endpoint')->pluck('value')->first();
$cephAdminUser = $module->where('setting', 'ceph_admin_user')->pluck('value')->first();
$cephAdminAccessKey = $module->where('setting', 'ceph_access_key')->pluck('value')->first();
$cephAdminSecretKey = $module->where('setting', 'ceph_secret_key')->pluck('value')->first();

// Validate configuration
if (empty($s3Endpoint) || empty($cephAdminAccessKey) || empty($cephAdminSecretKey)) {
    logModuleCall(
        'cloudstorage',
        'Historical Stats Update',
        [
            's3_endpoint' => !empty($s3Endpoint) ? 'SET' : 'MISSING',
            'ceph_admin_access_key' => !empty($cephAdminAccessKey) ? 'SET' : 'MISSING',
            'ceph_admin_secret_key' => !empty($cephAdminSecretKey) ? 'SET' : 'MISSING'
        ],
        'Error: Module configuration incomplete. Please check S3 endpoint and admin credentials.'
    );
    exit();
}

// Initialize the bucket controller with proper credentials
$bucketController = new BucketController($s3Endpoint, $cephAdminUser, $cephAdminAccessKey, $cephAdminSecretKey);

try {
    // Update historical stats for all users
    $bucketController->updateAllHistoricalStats();
    
    // Log successful execution to module log
    logModuleCall(
        'cloudstorage',
        'Historical Stats Update',
        [],
        'Historical stats updated successfully for all users'
    );
} catch (\Exception $e) {
    // Log any errors to module log
    logModuleCall(
        'cloudstorage',
        'Historical Stats Update',
        [],
        'Error updating historical stats: ' . $e->getMessage()
    );
} 