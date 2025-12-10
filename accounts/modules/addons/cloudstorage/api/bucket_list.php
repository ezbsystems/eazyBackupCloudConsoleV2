<?php
/**
 * API endpoint to list user's S3 buckets
 * Used by Cloud NAS and other AJAX components
 */

require_once __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

header('Content-Type: application/json');

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Session timeout'], 200))->send();
    exit;
}

$clientId = $ca->getUserID();
$packageId = ProductConfig::$E3_PRODUCT_ID;

// Get the user's product/account
$product = DBController::getProduct($clientId, $packageId);
if (is_null($product) || is_null($product->username)) {
    (new JsonResponse(['status' => 'fail', 'message' => 'No storage account found'], 200))->send();
    exit;
}

$username = $product->username;
$user = DBController::getUser($username);
if (is_null($user)) {
    (new JsonResponse(['status' => 'fail', 'message' => 'User not found'], 200))->send();
    exit;
}

// Get user tenants (sub-users)
$tenants = DBController::getResult('s3_users', [
    ['parent_id', '=', $user->id]
], [
    'id', 'username'
])->pluck('username', 'id')->toArray();

$tenants[$user->id] = $username;
$bucketUserIds = array_keys($tenants);

// Get all buckets for this user and their tenants
$buckets = DBController::getUserBuckets($bucketUserIds);

// Transform to simple array format for JSON response
$bucketList = [];
foreach ($buckets as $bucket) {
    $bucketList[] = [
        'id' => $bucket->id,
        'name' => $bucket->name,
        'user_id' => $bucket->user_id,
    ];
}

(new JsonResponse(['status' => 'success', 'buckets' => $bucketList], 200))->send();
exit;

