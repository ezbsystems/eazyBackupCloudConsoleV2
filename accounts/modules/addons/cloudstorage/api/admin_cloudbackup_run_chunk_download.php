<?php

require_once __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\UuidBinary;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function failPlain(int $code, string $msg): void
{
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $msg;
    exit;
}

if (!isset($_SESSION['adminid']) || !$_SESSION['adminid']) {
    failPlain(401, 'Admin authentication required');
}

if (!Capsule::schema()->hasTable('s3_cloudbackup_admin_log_chunks')) {
    failPlain(500, 'Admin log chunks store not available');
}

$runId = trim((string) ($_GET['run_id'] ?? ''));
$chunkSeqRaw = $_GET['chunk_seq'] ?? '';
$asAttachment = !empty($_GET['download']);
$decompress = isset($_GET['decompress']) ? (bool) $_GET['decompress'] : true;

if ($runId === '' || !UuidBinary::isUuid($runId)) {
    failPlain(400, 'Valid run_id (UUID) is required');
}
if ($chunkSeqRaw === '' || !ctype_digit((string) $chunkSeqRaw)) {
    failPlain(400, 'chunk_seq is required');
}
$chunkSeq = (int) $chunkSeqRaw;

$expr = UuidBinary::toDbExpr(UuidBinary::normalize($runId));
$row = Capsule::table('s3_cloudbackup_admin_log_chunks')
    ->whereRaw('run_id = ' . $expr)
    ->where('chunk_seq', $chunkSeq)
    ->first(['encoding', 'content_blob', 'first_ts', 'last_ts', 'source']);

if (!$row) {
    failPlain(404, 'Chunk not found');
}

$blob = (string) $row->content_blob;
$encoding = (string) $row->encoding;

if ($decompress && $encoding === 'gzip') {
    $decoded = @gzdecode($blob);
    if ($decoded === false) {
        failPlain(500, 'Failed to decompress chunk');
    }
    $body = $decoded;
    $sendEncoding = 'identity';
} else {
    $body = $blob;
    $sendEncoding = $encoding;
}

if ($asAttachment) {
    $name = sprintf('run-%s-chunk-%d.%s', preg_replace('/[^a-z0-9\-]/i', '', $runId), $chunkSeq, $sendEncoding === 'gzip' ? 'log.gz' : 'log');
    header('Content-Disposition: attachment; filename="' . $name . '"');
}
header('Content-Type: ' . ($sendEncoding === 'gzip' ? 'application/gzip' : 'text/plain; charset=utf-8'));
header('Content-Length: ' . strlen($body));
header('X-Chunk-Source: ' . (string) $row->source);
header('X-Chunk-First-TS: ' . (string) $row->first_ts);
header('X-Chunk-Last-TS: ' . (string) $row->last_ts);
echo $body;
exit;
