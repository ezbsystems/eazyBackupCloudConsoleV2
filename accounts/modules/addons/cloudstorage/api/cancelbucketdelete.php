<?php

require_once __DIR__ . '/../../../../init.php';

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Session timeout.'], 200))->send();
    exit();
}

// Resolve client ID (WHMCS v8 user->client mapping)
$loggedInUserId = (int) $ca->getUserID();
$clientId = 0;
try {
    $link = Capsule::table('tblusers_clients')->where('userid', $loggedInUserId)->orderBy('owner', 'desc')->first();
    if ($link && isset($link->clientid)) {
        $clientId = (int) $link->clientid;
    }
} catch (\Throwable $e) {}
if ($clientId <= 0 && isset($_SESSION['uid'])) {
    $clientId = (int) $_SESSION['uid'];
}
if ($clientId <= 0) {
    $clientId = $loggedInUserId; // legacy fallback
}

$bucketName = $_POST['bucket_name'] ?? '';
$bucketName = is_string($bucketName) ? trim($bucketName) : '';
if ($bucketName === '') {
    (new JsonResponse(['status' => 'fail', 'message' => 'Bucket name is required.'], 200))->send();
    exit();
}

$packageId = ProductConfig::$E3_PRODUCT_ID;
$product = DBController::getProduct($clientId, $packageId);
if (is_null($product) || is_null($product->username)) {
    (new JsonResponse(['status' => 'fail', 'message' => 'User does not exist.'], 200))->send();
    exit();
}

$username = $product->username;
$user = DBController::getUser($username);
if (is_null($user)) {
    (new JsonResponse(['status' => 'fail', 'message' => 'User does not exist.'], 200))->send();
    exit();
}

// Validate bucket ownership (including tenants)
$bucket = DBController::getRow('s3_buckets', [
    ['name', '=', $bucketName]
]);
if (is_null($bucket)) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Bucket not found.'], 200))->send();
    exit();
}
if ((int)$bucket->user_id !== (int)$user->id) {
    $tenants = DBController::getTenants($user->id, 'id');
    if ($tenants->isEmpty()) {
        (new JsonResponse(['status' => 'fail', 'message' => 'Bucket not found.'], 200))->send();
        exit();
    }
    $tenantIds = $tenants->pluck('id')->toArray();
    if (!in_array((int)$bucket->user_id, array_map('intval', $tenantIds), true)) {
        (new JsonResponse(['status' => 'fail', 'message' => 'Bucket not found.'], 200))->send();
        exit();
    }
}

// Cancel by deleting queued/blocked jobs (running jobs cannot be cancelled safely)
try {
    $hasStatus = Capsule::schema()->hasColumn('s3_delete_buckets', 'status');
    if (!$hasStatus) {
        (new JsonResponse(['status' => 'fail', 'message' => 'Cancel is unavailable on this installation.'], 200))->send();
        exit();
    }

    $running = Capsule::table('s3_delete_buckets')
        ->where('user_id', (int)$bucket->user_id)
        ->where('bucket_name', $bucketName)
        ->where('status', 'running')
        ->count();
    if ($running > 0) {
        (new JsonResponse(['status' => 'fail', 'message' => 'Deletion is already running and cannot be cancelled.'], 200))->send();
        exit();
    }

    $deleted = Capsule::table('s3_delete_buckets')
        ->where('user_id', (int)$bucket->user_id)
        ->where('bucket_name', $bucketName)
        ->whereIn('status', ['queued', 'blocked'])
        ->delete();

    if ((int)$deleted <= 0) {
        (new JsonResponse(['status' => 'fail', 'message' => 'No cancellable deletion request was found for this bucket.'], 200))->send();
        exit();
    }

    (new JsonResponse(['status' => 'success', 'message' => 'Deletion request cancelled.'], 200))->send();
    exit();
} catch (\Throwable $e) {
    logModuleCall('cloudstorage', 'cancelbucketdelete', ['bucket' => $bucketName], $e->getMessage());
    (new JsonResponse(['status' => 'fail', 'message' => 'Unable to cancel deletion at this time. Please try again later.'], 200))->send();
    exit();
}


