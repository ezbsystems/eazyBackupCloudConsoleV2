<?php

require_once __DIR__ . '/../../../../init.php';

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\CloudBackupController;

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Session timeout.'], 200))->send();
    exit;
}

$clientId = $ca->getUserID();
$runIdentifier = $_POST['run_uuid'] ?? ($_POST['run_id'] ?? null);
$type = isset($_POST['type']) ? strtolower(trim((string) $_POST['type'])) : '';
$payloadRaw = $_POST['payload_json'] ?? null;
$payload = null;
if ($payloadRaw) {
    $dec = json_decode($payloadRaw, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $payload = $dec;
    }
$run = CloudBackupController::getRun($runIdentifier, $clientId);
$runId = (int) ($run['id'] ?? 0);

if ($runId <= 0 || !in_array($type, ['maintenance_quick','maintenance_full','restore'], true)) {
    (new JsonResponse(['status' => 'fail', 'message' => 'run_id and valid type are required'], 200))->send();
    exit;
}

// Verify run ownership
if (!$run) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Run not found or access denied'], 200))->send();
    exit;
}

if (!Capsule::schema()->hasTable('s3_cloudbackup_run_commands')) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Commands not supported on this installation'], 200))->send();
    exit;
}

try {
    $cmdId = Capsule::table('s3_cloudbackup_run_commands')->insertGetId([
        'run_id' => $runId,
        'type' => $type,
        'payload_json' => $payload ? json_encode($payload) : null,
        'status' => 'pending',
        'created_at' => Capsule::raw('NOW()'),
    ]);
    (new JsonResponse(['status' => 'success', 'command_id' => $cmdId], 200))->send();
} catch (\Throwable $e) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Unable to enqueue command'], 200))->send();
}
exit;


