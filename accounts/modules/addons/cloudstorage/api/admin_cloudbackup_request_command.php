<?php

require_once __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

session_start();
if (!isset($_SESSION['adminid']) || !$_SESSION['adminid']) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Admin authentication required'], 200))->send();
    exit;
}

$runId = isset($_POST['run_id']) ? (int) $_POST['run_id'] : 0;
$agentId = isset($_POST['agent_id']) ? (int) $_POST['agent_id'] : 0;
$type = isset($_POST['type']) ? strtolower(trim((string) $_POST['type'])) : '';
$payloadRaw = $_POST['payload_json'] ?? null;
$payload = null;
if ($payloadRaw) {
    $dec = json_decode($payloadRaw, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $payload = $dec;
    }
}

if (!in_array($type, ['maintenance_quick', 'maintenance_full', 'reset_agent'], true)) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Invalid command type'], 200))->send();
    exit;
}

if ($type === 'reset_agent') {
    if ($agentId <= 0) {
        (new JsonResponse(['status' => 'fail', 'message' => 'agent_id is required for reset_agent'], 200))->send();
        exit;
    }
    if (!Capsule::schema()->hasColumn('s3_cloudbackup_run_commands', 'agent_id')) {
        (new JsonResponse(['status' => 'fail', 'message' => 'agent_id column not available on this installation'], 200))->send();
        exit;
    }
} else {
    if ($runId <= 0) {
        (new JsonResponse(['status' => 'fail', 'message' => 'run_id is required for maintenance commands'], 200))->send();
        exit;
    }
}

if (!Capsule::schema()->hasTable('s3_cloudbackup_run_commands')) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Commands not supported on this installation'], 200))->send();
    exit;
}

try {
    $insert = [
        'run_id' => $type === 'reset_agent' ? null : $runId,
        'type' => $type,
        'payload_json' => $payload ? json_encode($payload) : null,
        'status' => 'pending',
        'created_at' => Capsule::raw('NOW()'),
    ];
    if ($type === 'reset_agent') {
        $insert['agent_id'] = $agentId;
    }
    $cmdId = Capsule::table('s3_cloudbackup_run_commands')->insertGetId($insert);
    (new JsonResponse(['status' => 'success', 'command_id' => $cmdId], 200))->send();
} catch (\Throwable $e) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Unable to enqueue command'], 200))->send();
}
exit;


