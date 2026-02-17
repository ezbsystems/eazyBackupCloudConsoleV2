<?php

use WHMCS\ClientArea;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Client\MspController;
use WHMCS\Module\Addon\CloudStorage\Client\RecoveryMediaBundleService;

$packageId = ProductConfig::$E3_PRODUCT_ID;
$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    header('Location: clientarea.php');
    exit;
}

$loggedInUserId = (int) $ca->getUserID();
$product = DBController::getProduct($loggedInUserId, $packageId);
if (is_null($product) || is_null($product->username)) {
    header('Location: index.php?m=cloudstorage&page=s3storage');
    exit;
}

$isMspClient = MspController::isMspClient($loggedInUserId);
$tenants = [];
if ($isMspClient) {
    $tenants = MspController::getTenants($loggedInUserId);
}

$agents = Capsule::table('s3_cloudbackup_agents')
    ->where('client_id', $loggedInUserId)
    ->where('status', 'active')
    ->orderBy('hostname')
    ->get(['id', 'hostname', 'tenant_id', 'status', 'last_seen_at']);

$portableToolUrl = trim((string) RecoveryMediaBundleService::getModuleSetting(
    'recovery_media_creator_download_url',
    '/client_installer/e3-recovery-media-creator.exe'
));

return [
    'isMspClient' => $isMspClient,
    'tenants' => $tenants,
    'agents' => $agents,
    'portableToolUrl' => $portableToolUrl,
];

