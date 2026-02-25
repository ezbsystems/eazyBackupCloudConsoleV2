<?php
/**
 * Cloud NAS - Unmount Snapshot (Time Machine)
 * Unmounts a previously mounted Kopia snapshot
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

$manifestId = trim($input['manifest_id'] ?? '');
$agentUuid = trim((string) ($input['agent_uuid'] ?? ''));

if (empty($manifestId) || $agentUuid === '') {
    (new JsonResponse(['status' => 'error', 'message' => 'Manifest ID and agent UUID are required'], 200))->send();
    exit;
}

try {
    // Verify agent belongs to client
    $agent = Capsule::table('s3_cloudbackup_agents')
        ->where('agent_uuid', $agentUuid)
        ->where('client_id', $clientId)
        ->first();

    if (!$agent) {
        (new JsonResponse(['status' => 'error', 'message' => 'Agent not found'], 200))->send();
        exit;
    }

    // Queue unmount snapshot command
    $commandPayload = [
        'run_id' => null,
        'type' => 'nas_unmount_snapshot',
        'payload_json' => json_encode([
            'manifest_id' => $manifestId
        ]),
        'status' => 'pending',
        'created_at' => Capsule::raw('NOW()')
    ];
    if (Capsule::schema()->hasColumn('s3_cloudbackup_run_commands', 'agent_uuid')) {
        $commandPayload['agent_uuid'] = $agentUuid;
    } elseif (Capsule::schema()->hasColumn('s3_cloudbackup_run_commands', 'agent_id')) {
        $commandPayload['agent_id'] = (int) $agent->id;
    }
    $commandId = Capsule::table('s3_cloudbackup_run_commands')->insertGetId($commandPayload);

    (new JsonResponse([
        'status' => 'success',
        'message' => 'Unmount command queued',
        'command_id' => $commandId
    ], 200))->send();

} catch (Exception $e) {
    error_log("cloudnas_unmount_snapshot error: " . $e->getMessage());
    (new JsonResponse(['status' => 'error', 'message' => 'Failed to unmount snapshot'], 200))->send();
}
exit;

