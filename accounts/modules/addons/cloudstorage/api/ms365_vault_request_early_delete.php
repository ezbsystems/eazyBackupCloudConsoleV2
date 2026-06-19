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

$bucketId = (int) ($_POST['bucket_id'] ?? 0);
$userIdRaw = trim((string) ($_POST['user_id'] ?? ''));
$reason = trim((string) ($_POST['reason'] ?? ''));

if ($bucketId <= 0) {
    (new JsonResponse(['status' => 'fail', 'message' => 'bucket_id is required.'], 400))->send();
    exit;
}

$backupUserId = 0;
if ($userIdRaw !== '') {
    try {
        $user = Ms365E3Controller::resolveBackupUser($clientId, $userIdRaw);
        $backupUserId = (int) $user['id'];
    } catch (\Throwable $e) {
        (new JsonResponse(['status' => 'fail', 'message' => 'User not found.'], 404))->send();
        exit;
    }
}

$contactId = 0;
if (isset($_SESSION['contactid']) && (int) $_SESSION['contactid'] > 0) {
    $contactId = (int) $_SESSION['contactid'];
} elseif (isset($_SESSION['cid']) && (int) $_SESSION['cid'] > 0) {
    $contactId = (int) $_SESSION['cid'];
}

$context = [
    'actor_client_user_id' => $clientId,
    'actor_contact_id' => $contactId > 0 ? $contactId : null,
    'request_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
    'request_ua' => $_SERVER['HTTP_USER_AGENT'] ?? null,
    'backup_user_id' => $backupUserId > 0 ? $backupUserId : null,
    'reason' => $reason !== '' ? $reason : null,
];

$result = Ms365VaultLifecycleService::requestEarlyDeletion($bucketId, $clientId, $context);

$code = ($result['status'] ?? '') === 'success' ? 200 : 400;
(new JsonResponse($result, $code))->send();
exit();
