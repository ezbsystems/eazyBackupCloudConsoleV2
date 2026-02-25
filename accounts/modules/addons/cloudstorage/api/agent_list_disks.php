<?php

require_once __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function respond(array $data, int $httpCode = 200): void
{
    header('Content-Type: application/json');
    (new JsonResponse($data, $httpCode))->send();
    exit;
}

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    respond(['status' => 'fail', 'message' => 'Session timeout'], 401);
}
$clientId = $ca->getUserID();

if (!Capsule::schema()->hasTable('s3_cloudbackup_run_commands')) {
    respond(['status' => 'fail', 'message' => 'Command queue not available'], 500);
}

$agentUuid = trim((string) ($_GET['agent_uuid'] ?? ''));
if ($agentUuid === '') {
    respond(['status' => 'fail', 'message' => 'agent_uuid is required'], 400);
}

$agent = Capsule::table('s3_cloudbackup_agents')
    ->where('agent_uuid', $agentUuid)
    ->where('client_id', $clientId)
    ->where('status', 'active')
    ->first(['id', 'agent_uuid', 'client_id']);

if (!$agent) {
    respond(['status' => 'fail', 'message' => 'Agent not found'], 404);
}

try {
    $payload = [];
    $cmdTable = Capsule::table('s3_cloudbackup_run_commands');
    $hasCreatedAt = Capsule::schema()->hasColumn('s3_cloudbackup_run_commands', 'created_at');
    $hasUpdatedAt = Capsule::schema()->hasColumn('s3_cloudbackup_run_commands', 'updated_at');

    $insert = [
        'run_id' => null,
        'agent_uuid' => $agent->agent_uuid,
        'type' => 'list_disks',
        'payload_json' => json_encode($payload, JSON_UNESCAPED_SLASHES),
        'status' => 'pending',
    ];
    if ($hasCreatedAt) {
        $insert['created_at'] = Capsule::raw('NOW()');
    }
    if ($hasUpdatedAt) {
        $insert['updated_at'] = Capsule::raw('NOW()');
    }

    $commandId = $cmdTable->insertGetId($insert);

    $deadline = microtime(true) + 30.0;
    while (microtime(true) < $deadline) {
        $cmd = Capsule::table('s3_cloudbackup_run_commands')
            ->where('id', $commandId)
            ->first(['status', 'result_message']);

        if ($cmd && $cmd->status === 'completed') {
            $data = [];
            if (!empty($cmd->result_message)) {
                $decoded = json_decode($cmd->result_message, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $data = $decoded;
                }
            }
            respond(['status' => 'success', 'data' => $data]);
        }

        if ($cmd && $cmd->status === 'failed') {
            respond(['status' => 'fail', 'message' => $cmd->result_message ?: 'Disk list failed']);
        }

        usleep(500000);
    }

    respond(['status' => 'fail', 'message' => 'Timeout waiting for agent response'], 504);
} catch (\Throwable $e) {
    respond(['status' => 'fail', 'message' => 'Server error: '.$e->getMessage()], 500);
}
