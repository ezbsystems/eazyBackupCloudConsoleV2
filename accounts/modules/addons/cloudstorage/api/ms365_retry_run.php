<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/Ms365E3Controller.php';

use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Client\Ms365E3Controller;
use Symfony\Component\HttpFoundation\JsonResponse;

header('Content-Type: application/json');

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    (new JsonResponse(['status' => 'fail', 'message' => 'auth'], 401))->send();
    exit;
}

$runId = trim((string) ($_POST['run_id'] ?? ''));
if ($runId === '') {
    (new JsonResponse(['status' => 'fail', 'message' => 'run_id required'], 400))->send();
    exit;
}

try {
    $newRunId = Ms365E3Controller::retryRun($runId);
    (new JsonResponse(['status' => 'success', 'run_id' => $newRunId])->send();
} catch (\Throwable $e) {
    (new JsonResponse(['status' => 'fail', 'message' => Ms365E3Controller::customerErrorMessage($e, 'ms365_retry_run')], 500))->send();
}
