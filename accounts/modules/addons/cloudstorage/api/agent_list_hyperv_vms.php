<?php
/**
 * List Hyper-V VMs for a given agent
 * 
 * Queues a command to the agent to discover VMs, waits for response.
 * Used by the job creation wizard to show available VMs.
 */

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

$agentId = isset($_GET['agent_id']) ? (int) $_GET['agent_id'] : 0;

if ($agentId <= 0) {
    respond(['status' => 'fail', 'message' => 'agent_id is required'], 400);
}

$agent = Capsule::table('s3_cloudbackup_agents')
    ->where('id', $agentId)
    ->where('client_id', $clientId)
    ->where('status', 'active')
    ->first(['id', 'client_id']);

if (!$agent) {
    respond(['status' => 'fail', 'message' => 'Agent not found'], 404);
}

try {
    // Queue list_hyperv_vms command
    $payload = [];

    $cmdTable = Capsule::table('s3_cloudbackup_run_commands');
    $hasCreatedAt = Capsule::schema()->hasColumn('s3_cloudbackup_run_commands', 'created_at');
    $hasUpdatedAt = Capsule::schema()->hasColumn('s3_cloudbackup_run_commands', 'updated_at');

    $insert = [
        'run_id' => null,
        'agent_id' => $agent->id,
        'type' => 'list_hyperv_vms',
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

    // Poll for completion (up to 15 seconds - VM enumeration can take time)
    $deadline = microtime(true) + 15.0;
    while (microtime(true) < $deadline) {
        $cmd = Capsule::table('s3_cloudbackup_run_commands')
            ->where('id', $commandId)
            ->first(['status', 'result_message']);

        if ($cmd && $cmd->status === 'completed') {
            $vms = [];
            if (!empty($cmd->result_message)) {
                $decoded = json_decode($cmd->result_message, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $vms = $decoded['vms'] ?? $decoded;
                }
            }
            respond(['status' => 'success', 'vms' => $vms]);
        }

        if ($cmd && $cmd->status === 'failed') {
            respond(['status' => 'fail', 'message' => $cmd->result_message ?: 'Failed to list VMs']);
        }

        usleep(300000); // 300ms
    }

    respond(['status' => 'fail', 'message' => 'Timeout waiting for agent response. Ensure agent is online and Hyper-V is available.'], 504);
} catch (\Throwable $e) {
    respond(['status' => 'fail', 'message' => 'Server error: '.$e->getMessage()], 500);
}

