<?php
/**
 * Refresh recovery session expiry using session token.
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

function refreshSessionExpiry(object $tokenRow, int $runId, int $hours = 6, int $minRemainingMinutes = 30): string
{
    $currentExpiry = '';
    if (!empty($tokenRow->session_expires_at)) {
        $currentExpiry = (string) $tokenRow->session_expires_at;
    }
    if (!isset($tokenRow->id)) {
        return $currentExpiry;
    }
    $now = new DateTime();
    $refreshAt = (clone $now)->modify('+' . $minRemainingMinutes . ' minutes');
    $expiresAt = null;
    if ($currentExpiry !== '') {
        $tmp = DateTime::createFromFormat('Y-m-d H:i:s', $currentExpiry);
        if ($tmp !== false) {
            $expiresAt = $tmp;
        }
    }
    if ($expiresAt !== null && $expiresAt > $refreshAt) {
        return $currentExpiry;
    }
    $newExpiry = (clone $now)->modify('+' . $hours . ' hours')->format('Y-m-d H:i:s');
    $update = ['session_expires_at' => $newExpiry];
    if (Capsule::schema()->hasColumn('s3_cloudbackup_recovery_tokens', 'updated_at')) {
        $update['updated_at'] = date('Y-m-d H:i:s');
    }
    Capsule::table('s3_cloudbackup_recovery_tokens')
        ->where('id', (int) $tokenRow->id)
        ->update($update);
    return $newExpiry;
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
    ->first(['id']);
if (!$run) {
    respond(['status' => 'fail', 'message' => 'Run not found'], 404);
}

$newExpiry = refreshSessionExpiry($tokenRow, (int) $runId);

respond([
    'status' => 'success',
    'session_expires_at' => $newExpiry,
]);
