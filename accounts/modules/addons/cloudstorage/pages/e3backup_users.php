<?php

use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Client\E3BackupPricingPanelData;
use WHMCS\Module\Addon\CloudStorage\Client\MspController;

$packageId = ProductConfig::e3CloudBackupPid();
$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    header('Location: clientarea.php');
    exit;
}

$loggedInUserId = $ca->getUserID();
$product = DBController::getProduct($loggedInUserId, $packageId);
if (is_null($product) || empty($product->username)) {
    header('Location: index.php?m=cloudstorage&page=welcome');
    exit;
}

$isMspClient = MspController::isMspClient($loggedInUserId);
$tenants = [];
if ($isMspClient) {
    $tenants = MspController::getTenants($loggedInUserId);
}
$csrfToken = function_exists('generate_token') ? generate_token('plain') : '';

return [
    'isMspClient' => $isMspClient,
    'tenants' => $tenants,
    'csrfToken' => $csrfToken,
    'ebPricingPanel' => E3BackupPricingPanelData::forClient($loggedInUserId),
];

