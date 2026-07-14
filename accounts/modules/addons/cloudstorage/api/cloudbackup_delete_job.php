<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/MspController.php';
require_once __DIR__ . '/../lib/Client/UuidBinary.php';
require_once __DIR__ . '/../lib/Client/CloudBackupController.php';
require_once __DIR__ . '/../lib/Client/Ms365VaultLifecycleService.php';
require_once __DIR__ . '/../lib/Client/Ms365VaultNotificationService.php';

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Client\CloudBackupController;
use WHMCS\Module\Addon\CloudStorage\Client\E3BackupAccess;
use WHMCS\Module\Addon\CloudStorage\Client\MspController;
use WHMCS\Module\Addon\CloudStorage\Client\UuidBinary;

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    $jsonData = [
        'status' => 'fail',
        'message' => 'Session timeout.'
    ];
    $response = new JsonResponse($jsonData, 200);
    $response->send();
    exit();
}

$loggedInUserId = (int) $ca->getUserID();

if (!E3BackupAccess::clientHasE3BackupAccess($loggedInUserId)) {
    $jsonData = [
        'status' => 'fail',
        'message' => 'Product not found.'
    ];
    $response = new JsonResponse($jsonData, 200);
    $response->send();
    exit();
}

$jobId = isset($_POST['job_id']) ? trim((string) $_POST['job_id']) : '';
if ($jobId === '' || !UuidBinary::isUuid($jobId)) {
    $response = new JsonResponse([
        'status' => 'fail',
        'code' => 'invalid_identifier_format',
        'message' => 'job_id must be a valid UUID.',
    ], 400);
    $response->send();
    exit();
}

// MSP tenant authorization check
$accessCheck = MspController::validateJobAccess($jobId, $loggedInUserId);
if (!$accessCheck['valid']) {
    $response = new JsonResponse([
        'status' => 'fail',
        'message' => $accessCheck['message']
    ], 200);
    $response->send();
    exit();
}

$confirmPhrase = isset($_POST['confirm_phrase']) ? trim((string) $_POST['confirm_phrase']) : '';

$contactId = 0;
if (isset($_SESSION['contactid']) && (int) $_SESSION['contactid'] > 0) {
    $contactId = (int) $_SESSION['contactid'];
} elseif (isset($_SESSION['cid']) && (int) $_SESSION['cid'] > 0) {
    $contactId = (int) $_SESSION['cid'];
}

$auditContext = [
    'actor_client_user_id' => (int) $loggedInUserId,
    'actor_contact_id' => $contactId > 0 ? $contactId : null,
    'request_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
    'request_ua' => $_SERVER['HTTP_USER_AGENT'] ?? null,
];

$result = CloudBackupController::deleteJob($jobId, $loggedInUserId, $confirmPhrase, $auditContext);

$response = new JsonResponse($result, 200);
$response->send();
exit();

