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

$runId = $_GET['run_id'] ?? null;
if (!$runId) {
    header('Location: index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_jobs');
    exit;
}

// Verify run ownership
$run = CloudBackupController::getRun($runId, $loggedInUserId);
if (!$run) {
    header('Location: index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_jobs');
    exit;
}

// Get job details
$job = CloudBackupController::getJob($run['job_id'], $loggedInUserId);

return [
    'run' => $run,
    'job' => $job,
];

