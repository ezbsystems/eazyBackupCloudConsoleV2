<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/AgentIngestSupport.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\Module\Addon\CloudStorage\Client\AgentIngestSupport;
use WHMCS\Module\Addon\CloudStorage\Client\AgentAuth;

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
    return \WHMCS\Module\Addon\CloudStorage\Client\AgentAuth::authenticate(
        fn(array $data, int $code) => respond($data, $code)
    );
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
$events = $body['events'] ?? [];

// Compact transport (gzip+base64 JSON array) for large batches.
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

if (!is_array($events) || empty($events)) {
    respond(['status' => 'success', 'message' => 'No events to record', 'inserted' => 0]);
}

$agent = authenticateAgent();

if (!AgentIngestSupport::ensureAgentEventsTable()) {
    respond(['status' => 'fail', 'message' => 'Agent events store not available'], 500);
}

$gate = AgentIngestSupport::checkMinAgentVersion($agent, $body);
if ($gate !== null) {
    respond($gate[0], $gate[1]);
}

$agentUuid = (string) $agent->agent_uuid;
$clientId = (int) $agent->client_id;
$tenantId = isset($agent->tenant_id) ? (int) $agent->tenant_id : null;
$backupUserId = isset($agent->backup_user_id) ? (int) $agent->backup_user_id : null;

// Per-UTC-day cap per agent.
$maxPerDay = AgentIngestSupport::maxAgentEventsPerDayPerAgent();
$dayStart = gmdate('Y-m-d 00:00:00');
$todayCount = (int) Capsule::table('s3_cloudbackup_agent_events')
    ->where('agent_uuid', $agentUuid)
    ->where('ts', '>=', $dayStart)
    ->count();

// Server-side 60s dedupe window: gather candidate dedupe_keys for this agent.
$candidateDedupe = [];
foreach ($events as $event) {
    if (is_array($event) && isset($event['dedupe_key']) && is_string($event['dedupe_key']) && $event['dedupe_key'] !== '') {
        $candidateDedupe[$event['dedupe_key']] = true;
    }
}
$recentDedupe = [];
if (!empty($candidateDedupe)) {
    $rows = Capsule::table('s3_cloudbackup_agent_events')
        ->where('agent_uuid', $agentUuid)
        ->whereIn('dedupe_key', array_keys($candidateDedupe))
        ->where('ts', '>=', gmdate('Y-m-d H:i:s', time() - 60))
        ->select(['dedupe_key', 'ts'])
        ->get();
    foreach ($rows as $r) {
        $recentDedupe[(string) $r->dedupe_key] = true;
    }
}

$nowMicro = microtime(true);
$nowDt = date('Y-m-d H:i:s.u', $nowMicro);
$rows = [];
$inserted = 0;
$dropped = 0;
$dedupedCount = 0;
$truncated = false;
$seenInBatch = []; // collapse repeats within a single request

foreach ($events as $event) {
    if (!is_array($event)) {
        $dropped++;
        continue;
    }
    if ($todayCount + count($rows) >= $maxPerDay) {
        $truncated = true;
        $dropped++;
        continue;
    }
    $code = trim((string) ($event['code'] ?? ''));
    if ($code === '') {
        $dropped++;
        continue;
    }
    $source = strtolower((string) ($event['source'] ?? 'agent'));
    if (!in_array($source, ['agent', 'tray'], true)) {
        $source = 'agent';
    }
    $level = strtolower((string) ($event['level'] ?? 'info'));
    if (!in_array($level, ['info', 'warn', 'error'], true)) {
        $level = 'info';
    }
    $messageId = trim((string) ($event['message_id'] ?? $code));
    $params = $event['params_json'] ?? null;
    if (is_array($params)) {
        $params = json_encode($params);
    }
    if (!is_string($params)) {
        $params = '';
    }
    if (strlen($params) > 65000) {
        $params = substr($params, 0, 65000);
    }
    $dedupeKey = isset($event['dedupe_key']) && is_string($event['dedupe_key']) ? trim($event['dedupe_key']) : '';
    if ($dedupeKey !== '') {
        if (isset($recentDedupe[$dedupeKey]) || isset($seenInBatch[$dedupeKey])) {
            $dedupedCount++;
            continue;
        }
        $seenInBatch[$dedupeKey] = true;
    }

    $ts = $nowDt;
    if (isset($event['ts']) && is_string($event['ts']) && $event['ts'] !== '') {
        $parsed = strtotime($event['ts']);
        if ($parsed !== false) {
            $ts = gmdate('Y-m-d H:i:s', $parsed);
        }
    }

    $rows[] = [
        'agent_uuid' => $agentUuid,
        'client_id' => $clientId,
        'tenant_id' => $tenantId,
        'backup_user_id' => $backupUserId,
        'ts' => $ts,
        'source' => $source,
        'level' => $level,
        'code' => substr($code, 0, 64),
        'message_id' => substr($messageId !== '' ? $messageId : $code, 0, 64),
        'params_json' => $params,
        'dedupe_key' => $dedupeKey === '' ? null : substr($dedupeKey, 0, 191),
    ];
}

if (!empty($rows)) {
    try {
        Capsule::table('s3_cloudbackup_agent_events')->insert($rows);
        $inserted = count($rows);
    } catch (\Throwable $e) {
        logModuleCall('cloudstorage', 'agent_push_agent_events_insert_error', ['agent_uuid' => $agentUuid], $e->getMessage());
        respond(['status' => 'fail', 'message' => 'Insert failed'], 500);
    }
}

respond([
    'status' => 'success',
    'inserted' => $inserted,
    'dropped' => $dropped,
    'deduped' => $dedupedCount,
    'truncated' => $truncated,
]);
