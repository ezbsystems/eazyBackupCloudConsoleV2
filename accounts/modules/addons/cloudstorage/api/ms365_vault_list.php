<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/MspController.php';
require_once __DIR__ . '/../lib/Client/Ms365E3Controller.php';
require_once __DIR__ . '/../lib/Client/Ms365VaultLifecycleService.php';

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Client\Ms365E3Controller;
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

$userIdRaw = trim((string) ($_GET['user_id'] ?? $_POST['user_id'] ?? ''));
if ($userIdRaw === '') {
    (new JsonResponse(['status' => 'fail', 'message' => 'user_id is required.'], 400))->send();
    exit;
}

try {
    $user = Ms365E3Controller::resolveBackupUser($clientId, $userIdRaw);
    $backupUserId = (int) $user['id'];
} catch (\Throwable $e) {
    (new JsonResponse(['status' => 'fail', 'message' => 'User not found.'], 404))->send();
    exit;
}

$vaults = Ms365VaultLifecycleService::listVaultsForBackupUser($clientId, $backupUserId);

(new JsonResponse([
    'status' => 'success',
    'grace_days' => Ms365VaultLifecycleService::getGraceDays(),
    'vaults_active' => $vaults['vaults_active'],
    'vaults_recycle' => $vaults['vaults_recycle'],
], 200))->send();
exit();
