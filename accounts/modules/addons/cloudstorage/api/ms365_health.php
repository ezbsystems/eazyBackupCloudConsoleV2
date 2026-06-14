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

try {
    (new JsonResponse([
        'status' => 'success',
        'health' => Ms365E3Controller::health((int) $ca->getUserID()),
    ]))->send();
} catch (\Throwable $e) {
    (new JsonResponse(['status' => 'fail', 'message' => Ms365E3Controller::customerErrorMessage($e, 'ms365_health')], 500))->send();
}
