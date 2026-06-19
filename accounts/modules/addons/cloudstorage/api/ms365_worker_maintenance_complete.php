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
$status = trim((string) ($body['status'] ?? 'success'));
$result = $body['result'] ?? $body['result_json'] ?? [];
if (!is_array($result)) {
    $result = [];
}

if ($operationId <= 0) {
    (new JsonResponse(['status' => 'error', 'message' => 'operation_id required'], 400))->send();
    exit;
}

try {
    Ms365KopiaRepoOperationService::markComplete(
        $operationId,
        $status === 'error' ? 'error' : 'success',
        $result
    );
    (new JsonResponse(['status' => 'success']))->send();
} catch (\Throwable $e) {
    (new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 500))->send();
}
