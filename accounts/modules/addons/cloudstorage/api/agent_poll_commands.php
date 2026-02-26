<?php

require_once __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\UuidBinary;
use Symfony\Component\HttpFoundation\JsonResponse;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function respond($data, $code = 200)
{
    (new JsonResponse($data, $code))->send();
    exit;
}

$agentUuid = $_SERVER['HTTP_X_AGENT_UUID'] ?? ($_POST['agent_uuid'] ?? null);
$agentToken = $_SERVER['HTTP_X_AGENT_TOKEN'] ?? ($_POST['agent_token'] ?? null);
$runId = trim((string) ($_GET['run_id'] ?? ($_POST['run_id'] ?? '')));

if (!$agentUuid || !$agentToken) {
    respond(['status' => 'fail', 'message' => 'Missing agent headers'], 401);
}
if ($runId === '') {
    respond(['status' => 'fail', 'message' => 'run_id is required'], 400);
}
if (!UuidBinary::isUuid($runId)) {
    respond(['status' => 'fail', 'code' => 'invalid_identifier_format', 'message' => 'run_id must be a valid UUID'], 400);
}

$agent = Capsule::table('s3_cloudbackup_agents')->where('agent_uuid', $agentUuid)->first();
if (!$agent || $agent->status !== 'active' || $agent->agent_token !== $agentToken) {
    respond(['status' => 'fail', 'message' => 'Unauthorized'], 401);
}

// touch last_seen_at
Capsule::table('s3_cloudbackup_agents')
    ->where('agent_uuid', $agentUuid)
    ->update(['last_seen_at' => Capsule::raw('NOW()')]);

$run = Capsule::table('s3_cloudbackup_runs as r')
    ->join('s3_cloudbackup_jobs as j', 'r.job_id', '=', 'j.job_id')
    ->whereRaw('r.run_id = ' . UuidBinary::toDbExpr(UuidBinary::normalize($runId)))
    ->select('r.run_id', 'j.client_id', 'r.cancel_requested')
    ->first();

if (!$run || (int)$run->client_id !== (int)$agent->client_id) {
    respond(['status' => 'fail', 'message' => 'Run not found or unauthorized'], 403);
}

$commands = [];
if (!empty($run->cancel_requested)) {
    $commands[] = ['type' => 'cancel'];
}

// Pending run commands (maintenance/restore, etc.)
if (Capsule::schema()->hasTable('s3_cloudbackup_run_commands')) {
    $runIdBinExpr = UuidBinary::toDbExpr(UuidBinary::normalize($runId));
    $cmdRows = Capsule::table('s3_cloudbackup_run_commands')
        ->whereRaw('run_id = ' . $runIdBinExpr)
        ->where('status', 'pending')
        ->orderBy('id', 'asc')
        ->limit(5)
        ->get();
    foreach ($cmdRows as $c) {
        $payload = [];
        if (!empty($c->payload_json)) {
            $dec = json_decode($c->payload_json, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $payload = $dec;
            }
        }
        $commands[] = [
            'type' => $c->type,
            'command_id' => (int) $c->id,
            'payload' => $payload,
        ];
        // Mark as processing to avoid duplicate dispatch
        Capsule::table('s3_cloudbackup_run_commands')
            ->where('id', $c->id)
            ->update(['status' => 'processing']);
    }
}

respond(['status' => 'success', 'commands' => $commands]);

