<?php

require_once __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\UuidBinary;
use Symfony\Component\HttpFoundation\JsonResponse;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

session_start();
if (!isset($_SESSION['adminid']) || !$_SESSION['adminid']) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Admin authentication required'], 200))->send();
    exit;
}

$runId = trim((string) ($_POST['run_id'] ?? ''));
$agentUuid = trim((string) ($_POST['agent_uuid'] ?? ''));
$type = isset($_POST['type']) ? strtolower(trim((string) $_POST['type'])) : '';
$payloadRaw = $_POST['payload_json'] ?? null;
$payload = null;
if ($payloadRaw) {
    $dec = json_decode($payloadRaw, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $payload = $dec;
    }
}

if (!in_array($type, ['maintenance_quick', 'maintenance_full', 'reset_agent', 'refresh_inventory'], true)) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Invalid command type'], 200))->send();
    exit;
}

if ($type === 'reset_agent' || $type === 'refresh_inventory') {
    if ($agentUuid === '') {
        (new JsonResponse(['status' => 'fail', 'message' => 'agent_uuid is required for agent-scoped commands'], 200))->send();
        exit;
    }
    if (!Capsule::schema()->hasColumn('s3_cloudbackup_run_commands', 'agent_uuid')) {
        (new JsonResponse(['status' => 'fail', 'message' => 'agent_uuid column not available on this installation'], 200))->send();
        exit;
    }
} else {
    $hasRunIdPk = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'run_id');
    if ($runId === '' && $agentUuid !== '') {
        if ($hasRunIdPk) {
            $runRow = Capsule::table('s3_cloudbackup_runs')
                ->selectRaw('BIN_TO_UUID(run_id) as run_id_uuid')
                ->where('agent_uuid', $agentUuid)
                ->whereIn('status', ['queued', 'starting', 'running'])
                ->orderByDesc('created_at')
                ->first();
            $runId = $runRow->run_id_uuid ?? '';
        } else {
            $runId = (string) Capsule::table('s3_cloudbackup_runs')
                ->where('agent_uuid', $agentUuid)
                ->whereIn('status', ['queued', 'starting', 'running'])
                ->orderByDesc('created_at')
                ->value('id');
        }
    }
    if ($runId !== '' && $agentUuid !== '') {
        if ($hasRunIdPk && UuidBinary::isUuid($runId)) {
            $match = Capsule::table('s3_cloudbackup_runs')
                ->whereRaw('run_id = ' . UuidBinary::toDbExpr(UuidBinary::normalize($runId)))
                ->where('agent_uuid', $agentUuid)
                ->exists();
        } else {
            $match = Capsule::table('s3_cloudbackup_runs')
                ->where('id', $runId)
                ->where('agent_uuid', $agentUuid)
                ->exists();
        }
        if (!$match) {
            (new JsonResponse(['status' => 'fail', 'message' => 'run_id does not belong to selected agent'], 200))->send();
            exit;
        }
    }
    if ($runId === '') {
        (new JsonResponse(['status' => 'fail', 'message' => 'No eligible run found for maintenance command'], 200))->send();
        exit;
    }
    if (!UuidBinary::isUuid($runId)) {
        (new JsonResponse(['status' => 'fail', 'code' => 'invalid_identifier_format', 'message' => 'run_id must be a valid UUID'], 400))->send();
        exit;
    }
}

if (!Capsule::schema()->hasTable('s3_cloudbackup_run_commands')) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Commands not supported on this installation'], 200))->send();
    exit;
}

try {
    $runIdForInsert = in_array($type, ['reset_agent', 'refresh_inventory'], true)
        ? null
        : (UuidBinary::isUuid($runId) ? Capsule::raw(UuidBinary::toDbExpr(UuidBinary::normalize($runId))) : $runId);
    $insert = [
        'run_id' => $runIdForInsert,
        'type' => $type,
        'payload_json' => $payload ? json_encode($payload) : null,
        'status' => 'pending',
        'created_at' => Capsule::raw('NOW()'),
    ];
    if (in_array($type, ['reset_agent', 'refresh_inventory'], true)) {
        $insert['agent_uuid'] = $agentUuid;
    }
    $cmdId = Capsule::table('s3_cloudbackup_run_commands')->insertGetId($insert);
    (new JsonResponse(['status' => 'success', 'command_id' => $cmdId], 200))->send();
} catch (\Throwable $e) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Unable to enqueue command'], 200))->send();
}
exit;


