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

// Legacy e3 tenant routes remain valid entry points, but Partner Hub is canonical.
$tenantId = isset($_GET['tenant_id']) ? (int) $_GET['tenant_id'] : 0;
$legacyView = strtolower(trim((string) ($_GET['view'] ?? '')));
$mode = strtolower(trim((string) ($_GET['mode'] ?? '')));

$targetUrl = 'index.php?m=eazybackup&a=ph-tenants-manage&legacy=e3-tenants';

if ($legacyView === 'tenant_detail') {
    if ($mode === 'create') {
        $targetUrl = 'index.php?m=eazybackup&a=ph-tenants-manage&legacy=e3-tenant-create';
    } elseif ($tenantId > 0) {
        $targetUrl = 'index.php?m=eazybackup&a=ph-tenant&id=' . $tenantId . '&legacy=e3-tenant-detail';
    }
} elseif ($legacyView === 'tenant_members' || $legacyView === 'tenant_users') {
    if ($tenantId > 0) {
        $targetUrl = 'index.php?m=eazybackup&a=ph-tenant-members&id=' . $tenantId . '&legacy=e3-tenant-members';
    } else {
        $targetUrl = 'index.php?m=eazybackup&a=ph-tenants-manage&legacy=e3-tenant-members';
    }
} elseif ($tenantId > 0) {
    $targetUrl = 'index.php?m=eazybackup&a=ph-tenant&id=' . $tenantId . '&legacy=e3-tenants';
}

header('Location: ' . $targetUrl);
exit;

