<?php

require_once __DIR__ . '/../../../../init.php';

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Admin\AdminOps;
use WHMCS\Module\Addon\CloudStorage\Admin\DeprovisionHelper;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Database\Capsule;

$ca = new ClientArea();
$loggedInUserId = $ca->getUserID();

$bucketName = isset($_POST['bucket_name']) ? trim((string)$_POST['bucket_name']) : '';
if ($bucketName === '') {
    (new JsonResponse(['status' => 'fail', 'message' => 'Missing bucket name.'], 200))->send();
    exit;
}

// Validate: caller must have an active Cloud Storage product
$packageId = ProductConfig::$E3_PRODUCT_ID;
$product = DBController::getProduct($loggedInUserId, $packageId);
if (is_null($product) || is_null($product->username)) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Unauthorized.'], 200))->send();
    exit;
}

// Resolve primary storage user and its tenants (for ownership validation)
$primaryUsername = (string)$product->username;
$primaryUser = DBController::getUser($primaryUsername);
if (!$primaryUser) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Unauthorized.'], 200))->send();
    exit;
}

$tenants = DBController::getTenants($primaryUser->id, ['id']);
$tenantIds = $tenants ? $tenants->pluck('id')->toArray() : [];
$allowedOwnerIds = array_map('intval', array_unique(array_merge([(int)$primaryUser->id], $tenantIds)));

// Resolve bucket ownership in DB and verify it belongs to this account scope
$bucketRow = Capsule::table('s3_buckets')->where('name', $bucketName)->first(['name', 'user_id']);
if (!$bucketRow) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Bucket not found.'], 200))->send();
    exit;
}
$ownerId = (int)$bucketRow->user_id;
if (!in_array($ownerId, $allowedOwnerIds, true)) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Unauthorized.'], 200))->send();
    exit;
}

// Load module settings
$module = DBController::getResult('tbladdonmodules', [['module', '=', 'cloudstorage']]);
if (count($module) == 0) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Service unavailable.'], 200))->send();
    exit;
}

$endpoint = $module->where('setting', 's3_endpoint')->pluck('value')->first();
$adminAccessKey = $module->where('setting', 'ceph_access_key')->pluck('value')->first();
$adminSecretKey = $module->where('setting', 'ceph_secret_key')->pluck('value')->first();
if (!$endpoint || !$adminAccessKey || !$adminSecretKey) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Service unavailable.'], 200))->send();
    exit;
}

// Compute RGW uid for the bucket owner (prefer separate tenant param when available)
$ownerSelectCols = ['id', 'username', 'tenant_id'];
try { if (Capsule::schema()->hasColumn('s3_users', 'ceph_uid')) { $ownerSelectCols[] = 'ceph_uid'; } } catch (\Throwable $_) {}
$ownerUser = Capsule::table('s3_users')->where('id', $ownerId)->first($ownerSelectCols);
if (!$ownerUser) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Service unavailable.'], 200))->send();
    exit;
}
$baseUid = (string)($ownerUser->ceph_uid ?? '');
if ($baseUid === '') {
    $baseUid = (string)($ownerUser->username ?? '');
}
$tenantId = (string)($ownerUser->tenant_id ?? '');
$uidIdentity = ($tenantId !== '') ? ($tenantId . '$' . $baseUid) : $baseUid;
if ($uidIdentity === '') {
    (new JsonResponse(['status' => 'fail', 'message' => 'Service unavailable.'], 200))->send();
    exit;
}

// 30s session cache (quota is admin-plane metadata; no need to hammer RGW)
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}
$cacheKey = 'cloudstorage_bucket_quota_' . md5($uidIdentity . '|' . $bucketName);
$now = time();
if (!empty($_SESSION[$cacheKey]) && isset($_SESSION[$cacheKey]['expires']) && $_SESSION[$cacheKey]['expires'] > $now) {
    (new JsonResponse(['status' => 'success', 'data' => $_SESSION[$cacheKey]['data'], 'cached' => true], 200))->send();
    exit;
}

// Fetch quota (prefer lightweight call; AdminOps wrapper falls back if needed)
$q = AdminOps::getBucketQuota($endpoint, $adminAccessKey, $adminSecretKey, [
    'bucket' => $bucketName,
    'uid' => $baseUid,
    'tenant' => ($tenantId !== '' ? $tenantId : null),
]);

if (!is_array($q) || ($q['status'] ?? '') !== 'success' || !is_array($q['data'] ?? null)) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Unable to fetch quota.'], 200))->send();
    exit;
}

$quota = $q['data'];

$enabled = false;
if (array_key_exists('enabled', $quota)) {
    $enabled = (bool)$quota['enabled'];
}

// Normalize max size in bytes (RGW may return max_size and/or max_size_kb)
$maxSizeBytes = -1;
if (isset($quota['max_size']) && is_numeric($quota['max_size'])) {
    $maxSizeBytes = (int)$quota['max_size'];
} elseif (isset($quota['max_size_kb']) && is_numeric($quota['max_size_kb'])) {
    $maxSizeBytes = (int)$quota['max_size_kb'] * 1024;
}

$maxObjects = -1;
if (isset($quota['max_objects']) && is_numeric($quota['max_objects'])) {
    $maxObjects = (int)$quota['max_objects'];
}

$payload = [
    'enabled' => (bool)$enabled,
    'max_size_bytes' => $maxSizeBytes,
    'max_objects' => $maxObjects,
];

$_SESSION[$cacheKey] = [
    'data' => $payload,
    'expires' => $now + 30,
];

(new JsonResponse(['status' => 'success', 'data' => $payload, 'cached' => false], 200))->send();
exit;

