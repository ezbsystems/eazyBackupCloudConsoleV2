<?php
/**
 * Cloud NAS - Delete Mount Configuration
 * Removes a mount configuration (unmounts first if needed)
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

    // If mounted, send unmount command first
    if ($mount->status === 'mounted') {
        $agent = Capsule::table('s3_cloudbackup_agents')
            ->where('id', (int) $mount->agent_id)
            ->where('client_id', $clientId)
            ->first(['agent_uuid']);
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
        if ($agent && trim((string) ($agent->agent_uuid ?? '')) !== '' && Capsule::schema()->hasColumn('s3_cloudbackup_run_commands', 'agent_uuid')) {
            $commandPayload['agent_uuid'] = trim((string) $agent->agent_uuid);
        } elseif (Capsule::schema()->hasColumn('s3_cloudbackup_run_commands', 'agent_id')) {
            $commandPayload['agent_id'] = (int) $mount->agent_id;
        }
        Capsule::table('s3_cloudbackup_run_commands')->insert($commandPayload);
    }

    // Delete the mount configuration
    Capsule::table('s3_cloudnas_mounts')
        ->where('id', $mountId)
        ->delete();

    (new JsonResponse([
        'status' => 'success',
        'message' => 'Mount configuration deleted'
    ], 200))->send();

} catch (Exception $e) {
    error_log("cloudnas_delete_mount error: " . $e->getMessage());
    (new JsonResponse(['status' => 'error', 'message' => 'Failed to delete mount configuration'], 200))->send();
}
exit;

