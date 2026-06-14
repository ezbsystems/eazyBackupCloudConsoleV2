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

$clientId = (int) $ca->getUserID();
$runId = trim((string) ($_GET['run_id'] ?? ''));
$sinceId = max(0, (int) ($_GET['since_id'] ?? 0));

if ($runId === '') {
    (new JsonResponse(['status' => 'fail', 'message' => 'run_id required'], 400))->send();
    exit;
}

try {
    $payload = Ms365E3Controller::runLogs($clientId, $runId, $sinceId);
    (new JsonResponse([
        'status' => 'success',
        'lines' => $payload['lines'],
        'last_id' => $payload['last_id'],
    ]))->send();
} catch (\Throwable $e) {
    (new JsonResponse(['status' => 'fail', 'message' => Ms365E3Controller::customerErrorMessage($e, 'ms365_run_logs')], 500))->send();
}
