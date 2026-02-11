<?php
/**
 * Exchange a short recovery token for a session token and restore context.
 */

require_once __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\Module\Addon\CloudStorage\Client\HelperController;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function respond(array $data, int $httpCode = 200): void
{
    (new JsonResponse($data, $httpCode))->send();
    exit;
}

function respondError(string $code, string $message, int $httpCode = 200, array $extra = []): void
{
    respond(array_merge([
        'status' => 'fail',
        'code' => $code,
        'message' => $message,
    ], $extra), $httpCode);
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

function generateSessionToken(): string
{
    return bin2hex(random_bytes(24));
}

function normalizeToken(string $token): string
{
    return strtoupper(trim($token));
}

function hashToken(string $token): string
{
    return hash('sha256', normalizeToken($token));
}

function getClientIp(): string
{
    $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    if ($ip === '') {
        return 'unknown';
    }
    return $ip;
}

function hashIp(string $ip): string
{
    return hash('sha256', $ip);
}

function ensureTokenHashColumn(string $table): void
{
    if (Capsule::schema()->hasColumn($table, 'token_hash')) {
        return;
    }
    try {
        Capsule::statement("ALTER TABLE `{$table}` ADD COLUMN `token_hash` VARCHAR(64) NULL");
    } catch (\Throwable $e) {
        // Continue; it may already exist under race conditions.
    }
    if (!Capsule::schema()->hasColumn($table, 'token_hash')) {
        throw new \RuntimeException('token_hash column is missing');
    }
    try {
        Capsule::statement("UPDATE `{$table}` SET `token_hash` = SHA2(UPPER(TRIM(`token`)), 256) WHERE (`token_hash` IS NULL OR `token_hash` = '') AND `token` IS NOT NULL AND `token` != ''");
    } catch (\Throwable $e) {
        logModuleCall('cloudstorage', 'cloudbackup_recovery_token_exchange_token_hash_backfill_fail', [
            'table' => $table,
        ], $e->getMessage());
    }
}

function resetIpLimiterIfWindowExpired(object $limiterRow): void
{
    if (empty($limiterRow->window_started_at)) {
        return;
    }
    $windowStartedTs = strtotime((string) $limiterRow->window_started_at);
    if ($windowStartedTs === false) {
        return;
    }
    if ($windowStartedTs + 600 > time()) {
        return;
    }
    Capsule::table('s3_cloudbackup_recovery_exchange_limits')
        ->where('id', (int) $limiterRow->id)
        ->update([
            'attempt_count' => 0,
            'window_started_at' => date('Y-m-d H:i:s'),
            'locked_until' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
}

function failIpLimiter(string $ip, string $reason): void
{
    $ipHash = hashIp($ip);
    $now = date('Y-m-d H:i:s');
    $limiter = Capsule::table('s3_cloudbackup_recovery_exchange_limits')
        ->where('ip_hash', $ipHash)
        ->first();
    if (!$limiter) {
        Capsule::table('s3_cloudbackup_recovery_exchange_limits')->insert([
            'ip_hash' => $ipHash,
            'attempt_count' => 1,
            'window_started_at' => $now,
            'locked_until' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return;
    }

    resetIpLimiterIfWindowExpired($limiter);
    $limiter = Capsule::table('s3_cloudbackup_recovery_exchange_limits')
        ->where('id', (int) $limiter->id)
        ->first();
    if (!$limiter) {
        return;
    }

    $attemptCount = (int) ($limiter->attempt_count ?? 0) + 1;
    $update = [
        'attempt_count' => $attemptCount,
        'updated_at' => $now,
    ];
    if ($attemptCount >= 8) {
        $update['locked_until'] = date('Y-m-d H:i:s', time() + 900);
    }
    Capsule::table('s3_cloudbackup_recovery_exchange_limits')
        ->where('id', (int) $limiter->id)
        ->update($update);

    logModuleCall('cloudstorage', 'cloudbackup_recovery_token_exchange_fail', [
        'reason' => $reason,
        'ip_hash' => $ipHash,
    ], ['attempt_count' => $attemptCount]);
}

function isIpLocked(string $ip): bool
{
    $limiter = Capsule::table('s3_cloudbackup_recovery_exchange_limits')
        ->where('ip_hash', hashIp($ip))
        ->first();
    if (!$limiter) {
        return false;
    }
    if (empty($limiter->locked_until)) {
        resetIpLimiterIfWindowExpired($limiter);
        return false;
    }
    $lockedUntilTs = strtotime((string) $limiter->locked_until);
    if ($lockedUntilTs === false || $lockedUntilTs <= time()) {
        Capsule::table('s3_cloudbackup_recovery_exchange_limits')
            ->where('id', (int) $limiter->id)
            ->update([
                'locked_until' => null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        return false;
    }
    return true;
}

$body = getBodyJson();
$token = trim((string) ($_POST['token'] ?? ($body['token'] ?? '')));
$ip = getClientIp();

if ($token === '') {
    respondError('invalid_request', 'token is required', 400);
}

if (!Capsule::schema()->hasTable('s3_cloudbackup_recovery_tokens')) {
    respondError('schema_upgrade_required', 'Recovery tokens not supported', 500);
}
if (!Capsule::schema()->hasTable('s3_cloudbackup_recovery_exchange_limits')) {
    respondError('schema_upgrade_required', 'Recovery exchange limiter not supported. Please run module upgrade.', 500);
}

if (isIpLocked($ip)) {
    respondError('rate_limited', 'Too many recovery token attempts. Try again later.', 429);
}

$tokenTable = 's3_cloudbackup_recovery_tokens';
try {
    ensureTokenHashColumn($tokenTable);
} catch (\Throwable $e) {
    respondError('schema_upgrade_required', 'Recovery token schema is incomplete. Please run module upgrade.', 500, [
        'missing_columns' => ['token_hash'],
    ]);
}

$tokenHash = hashToken($token);
$row = Capsule::table('s3_cloudbackup_recovery_tokens')
    ->where('token_hash', $tokenHash)
    ->first();
if (!$row) {
    failIpLimiter($ip, 'token_not_found');
    respondError('invalid_token', 'Invalid recovery token', 404);
}

if (!hash_equals((string) ($row->token_hash ?? ''), $tokenHash)) {
    failIpLimiter($ip, 'token_hash_mismatch');
    respondError('invalid_token', 'Invalid recovery token', 404);
}
if (!empty($row->locked_until) && strtotime((string) $row->locked_until) > time()) {
    respondError('token_locked', 'Recovery token is temporarily locked', 429);
}
if (!empty($row->revoked_at)) {
    respondError('token_revoked', 'Recovery token has been revoked', 403);
}
if (!empty($row->used_at)) {
    respondError('token_used', 'Recovery token has already been used', 403);
}
if ((int) ($row->exchange_count ?? 0) > 0) {
    respondError('token_exchanged', 'Recovery token has already been exchanged', 403);
}
if (!empty($row->expires_at) && strtotime((string) $row->expires_at) < time()) {
    respondError('token_expired', 'Recovery token has expired', 403);
}

$restorePoint = Capsule::table('s3_cloudbackup_restore_points')
    ->where('id', $row->restore_point_id)
    ->where('client_id', $row->client_id)
    ->first();
if (!$restorePoint) {
    respondError('not_found', 'Restore point not found', 404);
}

$policyJSON = null;
if (!empty($restorePoint->job_id)) {
    $jobPolicyRaw = Capsule::table('s3_cloudbackup_jobs')
        ->where('id', $restorePoint->job_id)
        ->value('policy_json');
    if ($jobPolicyRaw !== null && $jobPolicyRaw !== '') {
        $dec = json_decode($jobPolicyRaw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($dec)) {
            $policyJSON = $dec;
        }
    }
}

if (!in_array(($restorePoint->status ?? ''), ['success', 'warning'], true)) {
    respondError('invalid_state', 'Restore point is not available for recovery', 400);
}

// Get bucket and access keys
$bucket = null;
if (!empty($restorePoint->dest_bucket_id)) {
    $bucket = Capsule::table('s3_buckets')
        ->where('id', $restorePoint->dest_bucket_id)
        ->first();
}

$keys = null;
if (!empty($restorePoint->s3_user_id)) {
    $keys = Capsule::table('s3_user_access_keys')
        ->where('user_id', $restorePoint->s3_user_id)
        ->orderByDesc('id')
        ->first();
}

// Get addon settings for endpoint/region
$settings = Capsule::table('tbladdonmodules')
    ->where('module', 'cloudstorage')
    ->pluck('value', 'setting');
$settingsMap = [];
foreach ($settings as $k => $v) {
    $settingsMap[$k] = $v;
}

$agentEndpoint = $settingsMap['cloudbackup_agent_s3_endpoint'] ?? '';
if (empty($agentEndpoint)) {
    $agentEndpoint = $settingsMap['s3_endpoint'] ?? '';
}
if (empty($agentEndpoint)) {
    $agentEndpoint = 'https://s3.ca-central-1.eazybackup.com';
}
$agentRegion = $settingsMap['cloudbackup_agent_s3_region'] ?? ($settingsMap['s3_region'] ?? '');

// Decrypt access keys
$encKeyPrimary = $settingsMap['cloudbackup_encryption_key'] ?? '';
$encKeySecondary = $settingsMap['encryption_key'] ?? '';
$accessKeyRaw = $keys->access_key ?? '';
$secretKeyRaw = $keys->secret_key ?? '';

$decryptWith = function (?string $key) use ($accessKeyRaw, $secretKeyRaw) {
    $ak = $accessKeyRaw;
    $sk = $secretKeyRaw;
    if ($key && $ak) {
        $ak = HelperController::decryptKey($ak, $key);
    }
    if ($key && $sk) {
        $sk = HelperController::decryptKey($sk, $key);
    }
    return [
        is_string($ak) ? $ak : '',
        is_string($sk) ? $sk : '',
    ];
};

[$decAkPrimary, $decSkPrimary] = $decryptWith($encKeyPrimary);
$decAk = $decAkPrimary;
$decSk = $decSkPrimary;
if ($decAk === '' || $decSk === '') {
    [$decAkSecondary, $decSkSecondary] = $decryptWith($encKeySecondary);
    if ($decAkSecondary !== '' && $decSkSecondary !== '') {
        $decAk = $decAkSecondary;
        $decSk = $decSkSecondary;
    }
}

$sessionToken = generateSessionToken();
$sessionExpiry = (new DateTime())->modify('+1 hour')->format('Y-m-d H:i:s');
$exchangedIp = $_SERVER['REMOTE_ADDR'] ?? null;
$exchangedUA = $_SERVER['HTTP_USER_AGENT'] ?? null;

Capsule::table('s3_cloudbackup_recovery_tokens')
    ->where('id', $row->id)
    ->update([
        'session_token' => $sessionToken,
        'session_expires_at' => $sessionExpiry,
        'exchange_count' => Capsule::raw('COALESCE(exchange_count, 0) + 1'),
        'failed_attempts' => 0,
        'locked_until' => null,
        'exchanged_at' => date('Y-m-d H:i:s'),
        'exchanged_ip' => $exchangedIp,
        'exchanged_user_agent' => $exchangedUA,
        'updated_at' => date('Y-m-d H:i:s'),
    ]);

Capsule::table('s3_cloudbackup_recovery_exchange_limits')
    ->where('ip_hash', hashIp($ip))
    ->update([
        'attempt_count' => 0,
        'locked_until' => null,
        'updated_at' => date('Y-m-d H:i:s'),
    ]);

respond([
    'status' => 'success',
    'session_token' => $sessionToken,
    'session_expires_at' => $sessionExpiry,
    'restore_point' => [
        'id' => (int) $restorePoint->id,
        'job_id' => (int) ($restorePoint->job_id ?? 0),
        'engine' => $restorePoint->engine ?? 'disk_image',
        'manifest_id' => $restorePoint->manifest_id ?? '',
        'disk_layout_json' => $restorePoint->disk_layout_json ?? null,
        'disk_total_bytes' => $restorePoint->disk_total_bytes ?? null,
        'disk_used_bytes' => $restorePoint->disk_used_bytes ?? null,
        'disk_boot_mode' => $restorePoint->disk_boot_mode ?? null,
        'disk_partition_style' => $restorePoint->disk_partition_style ?? null,
        'policy_json' => $policyJSON,
    ],
    'storage' => [
        'dest_type' => $restorePoint->dest_type ?? 's3',
        'bucket' => $bucket->name ?? '',
        'prefix' => $restorePoint->dest_prefix ?? '',
        'endpoint' => $agentEndpoint,
        'region' => $agentRegion,
        'access_key' => $decAk,
        'secret_key' => $decSk,
    ],
]);
