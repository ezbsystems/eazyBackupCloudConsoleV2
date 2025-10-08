<?php

// If accessed directly, initialize WHMCS
if (!defined("WHMCS")) {
    require_once __DIR__ . '/../../../init.php';
}

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\BucketController;

// Initialize the bucket controller with configured region if available
$settings = Capsule::table('tbladdonmodules')
    ->where('module', 'cloudstorage')
    ->pluck('value', 'setting');

$region = isset($settings['s3_region']) && $settings['s3_region'] ? $settings['s3_region'] : 'us-east-1';

$bucketController = new BucketController(null, null, null, null, $region);

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