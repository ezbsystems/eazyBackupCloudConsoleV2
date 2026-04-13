<?php
/**
 * Cloud NAS - Delete Mount Configuration
 * Removes a mount configuration (unmounts first if needed) and revokes temp S3 keys.
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

    // If mounted, send unmount command first
    if ($mount->status === 'mounted' || $mount->status === 'mounting') {
        $agent = Capsule::table('s3_cloudbackup_agents')
            ->where('id', (int) $mount->agent_id)
            ->where('client_id', $clientId)
            ->first(['agent_uuid']);
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
        if ($agent && trim((string) ($agent->agent_uuid ?? '')) !== '' && Capsule::schema()->hasColumn('s3_cloudbackup_run_commands', 'agent_uuid')) {
            $commandPayload['agent_uuid'] = trim((string) $agent->agent_uuid);
        } elseif (Capsule::schema()->hasColumn('s3_cloudbackup_run_commands', 'agent_id')) {
            $commandPayload['agent_id'] = (int) $mount->agent_id;
        }
        Capsule::table('s3_cloudbackup_run_commands')->insert($commandPayload);
    }

    // Revoke the temporary S3 key if one was issued for this mount
    if (Capsule::schema()->hasColumn('s3_cloudnas_mounts', 'temp_access_key')) {
        $tempAccessKey  = $mount->temp_access_key ?? null;
        $tempKeyCephUid = Capsule::schema()->hasColumn('s3_cloudnas_mounts', 'temp_key_ceph_uid')
            ? ($mount->temp_key_ceph_uid ?? '')
            : '';

        if ($tempAccessKey && $tempAccessKey !== '' && $tempKeyCephUid !== '') {
            $settings = Capsule::table('tbladdonmodules')
                ->where('module', 'cloudstorage')
                ->whereIn('setting', ['s3_endpoint', 'ceph_access_key', 'ceph_secret_key'])
                ->pluck('value', 'setting');

            $adminAccessKey = $settings['ceph_access_key'] ?? '';
            $adminSecretKey = $settings['ceph_secret_key'] ?? '';
            $s3Endpoint     = $settings['s3_endpoint']     ?? '';

            if ($adminAccessKey !== '' && $adminSecretKey !== '') {
                try {
                    AdminOps::removeKey($s3Endpoint, $adminAccessKey, $adminSecretKey, $tempAccessKey, $tempKeyCephUid, null);
                } catch (\Throwable $e) {
                    error_log("cloudnas_delete_mount: temp key revoke warning: " . $e->getMessage());
                }
            }
        }
    }

    // Delete the mount configuration
    Capsule::table('s3_cloudnas_mounts')
        ->where('id', $mountId)
        ->delete();

    (new JsonResponse([
        'status' => 'success',
        'message' => 'Mount configuration deleted',
    ], 200))->send();

} catch (Exception $e) {
    error_log("cloudnas_delete_mount error: " . $e->getMessage());
    (new JsonResponse(['status' => 'error', 'message' => 'Failed to delete mount configuration'], 200))->send();
}
exit;

