<?php

require_once __DIR__ . '/../../../../init.php';
require_once dirname(__DIR__) . '/../ms365backup/ms365backup_autoload.php';

use Ms365Backup\Ms365KopiaMaintenanceService;
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
$nodeId = trim((string) ($body['node_id'] ?? $request->headers->get('X-MS365-Worker-Node', '')));

if ($nodeId === '') {
    (new JsonResponse(['status' => 'error', 'message' => 'node_id required'], 400))->send();
    exit;
}

try {
    $job = Ms365KopiaMaintenanceService::claimNextForWorker($nodeId);
    if ($job === null) {
        (new JsonResponse(['status' => 'success', 'data' => null]))->send();
        exit;
    }
    (new JsonResponse(['status' => 'success', 'data' => $job]))->send();
} catch (\Throwable $e) {
    (new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 500))->send();
}
