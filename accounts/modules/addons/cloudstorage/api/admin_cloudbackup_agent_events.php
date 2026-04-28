<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/AgentIngestSupport.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\Module\Addon\CloudStorage\Client\AgentIngestSupport;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function respond(array $data, int $httpCode = 200): void
{
    (new JsonResponse($data, $httpCode))->send();
    exit;
}

if (!isset($_SESSION['adminid']) || !$_SESSION['adminid']) {
    respond(['status' => 'fail', 'message' => 'Admin authentication required'], 401);
}

if (!AgentIngestSupport::ensureAgentEventsTable()) {
    respond(['status' => 'fail', 'message' => 'Agent events store not available'], 500);
}

$agentUuid = trim((string) ($_GET['agent_uuid'] ?? ''));
$source = strtolower(trim((string) ($_GET['source'] ?? '')));
$level = strtolower(trim((string) ($_GET['level'] ?? '')));
$sinceMin = (int) ($_GET['since_minutes'] ?? 0);
$limit = (int) ($_GET['limit'] ?? 200);
$offset = (int) ($_GET['offset'] ?? 0);
$q = trim((string) ($_GET['q'] ?? ''));

if ($agentUuid === '') {
    respond(['status' => 'fail', 'message' => 'agent_uuid is required'], 400);
}
if ($limit <= 0 || $limit > 1000) {
    $limit = 200;
}
if ($offset < 0) {
    $offset = 0;
}

$query = Capsule::table('s3_cloudbackup_agent_events')
    ->where('agent_uuid', $agentUuid);

if (in_array($source, ['agent', 'tray'], true)) {
    $query->where('source', $source);
}
if (in_array($level, ['info', 'warn', 'error'], true)) {
    $query->where('level', $level);
}
if ($sinceMin > 0) {
    $query->where('ts', '>=', gmdate('Y-m-d H:i:s', time() - $sinceMin * 60));
}
if ($q !== '') {
    $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
    $query->where(function ($qq) use ($like) {
        $qq->where('code', 'like', $like)
           ->orWhere('message_id', 'like', $like)
           ->orWhere('params_json', 'like', $like);
    });
}

$total = (clone $query)->count();
$rows = $query
    ->orderByDesc('ts')
    ->orderByDesc('id')
    ->limit($limit)
    ->offset($offset)
    ->get(['id', 'agent_uuid', 'client_id', 'tenant_id', 'ts', 'source', 'level', 'code', 'message_id', 'params_json', 'dedupe_key']);

$out = [];
foreach ($rows as $r) {
    $params = null;
    if (!empty($r->params_json)) {
        $decoded = json_decode((string) $r->params_json, true);
        $params = (json_last_error() === JSON_ERROR_NONE) ? $decoded : (string) $r->params_json;
    }
    $out[] = [
        'id' => (int) $r->id,
        'ts' => (string) $r->ts,
        'source' => (string) $r->source,
        'level' => (string) $r->level,
        'code' => (string) $r->code,
        'message_id' => (string) $r->message_id,
        'params' => $params,
        'dedupe_key' => $r->dedupe_key !== null ? (string) $r->dedupe_key : null,
    ];
}

respond([
    'status' => 'success',
    'agent_uuid' => $agentUuid,
    'total' => (int) $total,
    'limit' => $limit,
    'offset' => $offset,
    'events' => $out,
]);
