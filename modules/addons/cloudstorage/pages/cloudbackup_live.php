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

// Build compact metrics for the live page from the run
$status = $run['status'] ?? null;
$bytesTransferred = $run['bytes_transferred'] ?? null;
$bytesTotal = $run['bytes_total'] ?? null;
$speed = $run['speed_bytes_per_sec'] ?? null;
$etaSeconds = $run['eta_seconds'] ?? null;
$metrics = [
	'status' => $status,
	'bytes_transferred' => $bytesTransferred,
	'bytes_total' => $bytesTotal,
	'speed_bytes_per_sec' => $speed,
	'eta_seconds' => $etaSeconds,
];

return [
    'run' => $run,
    'job' => $job,
	'metrics' => $metrics,
];

