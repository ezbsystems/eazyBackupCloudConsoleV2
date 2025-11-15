<?php

use WHMCS\ClientArea;
use WHMCS\Database\Capsule;
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

$jobId = $_GET['job_id'] ?? null;
if (!$jobId) {
    // No job selected: show job selector instead of redirecting
    $jobs = Capsule::table('s3_cloudbackup_jobs')
        ->where('client_id', $loggedInUserId)
        ->where('status', '!=', 'deleted')
        ->orderBy('created_at', 'desc')
        ->get();

    return [
        'jobs' => $jobs,
    ];
}

// Verify job ownership and get runs
$job = CloudBackupController::getJob($jobId, $loggedInUserId);
if (!$job) {
    header('Location: index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_jobs');
    exit;
}

$runs = CloudBackupController::getRunsForJob($jobId, $loggedInUserId);

return [
    'job' => $job,
    'runs' => $runs,
];

