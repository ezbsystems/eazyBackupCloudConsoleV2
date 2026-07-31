<?php
declare(strict_types=1);

/**
 * Debug-session NDJSON sink (session a377a1). Remove after inventory empty-list debug.
 */
header('Content-Type: application/json');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo '{"ok":false}';
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode((string) $raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo '{"ok":false}';
    exit;
}

$line = json_encode($data, JSON_UNESCAPED_SLASHES);
if ($line === false) {
    http_response_code(400);
    echo '{"ok":false}';
    exit;
}

$logPath = '/var/www/eazybackup.ca/.cursor/debug-a377a1.log';
$dir = dirname($logPath);
if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
}
@file_put_contents($logPath, $line . "\n", FILE_APPEND | LOCK_EX);
echo '{"ok":true}';
