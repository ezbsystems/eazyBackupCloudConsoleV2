<?php
/**
 * Request cancellation for a recovery restore run using session token.
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
$sessionToken = trim((string) ($_POST['session_token'] ?? ($body['session_token'] ?? '')));
$runId = $_POST['run_id'] ?? ($body['run_id'] ?? null);

if ($sessionToken === '') {
    respond(['status' => 'fail', 'message' => 'session_token is required'], 400);
}
if (!$runId) {
    respond(['status' => 'fail', 'message' => 'run_id is required'], 400);
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

$run = Capsule::table('s3_cloudbackup_runs')
    ->where('id', (int) $runId)
    ->first(['id', 'status']);
if (!$run) {
    respond(['status' => 'fail', 'message' => 'Run not found'], 404);
}

$update = ['cancel_requested' => 1];
if (Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'updated_at')) {
    $update['updated_at'] = Capsule::raw('NOW()');
}

Capsule::table('s3_cloudbackup_runs')
    ->where('id', (int) $runId)
    ->update($update);

if (Capsule::schema()->hasTable('s3_cloudbackup_run_events')) {
    Capsule::table('s3_cloudbackup_run_events')->insert([
        'run_id' => (int) $runId,
        'ts' => date('Y-m-d H:i:s.u', microtime(true)),
        'type' => 'cancelled',
        'level' => 'warn',
        'code' => 'CANCEL_REQUESTED',
        'message_id' => 'CANCEL_REQUESTED',
        'params_json' => '',
    ]);
}

respond(['status' => 'success', 'message' => 'Cancellation requested', 'run_id' => (int) $runId]);
