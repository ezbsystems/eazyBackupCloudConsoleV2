<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/MspController.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Client\MspController;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function respond(array $data, int $httpCode = 200): void
{
    header('Content-Type: application/json');
    (new JsonResponse($data, $httpCode))->send();
    exit;
}

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    respond(['status' => 'fail', 'message' => 'Session timeout'], 401);
}
$clientId = $ca->getUserID();

if (!Capsule::schema()->hasTable('s3_cloudbackup_run_commands')) {
    respond(['status' => 'fail', 'message' => 'Command queue not available'], 500);
}

$agentId = isset($_GET['agent_id']) ? (int) $_GET['agent_id'] : 0;
$restorePointId = isset($_GET['restore_point_id']) ? (int) $_GET['restore_point_id'] : 0;
$path = isset($_GET['path']) ? trim((string) $_GET['path']) : '';
$maxItems = isset($_GET['max_items']) ? (int) $_GET['max_items'] : 500;
if ($maxItems <= 0) {
    $maxItems = 500;
}
if ($maxItems > 1000) {
    $maxItems = 1000;
}

if ($agentId <= 0) {
    respond(['status' => 'fail', 'message' => 'agent_id is required'], 400);
}
if ($restorePointId <= 0) {
    respond(['status' => 'fail', 'message' => 'restore_point_id is required'], 400);
}

$agent = Capsule::table('s3_cloudbackup_agents')
    ->where('id', $agentId)
    ->where('client_id', $clientId)
    ->where('status', 'active')
    ->first(['id', 'client_id', 'tenant_id']);

if (!$agent) {
    respond(['status' => 'fail', 'message' => 'Agent not found or inactive'], 404);
}

$restorePoint = Capsule::table('s3_cloudbackup_restore_points')
    ->where('id', $restorePointId)
    ->where('client_id', $clientId)
    ->first();

if (!$restorePoint) {
    respond(['status' => 'fail', 'message' => 'Restore point not found'], 404);
}

if (!in_array(($restorePoint->status ?? ''), ['success', 'warning'], true)) {
    respond(['status' => 'fail', 'message' => 'Restore point is not available for browsing']);
}

if (($restorePoint->engine ?? '') !== 'kopia') {
    respond(['status' => 'fail', 'message' => 'Snapshot browsing is only supported for Kopia restore points']);
}

if (empty($restorePoint->manifest_id)) {
    respond(['status' => 'fail', 'message' => 'Restore point is missing manifest ID']);
}

if (MspController::isMspClient($clientId) && !empty($restorePoint->tenant_id)) {
    $tenant = MspController::getTenant((int) $restorePoint->tenant_id, $clientId);
    if (!$tenant) {
        respond(['status' => 'fail', 'message' => 'Tenant not found or access denied']);
    }
}

if (!empty($restorePoint->tenant_id)) {
    if ((int) $agent->tenant_id !== (int) $restorePoint->tenant_id) {
        respond(['status' => 'fail', 'message' => 'Agent does not belong to tenant']);
    }
} else {
    if (!empty($agent->tenant_id)) {
        respond(['status' => 'fail', 'message' => 'Agent must be a direct (non-tenant) agent']);
    }
}

try {
    $payload = [
        'restore_point_id' => $restorePointId,
        'path' => $path,
        'max_items' => $maxItems,
    ];

    $cmdTable = Capsule::table('s3_cloudbackup_run_commands');
    $hasCreatedAt = Capsule::schema()->hasColumn('s3_cloudbackup_run_commands', 'created_at');
    $hasUpdatedAt = Capsule::schema()->hasColumn('s3_cloudbackup_run_commands', 'updated_at');

    $insert = [
        'run_id' => null,
        'agent_id' => $agent->id,
        'type' => 'browse_snapshot',
        'payload_json' => json_encode($payload, JSON_UNESCAPED_SLASHES),
        'status' => 'pending',
    ];
    if ($hasCreatedAt) {
        $insert['created_at'] = Capsule::raw('NOW()');
    }
    if ($hasUpdatedAt) {
        $insert['updated_at'] = Capsule::raw('NOW()');
    }

    $commandId = $cmdTable->insertGetId($insert);

    // Poll for completion (up to 10 seconds)
    $deadline = microtime(true) + 10.0;
    while (microtime(true) < $deadline) {
        $cmd = Capsule::table('s3_cloudbackup_run_commands')
            ->where('id', $commandId)
            ->first(['status', 'result_message']);

        if ($cmd && $cmd->status === 'completed') {
            $data = [];
            if (!empty($cmd->result_message)) {
                $decoded = json_decode($cmd->result_message, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $data = $decoded;
                }
            }
            respond(['status' => 'success', 'data' => $data]);
        }

        if ($cmd && $cmd->status === 'failed') {
            respond(['status' => 'fail', 'message' => $cmd->result_message ?: 'Browse failed']);
        }

        usleep(200000); // 200ms
    }

    respond(['status' => 'fail', 'message' => 'Timeout waiting for agent response'], 504);
} catch (\Throwable $e) {
    respond(['status' => 'fail', 'message' => 'Server error: ' . $e->getMessage()], 500);
}

