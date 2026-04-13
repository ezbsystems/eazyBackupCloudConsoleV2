<?php
/**
 * Cloud NAS - Pending / Prepared Mounts for Agent
 *
 * Returns Cloud NAS mounts that should have their WebDAV servers prepared by
 * the local agent. Credentials are provisioned on demand via AdminOps temp
 * keys so the agent receives a complete mount payload without requiring any
 * client-side secret storage in the database.
 */

require_once __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\Module\Addon\CloudStorage\Admin\AdminOps;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;
use WHMCS\Module\Addon\CloudStorage\Client\HelperController;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function respondPendingMounts(array $data, int $httpCode = 200): void
{
    (new JsonResponse($data, $httpCode))->send();
    exit;
}

function authenticatePendingMountsAgent(): object
{
    $agentUuid = $_SERVER['HTTP_X_AGENT_UUID'] ?? ($_POST['agent_uuid'] ?? null);
    $agentToken = $_SERVER['HTTP_X_AGENT_TOKEN'] ?? ($_POST['agent_token'] ?? null);
    if (!$agentUuid || !$agentToken) {
        respondPendingMounts(['status' => 'fail', 'message' => 'Missing agent headers'], 401);
    }

    $agent = Capsule::table('s3_cloudbackup_agents')
        ->where('agent_uuid', $agentUuid)
        ->first();

    if (!$agent || $agent->status !== 'active' || $agent->agent_token !== $agentToken) {
        respondPendingMounts(['status' => 'fail', 'message' => 'Unauthorized'], 401);
    }

    Capsule::table('s3_cloudbackup_agents')
        ->where('agent_uuid', $agentUuid)
        ->update(['last_seen_at' => Capsule::raw('NOW()')]);

    return $agent;
}

function markPendingMountError(int $mountId, string $message): void
{
    try {
        Capsule::table('s3_cloudnas_mounts')
            ->where('id', $mountId)
            ->update([
                'status' => 'error',
                'error' => $message,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    } catch (\Throwable $ignored) {
    }
}

$agent = authenticatePendingMountsAgent();
$inputRaw = file_get_contents('php://input');
$input = $inputRaw ? json_decode($inputRaw, true) : [];
$activeMountIds = [];
if (is_array($input) && !empty($input['active_mount_ids']) && is_array($input['active_mount_ids'])) {
    foreach ($input['active_mount_ids'] as $mountId) {
        $mountId = (int) $mountId;
        if ($mountId > 0) {
            $activeMountIds[$mountId] = true;
        }
    }
}

try {
    if (!Capsule::schema()->hasTable('s3_cloudnas_mounts')) {
        respondPendingMounts(['status' => 'success', 'mounts' => []]);
    }

    $mountRows = Capsule::table('s3_cloudnas_mounts')
        ->where('agent_id', (int) $agent->id)
        ->whereIn('status', ['pending', 'mounted', 'mounting'])
        ->orderBy('id', 'asc')
        ->get();

    if ($mountRows->isEmpty()) {
        respondPendingMounts(['status' => 'success', 'mounts' => []]);
    }

    $settings = Capsule::table('tbladdonmodules')
        ->where('module', 'cloudstorage')
        ->whereIn('setting', [
            's3_endpoint',
            'cloudbackup_agent_s3_endpoint',
            'cloudbackup_agent_s3_region',
            'ceph_access_key',
            'ceph_secret_key',
        ])
        ->pluck('value', 'setting');

    $s3Endpoint = trim((string) ($settings['s3_endpoint'] ?? 'https://s3.eazybackup.ca'));
    $agentEndpoint = trim((string) ($settings['cloudbackup_agent_s3_endpoint'] ?? ''));
    if ($agentEndpoint === '') {
        $agentEndpoint = $s3Endpoint;
    }
    $agentRegion = trim((string) ($settings['cloudbackup_agent_s3_region'] ?? ''));
    if ($agentRegion === '') {
        $agentRegion = 'us-east-1';
    }
    $adminAccessKey = trim((string) ($settings['ceph_access_key'] ?? ''));
    $adminSecretKey = trim((string) ($settings['ceph_secret_key'] ?? ''));

    if ($adminAccessKey === '' || $adminSecretKey === '') {
        respondPendingMounts(['status' => 'fail', 'message' => 'Missing admin S3 settings'], 500);
    }

    $packageId = ProductConfig::$E3_PRODUCT_ID;
    $product = DBController::getProduct((int) $agent->client_id, $packageId);
    if (is_null($product) || is_null($product->username)) {
        respondPendingMounts(['status' => 'success', 'mounts' => []]);
    }

    $user = DBController::getUser($product->username);
    if (is_null($user)) {
        respondPendingMounts(['status' => 'success', 'mounts' => []]);
    }

    $tenants = DBController::getResult('s3_users', [
        ['parent_id', '=', $user->id]
    ], ['id'])->pluck('id')->toArray();
    $userIds = array_merge([$user->id], $tenants);

    $payloads = [];
    foreach ($mountRows as $mount) {
        $bucket = Capsule::table('s3_buckets')
            ->where('name', $mount->bucket_name)
            ->whereIn('user_id', $userIds)
            ->where('is_active', '1')
            ->first();

        if (!$bucket) {
            markPendingMountError((int) $mount->id, 'Bucket not found or access denied');
            continue;
        }

        $ownerRow = Capsule::table('s3_users')->where('id', $bucket->user_id)->first();
        $cephUid = $ownerRow ? HelperController::resolveCephQualifiedUid($ownerRow) : '';
        if ($cephUid === '') {
            markPendingMountError((int) $mount->id, 'Cannot resolve storage identity for bucket owner');
            continue;
        }

        $accessKey = '';
        $secretKey = '';
        if (!isset($activeMountIds[(int) $mount->id])) {
            if (Capsule::schema()->hasColumn('s3_cloudnas_mounts', 'temp_access_key')) {
                $prevKey = $mount->temp_access_key ?? null;
                if ($prevKey && $prevKey !== '') {
                    try {
                        AdminOps::removeKey($s3Endpoint, $adminAccessKey, $adminSecretKey, $prevKey, $cephUid, null);
                    } catch (\Throwable $ignored) {
                    }
                }
            }

            $tmp = AdminOps::createTempKey($s3Endpoint, $adminAccessKey, $adminSecretKey, $cephUid, null);
            $accessKey = is_array($tmp) ? trim((string) ($tmp['access_key'] ?? '')) : '';
            $secretKey = is_array($tmp) ? trim((string) ($tmp['secret_key'] ?? '')) : '';
            if ($accessKey === '' || $secretKey === '') {
                markPendingMountError((int) $mount->id, 'Unable to provision S3 credentials for mount');
                continue;
            }
        }

        $updateData = [
            'error' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($accessKey !== '' && Capsule::schema()->hasColumn('s3_cloudnas_mounts', 'temp_access_key')) {
            $updateData['temp_access_key'] = $accessKey;
        }
        if ($accessKey !== '' && Capsule::schema()->hasColumn('s3_cloudnas_mounts', 'temp_key_ceph_uid')) {
            $updateData['temp_key_ceph_uid'] = $cephUid;
        }
        Capsule::table('s3_cloudnas_mounts')->where('id', (int) $mount->id)->update($updateData);

        $payloads[] = [
            'mount_id' => (int) $mount->id,
            'bucket' => (string) $mount->bucket_name,
            'bucket_name' => (string) $mount->bucket_name,
            'prefix' => (string) ($mount->prefix ?? ''),
            'drive_letter' => (string) $mount->drive_letter,
            'read_only' => (bool) $mount->read_only,
            'cache_mode' => (string) ($mount->cache_mode ?? 'writes'),
            'persistent' => (bool) ($mount->persistent ?? true),
            'status' => (string) ($mount->status ?? 'pending'),
            'endpoint' => $agentEndpoint,
            'access_key' => $accessKey,
            'secret_key' => $secretKey,
            'region' => $agentRegion,
        ];
    }

    respondPendingMounts([
        'status' => 'success',
        'mounts' => $payloads,
    ]);
} catch (\Throwable $e) {
    logModuleCall('cloudstorage', 'cloudnas_pending_mounts', ['agent_uuid' => $agent->agent_uuid ?? ''], $e->getMessage(), $e->getTraceAsString());
    respondPendingMounts(['status' => 'fail', 'message' => 'Server error'], 500);
}
