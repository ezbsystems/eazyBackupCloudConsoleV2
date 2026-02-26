<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/MspController.php';
require_once __DIR__ . '/../lib/Client/UuidBinary.php';

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Client\CloudBackupController;
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

$result = CloudBackupController::deleteJob($jobId, $loggedInUserId);

$response = new JsonResponse($result, 200);
$response->send();
exit();

