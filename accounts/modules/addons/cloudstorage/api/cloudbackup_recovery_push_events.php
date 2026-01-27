<?php
/**
 * Record restore events from recovery environment using session token.
 */

require_once __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function respond(array $data, int $httpCode = 200): void
{
    (new JsonResponse($data, $httpCode))->send();
    exit;
}

function getBodyJson(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

$body = getBodyJson();
$runId = $_POST['run_id'] ?? ($body['run_id'] ?? null);
$events = $body['events'] ?? [];
$sessionToken = trim((string) ($_POST['session_token'] ?? ($body['session_token'] ?? '')));

if (!$runId || $sessionToken === '') {
    respond(['status' => 'fail', 'message' => 'run_id and session_token are required'], 400);
}

$tokenRow = Capsule::table('s3_cloudbackup_recovery_tokens')
    ->where('session_token', $sessionToken)
    ->first();
if (!$tokenRow) {
    respond(['status' => 'fail', 'message' => 'Invalid session token'], 403);
}
if (!empty($tokenRow->session_expires_at) && strtotime((string) $tokenRow->session_expires_at) < time()) {
    respond(['status' => 'fail', 'message' => 'Session token expired'], 403);
}
if (!empty($tokenRow->session_run_id) && (int) $tokenRow->session_run_id !== (int) $runId) {
    respond(['status' => 'fail', 'message' => 'Session token does not match run_id'], 403);
}

if (!is_array($events) || empty($events)) {
    respond(['status' => 'success', 'message' => 'No events to record']);
}

$rows = [];
$nowMicro = microtime(true);
foreach ($events as $event) {
    if (!is_array($event)) {
        continue;
    }
    $code = $event['code'] ?? ($event['message_id'] ?? '');
    $params = $event['params_json'] ?? null;
    if (is_array($params)) {
        $params = json_encode($params);
    }
    if ($params === null) {
        $params = '';
    }
    $rows[] = [
        'run_id' => (int) $runId,
        'ts' => date('Y-m-d H:i:s.u', $nowMicro),
        'type' => $event['type'] ?? 'info',
        'level' => $event['level'] ?? 'info',
        'code' => $code,
        'message_id' => $event['message_id'] ?? null,
        'params_json' => $params,
    ];
}

if (!empty($rows) && Capsule::schema()->hasTable('s3_cloudbackup_run_events')) {
    Capsule::table('s3_cloudbackup_run_events')->insert($rows);
}

respond(['status' => 'success', 'inserted' => count($rows)]);
