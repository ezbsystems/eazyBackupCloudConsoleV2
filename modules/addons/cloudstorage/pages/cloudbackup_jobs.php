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

$username = $product->username;
$user = DBController::getUser($username);

// Get user tenants
$tenants = DBController::getResult('s3_users', [
    ['parent_id', '=', $user->id]
], [
    'id', 'username'
])->pluck('username', 'id')->toArray();

$tenants[$user->id] = $username;
$bucketUserIds = array_keys($tenants);
$buckets = DBController::getUserBuckets($bucketUserIds);

// Create a lookup array for bucket names by bucket ID (use both string and int keys for safety)
$bucketNamesById = [];
foreach ($buckets as $bucket) {
    $bucketNamesById[(int)$bucket->id] = $bucket->name;
    $bucketNamesById[(string)$bucket->id] = $bucket->name; // Also add as string for lookup
}

// Get all jobs for this client
$jobs = CloudBackupController::getJobsForClient($loggedInUserId);

// Add bucket name to each job
foreach ($jobs as &$job) {
    $bucketId = isset($job['dest_bucket_id']) ? $job['dest_bucket_id'] : null;
    if ($bucketId !== null) {
        // Try both int and string lookups
        $bucketName = $bucketNamesById[(int)$bucketId] ?? $bucketNamesById[(string)$bucketId] ?? null;
        $job['dest_bucket_name'] = $bucketName ?: 'Unknown Bucket (ID: ' . $bucketId . ')';
    } else {
        $job['dest_bucket_name'] = 'No Bucket';
    }
}

return [
    'jobs' => $jobs,
    'buckets' => $buckets,
    's3_user_id' => $user->id,
    'client_id' => $loggedInUserId,
];

