<?php

require_once __DIR__ . '/../../../../init.php';

if (!defined('WHMCS')) {
	die('This file cannot be accessed directly');
}

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Request\S3ClientFactory;
use WHMCS\Database\Capsule;

$ca = new ClientArea();
$loggedInUserId = $ca->getUserID();
$packageId = ProductConfig::$E3_PRODUCT_ID;
$product = DBController::getProduct($loggedInUserId, $packageId);
if (is_null($product) || is_null($product->username)) {
	(new JsonResponse(['status' => 'fail', 'message' => 'Unauthorized.'], 200))->send();
	exit;
}

$username = $product->username;
$user = DBController::getUser($username);

$names = isset($_POST['bucket_names']) && is_array($_POST['bucket_names']) ? $_POST['bucket_names'] : [];
if (!$names) {
	(new JsonResponse(['status' => 'success', 'data' => []], 200))->send();
	exit;
}

// Module settings
$moduleRows = DBController::getResult('tbladdonmodules', [['module', '=', 'cloudstorage']]);
if (count($moduleRows) == 0) {
	(new JsonResponse(['status' => 'fail', 'message' => 'Service unavailable.'], 200))->send();
	exit;
}
$endpoint = $moduleRows->where('setting', 's3_endpoint')->pluck('value')->first();
$s3Region = $moduleRows->where('setting', 's3_region')->pluck('value')->first() ?: 'us-east-1';
$encryptionKey = $moduleRows->where('setting', 'encryption_key')->pluck('value')->first();

// Build allowed owner id set (self + tenants)
$tenants = DBController::getTenants($user->id, ['id']);
$tenantIds = $tenants ? $tenants->pluck('id')->toArray() : [];
$allowedOwnerIds = array_map('intval', array_unique(array_merge([$user->id], $tenantIds)));

// Resolve each bucket to owner and query live versioning
$rows = Capsule::table('s3_buckets')->whereIn('name', $names)->get(['name','user_id']);
$byName = [];
foreach ($rows as $r) {
	$byName[$r->name] = (int)$r->user_id;
}

$result = [];
foreach ($names as $n) {
	if (!isset($byName[$n])) {
		continue;
	}
	$ownerId = (int)$byName[$n];
	if (!in_array($ownerId, $allowedOwnerIds, true)) {
		continue;
	}
	$clientRes = S3ClientFactory::forUser($endpoint, $s3Region, $ownerId, $encryptionKey);
	if (($clientRes['status'] ?? 'fail') !== 'success') {
		continue;
	}
	try {
		$ver = $clientRes['client']->getBucketVersioning(['Bucket' => $n]);
		// Versioning.Status can be 'Enabled' or 'Suspended' (AWS); Ceph maps similarly
		$status = isset($ver['Status']) ? (string)$ver['Status'] : 'Off';
		$result[$n] = [
			'versioning' => $status, // 'Enabled' | 'Suspended' | 'Off'
		];
	} catch (\Throwable $e) {
		// Skip on error; do not leak details
		continue;
	}
}

(new JsonResponse(['status' => 'success', 'data' => $result], 200))->send();
exit;


