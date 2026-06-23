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

$restoreRunId = '';
$contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
if (str_contains($contentType, 'application/json')) {
    $rawInput = file_get_contents('php://input');
    $body = json_decode($rawInput ?: '', true);
    if (is_array($body)) {
        $restoreRunId = trim((string) ($body['restore_run_id'] ?? ''));
    }
} else {
    $restoreRunId = trim((string) ($_GET['restore_run_id'] ?? $_POST['restore_run_id'] ?? ''));
}

if ($restoreRunId === '') {
    (new JsonResponse(['status' => 'fail', 'message' => 'restore_run_id is required'], 400))->send();
    exit;
}

try {
    $clientId = (int) $ca->getUserID();
    $result = Ms365E3Controller::restoreArchiveDownloadUrl($clientId, $restoreRunId);
    (new JsonResponse([
        'status' => 'success',
        'download_url' => $result['download_url'],
        'expires_at' => $result['expires_at'],
        'restore_run_id' => $restoreRunId,
    ]))->send();
} catch (\Throwable $e) {
    Ms365E3Controller::apiErrorResponse($e, 'ms365_restore_download')->send();
}
