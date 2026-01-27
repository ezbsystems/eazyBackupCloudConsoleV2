<?php

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

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    respond(['status' => 'fail', 'message' => 'Session timeout.'], 200);
}

$clientId = $ca->getUserID();
$restorePointId = isset($_GET['restore_point_id']) ? (int) $_GET['restore_point_id'] : 0;
if ($restorePointId <= 0) {
    respond(['status' => 'fail', 'message' => 'restore_point_id is required'], 400);
}

$restorePoint = Capsule::table('s3_cloudbackup_restore_points')
    ->where('id', $restorePointId)
    ->where('client_id', $clientId)
    ->first();
if (!$restorePoint) {
    respond(['status' => 'fail', 'message' => 'Restore point not found'], 404);
}

if (MspController::isMspClient($clientId) && !empty($restorePoint->tenant_id)) {
    $tenant = MspController::getTenant((int) $restorePoint->tenant_id, $clientId);
    if (!$tenant) {
        respond(['status' => 'fail', 'message' => 'Tenant not found or access denied']);
    }
}

respond([
    'status' => 'success',
    'disk_layout_json' => $restorePoint->disk_layout_json ?? null,
    'disk_total_bytes' => $restorePoint->disk_total_bytes ?? null,
    'disk_used_bytes' => $restorePoint->disk_used_bytes ?? null,
    'disk_boot_mode' => $restorePoint->disk_boot_mode ?? null,
    'disk_partition_style' => $restorePoint->disk_partition_style ?? null,
]);
