<?php

require_once __DIR__ . '/../../../../init.php';
require_once dirname(__DIR__) . '/../ms365backup/ms365backup_autoload.php';

use Ms365Backup\Ms365WorkerLogRepository;
use Ms365Backup\Ms365WorkerApiAuth;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use WHMCS\Database\Capsule;

header('Content-Type: application/json');

$request = Request::createFromGlobals();
if ($auth = Ms365WorkerApiAuth::authenticate($request)) {
    $auth->send();
    exit;
}

$body = Ms365WorkerApiAuth::jsonBody($request);
$runId = trim((string) ($body['run_id'] ?? ''));
$nodeId = trim((string) ($body['node_id'] ?? $request->headers->get('X-MS365-Worker-Node', '')));
$lines = $body['lines'] ?? [];

if ($runId === '' || $nodeId === '') {
    (new JsonResponse(['status' => 'error', 'message' => 'run_id and node_id required'], 400))->send();
    exit;
}
if (!is_array($lines) || $lines === []) {
    (new JsonResponse(['status' => 'error', 'message' => 'lines array required'], 400))->send();
    exit;
}
if (count($lines) > 100) {
    (new JsonResponse(['status' => 'error', 'message' => 'max 100 lines per request'], 400))->send();
    exit;
}

if (!Ms365WorkerLogRepository::tablesReady()) {
    (new JsonResponse(['status' => 'error', 'message' => 'worker log tables not ready'], 503))->send();
    exit;
}

$allowed = Ms365WorkerLogRepository::isRunActiveOnNode($runId, $nodeId)
    || Ms365WorkerLogRepository::runRecentlyClaimedByNode($runId, $nodeId, 86400);
if (!$allowed) {
    (new JsonResponse(['status' => 'error', 'message' => 'run not assigned to this worker node'], 403))->send();
    exit;
}

$queueOk = Capsule::table('ms365_job_queue')
    ->where('run_id', $runId)
    ->whereIn('status', ['running', 'queued', 'done', 'failed'])
    ->exists();
if (!$queueOk) {
    (new JsonResponse(['status' => 'error', 'message' => 'unknown run_id'], 404))->send();
    exit;
}

$normalized = [];
foreach ($lines as $line) {
    if (!is_array($line)) {
        continue;
    }
    $message = trim((string) ($line['message'] ?? ''));
    if ($message === '') {
        continue;
    }
    $normalized[] = [
        'level' => (string) ($line['level'] ?? 'info'),
        'message' => $message,
        'ts' => isset($line['ts']) ? (int) $line['ts'] : time(),
    ];
}

if ($normalized === []) {
    (new JsonResponse(['status' => 'error', 'message' => 'no valid lines'], 400))->send();
    exit;
}

try {
    $inserted = Ms365WorkerLogRepository::insertLogLines($runId, $nodeId, $normalized);
    (new JsonResponse(['status' => 'success', 'inserted' => $inserted]))->send();
} catch (\Throwable $e) {
    (new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 500))->send();
}
