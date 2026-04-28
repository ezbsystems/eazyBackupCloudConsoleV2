<?php

require_once __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;

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

$agentUuid = trim((string) ($_GET['agent_uuid'] ?? ''));
if ($agentUuid === '') {
    respond(['status' => 'fail', 'message' => 'agent_uuid is required'], 400);
}
$limit = (int) ($_GET['limit'] ?? 50);
if ($limit <= 0 || $limit > 500) {
    $limit = 50;
}

$rows = Capsule::table('s3_cloudbackup_runs as r')
    ->leftJoin('s3_cloudbackup_jobs as j', 'r.job_id', '=', 'j.job_id')
    ->where('r.agent_uuid', $agentUuid)
    ->orderByDesc('r.id')
    ->limit($limit)
    ->get([
        'r.id',
        Capsule::raw('BIN_TO_UUID(r.run_id) as run_id_uuid'),
        'r.status',
        'r.run_type',
        'r.started_at',
        'r.finished_at',
        'r.progress_pct',
        'r.bytes_transferred',
        'r.bytes_total',
        'r.error_summary',
        'j.job_name',
    ]);

$out = [];
foreach ($rows as $r) {
    $out[] = [
        'id' => (int) $r->id,
        'run_id' => (string) $r->run_id_uuid,
        'status' => (string) $r->status,
        'run_type' => (string) ($r->run_type ?? ''),
        'started_at' => $r->started_at,
        'finished_at' => $r->finished_at,
        'progress_pct' => isset($r->progress_pct) ? (float) $r->progress_pct : null,
        'bytes_transferred' => isset($r->bytes_transferred) ? (int) $r->bytes_transferred : null,
        'bytes_total' => isset($r->bytes_total) ? (int) $r->bytes_total : null,
        'error_summary' => (string) ($r->error_summary ?? ''),
        'job_name' => (string) ($r->job_name ?? ''),
    ];
}

respond([
    'status' => 'success',
    'agent_uuid' => $agentUuid,
    'runs' => $out,
]);
