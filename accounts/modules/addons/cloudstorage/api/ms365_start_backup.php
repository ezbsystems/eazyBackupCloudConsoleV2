<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/Ms365E3Controller.php';

use Ms365Backup\CustomerBackupService;
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
$preset = (string) ($_POST['preset'] ?? $_GET['preset'] ?? CustomerBackupService::PRESET_USER_MAIL_CALENDAR);

try {
    $result = Ms365E3Controller::startBackup($clientId, $preset);
    (new JsonResponse(['status' => 'success'] + $result))->send();
} catch (\Throwable $e) {
    Ms365E3Controller::apiErrorResponse($e, 'ms365_start_backup')->send();
}
