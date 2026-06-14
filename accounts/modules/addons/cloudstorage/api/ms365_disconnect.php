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

$clientId = (int) $ca->getUserID();
$userId = trim((string) ($_POST['user_id'] ?? ''));

try {
    $ms365 = Ms365E3Controller::disconnect($clientId, $userId);
    (new JsonResponse([
        'status' => 'success',
        'ms365' => $ms365,
    ]))->send();
} catch (\Throwable $e) {
    Ms365E3Controller::apiErrorResponse($e, 'ms365_disconnect')->send();
}
