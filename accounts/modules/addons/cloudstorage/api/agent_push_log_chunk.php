<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/AgentIngestSupport.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\Module\Addon\CloudStorage\Client\UuidBinary;
use WHMCS\Module\Addon\CloudStorage\Client\AgentIngestSupport;
use WHMCS\Module\Addon\CloudStorage\Client\AgentAuth;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

const CHUNK_MAX_BYTES = 1048576; // 1 MB compressed cap per chunk

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

if (!Capsule::schema()->hasTable('s3_cloudbackup_admin_log_chunks')) {
    respond(['status' => 'fail', 'message' => 'Admin log chunks store not available'], 500);
}

$body = getBodyJson();
$runId = trim((string) ($_POST['run_id'] ?? ($body['run_id'] ?? '')));
if ($runId === '' || !UuidBinary::isUuid($runId)) {
    respond(['status' => 'fail', 'code' => 'invalid_identifier_format', 'message' => 'run_id must be a valid UUID'], 400);
}

$chunkSeq = (int) ($body['chunk_seq'] ?? -1);
if ($chunkSeq < 0) {
    respond(['status' => 'fail', 'message' => 'chunk_seq is required and must be >= 0'], 400);
}

$source = strtolower((string) ($body['source'] ?? 'run'));
if (!in_array($source, ['agent', 'tray', 'run'], true)) {
    $source = 'run';
}

$contentB64 = (string) ($body['content_b64'] ?? '');
if ($contentB64 === '') {
    respond(['status' => 'fail', 'message' => 'content_b64 is required'], 400);
}
$blob = base64_decode($contentB64, true);
if ($blob === false) {
    respond(['status' => 'fail', 'message' => 'Invalid content_b64'], 400);
}
if (strlen($blob) > CHUNK_MAX_BYTES) {
    respond(['status' => 'fail', 'message' => 'Chunk too large', 'max_bytes' => CHUNK_MAX_BYTES], 413);
}

$encoding = strtolower((string) ($body['encoding'] ?? 'gzip'));
if ($encoding !== 'gzip') {
    respond(['status' => 'fail', 'message' => 'Only gzip encoding is supported'], 400);
}

$lineCount = max(0, (int) ($body['line_count'] ?? 0));

$nowGmt = gmdate('Y-m-d H:i:s');
$firstTs = isset($body['first_ts']) && is_string($body['first_ts']) ? (strtotime($body['first_ts']) ?: time()) : time();
$lastTs = isset($body['last_ts']) && is_string($body['last_ts']) ? (strtotime($body['last_ts']) ?: time()) : time();
$firstTsDt = gmdate('Y-m-d H:i:s', $firstTs);
$lastTsDt = gmdate('Y-m-d H:i:s', $lastTs);

$agent = authenticateAgent();

$gate = AgentIngestSupport::checkMinAgentVersion($agent, $body);
if ($gate !== null) {
    respond($gate[0], $gate[1]);
}

$runIdExpr = UuidBinary::toDbExpr(UuidBinary::normalize($runId));

$run = Capsule::table('s3_cloudbackup_runs as r')
    ->join('s3_cloudbackup_jobs as j', 'r.job_id', '=', 'j.job_id')
    ->whereRaw('r.run_id = ' . $runIdExpr)
    ->select('r.run_id', 'j.client_id', 'r.agent_uuid')
    ->first();

if (!$run || (int) $run->client_id !== (int) $agent->client_id) {
    respond(['status' => 'fail', 'message' => 'Run not found or unauthorized'], 403);
}
if (!empty($run->agent_uuid) && (string) $run->agent_uuid !== (string) $agent->agent_uuid) {
    respond(['status' => 'fail', 'message' => 'Run not assigned to this agent'], 403);
}

$maxChunks = AgentIngestSupport::maxChunksPerRun();
$existingCount = (int) Capsule::table('s3_cloudbackup_admin_log_chunks')
    ->whereRaw('run_id = ' . $runIdExpr)
    ->count();
if ($existingCount >= $maxChunks) {
    respond(['status' => 'fail', 'code' => 'chunks_cap_reached', 'message' => 'Per-run chunk cap reached', 'max' => $maxChunks], 429);
}

try {
    Capsule::table('s3_cloudbackup_admin_log_chunks')->insert([
        'run_id' => Capsule::raw($runIdExpr),
        'chunk_seq' => $chunkSeq,
        'source' => $source,
        'first_ts' => $firstTsDt,
        'last_ts' => $lastTsDt,
        'encoding' => $encoding,
        'content_blob' => $blob,
        'line_count' => $lineCount,
        'byte_count' => strlen($blob),
        'created_at' => $nowGmt,
    ]);
} catch (\Throwable $e) {
    // Likely a duplicate (run_id, chunk_seq). Treat as idempotent success.
    if (stripos($e->getMessage(), 'Duplicate') !== false || stripos($e->getMessage(), '1062') !== false) {
        respond(['status' => 'success', 'duplicate' => true, 'chunk_seq' => $chunkSeq]);
    }
    logModuleCall('cloudstorage', 'agent_push_log_chunk_error', [
        'run_id' => $runId,
        'chunk_seq' => $chunkSeq,
        'agent_uuid' => $agent->agent_uuid,
    ], $e->getMessage());
    respond(['status' => 'fail', 'message' => 'Insert failed'], 500);
}

respond([
    'status' => 'success',
    'chunk_seq' => $chunkSeq,
    'byte_count' => strlen($blob),
    'line_count' => $lineCount,
    'remaining_capacity' => max(0, $maxChunks - ($existingCount + 1)),
]);
