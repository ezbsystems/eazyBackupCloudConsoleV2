<?php
/**
 * Create a short-lived recovery token for bare-metal restore.
 */

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/MspController.php';

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\MspController;

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

function generateShortToken(int $length = 10): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $max = strlen($alphabet) - 1;
    $token = '';
    for ($i = 0; $i < $length; $i++) {
        $token .= $alphabet[random_int(0, $max)];
    }
    return $token;
}

function hashRecoveryToken(string $token): string
{
    return hash('sha256', strtoupper(trim($token)));
}

function ensureTokenHashColumn(string $table): void
{
    if (Capsule::schema()->hasColumn($table, 'token_hash')) {
        return;
    }
    try {
        Capsule::statement("ALTER TABLE `{$table}` ADD COLUMN `token_hash` VARCHAR(64) NULL");
    } catch (\Throwable $e) {
        // Continue; it may already exist under a race or unsupported DDL path.
    }
    if (!Capsule::schema()->hasColumn($table, 'token_hash')) {
        throw new \RuntimeException('token_hash column is missing');
    }
    try {
        Capsule::statement("UPDATE `{$table}` SET `token_hash` = SHA2(UPPER(TRIM(`token`)), 256) WHERE (`token_hash` IS NULL OR `token_hash` = '') AND `token` IS NOT NULL AND `token` != ''");
    } catch (\Throwable $e) {
        logModuleCall('cloudstorage', 'cloudbackup_recovery_token_create_token_hash_backfill_fail', [
            'table' => $table,
        ], $e->getMessage());
    }
}

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    respondError('auth_required', 'Session timeout.', 200);
}

$body = getBodyJson();
$clientId = $ca->getUserID();
$restorePointId = isset($_POST['restore_point_id'])
    ? (int) $_POST['restore_point_id']
    : (int) ($body['restore_point_id'] ?? 0);
$description = isset($_POST['description'])
    ? trim((string) $_POST['description'])
    : trim((string) ($body['description'] ?? ''));
$ttlHours = isset($_POST['ttl_hours'])
    ? (int) $_POST['ttl_hours']
    : (int) ($body['ttl_hours'] ?? 24);
if ($ttlHours <= 0 || $ttlHours > 168) {
    $ttlHours = 24;
}

if ($restorePointId <= 0) {
    respondError('invalid_request', 'restore_point_id is required');
}

if (!Capsule::schema()->hasTable('s3_cloudbackup_recovery_tokens')) {
    respondError(
        'schema_upgrade_required',
        'Recovery tokens not supported on this installation. Please run module upgrade.',
        500
    );
}

$tokenTable = 's3_cloudbackup_recovery_tokens';
$requiredColumns = ['client_id', 'restore_point_id', 'token', 'token_hash'];
$tokenColumns = [];
try {
    ensureTokenHashColumn($tokenTable);
    $tokenColumns = Capsule::schema()->getColumnListing($tokenTable);
} catch (\Throwable $e) {
    logModuleCall('cloudstorage', 'cloudbackup_recovery_token_create_schema_list_failed', [
        'table' => $tokenTable,
        'client_id' => $clientId,
    ], [
        'error_class' => get_class($e),
        'error_message' => $e->getMessage(),
    ]);
    respondError(
        'schema_upgrade_required',
        'Unable to validate recovery token schema. Please run module upgrade.',
        500
    );
}

$columnMap = array_fill_keys($tokenColumns, true);
$missingRequired = [];
foreach ($requiredColumns as $required) {
    if (!isset($columnMap[$required])) {
        $missingRequired[] = $required;
    }
}
if (!empty($missingRequired)) {
    respondError(
        'schema_upgrade_required',
        'Recovery token table is missing required columns. Please run module upgrade.',
        500,
        ['missing_columns' => $missingRequired]
    );
}

$restorePoint = Capsule::table('s3_cloudbackup_restore_points')
    ->where('id', $restorePointId)
    ->where('client_id', $clientId)
    ->first();
if (!$restorePoint) {
    respondError('not_found', 'Restore point not found or access denied');
}

// MSP tenant authorization check (based on restore point tenant)
if (MspController::isMspClient($clientId) && !empty($restorePoint->tenant_id)) {
    $tenant = MspController::getTenant((int) $restorePoint->tenant_id, $clientId);
    if (!$tenant) {
        respondError('access_denied', 'Tenant not found or access denied');
    }
}

if (($restorePoint->status ?? '') === 'metadata_incomplete') {
    respondError('metadata_incomplete', 'Restore metadata is incomplete for this restore point. Create a fresh disk image backup and try again.');
}

if (!in_array(($restorePoint->status ?? ''), ['success', 'warning'], true)) {
    respondError('invalid_state', 'Restore point is not available for recovery.');
}

if (($restorePoint->engine ?? '') !== 'disk_image') {
    respondError('unsupported_engine', 'Recovery tokens are only supported for disk image restore points.');
}

$expiresAt = (new DateTime())->modify('+' . $ttlHours . ' hours')->format('Y-m-d H:i:s');

try {
    $token = '';
    $tokenHash = '';
    $attempts = 0;
    do {
        $token = generateShortToken(10);
        $tokenHash = hashRecoveryToken($token);
        $exists = Capsule::table($tokenTable)
            ->where('token_hash', $tokenHash)
            ->exists();
        $attempts++;
    } while ($exists && $attempts < 5);

    if ($token === '' || $tokenHash === '' || $exists) {
        respondError('server_error', 'Failed to generate recovery token', 500);
    }

    $createdIp = $_SERVER['REMOTE_ADDR'] ?? null;
    $createdUA = $_SERVER['HTTP_USER_AGENT'] ?? null;

    $insertData = [
        'client_id' => $clientId,
        'restore_point_id' => $restorePointId,
        // Keep the legacy token column non-sensitive; secret material is stored as a hash.
        'token' => bin2hex(random_bytes(8)),
        'token_hash' => $tokenHash,
    ];
    if (isset($columnMap['tenant_id'])) {
        $insertData['tenant_id'] = $restorePoint->tenant_id ?? null;
    }
    if (isset($columnMap['tenant_user_id'])) {
        $insertData['tenant_user_id'] = $restorePoint->tenant_user_id ?? null;
    }
    if (isset($columnMap['description'])) {
        $insertData['description'] = $description !== '' ? $description : null;
    }
    if (isset($columnMap['expires_at'])) {
        $insertData['expires_at'] = $expiresAt;
    }
    if (isset($columnMap['created_ip'])) {
        $insertData['created_ip'] = $createdIp;
    }
    if (isset($columnMap['created_user_agent'])) {
        $insertData['created_user_agent'] = $createdUA;
    }
    if (isset($columnMap['created_at'])) {
        $insertData['created_at'] = date('Y-m-d H:i:s');
    }
    if (isset($columnMap['updated_at'])) {
        $insertData['updated_at'] = date('Y-m-d H:i:s');
    }

    Capsule::table($tokenTable)->insert($insertData);

    respond([
        'status' => 'success',
        'token' => $token,
        'expires_at' => $expiresAt,
    ]);
} catch (\Throwable $e) {
    $errorPayload = [
        'error_class' => get_class($e),
        'error_message' => $e->getMessage(),
        'client_id' => $clientId,
        'restore_point_id' => $restorePointId,
    ];
    if ($e instanceof \Illuminate\Database\QueryException) {
        $errorPayload['sql'] = $e->getSql();
        $errorPayload['bindings'] = $e->getBindings();
    }
    logModuleCall('cloudstorage', 'cloudbackup_recovery_token_create_exception', [
        'client_id' => $clientId,
        'restore_point_id' => $restorePointId,
    ], $errorPayload);

    respondError('server_error', 'Failed to create recovery token', 500);
}
