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
if (!$runId) {
    respond(['status' => 'fail', 'message' => 'run_id is required'], 400);
}

$agent = authenticateAgent();

$run = Capsule::table('s3_cloudbackup_runs as r')
    ->join('s3_cloudbackup_jobs as j', 'r.job_id', '=', 'j.id')
    ->where('r.id', $runId)
    ->select('r.id', 'j.client_id')
    ->first();

if (!$run || (int)$run->client_id !== (int)$agent->client_id) {
    respond(['status' => 'fail', 'message' => 'Run not found or unauthorized'], 403);
}

$fields = [
    'status',
    'progress_pct',
    'bytes_transferred',
    'bytes_total',
    'objects_transferred',
    'objects_total',
    'speed_bytes_per_sec',
    'eta_seconds',
    'current_item',
    'log_excerpt',
    'error_summary',
    'validation_status',
    'validation_log_excerpt',
    'started_at',
    'finished_at',
    'progress_json',
    'stats_json',
    'log_ref',
];

$update = [];
foreach ($fields as $field) {
    if (!array_key_exists($field, $body)) {
        continue;
    }
    $val = $body[$field];
    // Normalize timestamps from RFC3339/ISO8601 into MySQL DATETIME
    if (in_array($field, ['started_at', 'finished_at'], true)) {
        if ($val !== null && $val !== '') {
            $ts = strtotime((string) $val);
            if ($ts !== false) {
                $update[$field] = date('Y-m-d H:i:s', $ts);
            } else {
                // Record parse failure for diagnostics but continue processing
                logModuleCall('cloudstorage', 'agent_update_run_invalid_ts', ['run_id' => $runId, 'agent_id' => $agent->id, 'field' => $field, 'value' => $val], 'invalid_timestamp');
            }
        }
        continue;
    }
    if (in_array($field, ['progress_json', 'stats_json'], true)) {
        $update[$field] = is_array($val) ? json_encode($val) : $val;
        continue;
    }
    $update[$field] = $val;
}

// Allow manifest_id alias to log_ref (e.g., agents posting manifest separately)
if (array_key_exists('manifest_id', $body) && !array_key_exists('log_ref', $body)) {
    $update['log_ref'] = $body['manifest_id'];
}

$hasUpdatedAtColumn = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'updated_at');

if (empty($update)) {
    if ($hasUpdatedAtColumn) {
        // Treat an empty payload as a heartbeat when updated_at exists
        $update['updated_at'] = Capsule::raw('NOW()');
    } else {
        logModuleCall('cloudstorage', 'agent_update_run_no_fields', ['run_id' => $runId, 'agent_id' => $agent->id], ['body_keys' => array_keys($body)]);
        respond(['status' => 'success', 'message' => 'No fields to update']);
    }
}

// Touch updated_at when the column exists (older schemas may not have it)
if ($hasUpdatedAtColumn) {
    $update['updated_at'] = Capsule::raw('NOW()');
}

try {
    $affected = Capsule::table('s3_cloudbackup_runs')
        ->where('id', $runId)
        ->update($update);
    if ((int) $affected === 0) {
        logModuleCall('cloudstorage', 'agent_update_run_noop', ['run_id' => $runId, 'agent_id' => $agent->id], ['update' => $update, 'affected' => $affected]);
    }
    respond(['status' => 'success']);
} catch (\Throwable $e) {
    logModuleCall('cloudstorage', 'agent_update_run_error', ['run_id' => $runId, 'agent_id' => $agent->id], $e->getMessage());
    respond(['status' => 'fail', 'message' => 'Update failed'], 500);
}

