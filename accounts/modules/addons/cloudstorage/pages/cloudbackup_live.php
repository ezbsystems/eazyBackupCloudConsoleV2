<?php

use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Client\CloudBackupController;

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

$runIdentifier = $_GET['run_uuid'] ?? ($_GET['run_id'] ?? null);
if (!$runIdentifier) {
    header('Location: index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_jobs');
    exit;
}

// Verify run ownership
$run = CloudBackupController::getRun($runIdentifier, $loggedInUserId);
if (!$run) {
    header('Location: index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_jobs');
    exit;
}

// Get job details
$job = CloudBackupController::getJob($run['job_id'], $loggedInUserId);

// Detect if this is a restore run
$isRestore = false;
$isHypervRestore = false;
$restoreMetadata = null;

// Check run_type column if it exists
if (!empty($run['run_type'])) {
    if ($run['run_type'] === 'restore') {
        $isRestore = true;
    } elseif ($run['run_type'] === 'hyperv_restore') {
        $isRestore = true;
        $isHypervRestore = true;
    }
}

// Also check stats_json for restore metadata
if (!empty($run['stats_json'])) {
    $statsJson = is_string($run['stats_json']) ? json_decode($run['stats_json'], true) : $run['stats_json'];
    if (json_last_error() === JSON_ERROR_NONE) {
        if (!empty($statsJson['type']) && $statsJson['type'] === 'restore') {
            $isRestore = true;
            $restoreMetadata = $statsJson;
        } elseif (!empty($statsJson['type']) && $statsJson['type'] === 'hyperv_restore') {
            $isRestore = true;
            $isHypervRestore = true;
            $restoreMetadata = $statsJson;
        }
    }
}

return [
    'run' => $run,
    'job' => $job,
    'is_restore' => $isRestore,
    'is_hyperv_restore' => $isHypervRestore,
    'restore_metadata' => $restoreMetadata,
];

