<?php

require_once __DIR__ . '/../../../../init.php';

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Client\CloudBackupController;

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

$packageId = ProductConfig::$E3_PRODUCT_ID;
$loggedInUserId = $ca->getUserID();

$product = DBController::getProduct($loggedInUserId, $packageId);
if (is_null($product) || empty($product->username)) {
    $jsonData = [
        'status' => 'fail',
        'message' => 'Product not found.'
    ];
    $response = new JsonResponse($jsonData, 200);
    $response->send();
    exit();
}

$runIdentifier = $_POST['run_id'] ?? $_POST['run_uuid'] ?? null;
$forceCancel = isset($_POST['force']) ? filter_var($_POST['force'], FILTER_VALIDATE_BOOLEAN) : false;
if (!$runIdentifier) {
    $jsonData = [
        'status' => 'fail',
        'message' => 'Run ID is required.'
    ];
    $response = new JsonResponse($jsonData, 200);
    $response->send();
    exit();
}

// Debug: Log the cancel request
logModuleCall('cloudstorage', 'cancel_run_request', [
    'run_identifier' => $runIdentifier,
    'client_id' => $loggedInUserId,
    'post_data' => $_POST,
], 'Received cancel request');

$result = CloudBackupController::cancelRun($runIdentifier, $loggedInUserId, $forceCancel);

// Debug: Log the result
logModuleCall('cloudstorage', 'cancel_run_result', [
    'run_identifier' => $runIdentifier,
    'result' => $result,
], 'Cancel result');

$response = new JsonResponse($result, 200);
$response->send();
exit();

