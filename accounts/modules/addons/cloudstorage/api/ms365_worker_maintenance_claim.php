<?php

require_once __DIR__ . '/../../../../init.php';
require_once dirname(__DIR__) . '/../ms365backup/ms365backup_autoload.php';

use Ms365Backup\Ms365KopiaRepoOperationService;
use Ms365Backup\Ms365WorkerApiAuth;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

header('Content-Type: application/json');

// #region agent log
$agentDebugStart = hrtime(true);
$agentDebugSample = random_int(1, 20) === 1;
if ($agentDebugSample) {
    register_shutdown_function(static function () use ($agentDebugStart): void {
        file_put_contents('/var/www/eazybackup.ca/.cursor/debug-459149.log', json_encode(['sessionId' => '459149', 'runId' => 'pre-fix', 'hypothesisId' => 'H4', 'location' => 'cloudstorage/api/ms365_worker_maintenance_claim.php:13', 'message' => 'Sampled worker maintenance claim request completed', 'data' => ['durationMs' => round((hrtime(true) - $agentDebugStart) / 1e6, 3), 'responseCode' => http_response_code(), 'peakMemoryMiB' => round(memory_get_peak_usage(true) / 1048576, 2)], 'timestamp' => (int) round(microtime(true) * 1000)], JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
    });
}
// #endregion

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
    $job = Ms365KopiaRepoOperationService::claimNextForWorker($nodeId);
    if ($job === null) {
        (new JsonResponse(['status' => 'success', 'data' => null]))->send();
        exit;
    }
    (new JsonResponse(['status' => 'success', 'data' => $job]))->send();
} catch (\Throwable $e) {
    (new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 500))->send();
}
