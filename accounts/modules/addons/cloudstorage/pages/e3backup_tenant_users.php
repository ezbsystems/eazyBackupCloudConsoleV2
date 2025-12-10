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

// Require MSP access
$isMspClient = MspController::isMspClient($loggedInUserId);
if (!$isMspClient) {
    header('Location: index.php?m=cloudstorage&page=e3backup');
    exit;
}

// Get tenants for dropdown
$tenants = MspController::getTenants($loggedInUserId);

return [
    'isMspClient' => $isMspClient,
    'tenants' => $tenants,
];

