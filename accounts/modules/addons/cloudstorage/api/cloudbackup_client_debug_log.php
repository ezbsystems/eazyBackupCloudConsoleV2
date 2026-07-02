<?php

require_once __DIR__ . '/../../../../init.php';

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Session timeout.'], 200))->send();
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    (new JsonResponse(['status' => 'fail', 'message' => 'Invalid method.'], 405))->send();
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '', true);
if (!is_array($payload)) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Invalid payload.'], 200))->send();
    exit;
}

$logPath = '/var/www/eazybackup.ca/.cursor/debug-91dc3e.log';
$entry = [
    'sessionId' => '91dc3e',
    'id' => uniqid('log_', true),
    'timestamp' => (int) round(microtime(true) * 1000),
    'location' => (string) ($payload['location'] ?? 'cloudbackup_client_debug_log.php'),
    'message' => (string) ($payload['message'] ?? ''),
    'data' => is_array($payload['data'] ?? null) ? $payload['data'] : [],
    'runId' => isset($payload['runId']) ? (string) $payload['runId'] : 'client',
    'hypothesisId' => (string) ($payload['hypothesisId'] ?? ''),
];
@file_put_contents($logPath, json_encode($entry) . PHP_EOL, FILE_APPEND);

(new JsonResponse(['status' => 'success'], 200))->send();
exit;
