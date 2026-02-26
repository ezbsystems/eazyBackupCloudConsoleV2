<?php
/**
 * Dev-only: read recovery debug logs from WHMCS module log.
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

function decodeLogPayload($raw)
{
    if (!is_string($raw) || $raw === '') {
        return null;
    }
    $trim = trim($raw);
    if ($trim === '') {
        return null;
    }
    if ($trim[0] === '{' || $trim[0] === '[') {
        $dec = json_decode($trim, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $dec;
        }
    }
    $b64 = base64_decode($trim, true);
    if ($b64 !== false && $b64 !== '') {
        $dec = @unserialize($b64, ['allowed_classes' => false]);
        if ($dec !== false || $b64 === 'b:0;') {
            return $dec;
        }
    }
    $dec = @unserialize($trim, ['allowed_classes' => false]);
    if ($dec !== false || $trim === 'b:0;') {
        return $dec;
    }
    return null;
}

function extractRunId($decoded, $raw, $fallbackRunId): ?string
{
    if (is_array($decoded) && isset($decoded['run_id'])) {
        $v = $decoded['run_id'];
        return is_string($v) && UuidBinary::isUuid($v) ? UuidBinary::normalize($v) : (string) $v;
    }
    if (is_string($raw) && $fallbackRunId !== null && $fallbackRunId !== '') {
        if (strpos($raw, (string) $fallbackRunId) !== false) {
            return UuidBinary::isUuid($fallbackRunId) ? UuidBinary::normalize($fallbackRunId) : (string) $fallbackRunId;
        }
    }
    return null;
}

if (getenv('E3_RECOVERY_DEBUG_LOG') !== '1') {
    respond(['status' => 'fail', 'message' => 'debug logging disabled'], 403);
}

$body = getBodyJson();
$sessionToken = trim((string) ($_POST['session_token'] ?? ($body['session_token'] ?? '')));
$runId = trim((string) ($_POST['run_id'] ?? ($body['run_id'] ?? '')));
$sinceId = isset($_GET['since_id']) ? (int) $_GET['since_id'] : (int) ($body['since_id'] ?? 0);
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : (int) ($body['limit'] ?? 200);
if ($limit <= 0 || $limit > 1000) {
    $limit = 200;
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

$query = Capsule::table('tblmodulelog')
    ->where('module', 'cloudstorage')
    ->where('action', 'cloudbackup_recovery_debug_log')
    ->orderBy('id', 'asc');
if ($sinceId > 0) {
    $query->where('id', '>', $sinceId);
}
$rows = $query->limit($limit)->get(['id', 'date', 'request', 'response', 'data']);

$logs = [];
foreach ($rows as $row) {
    $reqDecoded = decodeLogPayload($row->request ?? '');
    $respDecoded = decodeLogPayload($row->response ?? '');
    $runIdDecoded = extractRunId($reqDecoded, (string) ($row->request ?? ''), $runIdNorm);
    if ($runIdDecoded === null || $runIdDecoded !== $runIdNorm) {
        continue;
    }
    $level = is_array($reqDecoded) && isset($reqDecoded['level']) ? (string) $reqDecoded['level'] : 'info';
    $code = is_array($reqDecoded) && isset($reqDecoded['code']) ? (string) $reqDecoded['code'] : '';
    $message = '';
    $details = [];
    if (is_array($respDecoded)) {
        $message = isset($respDecoded['message']) ? (string) $respDecoded['message'] : '';
        $details = isset($respDecoded['details']) && is_array($respDecoded['details']) ? $respDecoded['details'] : [];
    } elseif (is_string($respDecoded)) {
        $message = $respDecoded;
    }
    $logs[] = [
        'id' => (int) $row->id,
        'time' => (string) ($row->date ?? ''),
        'level' => $level,
        'code' => $code,
        'message' => $message,
        'details' => sanitizeLogPayload($details),
    ];
}

respond(['status' => 'success', 'logs' => $logs]);
