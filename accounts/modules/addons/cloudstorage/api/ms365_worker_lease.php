<?php

require_once __DIR__ . '/../../../../init.php';
require_once dirname(__DIR__) . '/../ms365backup/ms365backup_autoload.php';

use Ms365Backup\Ms365WorkerApiAuth;
use Ms365Backup\WorkerLeaseService;
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
$nodeId = trim((string) ($body['node_id'] ?? $request->headers->get('X-MS365-Worker-Node', '')));

if ($runId === '') {
    (new JsonResponse(['status' => 'error', 'message' => 'run_id required'], 400))->send();
    exit;
}

try {
    $renewed = WorkerLeaseService::renewForRun($runId, $nodeId !== '' ? $nodeId : null);
    $leaseExpiresAt = WorkerLeaseService::leaseExpiresAt($runId);
    (new JsonResponse([
        'status' => 'success',
        'renewed' => $renewed,
        'lease_expires_at' => $leaseExpiresAt,
        'lease_seconds' => \Ms365Backup\Ms365EngineConfig::leaseSeconds(),
    ]))->send();
} catch (\Throwable $e) {
    (new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 500))->send();
}
