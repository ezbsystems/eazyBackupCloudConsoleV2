<?php

require_once __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\UuidBinary;
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

    Capsule::table('s3_cloudbackup_agents')
        ->where('agent_uuid', $agentUuid)
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

// For commands with agent_uuid set directly (browse, discovery commands), check ownership via agent_uuid
// These commands have run_id = NULL as they're not tied to a specific backup run
$agentScopedCommands = ['browse_directory', 'list_hyperv_vms', 'nas_mount', 'nas_unmount', 'nas_mount_snapshot', 'nas_unmount_snapshot', 'fetch_log_tail'];
$isAgentScoped = in_array(strtolower((string) $cmd->type), $agentScopedCommands, true);
if (!$isAgentScoped && empty($cmd->run_id) && !empty($cmd->agent_uuid)) {
    $isAgentScoped = true;
}

if ($isAgentScoped) {
    // Ownership check via agent_uuid column on the command itself
    if ((string) ($cmd->agent_uuid ?? '') !== (string) $agent->agent_uuid) {
        respond(['status' => 'fail', 'message' => 'Unauthorized'], 403);
    }
} else {
    // For run-bound commands (restore, maintenance), check via run -> job -> agent (UUID schema: run_id/job_id are BINARY(16))
    $hasRunIdBinary = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'run_id');
    $hasJobIdPk = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'job_id');
    $runJoinCol = $hasJobIdPk ? ['r.job_id', '=', 'j.job_id'] : ['r.job_id', '=', 'j.id'];
    $runQuery = Capsule::table('s3_cloudbackup_runs as r')
        ->join('s3_cloudbackup_jobs as j', $runJoinCol[0], $runJoinCol[1], $runJoinCol[2]);
    $runIdCol = $hasRunIdBinary ? 'run_id' : 'id';
    $runQuery->where('r.' . $runIdCol, $cmd->run_id);
    $run = $runQuery->select('j.client_id', 'j.agent_uuid', 'r.agent_uuid as run_agent_uuid')->first();

    if (!$run || (string) ($run->run_agent_uuid ?? $run->agent_uuid ?? '') !== (string) $agent->agent_uuid) {
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


