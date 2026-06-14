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
$jobId = trim((string) ($_GET['job_id'] ?? ''));

if ($userId === '' || $jobId === '') {
    (new JsonResponse(['status' => 'fail', 'message' => 'user_id and job_id are required'], 400))->send();
    exit;
}

try {
    $job = Ms365E3Controller::getJob($clientId, $userId, $jobId);
    if ($job === null) {
        (new JsonResponse(['status' => 'fail', 'message' => 'Job not found'], 404))->send();
        exit;
    }
    (new JsonResponse([
        'status' => 'success',
        'job' => $job,
    ]))->send();
} catch (\Throwable $e) {
    (new JsonResponse(['status' => 'fail', 'message' => Ms365E3Controller::customerErrorMessage($e, 'ms365_job_get')], 500))->send();
}
