<?php

require_once __DIR__ . '/../../../../init.php';

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

// CRITICAL: Release session lock IMMEDIATELY after init.php loads.
// WHMCS uses file-based sessions that block ALL concurrent requests.
// We must close the session before doing ANY slow operations.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Client\BucketController;

$bucketName = $_POST['bucket'] ?? '';
$key = $_POST['key'] ?? '';
$includeDetails = !empty($_POST['include_details']);
// Versions Index mode params
$mode = $_POST['mode'] ?? '';
$prefix = $_POST['prefix'] ?? '';
$keyMarker = $_POST['key_marker'] ?? '';
$versionIdMarker = $_POST['version_id_marker'] ?? '';
// Reduced default from 1000 to 100 for faster initial loads on large buckets.
// listObjectVersions is inherently slower than listObjectsV2.
$rawMax = $_POST['max_keys'] ?? null;
$maxKeys = 100;
if ($rawMax !== null) {
    if (is_numeric($rawMax)) {
        $mk = (int)$rawMax;
        if ($mk > 0) {
            $maxKeys = ($mk > 200) ? 200 : $mk; // Hard cap at 200
        }
    }
}
// Default include_deleted to true in index mode unless explicitly set to 0
$includeDeleted = isset($_POST['include_deleted']) ? (bool)$_POST['include_deleted'] : true;
$onlyWithVersions = isset($_POST['only_with_versions']) ? (bool)$_POST['only_with_versions'] : false;

if (empty($bucketName)) {
    $response = new JsonResponse(['status' => 'fail', 'message' => 'Bucket is required.'], 200);
    $response->send();
    exit();
}

$packageId = ProductConfig::$E3_PRODUCT_ID;
$ca = new ClientArea();
$loggedInUserId = $ca->getUserID();
$product = DBController::getProduct($loggedInUserId, $packageId);
if (is_null($product) || is_null($product->username)) {
    $response = new JsonResponse(['status' => 'fail', 'message' => 'User not exist.'], 200);
    $response->send();
    exit();
}

$browseUser = $_POST['username'] ?? $product->username;
$username = $product->username;
$user = DBController::getUser($username);
if (is_null($user)) {
    $response = new JsonResponse(['status' => 'fail', 'message' => 'Your account has been suspended. Please contact support.'], 200);
    $response->send();
    exit();
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
        exit();
    }
    $username = $browseUser;
    $userId = $tenant->id;
}

// Verify bucket
$bucket = DBController::getRow('s3_buckets', [
    ['name', '=', $bucketName],
    ['user_id', '=', $userId],
]);
if (is_null($bucket)) {
    $response = new JsonResponse(['status' => 'fail', 'message' => 'Bucket not found.'], 200);
    $response->send();
    exit();
}

// Module settings
$module = DBController::getResult('tbladdonmodules', [
    ['module', '=', 'cloudstorage']
]);
if (count($module) == 0) {
    $response = new JsonResponse(['status' => 'fail', 'message' => 'Cloud Storage service error.'], 200);
    $response->send();
    exit();
}
$s3Endpoint = $module->where('setting', 's3_endpoint')->pluck('value')->first();
$cephAdminUser = $module->where('setting', 'ceph_admin_user')->pluck('value')->first();
$cephAdminAccessKey = $module->where('setting', 'ceph_access_key')->pluck('value')->first();
$cephAdminSecretKey = $module->where('setting', 'ceph_secret_key')->pluck('value')->first();
$encryptionKey = $module->where('setting', 'encryption_key')->pluck('value')->first();
$s3Region = $module->where('setting', 's3_region')->pluck('value')->first() ?: 'us-east-1';

$bucketController = new BucketController($s3Endpoint, $cephAdminUser, $cephAdminAccessKey, $cephAdminSecretKey, $s3Region);
$conn = $bucketController->connectS3Client($userId, $encryptionKey);
if ($conn['status'] !== 'success') {
    $response = new JsonResponse(['status' => 'fail', 'message' => $conn['message']], 200);
    $response->send();
    exit();
}

$result = null;
if ($mode === 'index') {
    // Server-side cache for version listings (30 second TTL)
    // This dramatically improves performance for large buckets
    $cacheKey = 'versions_index_' . md5($bucketName . '|' . $prefix . '|' . $keyMarker . '|' . $versionIdMarker . '|' . $maxKeys . '|' . ($includeDeleted ? '1' : '0') . '|' . ($onlyWithVersions ? '1' : '0'));
    $cacheTtl = 30; // seconds
    $cacheDir = sys_get_temp_dir() . '/cloudstorage_cache';
    $cacheFile = $cacheDir . '/' . $cacheKey . '.json';

    // Try to read from cache
    if (file_exists($cacheFile)) {
        $cacheData = @file_get_contents($cacheFile);
        if ($cacheData !== false) {
            $cached = @json_decode($cacheData, true);
            if (is_array($cached) && isset($cached['expires']) && $cached['expires'] > time()) {
                $result = $cached['data'];
            }
        }
    }

    // If not cached or expired, fetch from S3
    if ($result === null) {
        $result = $bucketController->getVersionsIndex($bucketName, [
            'prefix' => $prefix,
            'key_marker' => $keyMarker ?: null,
            'version_id_marker' => $versionIdMarker ?: null,
            'max_keys' => $maxKeys,
            'include_deleted' => $includeDeleted,
            'only_with_versions' => $onlyWithVersions
        ]);
        
        // Cache successful responses
        if (isset($result['status']) && $result['status'] === 'success') {
            if (!is_dir($cacheDir)) {
                @mkdir($cacheDir, 0755, true);
            }
            $cachePayload = json_encode([
                'expires' => time() + $cacheTtl,
                'data' => $result
            ]);
            @file_put_contents($cacheFile, $cachePayload, LOCK_EX);
        }
    }
} elseif ($mode === 'restore') {
    $key = $_POST['key'] ?? '';
    $sourceVersionId = $_POST['source_version_id'] ?? '';
    $metadataDirective = strtoupper(trim($_POST['metadata_directive'] ?? 'COPY'));
    if ($metadataDirective !== 'COPY' && $metadataDirective !== 'REPLACE') {
        $metadataDirective = 'COPY';
    }

    if (empty($key) || empty($sourceVersionId)) {
        $response = new JsonResponse(['status' => 'fail', 'message' => 'Key and source_version_id are required.'], 200);
        $response->send();
        exit();
    }

    // Optional: metadata map (JSON or array)
    $metadataMap = null;
    if (isset($_POST['metadata']) && is_string($_POST['metadata'])) {
        $decoded = json_decode($_POST['metadata'], true);
        if (is_array($decoded)) {
            $metadataMap = $decoded;
        }
    } elseif (isset($_POST['metadata']) && is_array($_POST['metadata'])) {
        $metadataMap = $_POST['metadata'];
    }

    // Perform restore via controller
    $restore = $bucketController->restoreObjectVersion($bucketName, $key, $sourceVersionId, $metadataDirective, $metadataMap);

    // Audit log
    $audit = [
        'user_id' => $loggedInUserId,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'bucket' => $bucketName,
        'key' => $key,
        'source_version_id' => $sourceVersionId,
        'result' => $restore
    ];
    logModuleCall('cloudstorage', 'restore_version', $audit, $restore);

    $result = $restore;
} else {
    if (empty($key)) {
        $response = new JsonResponse(['status' => 'fail', 'message' => 'Key is required.'], 200);
        $response->send();
        exit();
    }
    $result = $bucketController->getObjectVersionsForKey($bucketName, $key, $includeDetails);
}

$response = new JsonResponse($result, 200);
$response->send();
exit();


