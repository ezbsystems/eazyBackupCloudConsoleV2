<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Admin\AdminOps;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;

class CloudBackupBootstrapService
{
    private static $module = 'cloudstorage';

    public static function ensureBackupOwnerUser(int $clientId): array
    {
        try {
            $rootUser = self::resolveClientRootStorageUser($clientId);
            $parentId = $rootUser ? (int) $rootUser->id : null;
            $tenantId = $rootUser && !empty($rootUser->tenant_id) ? (int) $rootUser->tenant_id : null;

            $existing = null;
            $q = Capsule::table('s3_users')
                ->where('system_key', 'cloudbackup_owner')
                ->where('is_system_managed', 1);
            if ($parentId !== null) {
                $q->where('parent_id', $parentId);
            } else {
                $q->whereNull('parent_id');
            }
            $existing = $q->orderBy('id', 'asc')->first();

            $baseUid = 'e3cloudbackupowner' . $clientId;
            $baseUid = preg_replace('/[^a-z0-9-]+/', '', strtolower($baseUid));
            if ($baseUid === '') {
                $baseUid = 'e3cloudbackupowner';
            }
            $rgwUid = $tenantId ? ($tenantId . '$' . $baseUid) : $baseUid;

            $adminCfg = self::getAdminSettings();
            if (($adminCfg['ok'] ?? false) !== true) {
                return ['status' => 'fail', 'message' => 'Cloud storage admin settings are not configured.'];
            }

            // Ensure RGW user exists.
            $info = AdminOps::getUserInfo(
                $adminCfg['endpoint'],
                $adminCfg['access_key'],
                $adminCfg['secret_key'],
                $baseUid,
                $tenantId
            );
            if (!is_array($info) || ($info['status'] ?? '') !== 'success') {
                $create = AdminOps::createUser(
                    $adminCfg['endpoint'],
                    $adminCfg['access_key'],
                    $adminCfg['secret_key'],
                    [
                        'uid' => $baseUid,
                        'name' => $rgwUid,
                        'email' => $rgwUid,
                        'tenant' => $tenantId,
                    ]
                );
                if (!is_array($create) || ($create['status'] ?? '') !== 'success') {
                    logModuleCall(self::$module, __FUNCTION__ . '_create_user_failed', [
                        'client_id' => $clientId,
                        'rgw_uid' => $rgwUid,
                        'tenant_id' => $tenantId,
                    ], $create);
                    return ['status' => 'fail', 'message' => 'Failed to ensure backup owner user in object storage.'];
                }
            }

            $payload = [
                'name' => 'Cloud Backup Owner',
                'username' => $rgwUid,
                'ceph_uid' => $baseUid,
                'tenant_id' => $tenantId,
                'parent_id' => $parentId,
                'is_active' => 1,
                'is_system_managed' => 1,
                'system_key' => 'cloudbackup_owner',
                'manage_locked' => 1,
                'deleted_at' => null,
            ];
            $payload = self::filterExistingColumns('s3_users', $payload);

            if ($existing) {
                Capsule::table('s3_users')
                    ->where('id', (int) $existing->id)
                    ->update($payload);
                $ownerId = (int) $existing->id;
            } else {
                if (!array_key_exists('created_at', $payload)) {
                    $payload['created_at'] = Capsule::raw('NOW()');
                }
                $ownerId = (int) Capsule::table('s3_users')->insertGetId($payload);
            }

            $owner = Capsule::table('s3_users')->where('id', $ownerId)->first();
            if (!$owner) {
                return ['status' => 'fail', 'message' => 'Failed to persist backup owner user.'];
            }

            $keyRes = self::ensureBackupOwnerAccessKey($owner);
            if (($keyRes['status'] ?? 'fail') !== 'success') {
                return ['status' => 'fail', 'message' => $keyRes['message'] ?? 'Failed to ensure backup owner access key.'];
            }

            return ['status' => 'success', 'owner_user' => $owner];
        } catch (\Throwable $e) {
            logModuleCall(self::$module, __FUNCTION__, ['client_id' => $clientId], $e->getMessage());
            return ['status' => 'fail', 'message' => 'Failed to ensure backup owner user.'];
        }
    }

    public static function ensureDirectBucket(int $clientId): array
    {
        try {
            $ownerRes = self::ensureBackupOwnerUser($clientId);
            if (($ownerRes['status'] ?? '') !== 'success') {
                return $ownerRes;
            }

            $owner = $ownerRes['owner_user'];
            // Keep deterministic per-client bucket naming to avoid global-name collisions.
            $bucketName = 'e3cloudbackup-' . $clientId;
            $bucketName = self::sanitizeBucketName($bucketName);

            $bucket = Capsule::table('s3_buckets')
                ->where('name', $bucketName)
                ->where('user_id', (int) $owner->id)
                ->where('is_active', 1)
                ->first();

            if (!$bucket) {
                $controller = self::makeBucketController();
                if (($controller['status'] ?? '') !== 'success') {
                    return $controller;
                }
                $create = $controller['controller']->createBucketAsAdmin($owner, $bucketName, true, false, 'GOVERNANCE', 1, false);
                if (!is_array($create) || ($create['status'] ?? '') !== 'success') {
                    return ['status' => 'fail', 'message' => $create['message'] ?? 'Failed to create direct bucket.'];
                }
                $bucket = Capsule::table('s3_buckets')
                    ->where('name', $bucketName)
                    ->where('user_id', (int) $owner->id)
                    ->where('is_active', 1)
                    ->first();
            }

            if (!$bucket) {
                return ['status' => 'fail', 'message' => 'Unable to resolve direct bucket after creation.'];
            }

            return ['status' => 'success', 'bucket' => $bucket, 'owner_user' => $owner];
        } catch (\Throwable $e) {
            logModuleCall(self::$module, __FUNCTION__, ['client_id' => $clientId], $e->getMessage());
            return ['status' => 'fail', 'message' => 'Failed to ensure direct bucket.'];
        }
    }

    public static function ensureTenantBucket(int $clientId, int $tenantId): array
    {
        try {
            $tenant = Capsule::table('s3_backup_tenants')
                ->where('id', $tenantId)
                ->where('client_id', $clientId)
                ->first();
            if (!$tenant) {
                return ['status' => 'fail', 'message' => 'Tenant not found.'];
            }

            $ownerRes = self::ensureBackupOwnerUser($clientId);
            if (($ownerRes['status'] ?? '') !== 'success') {
                return $ownerRes;
            }
            $owner = $ownerRes['owner_user'];

            $baseName = trim((string) ($tenant->bucket_name ?? ''));
            if ($baseName === '') {
                $slug = preg_replace('/[^a-z0-9-]+/', '-', strtolower((string) ($tenant->slug ?? 'tenant')));
                $slug = trim((string) $slug, '-');
                if ($slug === '') {
                    $slug = 'tenant';
                }
                $baseName = 'e3cb-' . $clientId . '-' . $tenantId . '-' . $slug;
            }
            $bucketName = self::sanitizeBucketName($baseName);

            $bucket = Capsule::table('s3_buckets')
                ->where('name', $bucketName)
                ->where('user_id', (int) $owner->id)
                ->where('is_active', 1)
                ->first();

            if (!$bucket) {
                $controller = self::makeBucketController();
                if (($controller['status'] ?? '') !== 'success') {
                    return $controller;
                }
                $create = $controller['controller']->createBucketAsAdmin($owner, $bucketName, true, false, 'GOVERNANCE', 1, false);
                if (!is_array($create) || ($create['status'] ?? '') !== 'success') {
                    return ['status' => 'fail', 'message' => $create['message'] ?? 'Failed to create tenant bucket.'];
                }
                $bucket = Capsule::table('s3_buckets')
                    ->where('name', $bucketName)
                    ->where('user_id', (int) $owner->id)
                    ->where('is_active', 1)
                    ->first();
            }

            if (!$bucket) {
                return ['status' => 'fail', 'message' => 'Unable to resolve tenant bucket after creation.'];
            }

            $tenantUpdates = ['bucket_name' => $bucketName, 'updated_at' => Capsule::raw('NOW()')];
            Capsule::table('s3_backup_tenants')->where('id', $tenantId)->update($tenantUpdates);

            return ['status' => 'success', 'bucket' => $bucket, 'owner_user' => $owner];
        } catch (\Throwable $e) {
            logModuleCall(self::$module, __FUNCTION__, ['client_id' => $clientId, 'tenant_id' => $tenantId], $e->getMessage());
            return ['status' => 'fail', 'message' => 'Failed to ensure tenant bucket.'];
        }
    }

    public static function ensureAgentDestination(int $agentId): array
    {
        try {
            $agent = Capsule::table('s3_cloudbackup_agents')->where('id', $agentId)->first();
            if (!$agent) {
                return ['status' => 'fail', 'message' => 'Agent not found.'];
            }

            $clientId = (int) $agent->client_id;
            $tenantId = !empty($agent->tenant_id) ? (int) $agent->tenant_id : null;

            $bucketRes = $tenantId
                ? self::ensureTenantBucket($clientId, $tenantId)
                : self::ensureDirectBucket($clientId);
            if (($bucketRes['status'] ?? '') !== 'success') {
                return $bucketRes;
            }
            $bucket = $bucketRes['bucket'];

            $existing = Capsule::table('s3_cloudbackup_agent_destinations')
                ->where('agent_id', $agentId)
                ->first();
            if ($existing && (int) ($existing->is_locked ?? 1) === 1) {
                return ['status' => 'success', 'destination' => $existing];
            }

            $prefix = self::buildAgentPrefix($agent);
            $prefix = self::ensureUniquePrefix((int) $bucket->id, $prefix, $agentId);

            $payload = [
                'agent_id' => $agentId,
                'client_id' => $clientId,
                'tenant_id' => $tenantId,
                's3_user_id' => (int) $bucket->user_id,
                'dest_bucket_id' => (int) $bucket->id,
                'root_prefix' => $prefix,
                'is_locked' => 1,
                'updated_at' => Capsule::raw('NOW()'),
            ];

            if ($existing) {
                Capsule::table('s3_cloudbackup_agent_destinations')
                    ->where('id', (int) $existing->id)
                    ->update($payload);
                $id = (int) $existing->id;
            } else {
                $payload['created_at'] = Capsule::raw('NOW()');
                $id = (int) Capsule::table('s3_cloudbackup_agent_destinations')->insertGetId($payload);
            }

            $destination = Capsule::table('s3_cloudbackup_agent_destinations')->where('id', $id)->first();
            if (!$destination) {
                return ['status' => 'fail', 'message' => 'Failed to persist agent destination.'];
            }

            return ['status' => 'success', 'destination' => $destination];
        } catch (\Throwable $e) {
            logModuleCall(self::$module, __FUNCTION__, ['agent_id' => $agentId], $e->getMessage());
            return ['status' => 'fail', 'message' => 'Failed to ensure agent destination.'];
        }
    }

    private static function resolveClientRootStorageUser(int $clientId): ?object
    {
        try {
            $product = DBController::getActiveProduct($clientId, ProductConfig::$E3_PRODUCT_ID);
            if (!$product) {
                $product = DBController::getProduct($clientId, ProductConfig::$E3_PRODUCT_ID);
            }
            if (!$product || empty($product->username)) {
                return null;
            }

            $root = DBController::getUser((string) $product->username, false);
            if ($root) {
                return $root;
            }
        } catch (\Throwable $e) {
            // no-op
        }
        return null;
    }

    private static function getAdminSettings(): array
    {
        try {
            $rows = Capsule::table('tbladdonmodules')
                ->where('module', 'cloudstorage')
                ->pluck('value', 'setting');

            $settings = [];
            foreach ($rows as $k => $v) {
                $settings[(string) $k] = (string) $v;
            }

            $endpoint = trim((string) ($settings['s3_endpoint'] ?? ''));
            $accessKey = trim((string) ($settings['ceph_access_key'] ?? ''));
            $secretKey = trim((string) ($settings['ceph_secret_key'] ?? ''));
            $region = trim((string) ($settings['s3_region'] ?? ''));
            if ($region === '') {
                $region = 'us-east-1';
            }

            if ($endpoint === '' || $accessKey === '' || $secretKey === '') {
                return ['ok' => false];
            }

            return [
                'ok' => true,
                'endpoint' => $endpoint,
                'access_key' => $accessKey,
                'secret_key' => $secretKey,
                'region' => $region,
                'admin_user' => trim((string) ($settings['ceph_admin_user'] ?? '')),
            ];
        } catch (\Throwable $e) {
            return ['ok' => false];
        }
    }

    private static function makeBucketController(): array
    {
        $cfg = self::getAdminSettings();
        if (($cfg['ok'] ?? false) !== true) {
            return ['status' => 'fail', 'message' => 'Bucket controller configuration is incomplete.'];
        }

        return [
            'status' => 'success',
            'controller' => new BucketController(
                $cfg['endpoint'],
                $cfg['admin_user'],
                $cfg['access_key'],
                $cfg['secret_key'],
                $cfg['region']
            ),
        ];
    }

    private static function ensureBackupOwnerAccessKey(object $owner): array
    {
        try {
            if (!Capsule::schema()->hasTable('s3_user_access_keys')) {
                return ['status' => 'success', 'message' => 'Access keys table not present; skipping owner key bootstrap.'];
            }

            $ownerId = (int) ($owner->id ?? 0);
            if ($ownerId <= 0) {
                return ['status' => 'fail', 'message' => 'Invalid backup owner user id.'];
            }

            $existing = Capsule::table('s3_user_access_keys')
                ->where('user_id', $ownerId)
                ->orderByDesc('id')
                ->first(['id', 'access_key', 'secret_key']);
            if ($existing && !empty($existing->access_key) && !empty($existing->secret_key)) {
                return ['status' => 'success', 'message' => 'Backup owner access key already present.'];
            }

            $controller = self::makeBucketController();
            if (($controller['status'] ?? 'fail') !== 'success') {
                return $controller;
            }

            $encryptionKey = self::getModuleEncryptionKey();
            if ($encryptionKey === '') {
                return ['status' => 'fail', 'message' => 'Module encryption key is not configured.'];
            }

            $update = $controller['controller']->updateUserAccessKey(
                (string) ($owner->username ?? ''),
                $ownerId,
                $encryptionKey
            );
            if (!is_array($update) || ($update['status'] ?? 'fail') !== 'success') {
                logModuleCall(self::$module, __FUNCTION__ . '_create_key_failed', [
                    'owner_id' => $ownerId,
                    'username' => (string) ($owner->username ?? ''),
                ], $update);
                return ['status' => 'fail', 'message' => $update['message'] ?? 'Failed to create backup owner access key.'];
            }

            $created = Capsule::table('s3_user_access_keys')
                ->where('user_id', $ownerId)
                ->orderByDesc('id')
                ->first(['id']);
            if (!$created) {
                return ['status' => 'fail', 'message' => 'Backup owner access key was not persisted.'];
            }

            logModuleCall(self::$module, __FUNCTION__, [
                'owner_id' => $ownerId,
                'username' => (string) ($owner->username ?? ''),
            ], ['status' => 'success', 'key_id' => (int) $created->id]);
            return ['status' => 'success'];
        } catch (\Throwable $e) {
            logModuleCall(self::$module, __FUNCTION__, ['owner_id' => (int) ($owner->id ?? 0)], $e->getMessage());
            return ['status' => 'fail', 'message' => 'Failed to ensure backup owner access key.'];
        }
    }

    private static function getModuleEncryptionKey(): string
    {
        try {
            $primary = trim((string) Capsule::table('tbladdonmodules')
                ->where('module', 'cloudstorage')
                ->where('setting', 'cloudbackup_encryption_key')
                ->value('value'));
            if ($primary !== '') {
                return $primary;
            }

            $fallback = trim((string) Capsule::table('tbladdonmodules')
                ->where('module', 'cloudstorage')
                ->where('setting', 'encryption_key')
                ->value('value'));
            return $fallback;
        } catch (\Throwable $e) {
            return '';
        }
    }

    private static function buildAgentPrefix(object $agent): string
    {
        $deviceId = trim((string) ($agent->device_id ?? ''));
        if ($deviceId === '') {
            return 'agent-' . (int) $agent->id;
        }

        $sanitized = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $deviceId);
        $sanitized = trim((string) $sanitized, '-');
        if ($sanitized === '') {
            $sanitized = 'agent-' . (int) $agent->id;
        }
        return $sanitized;
    }

    private static function ensureUniquePrefix(int $bucketId, string $prefix, int $agentId): string
    {
        $prefix = trim($prefix);
        if ($prefix === '') {
            $prefix = 'agent-' . $agentId;
        }
        $base = $prefix;
        $attempt = 0;
        while (true) {
            $conflict = Capsule::table('s3_cloudbackup_agent_destinations')
                ->where('dest_bucket_id', $bucketId)
                ->where('root_prefix', $prefix)
                ->where('agent_id', '!=', $agentId)
                ->exists();
            if (!$conflict) {
                return $prefix;
            }
            $attempt++;
            $prefix = $base . '-' . $agentId . '-' . $attempt;
        }
    }

    private static function sanitizeBucketName(string $bucketName): string
    {
        $bucketName = strtolower(trim($bucketName));
        $bucketName = preg_replace('/[^a-z0-9.-]+/', '-', $bucketName);
        $bucketName = trim((string) $bucketName, '-.');
        if (strlen($bucketName) < 3) {
            $bucketName = str_pad($bucketName, 3, '0');
        }
        if (strlen($bucketName) > 63) {
            $bucketName = substr($bucketName, 0, 63);
            $bucketName = rtrim($bucketName, '-.');
        }
        return $bucketName;
    }

    private static function filterExistingColumns(string $table, array $data): array
    {
        try {
            $cols = array_fill_keys(Capsule::schema()->getColumnListing($table), true);
            return array_filter(
                $data,
                function ($value, $key) use ($cols) {
                    return isset($cols[$key]);
                },
                ARRAY_FILTER_USE_BOTH
            );
        } catch (\Throwable $e) {
            return $data;
        }
    }
}

