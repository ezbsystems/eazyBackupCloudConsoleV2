<?php
/**
 * Cloud NAS - Mount Drive
 * Provisions temporary S3 credentials, queues the nas_mount agent command,
 * and updates the mount status so the dashboard can track progress.
 *
 * Uses AdminOps temporary keys so the mount works regardless of whether the
 * customer has stored access keys (Option B / key-less provisioning).
 * The temp key is recorded on the mount row and revoked on unmount.
 */

require_once __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Client\HelperController;
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

/**
 * Helper: set mount to error state in DB so the UI reflects it.
 */
function failMount(int $mountId, string $errorMessage): void
{
    try {
        Capsule::table('s3_cloudnas_mounts')
            ->where('id', $mountId)
            ->update([
                'status'     => 'error',
                'error'      => $errorMessage,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    } catch (\Throwable $ignored) {}
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

    if ($mount->status === 'mounted') {
        (new JsonResponse(['status' => 'error', 'message' => 'Drive is already mounted'], 200))->send();
        exit;
    }

    $agent = Capsule::table('s3_cloudbackup_agents')
        ->where('id', (int) $mount->agent_id)
        ->where('client_id', $clientId)
        ->first(['agent_uuid']);
    if (!$agent || trim((string) ($agent->agent_uuid ?? '')) === '') {
        failMount($mountId, 'Agent not found');
        (new JsonResponse(['status' => 'error', 'message' => 'Agent not found for mount'], 200))->send();
        exit;
    }
    $agentUuid = trim((string) $agent->agent_uuid);

    // Resolve the storage user that owns the bucket
    $packageId = ProductConfig::$E3_PRODUCT_ID;
    $product = DBController::getProduct($clientId, $packageId);
    if (is_null($product) || is_null($product->username)) {
        failMount($mountId, 'No storage account');
        (new JsonResponse(['status' => 'error', 'message' => 'No storage account found'], 200))->send();
        exit;
    }

    $user = DBController::getUser($product->username);
    if (is_null($user)) {
        failMount($mountId, 'Storage user not found');
        (new JsonResponse(['status' => 'error', 'message' => 'User not found'], 200))->send();
        exit;
    }

    $tenants = DBController::getResult('s3_users', [
        ['parent_id', '=', $user->id]
    ], ['id'])->pluck('id')->toArray();
    $userIds = array_merge([$user->id], $tenants);

    $bucket = Capsule::table('s3_buckets')
        ->where('name', $mount->bucket_name)
        ->whereIn('user_id', $userIds)
        ->where('is_active', '1')
        ->first();

    if (!$bucket) {
        failMount($mountId, 'Bucket not found or access denied');
        (new JsonResponse(['status' => 'error', 'message' => 'Bucket not found'], 200))->send();
        exit;
    }

    // ---------------------------------------------------------------
    // Resolve S3 credentials via AdminOps temporary key.
    // This avoids reliance on stored/encrypted customer keys which may
    // not exist under Option B provisioning.
    // ---------------------------------------------------------------
    $settings = Capsule::table('tbladdonmodules')
        ->where('module', 'cloudstorage')
        ->whereIn('setting', ['s3_endpoint', 'cloudbackup_agent_s3_endpoint', 'cloudbackup_agent_s3_region', 'ceph_access_key', 'ceph_secret_key', 'encryption_key'])
        ->pluck('value', 'setting');

    // s3_endpoint is the internal Ceph RGW address (for admin ops).
    // cloudbackup_agent_s3_endpoint is the public URL agents should use.
    $s3Endpoint     = $settings['s3_endpoint']     ?? 'https://s3.eazybackup.ca';
    $agentEndpoint  = trim($settings['cloudbackup_agent_s3_endpoint'] ?? '');
    if ($agentEndpoint === '') {
        $agentEndpoint = $s3Endpoint;
    }
    $agentRegion    = trim($settings['cloudbackup_agent_s3_region'] ?? '') ?: 'us-east-1';
    $adminAccessKey = $settings['ceph_access_key'] ?? '';
    $adminSecretKey = $settings['ceph_secret_key'] ?? '';
    $encryptionKey  = $settings['encryption_key']  ?? '';

    // Determine the Ceph UID for the bucket owner
    $ownerRow   = Capsule::table('s3_users')->where('id', $bucket->user_id)->first();
    $cephUid    = $ownerRow ? HelperController::resolveCephQualifiedUid($ownerRow) : '';

    if ($cephUid === '') {
        failMount($mountId, 'Cannot resolve storage identity for bucket owner');
        (new JsonResponse(['status' => 'error', 'message' => 'Cannot resolve storage identity for bucket owner'], 200))->send();
        exit;
    }

    // Revoke any previously-issued temp key for this mount (defensive cleanup)
    if (Capsule::schema()->hasColumn('s3_cloudnas_mounts', 'temp_access_key')) {
        $prevKey = $mount->temp_access_key ?? null;
        if ($prevKey && $prevKey !== '') {
            try {
                AdminOps::removeKey($s3Endpoint, $adminAccessKey, $adminSecretKey, $prevKey, $cephUid, null);
            } catch (\Throwable $ignored) {}
            Capsule::table('s3_cloudnas_mounts')->where('id', $mountId)->update(['temp_access_key' => null]);
        }
    }

    // Create a temporary S3 key for the bucket owner via AdminOps
    $accessKey = '';
    $secretKey = '';

    if ($adminAccessKey !== '' && $adminSecretKey !== '') {
        $tmp = AdminOps::createTempKey($s3Endpoint, $adminAccessKey, $adminSecretKey, $cephUid, null);
        if (is_array($tmp) && ($tmp['status'] ?? '') === 'success') {
            $accessKey = (string) ($tmp['access_key'] ?? '');
            $secretKey = (string) ($tmp['secret_key'] ?? '');
        }
    }

    if ($accessKey === '' || $secretKey === '') {
        failMount($mountId, 'Unable to provision S3 credentials for mount');
        (new JsonResponse(['status' => 'error', 'message' => 'Unable to provision S3 credentials for mount. Check admin S3 settings.'], 200))->send();
        exit;
    }

    // Persist the temp access key ID so it can be revoked on unmount.
    $mountUpdate = [
        'status'     => 'mounting',
        'error'      => null,
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    if (Capsule::schema()->hasColumn('s3_cloudnas_mounts', 'temp_access_key')) {
        $mountUpdate['temp_access_key'] = $accessKey;
    }
    if (Capsule::schema()->hasColumn('s3_cloudnas_mounts', 'temp_key_ceph_uid')) {
        $mountUpdate['temp_key_ceph_uid'] = $cephUid;
    }
    Capsule::table('s3_cloudnas_mounts')->where('id', $mountId)->update($mountUpdate);

    $commandPayload = [
        'run_id' => null,
        'type' => 'nas_mount',
        'payload_json' => json_encode([
            'mount_id' => $mountId,
            'bucket' => (string) $mount->bucket_name,
            'prefix' => (string) ($mount->prefix ?? ''),
            'drive_letter' => (string) $mount->drive_letter,
            'read_only' => !empty($mount->read_only),
            'cache_mode' => (string) ($mount->cache_mode ?? 'writes'),
            'persistent' => !empty($mount->persistent),
            'endpoint' => $agentEndpoint,
            'access_key' => $accessKey,
            'secret_key' => $secretKey,
            'region' => $agentRegion,
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

    (new JsonResponse([
        'status' => 'success',
        'message' => 'Mount command queued. Drive mounting in progress.',
        'mount_id' => $mountId,
        'agent_uuid' => $agentUuid,
        'command_id' => $commandId,
    ], 200))->send();

} catch (Exception $e) {
    error_log("cloudnas_mount error: " . $e->getMessage());
    failMount($mountId, 'Internal error: ' . $e->getMessage());
    (new JsonResponse(['status' => 'error', 'message' => 'Failed to send mount command'], 200))->send();
}
exit;

