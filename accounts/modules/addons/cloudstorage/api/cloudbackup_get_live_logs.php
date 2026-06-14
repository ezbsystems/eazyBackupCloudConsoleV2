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
use WHMCS\Module\Addon\CloudStorage\Client\Ms365BatchLiveService;
use WHMCS\Module\Addon\CloudStorage\Client\SanitizedLogFormatter;
use WHMCS\Module\Addon\CloudStorage\Client\TimezoneHelper;

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    $response = new JsonResponse(['status' => 'fail', 'message' => 'Session timeout.'], 200);
    $response->send();
    exit();
}

$packageId = ProductConfig::e3CloudBackupPid();
$loggedInUserId = $ca->getUserID();

$product = DBController::getProduct($loggedInUserId, $packageId);
if (is_null($product) || empty($product->username)) {
    $response = new JsonResponse(['status' => 'fail', 'message' => 'Product not found.'], 200);
    $response->send();
    exit();
}

$runIdentifier = $_GET['run_uuid'] ?? ($_GET['run_id'] ?? null);
if (!$runIdentifier) {
    $response = new JsonResponse(['status' => 'fail', 'message' => 'Run ID is required.'], 200);
    $response->send();
    exit();
}

// Verify run ownership and get run details
$run = CloudBackupController::getRun($runIdentifier, $loggedInUserId);
if (!$run) {
    $response = new JsonResponse(['status' => 'fail', 'message' => 'Run not found or access denied.'], 200);
    $response->send();
    exit();
}
$userTz = TimezoneHelper::resolveUserTimezone($loggedInUserId, $run['job_id'] ?? null);

if (Ms365BatchLiveService::isMs365BatchRun($run)) {
    try {
        $batchRunId = (string) ($run['run_id'] ?? $runIdentifier);
        $payload = Ms365BatchLiveService::aggregateStructuredLogs($batchRunId, (int) $loggedInUserId, $userTz);
        $formattedLog = (string) ($payload['backup_log'] ?? '');
        $hash = $formattedLog !== '' ? md5($formattedLog) : null;
        $clientHash = isset($_GET['hash']) ? (string) $_GET['hash'] : null;
        if ($hash && $clientHash && hash_equals($hash, $clientHash)) {
            (new JsonResponse([
                'status' => 'success',
                'unchanged' => true,
                'hash' => $hash,
            ], 200))->send();
            exit();
        }
        (new JsonResponse([
            'status' => 'success',
            'hash' => $hash,
            'formatted_log' => $formattedLog,
            'entries' => $payload['structured_logs'] ?? [],
            'sanitized' => true,
            'run' => [
                'status' => $run['status'] ?? null,
                'error_summary' => $run['error_summary'] ?? '',
                'worker_host' => '',
                'started_at' => $run['started_at'] ?? null,
                'finished_at' => $run['finished_at'] ?? null,
            ],
        ], 200))->send();
        exit();
    } catch (\Throwable $e) {
        (new JsonResponse(['status' => 'fail', 'message' => 'Unable to load run logs.'], 200))->send();
        exit();
    }
}

$logExcerpt = $run['log_excerpt'] ?? '';
$hash = $logExcerpt ? md5($logExcerpt) : null;
$clientHash = isset($_GET['hash']) ? (string)$_GET['hash'] : null;

// If hash unchanged, return minimal payload
if ($hash && $clientHash && hash_equals($hash, $clientHash)) {
    $response = new JsonResponse([
        'status' => 'success',
        'unchanged' => true,
        'hash' => $hash,
    ], 200);
    $response->send();
    exit();
}

$sanitized = $logExcerpt ? SanitizedLogFormatter::sanitizeAndStructure($logExcerpt, $run['status'] ?? null, $userTz) : ['entries'=>[],'formatted_log'=>'','hash'=>null,'sanitized'=>true];

$response = new JsonResponse([
    'status' => 'success',
    'hash' => $hash,
    'formatted_log' => $sanitized['formatted_log'],
    'entries' => $sanitized['entries'],
    'sanitized' => true,
    'run' => [
        'status' => $run['status'] ?? null,
        'error_summary' => $run['error_summary'] ?? '',
        'worker_host' => $run['worker_host'] ?? '',
        'started_at' => $run['started_at'] ?? null,
        'finished_at' => $run['finished_at'] ?? null,
    ],
], 200);
$response->send();
exit();


