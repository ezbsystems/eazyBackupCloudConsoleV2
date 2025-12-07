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

// Ownership check via run -> job -> agent.client_id
$run = Capsule::table('s3_cloudbackup_runs as r')
    ->join('s3_cloudbackup_jobs as j', 'r.job_id', '=', 'j.id')
    ->where('r.id', $cmd->run_id)
    ->select('j.client_id', 'j.agent_id', 'r.agent_id as run_agent_id')
    ->first();

if (!$run || (int)$run->agent_id !== (int)$agent->id) {
    respond(['status' => 'fail', 'message' => 'Unauthorized'], 403);
}

Capsule::table('s3_cloudbackup_run_commands')
    ->where('id', $commandId)
    ->update([
        'status' => $status,
        'result_message' => $resultMessage,
        'processed_at' => Capsule::raw('NOW()'),
    ]);

respond(['status' => 'success']);


