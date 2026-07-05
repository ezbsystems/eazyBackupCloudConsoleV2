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
require_once __DIR__ . '/../lib/Client/AgentAuth.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\AgentAuth;
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
    return AgentAuth::authenticate(fn(array $data, int $code) => respondPendingMounts($data, $code));
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
        $agentEndpoint = trim((string) ($settings['s3_endpoint'] ?? ''));
    }
    if ($agentEndpoint === '' || preg_match('#^https?://192\.168\.#i', $agentEndpoint)) {
        $agentEndpoint = 'https://s3.ca-central-1.eazybackup.com';
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

    $packageId = (int) ProductConfig::cloudStoragePid();
    $product = DBController::getProduct((int) $agent->client_id, $packageId);
    if (is_null($product) || empty($product->username)) {
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
        $mountId = (int) $mount->id;
        $isActive = isset($activeMountIds[$mountId]);
        $hasStoredKey = Capsule::schema()->hasColumn('s3_cloudnas_mounts', 'temp_access_key')
            && trim((string) ($mount->temp_access_key ?? '')) !== '';

        if ($isActive) {
            // Agent already has a live WebDAV instance; metadata only.
        } elseif ($hasStoredKey) {
            // Credentials were already issued (e.g. via cloudnas_mount command queue).
            // Do not revoke or rotate — the secret is not stored server-side.
            continue;
        } else {
            $tmp = AdminOps::createTempKey($s3Endpoint, $adminAccessKey, $adminSecretKey, $cephUid, null);
            $accessKey = is_array($tmp) ? trim((string) ($tmp['access_key'] ?? '')) : '';
            $secretKey = is_array($tmp) ? trim((string) ($tmp['secret_key'] ?? '')) : '';
            if ($accessKey === '' || $secretKey === '') {
                markPendingMountError($mountId, 'Unable to provision S3 credentials for mount');
                continue;
            }
        }

        $dbStatus = strtolower(trim((string) ($mount->status ?? 'pending')));
        // Agent prepare loop only auto-maps when status is "pending".
        $agentStatus = $dbStatus === 'mounting' ? 'pending' : $dbStatus;

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
        if (!empty($updateData)) {
            Capsule::table('s3_cloudnas_mounts')->where('id', $mountId)->update($updateData);
        }

        $payloads[] = [
            'mount_id' => $mountId,
            'bucket' => (string) $mount->bucket_name,
            'bucket_name' => (string) $mount->bucket_name,
            'prefix' => (string) ($mount->prefix ?? ''),
            'drive_letter' => (string) $mount->drive_letter,
            'read_only' => (bool) $mount->read_only,
            'cache_mode' => (string) ($mount->cache_mode ?? 'writes'),
            'persistent' => (bool) ($mount->persistent ?? true),
            'status' => $agentStatus,
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
