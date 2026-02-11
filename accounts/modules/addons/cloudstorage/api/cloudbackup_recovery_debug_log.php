<?php
/**
 * Dev-only recovery debug logging (throttled).
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

$debugEnabled = getenv('E3_RECOVERY_DEBUG_LOG');
if ($debugEnabled !== '1') {
    respond(['status' => 'fail', 'message' => 'debug logging disabled'], 403);
}

$body = getBodyJson();
$sessionToken = trim((string) ($_POST['session_token'] ?? ($body['session_token'] ?? '')));
$runId = $_POST['run_id'] ?? ($body['run_id'] ?? null);
$level = strtolower((string) ($body['level'] ?? 'info'));
$code = (string) ($body['code'] ?? '');
$message = (string) ($body['message'] ?? '');
$details = $body['details'] ?? [];

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

$throttleSeconds = (int) (getenv('E3_RECOVERY_DEBUG_LOG_THROTTLE_SECONDS') ?: 5);
$throttleSeconds = max(1, $throttleSeconds);
$throttleKey = hash('sha256', $sessionToken . '|' . (int) $runId . '|' . $level . '|' . $code);
$throttleFile = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'cloudbackup_recovery_debug_' . $throttleKey . '.json';
if (is_file($throttleFile)) {
    $last = @filemtime($throttleFile);
    if ($last && (time() - $last) < $throttleSeconds) {
        respond(['status' => 'success', 'throttled' => true]);
    }
}
@file_put_contents($throttleFile, json_encode(['ts' => time()]));

if (strlen($message) > 2000) {
    $message = substr($message, 0, 2000) . '…';
}

$details = sanitizeLogPayload($details);

logModuleCall('cloudstorage', 'cloudbackup_recovery_debug_log', [
    'run_id' => (int) $runId,
    'level' => $level,
    'code' => $code,
], [
    'message' => $message,
    'details' => $details,
], '', []);

respond(['status' => 'success']);
