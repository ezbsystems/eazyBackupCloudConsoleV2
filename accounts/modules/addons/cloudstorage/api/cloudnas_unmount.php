<?php
/**
 * Cloud NAS - Unmount Drive
 * Sends an unmount command to the agent and revokes the temp S3 key.
 */

require_once __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Admin\AdminOps;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Session timeout'], 200))->send();
    exit;
}
$clientId = $ca->getUserID();

$input = json_decode(file_get_contents('php://input'), true);
$mountId = intval($input['mount_id'] ?? 0);

if ($mountId <= 0) {
    (new JsonResponse(['status' => 'error', 'message' => 'Mount ID is required'], 200))->send();
    exit;
}

try {
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

    // Revoke the temporary S3 key that was created for this mount
    $hasTempKey = Capsule::schema()->hasColumn('s3_cloudnas_mounts', 'temp_access_key');
    if ($hasTempKey) {
        $tempAccessKey = $mount->temp_access_key ?? null;
        $tempKeyCephUid = '';
        if (Capsule::schema()->hasColumn('s3_cloudnas_mounts', 'temp_key_ceph_uid')) {
            $tempKeyCephUid = $mount->temp_key_ceph_uid ?? '';
        }
        if ($tempAccessKey && $tempAccessKey !== '' && $tempKeyCephUid !== '') {
            $settings = Capsule::table('tbladdonmodules')
                ->where('module', 'cloudstorage')
                ->whereIn('setting', ['s3_endpoint', 'ceph_access_key', 'ceph_secret_key'])
                ->pluck('value', 'setting');

            $s3Endpoint     = $settings['s3_endpoint']     ?? '';
            $adminAccessKey = $settings['ceph_access_key'] ?? '';
            $adminSecretKey = $settings['ceph_secret_key'] ?? '';

            if ($adminAccessKey !== '' && $adminSecretKey !== '') {
                try {
                    AdminOps::removeKey($s3Endpoint, $adminAccessKey, $adminSecretKey, $tempAccessKey, $tempKeyCephUid, null);
                } catch (\Throwable $e) {
                    error_log("cloudnas_unmount: temp key revoke warning: " . $e->getMessage());
                }
            }
        }
    }

    // Queue unmount command for agent
    $commandPayload = [
        'run_id' => null,
        'type' => 'nas_unmount',
        'payload_json' => json_encode([
            'mount_id' => $mountId,
            'drive_letter' => $mount->drive_letter,
        ]),
        'status' => 'pending',
        'created_at' => Capsule::raw('NOW()'),
    ];
    if (Capsule::schema()->hasColumn('s3_cloudbackup_run_commands', 'agent_uuid')) {
        $commandPayload['agent_uuid'] = $agentUuid;
    } elseif (Capsule::schema()->hasColumn('s3_cloudbackup_run_commands', 'agent_id')) {
        $commandPayload['agent_id'] = (int) $mount->agent_id;
    }
    $commandId = Capsule::table('s3_cloudbackup_run_commands')->insertGetId($commandPayload);

    // Update mount status
    $updateData = [
        'status' => 'unmounting',
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    if ($hasTempKey) {
        $updateData['temp_access_key'] = null;
        if (Capsule::schema()->hasColumn('s3_cloudnas_mounts', 'temp_key_ceph_uid')) {
            $updateData['temp_key_ceph_uid'] = null;
        }
    }
    Capsule::table('s3_cloudnas_mounts')->where('id', $mountId)->update($updateData);

    (new JsonResponse([
        'status' => 'success',
        'message' => 'Unmount command queued',
        'command_id' => $commandId,
    ], 200))->send();

} catch (Exception $e) {
    error_log("cloudnas_unmount error: " . $e->getMessage());
    (new JsonResponse(['status' => 'error', 'message' => 'Failed to send unmount command'], 200))->send();
}
exit;

