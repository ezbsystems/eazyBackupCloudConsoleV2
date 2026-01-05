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
use WHMCS\Database\Capsule;

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
	$response = new JsonResponse(['status' => 'fail', 'message' => 'Session timeout.'], 200);
	$response->send();
	exit();
}

$packageId = ProductConfig::$E3_PRODUCT_ID;
$loggedInUserId = $ca->getUserID();
// Resolve client ID (WHMCS v8 user->client mapping)
$clientId = 0;
try {
	$link = Capsule::table('tblusers_clients')->where('userid', (int)$loggedInUserId)->orderBy('owner', 'desc')->first();
	if ($link && isset($link->clientid)) {
		$clientId = (int)$link->clientid;
	}
} catch (\Throwable $e) {}
if ($clientId <= 0 && isset($_SESSION['uid'])) {
	$clientId = (int)$_SESSION['uid'];
}
if ($clientId <= 0) {
	$clientId = (int)$loggedInUserId; // legacy fallback
}

$product = DBController::getProduct($clientId, $packageId);
if (is_null($product) || empty($product->username)) {
	$response = new JsonResponse(['status' => 'fail', 'message' => 'Product not found.'], 200);
	$response->send();
	exit();
}

$username = $product->username;
$user = DBController::getUser($username);
if (!$user) {
	$response = new JsonResponse(['status' => 'fail', 'message' => 'User not found.'], 200);
	$response->send();
	exit();
}

// Determine target tenant (username) to create bucket under and validate ownership
$selectedUsername = isset($_POST['username']) ? trim((string)$_POST['username']) : '';
if ($selectedUsername === '') {
	$response = new JsonResponse(['status' => 'fail', 'message' => 'Tenant (username) is required.'], 200);
	$response->send();
	exit();
}

// Build allowed tenant list: parent + children
$tenants = DBController::getResult('s3_users', [
	['parent_id', '=', $user->id]
], [
	'id', 'username'
])->pluck('username', 'id')->toArray();
// Add parent username as allowed
$allowedUsernames = array_values($tenants);
$allowedUsernames[] = $username;

if (!in_array($selectedUsername, $allowedUsernames, true)) {
	$response = new JsonResponse(['status' => 'fail', 'message' => 'Invalid tenant selection.'], 200);
	$response->send();
	exit();
}

// Resolve to the exact s3_users row, mirroring buckets page logic
if ($selectedUsername !== $username) {
	$tenant = DBController::getRow('s3_users', [
		['username', '=', $selectedUsername],
		['parent_id', '=', $user->id],
	]);
	if (is_null($tenant)) {
		$response = new JsonResponse(['status' => 'fail', 'message' => 'Tenant not found or not owned by this account.'], 200);
		$response->send();
		exit();
	}
	$user = $tenant;
}

// Validate input
$bucketName = isset($_POST['bucket_name']) ? trim((string)$_POST['bucket_name']) : '';
$versioningEnabled = !empty($_POST['versioning_enabled']) ? true : false;
$retentionDays = isset($_POST['retention_days']) ? (int)$_POST['retention_days'] : 0;

if ($bucketName === '') {
	$response = new JsonResponse(['status' => 'fail', 'message' => 'Bucket name is required.'], 200);
	$response->send();
	exit();
}

// Optional server-side basic name validation if available
if (method_exists('\WHMCS\Module\Addon\CloudStorage\Client\HelperController', 'isValidBucketName')) {
	if (!\WHMCS\Module\Addon\CloudStorage\Client\HelperController::isValidBucketName($bucketName)) {
		$response = new JsonResponse(['status' => 'fail', 'message' => 'Invalid bucket name. Use lowercase letters, numbers, and hyphens; no leading/trailing hyphen or dot.'], 200);
		$response->send();
		exit();
	}
}

// Load module settings
$module = DBController::getResult('tbladdonmodules', [
	['module', '=', 'cloudstorage']
]);
if (count($module) == 0) {
	$response = new JsonResponse(['status' => 'fail', 'message' => 'Cloud Storage configuration missing.'], 200);
	$response->send();
	exit();
}
$endpoint = $module->where('setting', 's3_endpoint')->pluck('value')->first();
$region = $module->where('setting', 's3_region')->pluck('value')->first() ?: 'ca-central-1';
$adminUser = $module->where('setting', 'ceph_admin_user')->pluck('value')->first();
$adminAccessKey = $module->where('setting', 'ceph_access_key')->pluck('value')->first();
$adminSecretKey = $module->where('setting', 'ceph_secret_key')->pluck('value')->first();
$encryptionKey = $module->where('setting', 'encryption_key')->pluck('value')->first();

if (empty($endpoint) || empty($adminAccessKey) || empty($adminSecretKey) || empty($encryptionKey)) {
	$response = new JsonResponse(['status' => 'fail', 'message' => 'Cloud Storage service not configured.'], 200);
	$response->send();
	exit();
}

// Connect S3 client
$bc = new BucketController($endpoint, $adminUser, $adminAccessKey, $adminSecretKey, $region);

// Create bucket
$create = $bc->createBucketAsAdmin($user, $bucketName, $versioningEnabled, false, 'GOVERNANCE', max(1, $retentionDays ?: 1), false);
if (($create['status'] ?? 'fail') !== 'success') {
	$response = new JsonResponse(['status' => 'fail', 'message' => $create['message'] ?? 'Bucket creation failed.'], 200);
	$response->send();
	exit();
}

// Apply lifecycle rule for noncurrent versions when requested
if ($versioningEnabled && $retentionDays > 0 && method_exists($bc, 'setVersioningRetentionDays')) {
	$lc = $bc->setVersioningRetentionDays($bucketName, (int)$retentionDays);
	// ignore non-fatal lifecycle failures; surface message if needed
}

// Look up bucket ID from DB
$bucketRow = Capsule::table('s3_buckets')
	->where('user_id', $user->id)
	->where('name', $bucketName)
	->orderBy('id', 'desc')
	->first();
$bucketPayload = $bucketRow ? ['id' => (int)$bucketRow->id, 'name' => $bucketName] : ['id' => null, 'name' => $bucketName];

$response = new JsonResponse([
	'status' => 'success',
	'message' => $create['message'] ?? 'Bucket has been created successfully.',
	'bucket' => $bucketPayload
], 200);
$response->send();
exit();


