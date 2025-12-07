<?php

require_once __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function respond(array $data, int $httpCode = 200): void
{
    $response = new JsonResponse($data, $httpCode);
    $response->send();
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

function getBodyJson(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

$body = getBodyJson();
$runId = $_POST['run_id'] ?? ($body['run_id'] ?? null);
$events = $body['events'] ?? [];
$logEntries = $body['logs'] ?? [];
// Accept manifest_id as an alias for log_ref for agents that send manifest explicitly
$logRef = $body['log_ref'] ?? ($body['manifest_id'] ?? null);

if (!$runId) {
    respond(['status' => 'fail', 'message' => 'run_id is required'], 400);
}

if ((!is_array($events) || empty($events)) && (!is_array($logEntries) || empty($logEntries)) && $logRef === null) {
    respond(['status' => 'success', 'message' => 'No events to record']);
}

$agent = authenticateAgent();

$run = Capsule::table('s3_cloudbackup_runs as r')
    ->join('s3_cloudbackup_jobs as j', 'r.job_id', '=', 'j.id')
    ->where('r.id', $runId)
    ->select('r.id', 'j.client_id', 'r.agent_id')
    ->first();

if (!$run || (int)$run->client_id !== (int)$agent->client_id) {
    respond(['status' => 'fail', 'message' => 'Run not found or unauthorized'], 403);
}

// Extra guard: ensure the run is bound to this agent when applicable
if (!empty($run->agent_id) && (int)$run->agent_id !== (int)$agent->id) {
    respond(['status' => 'fail', 'message' => 'Run not assigned to this agent'], 403);
}

$rows = [];
$nowMicro = microtime(true);

foreach ($events as $event) {
    if (!is_array($event)) {
        continue;
    }
    $code = $event['code'] ?? null;
    if ($code === null) {
        // Fall back to message_id when code is absent (agent events often omit code)
        $code = $event['message_id'] ?? '';
    }
    $params = $event['params_json'] ?? null;
    if (is_array($params)) {
        $params = json_encode($params);
    }
    if ($params === null) {
        // Ensure params_json is never NULL for DB insert
        $params = '';
    }

    $rows[] = [
        'run_id' => $runId,
        'ts' => date('Y-m-d H:i:s.u', $nowMicro),
        'type' => $event['type'] ?? 'info',
        'level' => $event['level'] ?? 'info',
        'code' => $code,
        'message_id' => $event['message_id'] ?? null,
        'params_json' => $params,
    ];
}

if (!empty($rows)) {
    Capsule::table('s3_cloudbackup_run_events')->insert($rows);
}

// Optional: store structured logs for this run
$logRowsInserted = 0;
if (is_array($logEntries) && !empty($logEntries) && Capsule::schema()->hasTable('s3_cloudbackup_run_logs')) {
    $logRows = [];
    foreach ($logEntries as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $logRows[] = [
            'run_id' => $runId,
            'created_at' => date('Y-m-d H:i:s'),
            'level' => $entry['level'] ?? 'info',
            'code' => $entry['code'] ?? null,
            'message' => $entry['message'] ?? '',
            'details_json' => isset($entry['details_json']) && is_array($entry['details_json'])
                ? json_encode($entry['details_json'])
                : ($entry['details_json'] ?? null),
        ];
    }
    if (!empty($logRows)) {
        Capsule::table('s3_cloudbackup_run_logs')->insert($logRows);
        $logRowsInserted = count($logRows);
    }
}

if ($logRef !== null && Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'log_ref')) {
    Capsule::table('s3_cloudbackup_runs')
        ->where('id', $runId)
        ->update(['log_ref' => $logRef, 'updated_at' => Capsule::raw('NOW()')]);
}

respond(['status' => 'success', 'inserted' => count($rows), 'logs_inserted' => $logRowsInserted, 'log_ref' => $logRef]);

