<?php
/**
 * Cloud NAS - Unmount Drive
 * Sends an unmount command to the agent
 */

require_once __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Session timeout'], 200))->send();
    exit;
}
$clientId = $ca->getUserID();

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$mountId = intval($input['mount_id'] ?? 0);

if ($mountId <= 0) {
    (new JsonResponse(['status' => 'error', 'message' => 'Mount ID is required'], 200))->send();
    exit;
}

try {
    // Get mount configuration
    $mount = Capsule::table('s3_cloudnas_mounts')
        ->where('id', $mountId)
        ->where('client_id', $clientId)
        ->first();

    if (!$mount) {
        (new JsonResponse(['status' => 'error', 'message' => 'Mount configuration not found'], 200))->send();
        exit;
    }

    if ($mount->status === 'unmounted') {
        (new JsonResponse(['status' => 'error', 'message' => 'Drive is not mounted'], 200))->send();
        exit;
    }
    $agent = Capsule::table('s3_cloudbackup_agents')
        ->where('id', (int) $mount->agent_id)
        ->where('client_id', $clientId)
        ->first(['agent_uuid']);
    if (!$agent || trim((string) ($agent->agent_uuid ?? '')) === '') {
        (new JsonResponse(['status' => 'error', 'message' => 'Agent not found for mount'], 200))->send();
        exit;
    }
    $agentUuid = trim((string) $agent->agent_uuid);

    // Queue unmount command for agent
    $commandPayload = [
        'run_id' => null,
        'type' => 'nas_unmount',
        'payload_json' => json_encode([
            'mount_id' => $mountId,
            'drive_letter' => $mount->drive_letter
        ]),
        'status' => 'pending',
        'created_at' => Capsule::raw('NOW()')
    ];
    if (Capsule::schema()->hasColumn('s3_cloudbackup_run_commands', 'agent_uuid')) {
        $commandPayload['agent_uuid'] = $agentUuid;
    } elseif (Capsule::schema()->hasColumn('s3_cloudbackup_run_commands', 'agent_id')) {
        $commandPayload['agent_id'] = (int) $mount->agent_id;
    }
    $commandId = Capsule::table('s3_cloudbackup_run_commands')->insertGetId($commandPayload);

    // Update mount status to unmounting
    Capsule::table('s3_cloudnas_mounts')
        ->where('id', $mountId)
        ->update([
            'status' => 'unmounting',
            'updated_at' => date('Y-m-d H:i:s')
        ]);

    (new JsonResponse([
        'status' => 'success',
        'message' => 'Unmount command queued',
        'command_id' => $commandId
    ], 200))->send();

} catch (Exception $e) {
    error_log("cloudnas_unmount error: " . $e->getMessage());
    (new JsonResponse(['status' => 'error', 'message' => 'Failed to send unmount command'], 200))->send();
}
exit;

