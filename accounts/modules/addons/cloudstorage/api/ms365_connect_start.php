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
$userId = trim((string) ($_POST['user_id'] ?? $_GET['user_id'] ?? ''));
$returnPath = trim((string) ($_POST['return_path'] ?? $_GET['return_path'] ?? ''));
$consentMode = trim((string) ($_POST['consent_mode'] ?? $_GET['consent_mode'] ?? 'redirect'));
if ($consentMode !== 'popup') {
    $consentMode = 'redirect';
}

try {
    (new JsonResponse([
        'status' => 'success',
        'consent_url' => Ms365E3Controller::connectStartUrl($clientId, $userId, $returnPath, $consentMode),
    ]))->send();
} catch (\Throwable $e) {
    (new JsonResponse(['status' => 'fail', 'message' => Ms365E3Controller::customerErrorMessage($e, 'ms365_connect_start')], 500))->send();
}
