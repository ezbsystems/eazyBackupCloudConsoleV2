<?php
/**
 * Fetch recovery restore run status using session token.
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
    respond(['status' => 'fail', 'message' => 'session_token is required'], 400);
}

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

if ($runId === '' && !empty($tokenRow->session_run_id_uuid ?? '')) {
    $runId = trim((string) $tokenRow->session_run_id_uuid);
}
if ($runId === '' && !empty($tokenRow->session_run_id) && !$hasRunIdPk) {
    $runFromToken = Capsule::table('s3_cloudbackup_runs')->where('id', $tokenRow->session_run_id)->first(['run_uuid']);
    $runId = $runFromToken ? ($runFromToken->run_uuid ?? '') : '';
}
if ($runId === '') {
    respond(['status' => 'fail', 'message' => 'run_id is required'], 400);
}
if (!UuidBinary::isUuid($runId)) {
    respond(['status' => 'fail', 'code' => 'invalid_identifier_format', 'message' => 'run_id must be UUID'], 400);
}
$runIdNorm = UuidBinary::normalize($runId);

if ($hasRunIdPk && !empty($tokenRow->session_run_id_uuid ?? '')) {
    if (UuidBinary::normalize(trim((string) $tokenRow->session_run_id_uuid)) !== $runIdNorm) {
        respond(['status' => 'fail', 'message' => 'Session token does not match run_id'], 403);
    }
} elseif (!$hasRunIdPk && !empty($tokenRow->session_run_id)) {
    $runCheck = Capsule::table('s3_cloudbackup_runs')->where('run_uuid', $runIdNorm)->first();
    if (!$runCheck || $tokenRow->session_run_id != $runCheck->id) {
        respond(['status' => 'fail', 'message' => 'Session token does not match run_id'], 403);
    }
}

refreshSessionExpiry($tokenRow, $runIdNorm);

$runSelect = [
    'status',
    'progress_pct',
    'bytes_transferred',
    'bytes_total',
    'speed_bytes_per_sec',
    'eta_seconds',
    'current_item',
    'error_summary',
    'started_at',
    'finished_at',
    'run_uuid',
];
if ($hasRunIdPk) {
    $runSelect = array_merge([Capsule::raw('BIN_TO_UUID(run_id) as run_id')], $runSelect);
}
$run = $hasRunIdPk
    ? Capsule::table('s3_cloudbackup_runs')->whereRaw('run_id = ' . UuidBinary::toDbExpr($runIdNorm))->first($runSelect)
    : Capsule::table('s3_cloudbackup_runs')->where('run_uuid', $runIdNorm)->first($runSelect);
if (!$run) {
    respond(['status' => 'fail', 'message' => 'Run not found'], 404);
}

respond([
    'status' => 'success',
    'run' => [
        'run_id' => $hasRunIdPk ? (string) ($run->run_id ?? $runIdNorm) : (string) ($run->run_uuid ?? $runIdNorm),
        'status' => (string) ($run->status ?? ''),
        'progress_pct' => $run->progress_pct !== null ? (float) $run->progress_pct : null,
        'bytes_transferred' => $run->bytes_transferred !== null ? (int) $run->bytes_transferred : null,
        'bytes_total' => $run->bytes_total !== null ? (int) $run->bytes_total : null,
        'speed_bytes_per_sec' => $run->speed_bytes_per_sec !== null ? (int) $run->speed_bytes_per_sec : null,
        'eta_seconds' => $run->eta_seconds !== null ? (int) $run->eta_seconds : null,
        'current_item' => $run->current_item ?? null,
        'error_summary' => $run->error_summary ?? null,
        'started_at' => $run->started_at ?? null,
        'finished_at' => $run->finished_at ?? null,
    ],
]);
