<?php
/**
 * Cloud NAS - Update Mount Status (Agent Callback)
 * Called by the agent to update the status of a mount operation
 */

require_once __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function respond(array $data, int $httpCode = 200): void
{
    (new JsonResponse($data, $httpCode))->send();
    exit;
}

// Authenticate agent
$agentId = $_SERVER['HTTP_X_AGENT_ID'] ?? ($_POST['agent_id'] ?? null);
$agentToken = $_SERVER['HTTP_X_AGENT_TOKEN'] ?? ($_POST['agent_token'] ?? null);

if (!$agentId || !$agentToken) {
    respond(['status' => 'fail', 'message' => 'Missing agent headers'], 401);
}

$agent = Capsule::table('s3_cloudbackup_agents')
    ->where('id', $agentId)
    ->first();

if (!$agent || $agent->status !== 'active' || $agent->agent_token !== $agentToken) {
    respond(['status' => 'fail', 'message' => 'Unauthorized'], 401);
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    respond(['status' => 'error', 'message' => 'Invalid request']);
}

$mountId = intval($input['mount_id'] ?? 0);
$status = trim($input['status'] ?? '');
$error = trim($input['error'] ?? '');

if ($mountId <= 0 || empty($status)) {
    respond(['status' => 'error', 'message' => 'mount_id and status are required']);
}

// Validate status
$validStatuses = ['mounted', 'unmounted', 'mounting', 'unmounting', 'error'];
if (!in_array($status, $validStatuses)) {
    respond(['status' => 'error', 'message' => 'Invalid status']);
}

try {
    // Verify mount belongs to this agent
    $mount = Capsule::table('s3_cloudnas_mounts')
        ->where('id', $mountId)
        ->where('agent_id', $agentId)
        ->first();

    if (!$mount) {
        respond(['status' => 'error', 'message' => 'Mount not found']);
    }

    // Update status
    $updateData = [
        'status' => $status,
        'updated_at' => date('Y-m-d H:i:s')
    ];

    if ($status === 'mounted') {
        $updateData['last_mounted_at'] = date('Y-m-d H:i:s');
        $updateData['error'] = null;
    } elseif ($status === 'error') {
        $updateData['error'] = $error ?: 'Unknown error';
    } elseif ($status === 'unmounted') {
        $updateData['error'] = null;
    }

    Capsule::table('s3_cloudnas_mounts')
        ->where('id', $mountId)
        ->update($updateData);

    respond(['status' => 'success', 'message' => 'Status updated']);

} catch (Exception $e) {
    error_log("cloudnas_update_status error: " . $e->getMessage());
    respond(['status' => 'error', 'message' => 'Failed to update status']);
}

