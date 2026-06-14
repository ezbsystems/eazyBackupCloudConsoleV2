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
$userIdRaw = trim((string) ($_GET['user_id'] ?? $_POST['user_id'] ?? ''));
$batchRunId = trim((string) ($_GET['batch_run_id'] ?? ''));
$manifestId = trim((string) ($_GET['manifest_id'] ?? ''));
$path = (string) ($_GET['path'] ?? '');
$childRunId = trim((string) ($_GET['child_run_id'] ?? ''));

if ($userIdRaw === '' || $batchRunId === '') {
    (new JsonResponse(['status' => 'fail', 'message' => 'user_id and batch_run_id are required'], 400))->send();
    exit;
}

try {
    $user = Ms365E3Controller::resolveBackupUser($clientId, $userIdRaw);
    $entries = Ms365E3Controller::browseRestoreSnapshot(
        $clientId,
        $user['id'],
        $batchRunId,
        $manifestId,
        $path,
        $childRunId,
    );
    (new JsonResponse(['status' => 'success', 'entries' => $entries]))->send();
} catch (\Throwable $e) {
    Ms365E3Controller::apiErrorResponse($e, 'ms365_restore_browse')->send();
}
