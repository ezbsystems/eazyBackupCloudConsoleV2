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
$userId = trim((string) ($_GET['user_id'] ?? ''));
if ($userId === '') {
    (new JsonResponse(['status' => 'fail', 'message' => 'user_id is required'], 400))->send();
    exit;
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

try {
    $inventory = Ms365E3Controller::inventoryFull($clientId, $userId);
    (new JsonResponse([
        'status' => 'success',
        'inventory' => $inventory,
    ]))->send();
} catch (\Throwable $e) {
    Ms365E3Controller::apiErrorResponse($e, 'ms365_inventory')->send();
}
