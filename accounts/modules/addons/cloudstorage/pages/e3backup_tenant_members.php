<?php

use WHMCS\ClientArea;
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
$tenantId = isset($_GET['tenant_id']) ? (int)$_GET['tenant_id'] : 0;
if ($tenantId > 0) {
    header('Location: index.php?m=eazybackup&a=ph-tenant-members&id=' . $tenantId . '&legacy=e3-tenant-members');
    exit;
}

header('Location: index.php?m=eazybackup&a=ph-tenants-manage&legacy=e3-tenant-members');
exit;
