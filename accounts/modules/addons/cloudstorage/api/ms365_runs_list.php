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
$status = isset($_GET['status']) ? (string) $_GET['status'] : null;
$since = isset($_GET['since']) ? (int) $_GET['since'] : null;
$limit = min(100, max(1, (int) ($_GET['limit'] ?? 50)));

try {
    (new JsonResponse([
        'status' => 'success',
        'runs' => Ms365E3Controller::listRuns($clientId, $status, $since, $limit),
    ]))->send();
} catch (\Throwable $e) {
    (new JsonResponse(['status' => 'fail', 'message' => Ms365E3Controller::customerErrorMessage($e, 'ms365_runs_list')], 500))->send();
}
