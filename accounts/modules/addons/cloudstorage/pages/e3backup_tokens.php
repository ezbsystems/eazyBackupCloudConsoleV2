<?php

use WHMCS\ClientArea;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
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

$csrfToken = function_exists('generate_token') ? generate_token('plain') : '';

$isMspClient = MspController::isMspClient($loggedInUserId);

// Get tenants for dropdown (MSP only)
$tenants = [];
if ($isMspClient) {
    $tenants = MspController::getTenants($loggedInUserId);
}

return [
    'isMspClient' => $isMspClient,
    'tenants' => $tenants,
    'token' => $csrfToken,
];

