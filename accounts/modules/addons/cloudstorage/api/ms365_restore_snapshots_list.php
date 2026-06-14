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
$userIdRaw = trim((string) ($_GET['user_id'] ?? ''));
$jobId = trim((string) ($_GET['job_id'] ?? ''));
$limit = min(100, max(1, (int) ($_GET['limit'] ?? 50)));
$offset = max(0, (int) ($_GET['offset'] ?? 0));

if ($userIdRaw === '' || $jobId === '') {
    (new JsonResponse(['status' => 'fail', 'message' => 'user_id and job_id are required'], 400))->send();
    exit;
}

try {
    $user = Ms365E3Controller::resolveBackupUser($clientId, $userIdRaw);
    $snapshots = Ms365E3Controller::listRestoreSnapshots($clientId, $user['id'], $jobId, $limit, $offset);
    (new JsonResponse([
        'status' => 'success',
        'restore_points' => $snapshots,
        'has_more' => count($snapshots) >= $limit,
        'next_offset' => $offset + count($snapshots),
    ]))->send();
} catch (\Throwable $e) {
    Ms365E3Controller::apiErrorResponse($e, 'ms365_restore_snapshots_list')->send();
}
