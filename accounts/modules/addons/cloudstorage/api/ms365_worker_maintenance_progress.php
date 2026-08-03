<?php

require_once __DIR__ . '/../../../../init.php';
require_once dirname(__DIR__) . '/../ms365backup/ms365backup_autoload.php';

use Ms365Backup\Ms365KopiaRepoOperationService;
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
$operationId = (int) ($body['operation_id'] ?? 0);
$nodeId = trim((string) ($body['node_id'] ?? $request->headers->get('X-MS365-Worker-Node', '')));
$phase = trim((string) ($body['phase'] ?? ''));
$fields = $body['fields'] ?? $body['result'] ?? [];
if (!is_array($fields)) {
    $fields = [];
}

if ($operationId <= 0 || $nodeId === '' || $phase === '') {
    (new JsonResponse(['status' => 'error', 'message' => 'operation_id, node_id, and phase required'], 400))->send();
    exit;
}

try {
    $ok = Ms365KopiaRepoOperationService::recordProgress($operationId, $nodeId, $phase, $fields);
    if (!$ok) {
        (new JsonResponse(['status' => 'error', 'message' => 'progress not accepted'], 409))->send();
        exit;
    }
    (new JsonResponse(['status' => 'success']))->send();
} catch (\Throwable $e) {
    (new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 500))->send();
}
