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

$isMspClient = MspController::isMspClient($loggedInUserId);

// Tenants for MSP filter
$tenants = [];
if ($isMspClient) {
    $tenants = MspController::getTenants($loggedInUserId);
}

// Agents for filter
$agents = Capsule::table('s3_cloudbackup_agents')
    ->where('client_id', $loggedInUserId)
    ->where('status', 'active')
    ->orderBy('hostname')
    ->get(['agent_uuid', 'hostname', 'device_name', 'tenant_id', 'status', 'last_seen_at']);

return [
    'isMspClient' => $isMspClient,
    'tenants' => $tenants,
    'agents' => $agents,
];
