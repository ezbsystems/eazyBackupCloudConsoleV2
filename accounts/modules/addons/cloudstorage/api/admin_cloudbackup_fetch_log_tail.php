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

function redactSensitiveText(string $text): string
{
    $patterns = [
        '/\b(access[_-]?key|secret|token|password|authorization)\b\s*[:=]\s*([^\s,;]+)/i',
        '/\b(AWS_ACCESS_KEY_ID|AWS_SECRET_ACCESS_KEY|KOPIA_PASSWORD)\b\s*[:=]\s*([^\s,;]+)/i',
    ];
    foreach ($patterns as $pattern) {
        $text = preg_replace($pattern, '$1=[redacted]', $text);
    }
    return $text;
}

function getModuleSetting(string $key, $default = null)
{
    try {
        $val = Capsule::table('tbladdonmodules')
            ->where('module', 'cloudstorage')
            ->where('setting', $key)
            ->value('value');
        return ($val !== null && $val !== '') ? $val : $default;
    } catch (\Throwable $e) {
        return $default;
    }
}

if (!isset($_SESSION['adminid']) || !$_SESSION['adminid']) {
    respond(['status' => 'fail', 'message' => 'Admin authentication required'], 401);
}

if (!Capsule::schema()->hasTable('s3_cloudbackup_run_commands')) {
    respond(['status' => 'fail', 'message' => 'Command queue not available'], 500);
}

$agentUuid = trim((string) ($_GET['agent_uuid'] ?? ($_POST['agent_uuid'] ?? '')));
$logKind = strtolower(trim((string) ($_GET['log_kind'] ?? ($_POST['log_kind'] ?? 'agent'))));
$maxBytes = isset($_GET['max_bytes']) ? (int) $_GET['max_bytes'] : (int) ($_POST['max_bytes'] ?? 131072);
$timeoutSecs = isset($_GET['timeout_secs']) ? (int) $_GET['timeout_secs'] : (int) ($_POST['timeout_secs'] ?? 15);

if ($agentUuid === '') {
    respond(['status' => 'fail', 'message' => 'agent_uuid is required'], 400);
}
if (!in_array($logKind, ['agent', 'tray'], true)) {
    respond(['status' => 'fail', 'message' => 'log_kind must be agent or tray'], 400);
}
if ($maxBytes <= 0) {
    $maxBytes = 131072;
}
if ($maxBytes > 262144) {
    $maxBytes = 262144;
}
if ($timeoutSecs <= 0) {
    $timeoutSecs = 15;
}
if ($timeoutSecs > 60) {
    $timeoutSecs = 60;
}

$allowedPaths = [
    'agent' => 'C:\\ProgramData\\E3Backup\\logs\\agent.log',
    'tray' => 'C:\\ProgramData\\E3Backup\\logs\\tray.log',
];
$logPath = $allowedPaths[$logKind];

$agent = Capsule::table('s3_cloudbackup_agents')
    ->where('agent_uuid', $agentUuid)
    ->first(['id', 'agent_uuid', 'status', 'last_seen_at']);
if (!$agent) {
    respond(['status' => 'fail', 'message' => 'Agent not found'], 404);
}
if ((string) $agent->status !== 'active') {
    respond(['status' => 'fail', 'message' => 'Agent is not active'], 409);
}

// Short-circuit when agent appears offline to avoid waiting on predictable timeout.
$onlineThreshold = (int) getModuleSetting('cloudbackup_agent_online_threshold_seconds', 180);
if ($onlineThreshold <= 0) {
    $onlineThreshold = 180;
}
if (empty($agent->last_seen_at)) {
    respond(['status' => 'fail', 'message' => 'Agent is offline (never seen)'], 409);
}
$secs = (int) Capsule::table('s3_cloudbackup_agents')
    ->where('agent_uuid', $agentUuid)
    ->selectRaw('TIMESTAMPDIFF(SECOND, last_seen_at, NOW()) as seconds_since_seen')
    ->value('seconds_since_seen');
if ($secs > $onlineThreshold) {
    respond(['status' => 'fail', 'message' => 'Agent appears offline'], 409);
}

try {
    $hasCreatedAt = Capsule::schema()->hasColumn('s3_cloudbackup_run_commands', 'created_at');
    $hasUpdatedAt = Capsule::schema()->hasColumn('s3_cloudbackup_run_commands', 'updated_at');
    $insert = [
        'run_id' => null,
        'agent_uuid' => $agentUuid,
        'type' => 'fetch_log_tail',
        'payload_json' => json_encode([
            'log_kind' => $logKind,
            'log_path' => $logPath,
            'max_bytes' => $maxBytes,
        ], JSON_UNESCAPED_SLASHES),
        'status' => 'pending',
    ];
    if ($hasCreatedAt) {
        $insert['created_at'] = Capsule::raw('NOW()');
    }
    if ($hasUpdatedAt) {
        $insert['updated_at'] = Capsule::raw('NOW()');
    }
    $commandId = Capsule::table('s3_cloudbackup_run_commands')->insertGetId($insert);

    $deadline = microtime(true) + (float) $timeoutSecs;
    while (microtime(true) < $deadline) {
        $cmd = Capsule::table('s3_cloudbackup_run_commands')
            ->where('id', $commandId)
            ->first(['status', 'result_message']);
        if (!$cmd) {
            respond(['status' => 'fail', 'message' => 'Command not found after enqueue'], 500);
        }
        if ($cmd->status === 'completed') {
            $decoded = [];
            if (!empty($cmd->result_message)) {
                $maybe = json_decode((string) $cmd->result_message, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($maybe)) {
                    $decoded = $maybe;
                }
            }
            $content = (string) ($decoded['content'] ?? '');
            $content = redactSensitiveText($content);
            $resultPath = (string) ($decoded['path'] ?? $logPath);
            $truncated = !empty($decoded['truncated']);
            $retrievedAt = (string) ($decoded['retrieved_at'] ?? date('c'));
            respond([
                'status' => 'success',
                'command_id' => (int) $commandId,
                'log_kind' => $logKind,
                'path' => $resultPath,
                'truncated' => $truncated,
                'retrieved_at' => $retrievedAt,
                'content' => $content,
            ]);
        }
        if ($cmd->status === 'failed') {
            respond(['status' => 'fail', 'message' => (string) ($cmd->result_message ?: 'Log fetch failed')], 200);
        }
        usleep(200000);
    }

    respond(['status' => 'fail', 'message' => 'Timeout waiting for agent response'], 504);
} catch (\Throwable $e) {
    respond(['status' => 'fail', 'message' => 'Unable to fetch logs'], 500);
}

