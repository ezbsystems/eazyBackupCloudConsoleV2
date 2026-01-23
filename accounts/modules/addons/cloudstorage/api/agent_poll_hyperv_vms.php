<?php
/**
 * Poll for Hyper-V VM discovery command results.
 * Returns pending until the agent completes the queued command.
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

$commandId = isset($_GET['command_id']) ? (int) $_GET['command_id'] : 0;
if ($commandId <= 0) {
    respond(['status' => 'fail', 'message' => 'command_id is required'], 400);
}

$cmd = Capsule::table('s3_cloudbackup_run_commands as c')
    ->join('s3_cloudbackup_agents as a', 'c.agent_id', '=', 'a.id')
    ->where('c.id', $commandId)
    ->where('a.client_id', $clientId)
    ->select('c.status', 'c.result_message', 'c.type')
    ->first();

if (!$cmd) {
    respond(['status' => 'fail', 'message' => 'Command not found'], 404);
}

$type = strtolower((string) $cmd->type);
if (!in_array($type, ['list_hyperv_vms', 'list_hyperv_vm_details'], true)) {
    respond(['status' => 'fail', 'message' => 'Unsupported command type'], 400);
}

if ($cmd->status === 'failed') {
    respond(['status' => 'fail', 'message' => $cmd->result_message ?: 'Command failed'], 200);
}

if ($cmd->status !== 'completed') {
    respond(['status' => 'pending', 'command_id' => $commandId], 200);
}

$decoded = [];
if (!empty($cmd->result_message)) {
    $result = json_decode($cmd->result_message, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($result)) {
        $decoded = $result;
    }
}

if ($type === 'list_hyperv_vms') {
    $vms = $decoded['vms'] ?? $decoded;
    respond(['status' => 'success', 'type' => $type, 'vms' => $vms, 'result' => $decoded], 200);
}

$details = $decoded['details'] ?? $decoded;
respond(['status' => 'success', 'type' => $type, 'details' => $details, 'result' => $decoded], 200);
