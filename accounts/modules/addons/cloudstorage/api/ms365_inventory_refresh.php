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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    (new JsonResponse(['status' => 'fail', 'message' => 'POST required'], 405))->send();
    exit;
}

set_time_limit(600);

$clientId = (int) $ca->getUserID();
$userId = trim((string) ($_POST['user_id'] ?? ''));
if ($userId === '') {
    (new JsonResponse(['status' => 'fail', 'message' => 'user_id is required'], 400))->send();
    exit;
}

try {
    $result = Ms365E3Controller::refreshInventory($clientId, $userId);
    (new JsonResponse([
        'status' => 'success',
        'inventory' => $result,
        'warnings' => $result['warnings'] ?? [],
    ]))->send();
} catch (\Throwable $e) {
    Ms365E3Controller::apiErrorResponse($e, 'ms365_inventory_refresh')->send();
}
