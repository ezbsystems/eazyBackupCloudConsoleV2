<?php
/**
 * Record restore events from recovery environment using session token.
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

function shouldRedactKey(string $key): bool
{
    $k = strtolower($key);
    $tokens = ['access', 'secret', 'token', 'authorization', 'password'];
    foreach ($tokens as $t) {
        if (strpos($k, $t) !== false) {
            return true;
        }
    }
    return false;
}

function sanitizeLogPayload($value)
{
    if (is_array($value)) {
        $out = [];
        foreach ($value as $k => $v) {
            if (is_string($k) && shouldRedactKey($k)) {
                $out[$k] = '[redacted]';
                continue;
            }
            $out[$k] = sanitizeLogPayload($v);
        }
        return $out;
    }
    return $value;
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
$runId = trim((string) ($_POST['run_id'] ?? ($body['run_id'] ?? '')));
$events = $body['events'] ?? [];
$logEntries = $body['logs'] ?? [];
$sessionToken = trim((string) ($_POST['session_token'] ?? ($body['session_token'] ?? '')));

if ($sessionToken === '') {
    respond(['status' => 'fail', 'message' => 'run_id and session_token are required'], 400);
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

$runForLegacy = null;
if (!$hasRunIdPk && ((is_array($events) && !empty($events)) || (is_array($logEntries) && !empty($logEntries)))) {
    $runForLegacy = Capsule::table('s3_cloudbackup_runs')->where('run_uuid', $runIdNorm)->first();
    if (!$runForLegacy) {
        respond(['status' => 'fail', 'message' => 'Run not found'], 404);
    }
}

if ((!is_array($events) || empty($events)) && (!is_array($logEntries) || empty($logEntries))) {
    respond(['status' => 'success', 'message' => 'No events to record']);
}

$rows = [];
$nowMicro = microtime(true);
$importantEvents = [];
$timeSyncCodes = [
    'RECOVERY_TIME_SYNC_ATTEMPT' => true,
    'RECOVERY_TIME_DIAGNOSTICS' => true,
    'RECOVERY_TIME_SYNC_OK' => true,
    'RECOVERY_TIME_SYNC_FAILED' => true,
    'RECOVERY_STORAGE_INIT_FAILED' => true,
];
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
    $level = strtolower((string) ($event['level'] ?? 'info'));
    $messageId = (string) ($event['message_id'] ?? '');
    $isTimeSync = isset($timeSyncCodes[strtoupper((string) $code)]) || isset($timeSyncCodes[strtoupper($messageId)]);
    if ($level === 'error' || $isTimeSync) {
        $decodedParams = [];
        if (is_string($params) && $params !== '') {
            $dec = json_decode($params, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($dec)) {
                $decodedParams = $dec;
            }
        }
        $importantEvents[] = [
            'level' => $level,
            'code' => (string) $code,
            'message_id' => $messageId,
            'params' => sanitizeLogPayload($decodedParams),
        ];
    }
    $rows[] = [
        'run_id' => $hasRunIdPk ? Capsule::raw(UuidBinary::toDbExpr($runIdNorm)) : (int) ($runForLegacy->id ?? 0),
        'ts' => date('Y-m-d H:i:s.u', $nowMicro),
        'type' => $event['type'] ?? 'info',
        'level' => $level,
        'code' => $code,
        'message_id' => $event['message_id'] ?? null,
        'params_json' => $params,
    ];
}

if (!empty($rows) && Capsule::schema()->hasTable('s3_cloudbackup_run_events')) {
    Capsule::table('s3_cloudbackup_run_events')->insert($rows);
}

$logRowsInserted = 0;
if (is_array($logEntries) && !empty($logEntries) && Capsule::schema()->hasTable('s3_cloudbackup_run_logs')) {
    $logRows = [];
    foreach ($logEntries as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $logRows[] = [
            'run_id' => $hasRunIdPk ? Capsule::raw(UuidBinary::toDbExpr($runIdNorm)) : (int) ($runForLegacy->id ?? 0),
            'created_at' => date('Y-m-d H:i:s'),
            'level' => $entry['level'] ?? 'info',
            'code' => $entry['code'] ?? null,
            'message' => $entry['message'] ?? '',
            'details_json' => isset($entry['details_json']) && is_array($entry['details_json'])
                ? json_encode($entry['details_json'])
                : ($entry['details_json'] ?? null),
        ];
    }
    if (!empty($logRows)) {
        Capsule::table('s3_cloudbackup_run_logs')->insert($logRows);
        $logRowsInserted = count($logRows);
    }
}

if (!empty($importantEvents)) {
    logModuleCall('cloudstorage', 'cloudbackup_recovery_event', [
        'run_id' => $runIdNorm,
        'events' => count($rows),
        'important' => count($importantEvents),
    ], $importantEvents, '', []);
}

respond(['status' => 'success', 'inserted' => count($rows), 'logs_inserted' => $logRowsInserted]);
