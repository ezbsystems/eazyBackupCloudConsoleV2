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

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    respond(['status' => 'fail', 'message' => 'Session timeout.'], 200);
}

$clientId = $ca->getUserID();
$restorePointId = isset($_POST['restore_point_id']) ? (int) $_POST['restore_point_id'] : 0;
$description = isset($_POST['description']) ? trim((string) $_POST['description']) : '';
$ttlHours = isset($_POST['ttl_hours']) ? (int) $_POST['ttl_hours'] : 24;
if ($ttlHours <= 0 || $ttlHours > 168) {
    $ttlHours = 24;
}

if ($restorePointId <= 0) {
    respond(['status' => 'fail', 'message' => 'restore_point_id is required']);
}

if (!Capsule::schema()->hasTable('s3_cloudbackup_recovery_tokens')) {
    respond(['status' => 'fail', 'message' => 'Recovery tokens not supported on this installation']);
}

$restorePoint = Capsule::table('s3_cloudbackup_restore_points')
    ->where('id', $restorePointId)
    ->where('client_id', $clientId)
    ->first();
if (!$restorePoint) {
    respond(['status' => 'fail', 'message' => 'Restore point not found or access denied']);
}

// MSP tenant authorization check (based on restore point tenant)
if (MspController::isMspClient($clientId) && !empty($restorePoint->tenant_id)) {
    $tenant = MspController::getTenant((int) $restorePoint->tenant_id, $clientId);
    if (!$tenant) {
        respond(['status' => 'fail', 'message' => 'Tenant not found or access denied']);
    }
}

if (!in_array(($restorePoint->status ?? ''), ['success', 'warning'], true)) {
    respond(['status' => 'fail', 'message' => 'Restore point is not available for recovery.']);
}

if (($restorePoint->engine ?? '') !== 'disk_image') {
    respond(['status' => 'fail', 'message' => 'Recovery tokens are only supported for disk image restore points.']);
}

$expiresAt = (new DateTime())->modify('+' . $ttlHours . ' hours')->format('Y-m-d H:i:s');

try {
    $token = '';
    $attempts = 0;
    do {
        $token = generateShortToken(10);
        $exists = Capsule::table('s3_cloudbackup_recovery_tokens')
            ->where('token', $token)
            ->exists();
        $attempts++;
    } while ($exists && $attempts < 5);

    if ($token === '' || $exists) {
        respond(['status' => 'fail', 'message' => 'Failed to generate recovery token'], 500);
    }

    $createdIp = $_SERVER['REMOTE_ADDR'] ?? null;
    $createdUA = $_SERVER['HTTP_USER_AGENT'] ?? null;

    Capsule::table('s3_cloudbackup_recovery_tokens')->insert([
        'client_id' => $clientId,
        'tenant_id' => $restorePoint->tenant_id ?? null,
        'tenant_user_id' => $restorePoint->tenant_user_id ?? null,
        'restore_point_id' => $restorePointId,
        'token' => $token,
        'description' => $description !== '' ? $description : null,
        'expires_at' => $expiresAt,
        'created_ip' => $createdIp,
        'created_user_agent' => $createdUA,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ]);

    respond([
        'status' => 'success',
        'token' => $token,
        'expires_at' => $expiresAt,
    ]);
} catch (\Throwable $e) {
    respond(['status' => 'fail', 'message' => 'Failed to create recovery token'], 500);
}
