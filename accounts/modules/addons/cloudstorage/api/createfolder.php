<?php

require_once __DIR__ . '/../../../../init.php';

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Client\BucketController;

// Expected POST: bucket, username, parent_prefix, name
if (empty($_POST['bucket']) || empty($_POST['username']) || !isset($_POST['name'])) {
    $response = new JsonResponse(['status' => 'fail', 'message' => 'Invalid request.'], 200);
    $response->send();
    exit;
}

$bucketName = trim($_POST['bucket']);
$browseUser = trim($_POST['username']);
$parent = isset($_POST['parent_prefix']) ? trim($_POST['parent_prefix']) : '';
$name = trim($_POST['name']);

// Basic name validation (frontend also validates)
if ($name === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $name)) {
    $response = new JsonResponse(['status' => 'fail', 'message' => 'Invalid folder name.'], 200);
    $response->send();
    exit;
}

$packageId = ProductConfig::$E3_PRODUCT_ID;
$ca = new ClientArea();
$loggedInUserId = $ca->getUserID();
$product = DBController::getProduct($loggedInUserId, $packageId);
if (is_null($product) || is_null($product->username)) {
    $response = new JsonResponse(['status' => 'fail', 'message' => 'User not exist.'], 200);
    $response->send();
    exit;
}

// Resolve tenant scope
$username = $product->username;
$user = DBController::getUser($username);
if (is_null($user)) {
    $response = new JsonResponse(['status' => 'fail', 'message' => 'Your account has been suspended. Please contact support.'], 200);
    $response->send();
    exit;
}
$userId = $user->id;
if ($username !== $browseUser) {
    $tenant = DBController::getRow('s3_users', [
        ['username', '=', $browseUser],
        ['parent_id', '=', $userId],
    ]);
    if (is_null($tenant)) {
        $response = new JsonResponse(['status' => 'fail', 'message' => 'Invalid browse user.'], 200);
        $response->send();
        exit;
    }
    $username = $browseUser;
    $userId = $tenant->id;
}

// Verify bucket ownership
$bucket = DBController::getRow('s3_buckets', [
    ['name', '=', $bucketName],
    ['user_id', '=', $userId],
    ['is_active', '=', '1']
]);
if (is_null($bucket)) {
    $response = new JsonResponse(['status' => 'fail', 'message' => 'Bucket not found.'], 200);
    $response->send();
    exit;
}

// Load module settings
$module = DBController::getResult('tbladdonmodules', [['module', '=', 'cloudstorage']]);
if (count($module) == 0) {
    $response = new JsonResponse(['status' => 'fail', 'message' => 'Cloudstorage is not configured.'], 200);
    $response->send();
    exit;
}
$s3Endpoint = $module->where('setting', 's3_endpoint')->pluck('value')->first();
$cephAdminUser = $module->where('setting', 'ceph_admin_user')->pluck('value')->first();
$cephAdminAccessKey = $module->where('setting', 'ceph_access_key')->pluck('value')->first();
$cephAdminSecretKey = $module->where('setting', 'ceph_secret_key')->pluck('value')->first();
$encryptionKey = $module->where('setting', 'encryption_key')->pluck('value')->first();
$s3Region = $module->where('setting', 's3_region')->pluck('value')->first() ?: 'us-east-1';

// Connect S3 client
$bucketCtrl = new BucketController($s3Endpoint, $cephAdminUser, $cephAdminAccessKey, $cephAdminSecretKey, $s3Region);
$conn = $bucketCtrl->connectS3Client($userId, $encryptionKey);
if (($conn['status'] ?? 'fail') !== 'success') {
    $response = new JsonResponse(['status' => 'fail', 'message' => $conn['message'] ?? 'Storage connection failed.'], 200);
    $response->send();
    exit;
}

// Normalize prefix
$parent = trim($parent, "/");
$key = $parent === '' ? $name . '/' : ($parent . '/' . $name . '/');

try {
    $s3 = $conn['s3client'];
    $s3->putObject([
        'Bucket' => $bucketName,
        'Key' => $key,
        'Body' => '',
    ]);
    $response = new JsonResponse(['status' => 'success', 'message' => 'Folder created.', 'key' => $key], 200);
    $response->send();
    exit;
} catch (\Exception $e) {
    $response = new JsonResponse(['status' => 'fail', 'message' => 'Failed to create folder.'], 200);
    $response->send();
    exit;
}


