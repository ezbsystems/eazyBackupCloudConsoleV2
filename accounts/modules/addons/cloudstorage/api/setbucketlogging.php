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
use WHMCS\Module\Addon\CloudStorage\Client\BucketController;
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
$targetBucket = $_POST['target_bucket'] ?? '';
$prefix = $_POST['prefix'] ?? '';
$createTarget = isset($_POST['create_target']) && (int)$_POST['create_target'] === 1;

if ($bucketName === '' || $targetBucket === '') {
    (new JsonResponse(['status' => 'fail', 'message' => 'Bucket and target bucket are required.'], 200))->send();
    exit;
}

// Validate bucket ownership (self or tenants)
$bucket = DBController::getRow('s3_buckets', [['name', '=', $bucketName]]);
if (is_null($bucket)) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Bucket not found.'], 200))->send();
    exit;
}
$ownerUserId = (int)$bucket->user_id;
if ($ownerUserId !== (int)$user->id) {
    $tenants = DBController::getTenants($user->id, ['id']);
    $tenantIds = $tenants ? $tenants->pluck('id')->toArray() : [];
    if (!in_array($ownerUserId, $tenantIds, true)) {
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
$adminUser = $moduleRows->where('setting', 'ceph_admin_user')->pluck('value')->first();
$adminAccessKey = $moduleRows->where('setting', 'ceph_access_key')->pluck('value')->first();
$adminSecretKey = $moduleRows->where('setting', 'ceph_secret_key')->pluck('value')->first();
$encryptionKey = $moduleRows->where('setting', 'encryption_key')->pluck('value')->first();
$s3Region = $moduleRows->where('setting', 's3_region')->pluck('value')->first() ?: 'us-east-1';

// Optionally enforce default target when disallowing custom choice
$allowChoice = (int)($moduleRows->where('setting', 'allow_customer_target_choice')->pluck('value')->first() ?? 1) === 1;
if (!$allowChoice) {
    $targetBucket = $bucketName . '-logs';
}

// Create target bucket if requested and not found
$targetRecord = Capsule::table('s3_buckets')->where('name', $targetBucket)->first();
if (!$targetRecord && $createTarget) {
    $bc = new BucketController($endpoint, $adminUser, $adminAccessKey, $adminSecretKey, $s3Region);
    $conn = $bc->connectS3Client($ownerUserId, $encryptionKey);
    if (($conn['status'] ?? 'fail') !== 'success') {
        (new JsonResponse(['status' => 'fail', 'message' => 'Unable to connect to storage to create target bucket.'], 200))->send();
        exit;
    }
    $ownerObj = Capsule::table('s3_users')->where('id', $ownerUserId)->first();
    $create = $bc->createBucket($ownerObj, $targetBucket, false, false);
    if (($create['status'] ?? 'fail') !== 'success') {
        (new JsonResponse(['status' => 'fail', 'message' => $create['message'] ?? 'Failed to create target bucket.'], 200))->send();
        exit;
    }
    $targetRecord = Capsule::table('s3_buckets')->where('name', $targetBucket)->first();
}

if (!$targetRecord) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Target bucket does not exist.'], 200))->send();
    exit;
}
if ((int)$targetRecord->user_id !== $ownerUserId) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Target bucket must be owned by the same account.'], 200))->send();
    exit;
}

$service = new BucketLoggingService($endpoint, $s3Region);
$res = $service->enableLogging($bucketName, $targetBucket, (string)$prefix, $ownerUserId, $encryptionKey);

if (($res['status'] ?? 'fail') !== 'success') {
    (new JsonResponse(['status' => 'fail', 'message' => $res['message'] ?? 'Failed to enable logging.'], 200))->send();
    exit;
}

(new JsonResponse(['status' => 'success', 'message' => $res['message'] ?? 'Logging enabled.']))->send();
exit;


