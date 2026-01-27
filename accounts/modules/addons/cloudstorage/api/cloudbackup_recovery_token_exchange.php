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
    return bin2hex(random_bytes(16));
}

$body = getBodyJson();
$token = trim((string) ($_POST['token'] ?? ($body['token'] ?? '')));

if ($token === '') {
    respond(['status' => 'fail', 'message' => 'token is required'], 400);
}

if (!Capsule::schema()->hasTable('s3_cloudbackup_recovery_tokens')) {
    respond(['status' => 'fail', 'message' => 'Recovery tokens not supported'], 500);
}

$row = Capsule::table('s3_cloudbackup_recovery_tokens')
    ->where('token', $token)
    ->first();
if (!$row) {
    respond(['status' => 'fail', 'message' => 'Invalid recovery token'], 404);
}

if (!empty($row->revoked_at)) {
    respond(['status' => 'fail', 'message' => 'Recovery token has been revoked'], 403);
}
if (!empty($row->used_at)) {
    respond(['status' => 'fail', 'message' => 'Recovery token has already been used'], 403);
}
if (!empty($row->expires_at) && strtotime((string) $row->expires_at) < time()) {
    respond(['status' => 'fail', 'message' => 'Recovery token has expired'], 403);
}

$restorePoint = Capsule::table('s3_cloudbackup_restore_points')
    ->where('id', $row->restore_point_id)
    ->where('client_id', $row->client_id)
    ->first();
if (!$restorePoint) {
    respond(['status' => 'fail', 'message' => 'Restore point not found'], 404);
}

if (!in_array(($restorePoint->status ?? ''), ['success', 'warning'], true)) {
    respond(['status' => 'fail', 'message' => 'Restore point is not available for recovery'], 400);
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
$sessionExpiry = (new DateTime())->modify('+6 hours')->format('Y-m-d H:i:s');
$exchangedIp = $_SERVER['REMOTE_ADDR'] ?? null;
$exchangedUA = $_SERVER['HTTP_USER_AGENT'] ?? null;

Capsule::table('s3_cloudbackup_recovery_tokens')
    ->where('id', $row->id)
    ->update([
        'session_token' => $sessionToken,
        'session_expires_at' => $sessionExpiry,
        'exchanged_at' => date('Y-m-d H:i:s'),
        'exchanged_ip' => $exchangedIp,
        'exchanged_user_agent' => $exchangedUA,
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
