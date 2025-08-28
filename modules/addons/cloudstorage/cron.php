<?php

// If accessed directly, initialize WHMCS
if (!defined("WHMCS")) {
    require_once __DIR__ . '/../../../init.php';
}

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\BucketController;

// Initialize the bucket controller
$bucketController = new BucketController();

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