<?php
/**
 * Update restore run progress from recovery environment using session token.
 */

require_once __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\Module\Addon\CloudStorage\Client\UuidBinary;

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

function refreshSessionExpiry(object $tokenRow, string $runId, int $hours = 6, int $minRemainingMinutes = 30): void
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
$runId = trim((string) ($_POST['run_id'] ?? ($body['run_id'] ?? '')));

if ($sessionToken === '') {
    respond(['status' => 'fail', 'message' => 'session_token and run_id are required'], 400);
}
if ($runId === '') {
    respond(['status' => 'fail', 'code' => 'invalid_identifier_format', 'message' => 'run_id must be UUID'], 400);
}
if (!UuidBinary::isUuid($runId)) {
    respond(['status' => 'fail', 'code' => 'invalid_identifier_format', 'message' => 'run_id must be UUID'], 400);
}
$runIdNorm = UuidBinary::normalize($runId);

$hasRunIdPk = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'run_id');
$tokenQuery = Capsule::table('s3_cloudbackup_recovery_tokens')->where('session_token', $sessionToken);
if ($hasRunIdPk && Capsule::schema()->hasColumn('s3_cloudbackup_recovery_tokens', 'session_run_id')) {
    $tokenQuery->selectRaw('*, BIN_TO_UUID(session_run_id) as session_run_id_uuid');
}
$tokenRow = $tokenQuery->first();
if (!$tokenRow) {
    respond(['status' => 'fail', 'message' => 'Invalid session token'], 403);
}
if (!empty($tokenRow->session_expires_at) && strtotime((string) $tokenRow->session_expires_at) < time()) {
    respond(['status' => 'fail', 'message' => 'Session token expired'], 403);
}

$runQuery = Capsule::table('s3_cloudbackup_runs');
$run = $hasRunIdPk
    ? $runQuery->whereRaw('run_id = ' . UuidBinary::toDbExpr($runIdNorm))->first()
    : $runQuery->where('run_uuid', $runIdNorm)->first();
if (!$run) {
    respond(['status' => 'fail', 'message' => 'Run not found'], 404);
}

if ($hasRunIdPk && !empty($tokenRow->session_run_id_uuid ?? '')) {
    if (UuidBinary::normalize(trim((string) $tokenRow->session_run_id_uuid)) !== $runIdNorm) {
        respond(['status' => 'fail', 'message' => 'Session token does not match run_id'], 403);
    }
} elseif (!$hasRunIdPk && !empty($tokenRow->session_run_id)) {
    if ($tokenRow->session_run_id != $run->id) {
        respond(['status' => 'fail', 'message' => 'Session token does not match run_id'], 403);
    }
}

refreshSessionExpiry($tokenRow, $runIdNorm);

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

if ($hasRunIdPk) {
    Capsule::table('s3_cloudbackup_runs')->whereRaw('run_id = ' . UuidBinary::toDbExpr($runIdNorm))->update($update);
} else {
    Capsule::table('s3_cloudbackup_runs')->where('run_uuid', $runIdNorm)->update($update);
}

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
        'run_id' => $runIdNorm,
        'status' => $update['status'] ?? null,
    ], $logResult, '', []);
}

respond(['status' => 'success']);
