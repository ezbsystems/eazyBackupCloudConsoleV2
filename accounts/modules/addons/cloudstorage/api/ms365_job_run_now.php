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
$jobId = trim((string) ($_POST['job_id'] ?? ''));

if ($userId === '' || $jobId === '') {
    (new JsonResponse(['status' => 'fail', 'message' => 'user_id and job_id are required'], 400))->send();
    exit;
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

try {
    $result = Ms365E3Controller::runJobNow($clientId, $userId, $jobId);
    (new JsonResponse([
        'status' => 'success',
        'run_ids' => $result['run_ids'],
        'batch_run_id' => $result['batch_run_id'],
        'count' => $result['count'],
    ]))->send();
} catch (\Throwable $e) {
    Ms365E3Controller::apiErrorResponse($e, 'ms365_job_run_now')->send();
}
