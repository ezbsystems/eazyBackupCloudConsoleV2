<?php

require_once __DIR__ . '/../../../../init.php';
require_once dirname(__DIR__) . '/../ms365backup/ms365backup_autoload.php';

use Ms365Backup\Ms365RestoreWorkerHooks;
use Ms365Backup\Ms365WorkerApiAuth;
use Ms365Backup\WorkerClaimService;
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
    // #region agent log
    @file_put_contents('/var/www/eazybackup.ca/.cursor/debug-95d465.log', json_encode([
        'sessionId' => '95d465',
        'hypothesisId' => 'B',
        'location' => 'ms365_worker_fail.php',
        'message' => 'worker fail received',
        'data' => ['run_id' => $runId, 'message_len' => strlen($message)],
        'timestamp' => (int) round(microtime(true) * 1000),
    ], JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND);
    // #endregion
    WorkerClaimService::requireRestoreRunId($runId);
    Ms365RestoreWorkerHooks::onFail($runId, $message);
    (new JsonResponse(['status' => 'success']))->send();
} catch (\Throwable $e) {
    // #region agent log
    @file_put_contents('/var/www/eazybackup.ca/.cursor/debug-95d465.log', json_encode([
        'sessionId' => '95d465',
        'hypothesisId' => 'B',
        'location' => 'ms365_worker_fail.php',
        'message' => 'worker fail exception',
        'data' => ['run_id' => $runId, 'error' => $e->getMessage()],
        'timestamp' => (int) round(microtime(true) * 1000),
    ], JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND);
    // #endregion
    (new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 500))->send();
}
