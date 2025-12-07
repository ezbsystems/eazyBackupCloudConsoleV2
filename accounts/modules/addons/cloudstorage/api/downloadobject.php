<?php

require_once __DIR__ . '/../../../../init.php';

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

// CRITICAL: Release session lock IMMEDIATELY after init.php loads.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Client\BucketController;

// Expected GET: bucket, username, key
$bucketName = isset($_GET['bucket']) ? trim($_GET['bucket']) : '';
$browseUser = isset($_GET['username']) ? trim($_GET['username']) : '';
$key = isset($_GET['key']) ? $_GET['key'] : '';

if ($bucketName === '' || $browseUser === '' || $key === '') {
    header('HTTP/1.1 400 Bad Request');
    echo 'Invalid request';
    exit;
}

$packageId = ProductConfig::$E3_PRODUCT_ID;
$ca = new ClientArea();
$loggedInUserId = $ca->getUserID();
$product = DBController::getProduct($loggedInUserId, $packageId);
if (is_null($product) || is_null($product->username)) {
    header('HTTP/1.1 403 Forbidden');
    echo 'Permission denied';
    exit;
}

// Tenant scope resolution
$username = $product->username;
$user = DBController::getUser($username);
if (is_null($user)) {
    header('HTTP/1.1 403 Forbidden');
    echo 'Permission denied';
    exit;
}
$userId = $user->id;
if ($username !== $browseUser) {
    $tenant = DBController::getRow('s3_users', [
        ['username', '=', $browseUser],
        ['parent_id', '=', $userId],
    ]);
    if (is_null($tenant)) {
        header('HTTP/1.1 403 Forbidden');
        echo 'Permission denied';
        exit;
    }
    $username = $browseUser;
    $userId = $tenant->id;
}

// Check bucket ownership
$bucket = DBController::getRow('s3_buckets', [
    ['name', '=', $bucketName],
    ['user_id', '=', $userId],
    ['is_active', '=', '1']
]);
if (is_null($bucket)) {
    header('HTTP/1.1 404 Not Found');
    echo 'Bucket not found';
    exit;
}

// Module settings
$module = DBController::getResult('tbladdonmodules', [['module', '=', 'cloudstorage']]);
if (count($module) == 0) {
    header('HTTP/1.1 500 Internal Server Error');
    echo 'Module not configured';
    exit;
}
$s3Endpoint = $module->where('setting', 's3_endpoint')->pluck('value')->first();
$cephAdminUser = $module->where('setting', 'ceph_admin_user')->pluck('value')->first();
$cephAdminAccessKey = $module->where('setting', 'ceph_access_key')->pluck('value')->first();
$cephAdminSecretKey = $module->where('setting', 'ceph_secret_key')->pluck('value')->first();
$encryptionKey = $module->where('setting', 'encryption_key')->pluck('value')->first();
$s3Region = $module->where('setting', 's3_region')->pluck('value')->first() ?: 'us-east-1';

// Connect S3 and stream object
$bucketCtrl = new BucketController($s3Endpoint, $cephAdminUser, $cephAdminAccessKey, $cephAdminSecretKey, $s3Region);
$conn = $bucketCtrl->connectS3Client($userId, $encryptionKey);
if (($conn['status'] ?? 'fail') !== 'success') {
    header('HTTP/1.1 500 Internal Server Error');
    echo 'Storage connection failed';
    exit;
}

try {
    $s3 = $conn['s3client'];
    $res = $s3->getObject([
        'Bucket' => $bucketName,
        'Key' => $key,
    ]);

    // Derive filename
    $basename = basename($key);
    if ($basename === '' || substr($key, -1) === '/') {
        header('HTTP/1.1 400 Bad Request');
        echo 'Folders cannot be downloaded';
        exit;
    }
    $contentType = $res['ContentType'] ?? 'application/octet-stream';
    $length = $res['ContentLength'] ?? null;

    header('Content-Type: ' . $contentType);
    header('Content-Disposition: attachment; filename="' . addslashes($basename) . '"');
    if ($length !== null) {
        header('Content-Length: ' . $length);
    }
    // Disable output buffering for streaming
    if (function_exists('apache_setenv')) {
        @apache_setenv('no-gzip', '1');
    }
    @ini_set('zlib.output_compression', 'Off');
    @ini_set('output_buffering', 'Off');
    @ini_set('implicit_flush', '1');
    while (ob_get_level() > 0) { ob_end_flush(); }
    ob_implicit_flush(1);

    echo (string)$res['Body'];
    exit;
} catch (\Exception $e) {
    header('HTTP/1.1 404 Not Found');
    echo 'Object not found';
    exit;
}


