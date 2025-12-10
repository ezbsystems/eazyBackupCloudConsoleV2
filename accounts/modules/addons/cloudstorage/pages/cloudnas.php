<?php
/**
 * Cloud NAS Page Handler
 * 
 * Handles authentication and data loading for the Cloud NAS feature.
 */

use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;

// Check if user is logged in
$packageId = ProductConfig::$E3_PRODUCT_ID;
$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    header('Location: clientarea.php');
    exit;
}

$loggedInUserId = $ca->getUserID();

// Verify user has an active cloud storage product
$product = DBController::getProduct($loggedInUserId, $packageId);
if (is_null($product) || is_null($product->username)) {
    header('Location: index.php?m=cloudstorage&page=s3storage');
    exit;
}

$username = $product->username;
$user = DBController::getUser($username);

if (!$user) {
    header('Location: index.php?m=cloudstorage&page=s3storage');
    exit;
}

// Return view variables (template handles data loading via AJAX APIs)
return [
    'client_id' => $loggedInUserId,
    'username' => $username,
    'user_id' => $user->id,
];

