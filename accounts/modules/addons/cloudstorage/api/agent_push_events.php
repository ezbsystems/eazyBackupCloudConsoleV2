<?php

require_once __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\UuidBinary;
use Symfony\Component\HttpFoundation\JsonResponse;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

// #region agent log
function debugLog(string $message, array $data, string $hypothesisId): void
{
    $entry = [
        'id' => uniqid('log_', true),
        'timestamp' => (int) round(microtime(true) * 1000),
        'location' => 'agent_push_events.php:debug',
        'message' => $message,
        'data' => $data,
        'runId' => isset($data['run_id']) ? ('run_' . $data['run_id']) : 'run_unknown',
        'hypothesisId' => $hypothesisId,
    ];
    @file_put_contents('/var/www/eazybackup.ca/.cursor/debug.log', json_encode($entry) . PHP_EOL, FILE_APPEND);
}
// #endregion

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

$rows = [];
$nowMicro = microtime(true);
$eventCodes = [];
$interestingCodes = [
    'KOPIA_PROGRESS_UPDATE' => true,
    'DISK_IMAGE_STALLED' => true,
    'DISK_IMAGE_FINALIZING_SLOW' => true,
    'DISK_IMAGE_FINALIZING_STALLED' => true,
    'DISK_IMAGE_STREAM_START' => true,
    'DISK_IMAGE_STREAM_COMPLETED' => true,
    'KOPIA_UPLOAD_START' => true,
    'KOPIA_UPLOAD_CALL_START' => true,
    'KOPIA_UPLOAD_CALL_DONE' => true,
    'KOPIA_UPLOAD_FINISHED' => true,
    'KOPIA_UPLOAD_FAILED' => true,
    'KOPIA_PREVIOUS_SNAPSHOTS' => true,
    'CANCEL_REQUESTED' => true,
    'CANCELLED' => true,
];

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
        'run_id' => Capsule::raw(UuidBinary::toDbExpr(UuidBinary::normalize($runId))),
        'ts' => date('Y-m-d H:i:s.u', $nowMicro),
        'type' => $event['type'] ?? 'info',
        'level' => $event['level'] ?? 'info',
        'code' => $code,
        'message_id' => $event['message_id'] ?? null,
        'params_json' => $params,
    ];
    $eventCodes[] = (string) $code;
    if (isset($interestingCodes[$code])) {
        $paramsPreview = '';
        $paramsDecoded = null;
        if (is_string($params) && $params !== '') {
            $paramsPreview = strlen($params) > 400 ? substr($params, 0, 400) . '…' : $params;
            $decoded = json_decode($params, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $paramsDecoded = $decoded;
            }
        }
        // #region agent log
        debugLog('agent_event', [
            'run_id' => $runId,
            'code' => (string) $code,
            'type' => (string) ($event['type'] ?? ''),
            'level' => (string) ($event['level'] ?? ''),
            'has_params' => $params !== '',
            'params_preview' => $paramsPreview,
            'params_decoded' => $paramsDecoded,
        ], 'H1');
        // #endregion
    }
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

// #region agent log
debugLog('agent_events_batch', [
    'run_id' => $runId,
    'events_count' => is_array($events) ? count($events) : 0,
    'logs_count' => is_array($logEntries) ? count($logEntries) : 0,
    'log_ref' => $logRef !== null ? (string) $logRef : null,
    'codes_sample' => array_slice($eventCodes, 0, 5),
], 'H2');
// #endregion

respond(['status' => 'success', 'inserted' => count($rows), 'logs_inserted' => $logRowsInserted, 'log_ref' => $logRef]);

