<?php

use WHMCS\ClientArea;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Client\MspController;

$packageId = ProductConfig::$E3_PRODUCT_ID;
$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    header('Location: clientarea.php');
    exit;
}

$loggedInUserId = $ca->getUserID();
$product = DBController::getProduct($loggedInUserId, $packageId);
if (is_null($product) || is_null($product->username)) {
    header('Location: index.php?m=cloudstorage&page=s3storage');
    exit;
}

$username = $product->username;
$user = DBController::getUser($username);

$isMspClient = MspController::isMspClient($loggedInUserId);

// Tenants for MSP filter
$tenants = [];
if ($isMspClient) {
    $tenants = MspController::getTenants($loggedInUserId);
}

// Agents for agent filter (will be client-scoped; tenant filtering handled in UI)
$agents = Capsule::table('s3_cloudbackup_agents')
    ->where('client_id', $loggedInUserId)
    ->where('status', 'active')
    ->orderBy('hostname')
    ->get(['id', 'hostname', 'tenant_id']);

// Get user tenants (s3_users) for bucket access
$s3Tenants = DBController::getResult('s3_users', [
    ['parent_id', '=', $user->id]
], [
    'id', 'username'
])->pluck('username', 'id')->toArray();

$s3Tenants[$user->id] = $username;
$bucketUserIds = array_keys($s3Tenants);
$buckets = DBController::getUserBuckets($bucketUserIds);

return [
    'isMspClient' => $isMspClient,
    'tenants' => $tenants,
    'agents' => $agents,
    'buckets' => $buckets,
    's3_user_id' => $user->id,
    'client_id' => $loggedInUserId,
    'usernames' => $s3Tenants, // For inline bucket creation
];

