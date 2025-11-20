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
use WHMCS\Module\Addon\CloudStorage\Client\CloudBackupLogFormatter;
use WHMCS\Module\Addon\CloudStorage\Client\SanitizedLogFormatter;

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

$runId = $_GET['run_id'] ?? null;
if (!$runId) {
    $jsonData = [
        'status' => 'fail',
        'message' => 'Run ID is required.'
    ];
    $response = new JsonResponse($jsonData, 200);
    $response->send();
    exit();
}

// Verify run ownership and get run details
$run = CloudBackupController::getRun($runId, $loggedInUserId);
if (!$run) {
    $jsonData = [
        'status' => 'fail',
        'message' => 'Run not found or access denied.'
    ];
    $response = new JsonResponse($jsonData, 200);
    $response->send();
    exit();
}

// Client area: sanitized logs
$sanitized = SanitizedLogFormatter::sanitizeAndStructure($run['log_excerpt'] ?? null, $run['status'] ?? null);
$formattedBackupLog = $sanitized['formatted_log'];
$formattedValidationLog = null;

// Format validation log if available
if (!empty($run['validation_log_excerpt']) && $run['validation_mode'] === 'post_run') {
	$valSan = SanitizedLogFormatter::sanitizeAndStructure($run['validation_log_excerpt'], $run['validation_status'] ?? null);
	$formattedValidationLog = $valSan['formatted_log'];
}

$jsonData = [
    'status' => 'success',
    'backup_log' => $formattedBackupLog,
    'validation_log' => $formattedValidationLog,
    'has_validation' => !empty($run['validation_log_excerpt']) && $run['validation_mode'] === 'post_run'
];

$response = new JsonResponse($jsonData, 200);
$response->send();
exit();

