<?php

ob_start();

require_once __DIR__ . '/../../../../init.php';

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Client\E3BackupAccess;
use WHMCS\Module\Addon\CloudStorage\Client\CloudBackupController;
use WHMCS\Module\Addon\CloudStorage\Client\Ms365BatchLiveService;
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

$runIdentifier = trim((string) ($_POST['run_id'] ?? $_POST['run_uuid'] ?? ''));
$forceCancel = isset($_POST['force']) ? filter_var($_POST['force'], FILTER_VALIDATE_BOOLEAN) : false;
if ($runIdentifier === '' || !UuidBinary::isUuid($runIdentifier)) {
    $response = new JsonResponse([
        'status' => 'fail',
        'code' => 'invalid_identifier_format',
        'message' => 'run_id must be a valid UUID.',
    ], 400);
    $response->send();
    exit();
}

// Debug: Log the cancel request
logModuleCall('cloudstorage', 'cancel_run_request', [
    'run_identifier' => $runIdentifier,
    'client_id' => $loggedInUserId,
    'post_data' => $_POST,
], 'Received cancel request');

$run = CloudBackupController::getRun($runIdentifier, $loggedInUserId);
if ($run && Ms365BatchLiveService::isMs365BatchRun($run)) {
    try {
        $result = Ms365BatchLiveService::cancelBatch($runIdentifier, (int) $loggedInUserId, $forceCancel);
    } catch (\Throwable $e) {
        logModuleCall('cloudstorage', 'ms365_cancel_batch_uncaught', [
            'run_identifier' => $runIdentifier,
            'client_id' => $loggedInUserId,
        ], $e->getMessage());
        $result = ['status' => 'fail', 'message' => 'Failed to cancel run. Please try again later.'];
    }
} else {
    $result = CloudBackupController::cancelRun($runIdentifier, $loggedInUserId, $forceCancel);
}

// Debug: Log the result
logModuleCall('cloudstorage', 'cancel_run_result', [
    'run_identifier' => $runIdentifier,
    'result' => $result,
], 'Cancel result');

if (ob_get_level() > 0) {
    ob_end_clean();
}

$response = new JsonResponse($result, 200);
$response->send();
exit();

