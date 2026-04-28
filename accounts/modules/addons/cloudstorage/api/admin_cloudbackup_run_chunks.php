<?php

require_once __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\Module\Addon\CloudStorage\Client\UuidBinary;

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

if (!Capsule::schema()->hasTable('s3_cloudbackup_admin_log_chunks')) {
    respond(['status' => 'fail', 'message' => 'Admin log chunks store not available'], 500);
}

$runId = trim((string) ($_GET['run_id'] ?? ''));
if ($runId === '' || !UuidBinary::isUuid($runId)) {
    respond(['status' => 'fail', 'message' => 'Valid run_id (UUID) is required'], 400);
}

$expr = UuidBinary::toDbExpr(UuidBinary::normalize($runId));

$rows = Capsule::table('s3_cloudbackup_admin_log_chunks')
    ->whereRaw('run_id = ' . $expr)
    ->orderBy('chunk_seq')
    ->get(['id', 'chunk_seq', 'source', 'first_ts', 'last_ts', 'encoding', 'line_count', 'byte_count', 'created_at']);

$out = [];
foreach ($rows as $r) {
    $out[] = [
        'id' => (int) $r->id,
        'chunk_seq' => (int) $r->chunk_seq,
        'source' => (string) $r->source,
        'first_ts' => (string) $r->first_ts,
        'last_ts' => (string) $r->last_ts,
        'encoding' => (string) $r->encoding,
        'line_count' => (int) $r->line_count,
        'byte_count' => (int) $r->byte_count,
        'created_at' => (string) $r->created_at,
    ];
}

respond([
    'status' => 'success',
    'run_id' => $runId,
    'chunks' => $out,
]);
