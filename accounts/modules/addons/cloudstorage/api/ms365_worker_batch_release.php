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
$nodeId = trim((string) ($body['node_id'] ?? $request->headers->get('X-MS365-Worker-Node', '')));
$batchRunId = trim((string) ($body['batch_run_id'] ?? ''));
$reason = trim((string) ($body['reason'] ?? ''));

if ($nodeId === '' || $batchRunId === '') {
    (new JsonResponse(['status' => 'error', 'message' => 'node_id and batch_run_id required'], 400))->send();
    exit;
}

try {
    $released = WorkerClaimService::releaseBatchClaim($nodeId, $batchRunId, 'Worker released batch', $reason);
    (new JsonResponse(['status' => 'success', 'data' => ['released' => $released]]))->send();
} catch (\Throwable $e) {
    (new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 500))->send();
}
