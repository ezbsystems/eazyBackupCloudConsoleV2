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

// Parse inputs
$enabledRaw = $_POST['enabled'] ?? null;
$enabled = filter_var($enabledRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
if ($enabled === null) {
    $enabled = ((string)$enabledRaw === '1');
}

$maxSizeGbRaw = $_POST['max_size_gb'] ?? '';
$maxObjectsRaw = $_POST['max_objects'] ?? '';

// Normalize empty as unlimited (-1)
$maxSizeGb = -1;
if ($maxSizeGbRaw !== '' && $maxSizeGbRaw !== null) {
    if (!is_numeric($maxSizeGbRaw)) {
        (new JsonResponse(['status' => 'fail', 'message' => 'Invalid max size.'], 200))->send();
        exit;
    }
    $maxSizeGb = (float)$maxSizeGbRaw;
}

$maxObjects = -1;
if ($maxObjectsRaw !== '' && $maxObjectsRaw !== null) {
    if (!is_numeric($maxObjectsRaw)) {
        (new JsonResponse(['status' => 'fail', 'message' => 'Invalid max objects.'], 200))->send();
        exit;
    }
    $maxObjects = (int)$maxObjectsRaw;
}

// Validate numeric constraints (allow -1 unlimited)
if ($maxSizeGb !== -1 && $maxSizeGb <= 0) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Max size must be greater than 0 (or -1 for unlimited).'], 200))->send();
    exit;
}
if ($maxObjects !== -1 && $maxObjects <= 0) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Max objects must be greater than 0 (or -1 for unlimited).'], 200))->send();
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
$ownerUser = Capsule::table('s3_users')->where('id', $ownerId)->first(['id', 'username', 'ceph_uid', 'tenant_id']);
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

// Convert GB → KB for RGW (allow -1 unlimited)
$maxSizeKb = -1;
if ($maxSizeGb !== -1) {
    // 1 GiB = 1024*1024 KiB
    $maxSizeKb = (int)round($maxSizeGb * 1024 * 1024);
}

$resp = AdminOps::setBucketQuota($endpoint, $adminAccessKey, $adminSecretKey, [
    'bucket' => $bucketName,
    'uid' => $baseUid,
    'tenant' => ($tenantId !== '' ? $tenantId : null),
    'enabled' => $enabled ? true : false,
    'max_size_kb' => $maxSizeKb,
    'max_objects' => $maxObjects,
]);

if (!is_array($resp) || ($resp['status'] ?? '') !== 'success') {
    (new JsonResponse(['status' => 'fail', 'message' => 'Unable to save quota.'], 200))->send();
    exit;
}

// Invalidate cached quota for this bucket (otherwise UI may keep showing stale "no quota" for up to 30s)
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}
$cacheKey = 'cloudstorage_bucket_quota_' . md5($uidIdentity . '|' . $bucketName);
unset($_SESSION[$cacheKey]);

(new JsonResponse(['status' => 'success', 'message' => 'Bucket quota updated.'], 200))->send();
exit;

