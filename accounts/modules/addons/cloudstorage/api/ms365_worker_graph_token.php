<?php

require_once __DIR__ . '/../../../../init.php';
require_once dirname(__DIR__) . '/../ms365backup/ms365backup_autoload.php';

use Ms365Backup\Ms365WorkerApiAuth;
use Ms365Backup\WorkerClaimService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

header('Content-Type: application/json');

$request = Request::createFromGlobals();
if ($auth = Ms365WorkerApiAuth::authenticate($request)) {
    $auth->send();
    exit;
}

$body = Ms365WorkerApiAuth::jsonBody($request);
$runId = trim((string) ($body['run_id'] ?? ''));

if ($runId === '') {
    (new JsonResponse(['status' => 'error', 'message' => 'run_id required'], 400))->send();
    exit;
}

try {
    $token = WorkerClaimService::refreshGraphTokenForRun($runId);
    (new JsonResponse(['status' => 'success', 'data' => $token]))->send();
} catch (\Throwable $e) {
    (new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 500))->send();
}
