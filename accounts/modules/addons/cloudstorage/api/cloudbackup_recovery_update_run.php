<?php
/**
 * Update restore run progress from recovery environment using session token.
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

function refreshSessionExpiry(object $tokenRow, int $runId, int $hours = 6, int $minRemainingMinutes = 30): void
{
    if (!isset($tokenRow->id)) {
        return;
    }
    $now = new DateTime();
    $refreshAt = (clone $now)->modify('+' . $minRemainingMinutes . ' minutes');
    $expiresAt = null;
    if (!empty($tokenRow->session_expires_at)) {
        $tmp = DateTime::createFromFormat('Y-m-d H:i:s', (string) $tokenRow->session_expires_at);
        if ($tmp !== false) {
            $expiresAt = $tmp;
        }
    }
    if ($expiresAt !== null && $expiresAt > $refreshAt) {
        return;
    }
    $newExpiry = (clone $now)->modify('+' . $hours . ' hours')->format('Y-m-d H:i:s');
    $update = ['session_expires_at' => $newExpiry];
    if (Capsule::schema()->hasColumn('s3_cloudbackup_recovery_tokens', 'updated_at')) {
        $update['updated_at'] = date('Y-m-d H:i:s');
    }
    Capsule::table('s3_cloudbackup_recovery_tokens')
        ->where('id', (int) $tokenRow->id)
        ->update($update);
}

$body = getBodyJson();
$sessionToken = trim((string) ($_POST['session_token'] ?? ($body['session_token'] ?? '')));
$runId = $_POST['run_id'] ?? ($body['run_id'] ?? null);

if ($sessionToken === '' || !$runId) {
    respond(['status' => 'fail', 'message' => 'session_token and run_id are required'], 400);
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

refreshSessionExpiry($tokenRow, (int) $runId);

$run = Capsule::table('s3_cloudbackup_runs')
    ->where('id', $runId)
    ->first();
if (!$run) {
    respond(['status' => 'fail', 'message' => 'Run not found'], 404);
}

$fields = [
    'status',
    'progress_pct',
    'bytes_transferred',
    'bytes_processed',
    'bytes_total',
    'objects_transferred',
    'objects_total',
    'speed_bytes_per_sec',
    'eta_seconds',
    'current_item',
    'log_excerpt',
    'error_summary',
    'started_at',
    'finished_at',
    'progress_json',
    'stats_json',
];

$update = [];
foreach ($fields as $field) {
    if (!array_key_exists($field, $body)) {
        continue;
    }
    $val = $body[$field];
    if (in_array($field, ['started_at', 'finished_at'], true)) {
        if ($val !== null && $val !== '') {
            $ts = strtotime((string) $val);
            if ($ts !== false) {
                $update[$field] = date('Y-m-d H:i:s', $ts);
            }
        }
        continue;
    }
    if (in_array($field, ['progress_json', 'stats_json'], true)) {
        $update[$field] = is_array($val) ? json_encode($val) : $val;
        continue;
    }
    $update[$field] = $val;
}

if (empty($update)) {
    respond(['status' => 'success', 'message' => 'No fields to update']);
}

if (Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'updated_at')) {
    $update['updated_at'] = Capsule::raw('NOW()');
}

Capsule::table('s3_cloudbackup_runs')
    ->where('id', $runId)
    ->update($update);

// Module logging for failed status or error summary (admin visibility)
$logResult = [];
if (isset($update['status']) && $update['status'] === 'failed' && (string) ($run->status ?? '') !== 'failed') {
    $logResult['status'] = 'failed';
}
if (isset($update['error_summary']) && $update['error_summary'] !== '') {
    $logResult['error_summary'] = $update['error_summary'];
}
if (isset($update['started_at'])) {
    $logResult['started_at'] = $update['started_at'];
}
if (isset($update['finished_at'])) {
    $logResult['finished_at'] = $update['finished_at'];
}
if (!empty($logResult)) {
    logModuleCall('cloudstorage', 'cloudbackup_recovery_update_run', [
        'run_id' => (int) $runId,
        'status' => $update['status'] ?? null,
    ], $logResult, '', []);
}

respond(['status' => 'success']);
