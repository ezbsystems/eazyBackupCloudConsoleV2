<?php

require_once __DIR__ . '/../../../../init.php';
require_once dirname(__DIR__) . '/../ms365backup/ms365backup_autoload.php';

use Ms365Backup\Ms365RestoreWorkerHooks;
use Ms365Backup\Ms365WorkerApiAuth;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use WHMCS\Module\Addon\CloudStorage\Client\CustomerFacingTextSanitizer;

header('Content-Type: application/json');

$request = Request::createFromGlobals();
if ($auth = Ms365WorkerApiAuth::authenticate($request)) {
    $auth->send();
    exit;
}

$body = Ms365WorkerApiAuth::jsonBody($request);
$runId = trim((string) ($body['run_id'] ?? ''));
$message = CustomerFacingTextSanitizer::scrubLogMessage(trim((string) ($body['message'] ?? 'Job failed')));

if ($runId === '') {
    (new JsonResponse(['status' => 'error', 'message' => 'run_id required'], 400))->send();
    exit;
}

try {
    Ms365RestoreWorkerHooks::onFail($runId, $message);
    (new JsonResponse(['status' => 'success']))->send();
} catch (\Throwable $e) {
    (new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 500))->send();
}
