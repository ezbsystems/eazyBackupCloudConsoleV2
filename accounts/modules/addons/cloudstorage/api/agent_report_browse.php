<?php

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

// Agent authentication
$agentUuid = $_SERVER['HTTP_X_AGENT_UUID'] ?? ($_POST['agent_uuid'] ?? null);
$agentToken = $_SERVER['HTTP_X_AGENT_TOKEN'] ?? ($_POST['agent_token'] ?? null);
if (!$agentUuid || !$agentToken) {
    respond(['status' => 'fail', 'message' => 'Missing agent headers'], 401);
}

$agent = Capsule::table('s3_cloudbackup_agents')
    ->where('agent_uuid', $agentUuid)
    ->first();

if (!$agent || $agent->status !== 'active' || $agent->agent_token !== $agentToken) {
    respond(['status' => 'fail', 'message' => 'Unauthorized'], 401);
}

// Touch last_seen_at
Capsule::table('s3_cloudbackup_agents')
    ->where('agent_uuid', $agentUuid)
    ->update(['last_seen_at' => Capsule::raw('NOW()')]);

$bodyRaw = file_get_contents('php://input');
$body = $bodyRaw ? json_decode($bodyRaw, true) : [];

$commandId = isset($body['command_id']) ? (int) $body['command_id'] : 0;
$result = $body['result'] ?? null;

// Support robust transport where the agent sends gzip+base64 encoded JSON to avoid WAF false positives
// on Windows paths like "C:\Users\..." that can trigger HTTP 403 before PHP runs.
if ($result === null && isset($body['result_b64'])) {
    $encoding = strtolower((string) ($body['result_encoding'] ?? 'base64'));
    $b64 = (string) $body['result_b64'];
    $bin = base64_decode($b64, true);
    if ($bin === false) {
        respond(['status' => 'fail', 'message' => 'Invalid result_b64'], 400);
    }
    if ($encoding === 'gzip+base64' || $encoding === 'gzip' || $encoding === 'gz+b64') {
        $json = @gzdecode($bin);
        if ($json === false) {
            respond(['status' => 'fail', 'message' => 'Failed to gzdecode browse result'], 400);
        }
    } else {
        $json = $bin;
    }
    $decoded = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        respond(['status' => 'fail', 'message' => 'Invalid decoded browse JSON'], 400);
    }
    $result = $decoded;
}

if ($commandId <= 0) {
    respond(['status' => 'fail', 'message' => 'command_id is required'], 400);
}

$cmd = Capsule::table('s3_cloudbackup_run_commands')
    ->where('id', $commandId)
    ->first(['id', 'agent_id', 'type', 'status']);

if (!$cmd || (int) $cmd->agent_id !== (int) ($agent->id ?? 0)) {
    respond(['status' => 'fail', 'message' => 'Command not found'], 404);
}
$validTypes = ['browse_directory', 'list_hyperv_vms', 'list_hyperv_vm_details', 'browse_snapshot', 'list_disks', 'fetch_log_tail'];
if (!in_array(strtolower((string) $cmd->type), $validTypes, true)) {
    respond(['status' => 'fail', 'message' => 'Invalid command type for browse/discovery'], 400);
}

$resultJson = json_encode($result ?? [], JSON_UNESCAPED_SLASHES);

// Avoid re-completing an already completed command
if (strtolower((string) $cmd->status) === 'completed') {
    respond(['status' => 'success']);
}

// Be tolerant of older schemas missing updated_at/processed_at
$updates = [
    'status' => 'completed',
    'result_message' => $resultJson,
];
$hasUpdatedAt = Capsule::schema()->hasColumn('s3_cloudbackup_run_commands', 'updated_at');
$hasProcessedAt = Capsule::schema()->hasColumn('s3_cloudbackup_run_commands', 'processed_at');
if ($hasUpdatedAt) {
    $updates['updated_at'] = Capsule::raw('NOW()');
}
if ($hasProcessedAt) {
    $updates['processed_at'] = Capsule::raw('NOW()');
}

Capsule::table('s3_cloudbackup_run_commands')
    ->where('id', $commandId)
    ->update($updates);

respond(['status' => 'success']);

