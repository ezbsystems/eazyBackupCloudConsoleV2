<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/AgentIngestSupport.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\Module\Addon\CloudStorage\Client\UuidBinary;
use WHMCS\Module\Addon\CloudStorage\Client\AgentIngestSupport;

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
$runId = trim((string) ($_POST['run_id'] ?? ($body['run_id'] ?? '')));
$events = $body['events'] ?? [];
$logEntries = $body['logs'] ?? [];
// Accept manifest_id as an alias for log_ref for agents that send manifest explicitly
$logRef = $body['log_ref'] ?? ($body['manifest_id'] ?? null);

// Support compact transport for large event batches: gzip+base64 encoded JSON array.
if ((!is_array($events) || empty($events)) && isset($body['events_b64'])) {
    $encoding = strtolower((string)($body['events_encoding'] ?? 'base64'));
    $b64 = (string)$body['events_b64'];
    $bin = base64_decode($b64, true);
    if ($bin === false) {
        respond(['status' => 'fail', 'message' => 'Invalid events_b64 payload'], 400);
    }
    if ($encoding === 'gzip+base64' || $encoding === 'gzip' || $encoding === 'gz+b64') {
        $decoded = @gzdecode($bin);
        if ($decoded === false) {
            respond(['status' => 'fail', 'message' => 'Failed to decode compressed events payload'], 400);
        }
    } else {
        $decoded = $bin;
    }
    $decodedEvents = json_decode($decoded, true);
    if (!is_array($decodedEvents)) {
        respond(['status' => 'fail', 'message' => 'Decoded events payload is invalid JSON'], 400);
    }
    $events = $decodedEvents;
}

if ($runId === '') {
    respond(['status' => 'fail', 'message' => 'run_id is required'], 400);
}
if (!UuidBinary::isUuid($runId)) {
    respond(['status' => 'fail', 'code' => 'invalid_identifier_format', 'message' => 'run_id must be a valid UUID'], 400);
}

if ((!is_array($events) || empty($events)) && (!is_array($logEntries) || empty($logEntries)) && $logRef === null) {
    respond(['status' => 'success', 'message' => 'No events to record']);
}

$agent = authenticateAgent();

$gate = AgentIngestSupport::checkMinAgentVersion($agent, $body);
if ($gate !== null) {
    respond($gate[0], $gate[1]);
}

$run = Capsule::table('s3_cloudbackup_runs as r')
    ->join('s3_cloudbackup_jobs as j', 'r.job_id', '=', 'j.job_id')
    ->whereRaw('r.run_id = ' . UuidBinary::toDbExpr(UuidBinary::normalize($runId)))
    ->select('r.run_id', 'j.client_id', 'r.agent_uuid')
    ->first();

if (!$run || (int)$run->client_id !== (int)$agent->client_id) {
    respond(['status' => 'fail', 'message' => 'Run not found or unauthorized'], 403);
}

// Extra guard: ensure the run is bound to this agent when applicable
if (!empty($run->agent_uuid) && (string) $run->agent_uuid !== (string) $agent->agent_uuid) {
    respond(['status' => 'fail', 'message' => 'Run not assigned to this agent'], 403);
}

// Enforce per-run cap. Once we hit the cap, drop additional events and record
// a single EVENTS_TRUNCATED row (idempotent: skip if one already exists for run).
$maxPerRun = AgentIngestSupport::maxEventsPerRun();
$runIdExpr = UuidBinary::toDbExpr(UuidBinary::normalize($runId));
$currentCount = (int) Capsule::table('s3_cloudbackup_run_events')
    ->whereRaw('run_id = ' . $runIdExpr)
    ->count();

$rows = [];
$nowMicro = microtime(true);
$truncated = false;
$dropped = 0;

if ($currentCount >= $maxPerRun) {
    $truncated = true;
    $dropped = is_array($events) ? count($events) : 0;
} else {
    $allowed = max(0, $maxPerRun - $currentCount);
    foreach ($events as $idx => $event) {
        if (!is_array($event)) {
            continue;
        }
        if (count($rows) >= $allowed) {
            $truncated = true;
            $dropped++;
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
            $params = '';
        }

        $rows[] = [
            'run_id' => Capsule::raw($runIdExpr),
            'ts' => date('Y-m-d H:i:s.u', $nowMicro),
            'type' => $event['type'] ?? 'info',
            'level' => $event['level'] ?? 'info',
            'code' => $code,
            'message_id' => $event['message_id'] ?? null,
            'params_json' => $params,
        ];
    }
}

if (!empty($rows)) {
    Capsule::table('s3_cloudbackup_run_events')->insert($rows);
}

if ($truncated) {
    // Idempotent EVENTS_TRUNCATED marker: ensure at most one per run.
    $existsTrunc = Capsule::table('s3_cloudbackup_run_events')
        ->whereRaw('run_id = ' . $runIdExpr)
        ->where('code', 'EVENTS_TRUNCATED')
        ->limit(1)
        ->exists();
    if (!$existsTrunc) {
        try {
            Capsule::table('s3_cloudbackup_run_events')->insert([
                'run_id' => Capsule::raw($runIdExpr),
                'ts' => date('Y-m-d H:i:s.u', microtime(true)),
                'type' => 'warning',
                'level' => 'warn',
                'code' => 'EVENTS_TRUNCATED',
                'message_id' => 'EVENTS_TRUNCATED',
                'params_json' => json_encode([
                    'cap' => $maxPerRun,
                    'note' => 'Per-run event cap reached; further events dropped.',
                ]),
            ]);
        } catch (\Throwable $e) {
            logModuleCall('cloudstorage', 'agent_push_events_truncated_marker_error', ['run_id' => $runId], $e->getMessage());
        }
    }
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
            'run_id' => Capsule::raw(UuidBinary::toDbExpr(UuidBinary::normalize($runId))),
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
        ->whereRaw('run_id = ' . UuidBinary::toDbExpr(UuidBinary::normalize($runId)))
        ->update(['log_ref' => $logRef, 'updated_at' => Capsule::raw('NOW()')]);
}

respond([
    'status' => 'success',
    'inserted' => count($rows),
    'dropped' => $dropped,
    'truncated' => $truncated,
    'logs_inserted' => $logRowsInserted,
    'log_ref' => $logRef,
]);
