<?php

require_once __DIR__ . '/../../../../init.php';
require_once dirname(__DIR__) . '/../ms365backup/ms365backup_autoload.php';

use Ms365Backup\Ms365BatchClaimRepository;
use Ms365Backup\Ms365RestoreWorkerHooks;
use Ms365Backup\Ms365WorkerApiAuth;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

header('Content-Type: application/json');

$request = Request::createFromGlobals();
if ($auth = Ms365WorkerApiAuth::authenticate($request)) {
    $auth->send();
    exit;
}

$body = Ms365WorkerApiAuth::jsonBody($request);
$batchRunId = trim((string) ($body['batch_run_id'] ?? ''));
$nodeId = trim((string) ($body['node_id'] ?? $request->headers->get('X-MS365-Worker-Node', '')));
$children = $body['children'] ?? [];

if ($batchRunId === '' || $nodeId === '') {
    (new JsonResponse(['status' => 'error', 'message' => 'batch_run_id and node_id required'], 400))->send();
    exit;
}
if (!is_array($children)) {
    (new JsonResponse(['status' => 'error', 'message' => 'children must be an array'], 400))->send();
    exit;
}

try {
    if (!Ms365BatchClaimRepository::hasLiveLease($batchRunId, $nodeId)) {
        (new JsonResponse(['status' => 'error', 'message' => 'Batch lease is not active for this node'], 409))->send();
        exit;
    }

    $graphTenantBudget = Ms365RestoreWorkerHooks::onBatchProgress($batchRunId, $nodeId, $children);

    $response = ['status' => 'success'];
    $data = [];
    if ($graphTenantBudget > 0) {
        $data['graph_tenant_budget'] = $graphTenantBudget;
    }
    if ($data !== []) {
        $response['data'] = $data;
    }
    (new JsonResponse($response))->send();
} catch (\Throwable $e) {
    (new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 500))->send();
}
