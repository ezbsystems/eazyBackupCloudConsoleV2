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

function authenticateAgent(): object
{
    $agentId = $_SERVER['HTTP_X_AGENT_ID'] ?? ($_POST['agent_id'] ?? null);
    $agentToken = $_SERVER['HTTP_X_AGENT_TOKEN'] ?? ($_POST['agent_token'] ?? null);
    if (!$agentId || !$agentToken) {
        respond(['status' => 'fail', 'message' => 'Missing agent headers'], 401);
    }

    $agent = Capsule::table('s3_cloudbackup_agents')
        ->where('id', $agentId)
        ->first();

    if (!$agent || $agent->status !== 'active' || $agent->agent_token !== $agentToken) {
        respond(['status' => 'fail', 'message' => 'Unauthorized'], 401);
    }

    Capsule::table('s3_cloudbackup_agents')
        ->where('id', $agentId)
        ->update(['last_seen_at' => Capsule::raw('NOW()')]);

    return $agent;
}

$bodyRaw = file_get_contents('php://input');
$body = $bodyRaw ? json_decode($bodyRaw, true) : [];

$agent = authenticateAgent();
$commandId = isset($body['command_id']) ? (int)$body['command_id'] : 0;
$status = isset($body['status']) ? strtolower((string)$body['status']) : '';
$resultMessage = isset($body['result_message']) ? (string)$body['result_message'] : '';

if ($commandId <= 0 || !in_array($status, ['completed', 'failed'], true)) {
    respond(['status' => 'fail', 'message' => 'command_id and status (completed|failed) are required'], 400);
}

$cmd = Capsule::table('s3_cloudbackup_run_commands')->where('id', $commandId)->first();
if (!$cmd) {
    respond(['status' => 'fail', 'message' => 'Command not found'], 404);
}

// For commands with agent_id set directly (browse, discovery commands), check ownership via agent_id
// These commands have run_id = NULL as they're not tied to a specific backup run
$agentScopedCommands = ['browse_directory', 'list_hyperv_vms', 'nas_mount', 'nas_unmount', 'nas_mount_snapshot', 'nas_unmount_snapshot'];
if (in_array(strtolower((string) $cmd->type), $agentScopedCommands, true)) {
    // Ownership check via agent_id column on the command itself
    if ((int) ($cmd->agent_id ?? 0) !== (int) $agent->id) {
        respond(['status' => 'fail', 'message' => 'Unauthorized'], 403);
    }
} else {
    // For run-bound commands (restore, maintenance), check via run -> job -> agent
    $run = Capsule::table('s3_cloudbackup_runs as r')
        ->join('s3_cloudbackup_jobs as j', 'r.job_id', '=', 'j.id')
        ->where('r.id', $cmd->run_id)
        ->select('j.client_id', 'j.agent_id', 'r.agent_id as run_agent_id')
        ->first();

    if (!$run || (int)$run->agent_id !== (int)$agent->id) {
        respond(['status' => 'fail', 'message' => 'Unauthorized'], 403);
    }
}

// Build update array with schema compatibility
$updates = [
    'status' => $status,
    'result_message' => $resultMessage,
];

// Be tolerant of older schemas that may not have these columns
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


