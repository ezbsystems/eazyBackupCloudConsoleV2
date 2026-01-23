<?php
/**
 * Queue Hyper-V VM details request for selected VMs.
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

$bodyRaw = file_get_contents('php://input');
$body = $bodyRaw ? json_decode($bodyRaw, true) : [];

$vmIds = $body['vm_ids'] ?? ($_POST['vm_ids'] ?? ($_GET['vm_ids'] ?? []));
if (is_string($vmIds)) {
    $decoded = json_decode(html_entity_decode($vmIds, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $vmIds = $decoded;
    } else {
        $vmIds = array_filter(array_map('trim', explode(',', $vmIds)));
    }
}

if (!is_array($vmIds)) {
    $vmIds = [];
}

$vmIds = array_values(array_filter(array_map('strval', $vmIds), function ($id) {
    return $id !== '';
}));

if (count($vmIds) === 0) {
    respond(['status' => 'fail', 'message' => 'vm_ids is required'], 400);
}

try {
    $payload = ['vm_ids' => $vmIds];

    $cmdTable = Capsule::table('s3_cloudbackup_run_commands');
    $hasCreatedAt = Capsule::schema()->hasColumn('s3_cloudbackup_run_commands', 'created_at');
    $hasUpdatedAt = Capsule::schema()->hasColumn('s3_cloudbackup_run_commands', 'updated_at');

    $insert = [
        'run_id' => null,
        'agent_id' => $agent->id,
        'type' => 'list_hyperv_vm_details',
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

    respond(['status' => 'pending', 'command_id' => (int) $commandId], 202);
} catch (\Throwable $e) {
    respond(['status' => 'fail', 'message' => 'Server error: '.$e->getMessage()], 500);
}
