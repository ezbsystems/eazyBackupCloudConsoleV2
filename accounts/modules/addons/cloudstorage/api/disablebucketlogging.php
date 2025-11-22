<?php

require_once __DIR__ . '/../../../../init.php';

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Client\BucketLoggingService;
use WHMCS\Database\Capsule;

$ca = new ClientArea();
$loggedInUserId = $ca->getUserID();
$packageId = ProductConfig::$E3_PRODUCT_ID;
$product = DBController::getProduct($loggedInUserId, $packageId);
if (is_null($product) || is_null($product->username)) {
    (new JsonResponse(['status' => 'fail', 'message' => 'User does not exist.'], 200))->send();
    exit;
}

$username = $product->username;
$user = DBController::getUser($username);
$bucketName = $_POST['bucket_name'] ?? '';

if ($bucketName === '') {
    (new JsonResponse(['status' => 'fail', 'message' => 'Bucket name is required.'], 200))->send();
    exit;
}

// Validate bucket ownership (self or tenants)
$bucket = DBController::getRow('s3_buckets', [['name', '=', $bucketName]]);
if (is_null($bucket)) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Bucket not found.'], 200))->send();
    exit;
}
if ((int)$bucket->user_id !== (int)$user->id) {
    $tenants = DBController::getTenants($user->id, ['id']);
    $tenantIds = $tenants ? $tenants->pluck('id')->toArray() : [];
    if (!in_array((int)$bucket->user_id, $tenantIds, true)) {
        (new JsonResponse(['status' => 'fail', 'message' => 'Bucket not found.'], 200))->send();
        exit;
    }
}

// Module settings
$moduleRows = DBController::getResult('tbladdonmodules', [['module', '=', 'cloudstorage']]);
if (count($moduleRows) == 0) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Cloud Storage service error. Please contact support.'], 200))->send();
    exit;
}
$endpoint = $moduleRows->where('setting', 's3_endpoint')->pluck('value')->first();
$s3Region = $moduleRows->where('setting', 's3_region')->pluck('value')->first() ?: 'us-east-1';
$encryptionKey = $moduleRows->where('setting', 'encryption_key')->pluck('value')->first();

$service = new BucketLoggingService($endpoint, $s3Region);
$owner = Capsule::table('s3_users')->where('id', $bucket->user_id)->first();
$res = $service->disableLogging($bucketName, (int)$owner->id, $encryptionKey);

if (($res['status'] ?? 'fail') !== 'success') {
    (new JsonResponse(['status' => 'fail', 'message' => $res['message'] ?? 'Failed to disable logging.'], 200))->send();
    exit;
}

(new JsonResponse(['status' => 'success', 'message' => $res['message'] ?? 'Logging disabled.']))->send();
exit;


