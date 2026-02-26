<?php
/**
 * Fetch recovery run events using session token.
 */

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/CloudBackupEventFormatter.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\Module\Addon\CloudStorage\Client\CloudBackupEventFormatter;
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

function sanitizeEventId(string $value): string
{
    if ($value === '') {
        return $value;
    }
    return str_ireplace('KOPIA', 'EAZYBACKUP', $value);
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
$sinceId = isset($_GET['since_id']) ? (int) $_GET['since_id'] : (int) ($body['since_id'] ?? 0);
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : (int) ($body['limit'] ?? 250);
if ($limit <= 0 || $limit > 1000) {
    $limit = 250;
}

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

$run = $hasRunIdPk
    ? Capsule::table('s3_cloudbackup_runs')->whereRaw('run_id = ' . UuidBinary::toDbExpr($runIdNorm))->first(['status', 'bytes_transferred', 'finished_at'])
    : Capsule::table('s3_cloudbackup_runs')->where('run_uuid', $runIdNorm)->first(['status', 'bytes_transferred', 'finished_at']);
if (!$run) {
    respond(['status' => 'fail', 'message' => 'Run not found'], 404);
}

$query = Capsule::table('s3_cloudbackup_run_events')
    ->select(['id', 'ts', 'type', 'level', 'code', 'message_id', 'params_json']);
if ($hasRunIdPk) {
    $query->whereRaw('run_id = ' . UuidBinary::toDbExpr($runIdNorm));
} else {
    $runForEvents = Capsule::table('s3_cloudbackup_runs')->where('run_uuid', $runIdNorm)->first(['id']);
    $query->where('run_id', (int) ($runForEvents->id ?? 0));
}
$query->orderBy('id', 'asc');
if ($sinceId > 0) {
    $query->where('id', '>', $sinceId);
}
$events = $query->limit($limit)->get();

$out = [];
$hasProgressOrNoChanges = false;
foreach ($events as $e) {
    $params = [];
    if (!empty($e->params_json)) {
        $decoded = json_decode($e->params_json, true);
        if (is_array($decoded)) {
            $params = $decoded;
        }
    }
    $safeParams = CloudBackupEventFormatter::sanitizeParamsForOutput($params);
    $message = CloudBackupEventFormatter::render($e->message_id, $safeParams);
    $safeCode = sanitizeEventId((string) $e->code);
    $safeMessageId = sanitizeEventId((string) $e->message_id);
    $out[] = [
        'id' => (int) $e->id,
        'ts' => (string) $e->ts,
        'type' => (string) $e->type,
        'level' => (string) $e->level,
        'code' => $safeCode,
        'message_id' => $safeMessageId,
        'params' => $safeParams,
        'message' => $message,
    ];
    if (in_array((string) $e->message_id, ['PROGRESS_UPDATE','NO_CHANGES','SUMMARY_TOTAL'], true)) {
        $hasProgressOrNoChanges = true;
    }
}

try {
    $isInitialLoad = ($sinceId <= 0);
    $isTerminal = in_array((string) ($run->status ?? ''), ['success','failed','warning','cancelled'], true);
    if ($isInitialLoad && $isTerminal && !$hasProgressOrNoChanges && isset($run->bytes_transferred)) {
        $bytes = (int) ($run->bytes_transferred ?? 0);
        $summaryMsg = CloudBackupEventFormatter::render('SUMMARY_TOTAL', ['bytes_done' => $bytes]);
        $out[] = [
            'id' => $out ? ((int) end($out)['id'] + 1) : 1,
            'ts' => (string) ($run->finished_at ?? date('Y-m-d H:i:s')),
            'type' => 'summary',
            'level' => 'info',
            'code' => 'SUMMARY_TOTAL',
            'message_id' => 'SUMMARY_TOTAL',
            'params' => ['bytes_done' => $bytes],
            'message' => $summaryMsg,
        ];
    }
} catch (\Throwable $e) {
    // Best-effort; do not fail the entire request
}

respond(['status' => 'success', 'events' => $out]);
