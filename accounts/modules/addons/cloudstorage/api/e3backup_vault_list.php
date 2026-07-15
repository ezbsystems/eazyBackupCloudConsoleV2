<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/MspController.php';
require_once __DIR__ . '/../lib/Client/Ms365VaultLifecycleService.php';
require_once __DIR__ . '/../lib/Client/E3BackupUserScope.php';

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Client\MspController;
use WHMCS\Module\Addon\CloudStorage\Client\Ms365VaultLifecycleService;

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Session timeout.'], 200))->send();
    exit;
}

$packageId = ProductConfig::e3CloudBackupPid();
$clientId = (int) $ca->getUserID();
$product = DBController::getProduct($clientId, $packageId);
if ($product === null || empty($product->username)) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Product not found.'], 200))->send();
    exit;
}

$isMsp = MspController::isMspClient($clientId);
$tenantFilterRaw = isset($_GET['tenant_id']) ? trim((string) $_GET['tenant_id']) : null;
$tenantFilter = null;
$directOnly = false;

if ($tenantFilterRaw !== null && $tenantFilterRaw !== '') {
    if (!$isMsp) {
        (new JsonResponse(['status' => 'fail', 'message' => 'Tenant filtering is only available for MSP accounts'], 403))->send();
        exit;
    }
    if ($tenantFilterRaw === 'direct') {
        $directOnly = true;
    } else {
        $tenant = MspController::getTenantByPublicId($tenantFilterRaw, $clientId);
        if (!$tenant) {
            (new JsonResponse(['status' => 'fail', 'message' => 'Tenant not found'], 404))->send();
            exit;
        }
        $tenantFilter = (int) $tenant->id;
    }
}

try {
    $data = Ms365VaultLifecycleService::listVaultsForClient($clientId, $tenantFilter, $directOnly);

    (new JsonResponse([
        'status' => 'success',
        'grace_days' => $data['grace_days'],
        'vaults_active' => $data['vaults_active'],
        'vaults_recycle' => $data['vaults_recycle'],
        'legacy_vaults' => $data['legacy_vaults'],
    ], 200))->send();
} catch (\Throwable $e) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Failed to load vaults'], 500))->send();
}
exit();
