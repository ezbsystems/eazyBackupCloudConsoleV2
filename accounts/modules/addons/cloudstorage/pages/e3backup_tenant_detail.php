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
$tenantPublicId = MspController::resolveTenantPublicIdForClient((string) ($_GET['tenant_id'] ?? ''), $loggedInUserId) ?? '';
$mode = strtolower(trim((string)($_GET['mode'] ?? '')));

if ($mode === 'create') {
    header('Location: index.php?m=eazybackup&a=ph-tenants-manage&legacy=e3-tenant-create');
    exit;
}

if ($tenantPublicId !== '') {
    header('Location: index.php?m=eazybackup&a=ph-tenant&id=' . rawurlencode($tenantPublicId) . '&legacy=e3-tenant-detail');
    exit;
}

header('Location: index.php?m=eazybackup&a=ph-tenants-manage&legacy=e3-tenant-detail');
exit;
