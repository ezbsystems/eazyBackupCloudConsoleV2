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

            // Name resolution strategy:
            //   - EXISTING owner with a legacy e3cloudbackupowner<clientId> uid -> keep
            //     it (rebranding would orphan their existing bucket).
            //   - NEW owner -> mint an opaque token and use e3cbown-<token>.
            //   - EXISTING owner that has already been migrated (has external_token) ->
            //     reuse e3cbown-<token>.
            // The opaque form removes the clientId leak that allowed external
            // observers to estimate signup volume from bucket / uid names.
            $existingCephUid = $existing ? trim((string) ($existing->ceph_uid ?? '')) : '';
            $existingToken   = $existing ? trim((string) ($existing->external_token ?? '')) : '';
            $hasTokenColumn  = Capsule::schema()->hasColumn('s3_users', 'external_token');

            if ($existingCephUid !== '' && stripos($existingCephUid, 'e3cloudbackupowner') === 0) {
                $baseUid = $existingCephUid; // legacy owner - keep
            } elseif ($existingCephUid !== '') {
                $baseUid = $existingCephUid; // pre-existing opaque owner - keep
            } else {
                $token = $existingToken !== '' ? $existingToken : self::generateOpaqueToken();
                $baseUid = 'e3cbown-' . $token;
            }
            $baseUid = preg_replace('/[^a-z0-9-]+/', '', strtolower($baseUid));
            if ($baseUid === '' || $baseUid === '-') {
                // Fallback: never let baseUid be empty - that produced cross-customer
                // collisions historically. Mint a fresh opaque uid.
                $baseUid = 'e3cbown-' . self::generateOpaqueToken();
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
            // Persist the opaque token on new rows (only when the column
            // exists, the uid is the new opaque form, and we have a token).
            if ($hasTokenColumn && strpos($baseUid, 'e3cbown-') === 0) {
                $token = substr($baseUid, strlen('e3cbown-'));
                if ($token !== '') {
                    $payload['external_token'] = $token;
                }
            }
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
            // Bucket-name strategy:
            //   - Owners with an opaque token (new flow) -> "e3cb-<token>".
            //     16 hex chars of entropy, zero clientId leak.
            //   - Legacy owners (created before the opaque-token migration)
            //     keep their existing "e3cloudbackup-<clientId>" bucket so the
            //     rename does not orphan their already-uploaded data.
            $ownerToken = self::resolveOrAssignOwnerToken($owner);
            if ($ownerToken !== '') {
                $bucketName = 'e3cb-' . $ownerToken;
            } else {
                $bucketName = 'e3cloudbackup-' . $clientId;
            }
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
            $tenantTable = MspController::getTenantTableName();
            $tenantQuery = Capsule::table($tenantTable)->where('id', $tenantId);
            if ($tenantTable === 'eb_tenants') {
                $mspId = MspController::getMspIdForClient($clientId);
                if ($mspId === null) {
                    return ['status' => 'fail', 'message' => 'MSP account not found.'];
                }
                $tenantQuery->where('msp_id', (int)$mspId);
            } else {
                $tenantQuery->where('client_id', $clientId);
            }
            $tenant = $tenantQuery->first();
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
                // New tenants: opaque token suffix so the bucket name does not
                // disclose client_id / tenant_id (and therefore signup rate).
                $ownerToken = self::resolveOrAssignOwnerToken($owner);
                if ($ownerToken !== '') {
                    $baseName = 'e3cb-t-' . $ownerToken . '-' . self::generateOpaqueToken();
                } else {
                    // Owner predates the token migration - fall back to the
                    // legacy id-based name to avoid breaking existing data.
                    $slug = preg_replace('/[^a-z0-9-]+/', '-', strtolower((string) ($tenant->slug ?? 'tenant')));
                    $slug = trim((string) $slug, '-');
                    if ($slug === '') {
                        $slug = 'tenant';
                    }
                    $baseName = 'e3cb-' . $clientId . '-' . $tenantId . '-' . $slug;
                }
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

            if (Capsule::schema()->hasColumn($tenantTable, 'bucket_name')) {
                $tenantUpdates = ['bucket_name' => $bucketName, 'updated_at' => Capsule::raw('NOW()')];
                Capsule::table($tenantTable)->where('id', $tenantId)->update($tenantUpdates);
            }

            return ['status' => 'success', 'bucket' => $bucket, 'owner_user' => $owner];
        } catch (\Throwable $e) {
            logModuleCall(self::$module, __FUNCTION__, ['client_id' => $clientId, 'tenant_id' => $tenantId], $e->getMessage());
            return ['status' => 'fail', 'message' => 'Failed to ensure tenant bucket.'];
        }
    }

    public static function ensureAgentDestination($agentIdentity): array
    {
        try {
            $agentLookup = trim((string) $agentIdentity);
            $agentQuery = Capsule::table('s3_cloudbackup_agents');
            if ($agentLookup !== '' && ctype_digit($agentLookup)) {
                $agentQuery->where('id', (int) $agentLookup);
            } else {
                $agentQuery->where('agent_uuid', $agentLookup);
            }
            $agent = $agentQuery->first();
            if (!$agent) {
                return ['status' => 'fail', 'message' => 'Agent not found.'];
            }
            $agentUuid = trim((string) ($agent->agent_uuid ?? ''));
            if ($agentUuid === '') {
                return ['status' => 'fail', 'message' => 'Agent UUID is missing.'];
            }

            $clientId = (int) $agent->client_id;
            $tenantId = !empty($agent->tenant_id) ? (int) $agent->tenant_id : null;
            $schema = Capsule::schema();
            $hasAgentUuidDest = $schema->hasColumn('s3_cloudbackup_agent_destinations', 'agent_uuid');
            $hasAgentIdDest = $schema->hasColumn('s3_cloudbackup_agent_destinations', 'agent_id');

            $existingQuery = Capsule::table('s3_cloudbackup_agent_destinations');
            if ($hasAgentUuidDest) {
                $existingQuery->where('agent_uuid', $agentUuid);
            } elseif ($hasAgentIdDest) {
                $existingQuery->where('agent_id', (int) $agent->id);
            } else {
                return ['status' => 'fail', 'message' => 'Agent destination schema does not have agent identity columns.'];
            }
            $existing = $existingQuery->first();
            if ($existing && (int) ($existing->is_locked ?? 1) === 1) {
                return ['status' => 'success', 'destination' => $existing];
            }

            $bucketRes = $tenantId
                ? self::ensureTenantBucket($clientId, $tenantId)
                : self::ensureDirectBucket($clientId);
            if (($bucketRes['status'] ?? '') !== 'success') {
                return $bucketRes;
            }
            $bucket = $bucketRes['bucket'];

            $prefix = self::buildAgentPrefix($agent);
            $prefix = self::ensureUniquePrefix((int) $bucket->id, $prefix, $agentUuid, (int) $agent->id);

            $payload = [
                'client_id' => $clientId,
                'tenant_id' => $tenantId,
                's3_user_id' => (int) $bucket->user_id,
                'dest_bucket_id' => (int) $bucket->id,
                'root_prefix' => $prefix,
                'is_locked' => 1,
                'updated_at' => Capsule::raw('NOW()'),
            ];
            if ($hasAgentUuidDest) {
                $payload['agent_uuid'] = $agentUuid;
            }
            if ($hasAgentIdDest) {
                $payload['agent_id'] = (int) $agent->id;
            }

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
            logModuleCall(self::$module, __FUNCTION__, ['agent_identity' => $agentIdentity], $e->getMessage());
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

            $encryptionKey = self::getModuleEncryptionKey();

            $existing = Capsule::table('s3_user_access_keys')
                ->where('user_id', $ownerId)
                ->orderByDesc('id')
                ->first(['id', 'access_key', 'secret_key']);

            // Self-heal: even when a DB row exists, verify the access key is
            // actually present on RGW. Drift between the DB and RGW (e.g. from
            // a partial rotation, manual `radosgw-admin key rm`, or an aborted
            // bucket-create flow) silently produces "Access Denied" errors on
            // every agent backup until rotated. Detect + repair here.
            if ($existing && !empty($existing->access_key) && !empty($existing->secret_key)) {
                $drift = self::detectOwnerKeyDrift($owner, $existing, $encryptionKey);
                if ($drift === 'in_sync') {
                    return ['status' => 'success', 'message' => 'Backup owner access key already present.'];
                }
                logModuleCall(self::$module, __FUNCTION__ . '_drift_detected', [
                    'owner_id' => $ownerId,
                    'username' => (string) ($owner->username ?? ''),
                    'drift'    => $drift,
                ], 'Stored backup-owner access key is not present on RGW; rotating to recover.');
                // Fall through to rotation.
            }

            $controller = self::makeBucketController();
            if (($controller['status'] ?? 'fail') !== 'success') {
                return $controller;
            }

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

    /**
     * Check whether the persisted backup-owner access key string is actually
     * present on the RGW user record. Returns:
     *   'in_sync'    - DB access key is one of the RGW user's keys.
     *   'drifted'    - DB has a key but RGW does not list it (orphan).
     *   'no_user'    - The RGW user does not exist (caller should rotate / recreate).
     *   'unknown'    - The probe failed for an environmental reason; treat as
     *                  in_sync to avoid spurious rotations on transient errors.
     */
    private static function detectOwnerKeyDrift(object $owner, object $existingKeyRow, string $encryptionKey): string
    {
        try {
            $cfg = self::getAdminSettings();
            if (($cfg['ok'] ?? false) !== true) {
                return 'unknown';
            }
            if ($encryptionKey === '') {
                return 'unknown';
            }
            $decrypted = HelperController::decryptKey((string) $existingKeyRow->access_key, $encryptionKey);
            if (!is_string($decrypted) || $decrypted === '') {
                // Cannot decrypt - cannot reason about drift; let normal flow handle later.
                return 'unknown';
            }

            $ownerUid = trim((string) ($owner->ceph_uid ?? ''));
            if ($ownerUid === '') {
                $ownerUid = trim((string) ($owner->username ?? ''));
            }
            if ($ownerUid === '') {
                return 'unknown';
            }
            $tenantId = !empty($owner->tenant_id) ? (string) $owner->tenant_id : null;

            $info = AdminOps::getUserInfo($cfg['endpoint'], $cfg['access_key'], $cfg['secret_key'], $ownerUid, $tenantId);
            if (!is_array($info)) {
                return 'unknown';
            }
            if (($info['status'] ?? '') !== 'success') {
                // Treat NoSuchUser as drift; everything else as unknown so a
                // transient RGW blip does not trigger surprise key rotations.
                $msg = strtolower((string) ($info['message'] ?? ''));
                if (strpos($msg, 'nosuchuser') !== false || strpos($msg, 'no such user') !== false) {
                    return 'no_user';
                }
                return 'unknown';
            }
            $keys = $info['data']['keys'] ?? [];
            if (!is_array($keys)) {
                return 'unknown';
            }
            foreach ($keys as $k) {
                if (is_array($k) && !empty($k['access_key']) && (string) $k['access_key'] === $decrypted) {
                    return 'in_sync';
                }
            }
            return 'drifted';
        } catch (\Throwable $e) {
            logModuleCall(self::$module, __FUNCTION__, [
                'owner_id' => (int) ($owner->id ?? 0),
                'username' => (string) ($owner->username ?? ''),
            ], $e->getMessage());
            return 'unknown';
        }
    }

    /**
     * Generate an opaque, lowercase-hex token used as the storage-naming
     * suffix for cloudbackup-owner user uids and direct buckets. 16 hex chars
     * = 64 bits of entropy; opaque enough that customer enumeration / signup
     * counting cannot be inferred from the names.
     */
    public static function generateOpaqueToken(): string
    {
        try {
            return bin2hex(random_bytes(8));
        } catch (\Throwable $e) {
            return substr(hash('sha256', uniqid('', true) . microtime(true)), 0, 16);
        }
    }

    /**
     * Resolve (or generate + persist) the opaque storage token attached to a
     * given cloudbackup_owner s3_users row. Existing owners that were created
     * before this column existed keep their legacy names; for them we return
     * an empty string and callers fall back to legacy naming.
     */
    private static function resolveOrAssignOwnerToken(object $owner): string
    {
        try {
            if (!Capsule::schema()->hasColumn('s3_users', 'external_token')) {
                return '';
            }
            $current = trim((string) ($owner->external_token ?? ''));
            if ($current !== '') {
                return $current;
            }

            // Legacy owners (those whose ceph_uid already matches the old
            // e3cloudbackupowner<clientId> pattern) keep their existing name
            // for back-compat - rebranding them would orphan their bucket.
            $cephUid = trim((string) ($owner->ceph_uid ?? ''));
            if ($cephUid !== '' && stripos($cephUid, 'e3cloudbackupowner') === 0) {
                return '';
            }

            // Brand new owner row - mint a token and persist it.
            for ($i = 0; $i < 5; $i++) {
                $candidate = self::generateOpaqueToken();
                $exists = Capsule::table('s3_users')
                    ->where('external_token', $candidate)
                    ->exists();
                if (!$exists) {
                    Capsule::table('s3_users')
                        ->where('id', (int) $owner->id)
                        ->update(['external_token' => $candidate]);
                    return $candidate;
                }
            }
        } catch (\Throwable $e) {
            logModuleCall(self::$module, __FUNCTION__, [
                'owner_id' => (int) ($owner->id ?? 0),
            ], $e->getMessage());
        }
        return '';
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
            $agentUuid = trim((string) ($agent->agent_uuid ?? ''));
            if ($agentUuid !== '') {
                return 'agent-' . substr(str_replace('-', '', $agentUuid), 0, 12);
            }
            return 'agent-' . (int) $agent->id;
        }

        $sanitized = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $deviceId);
        $sanitized = trim((string) $sanitized, '-');
        if ($sanitized === '') {
            $agentUuid = trim((string) ($agent->agent_uuid ?? ''));
            if ($agentUuid !== '') {
                $sanitized = 'agent-' . substr(str_replace('-', '', $agentUuid), 0, 12);
            } else {
                $sanitized = 'agent-' . (int) $agent->id;
            }
        }
        return $sanitized;
    }

    private static function ensureUniquePrefix(int $bucketId, string $prefix, string $agentUuid, ?int $agentId = null): string
    {
        $prefix = trim($prefix);
        if ($prefix === '') {
            if ($agentUuid !== '') {
                $prefix = 'agent-' . substr(str_replace('-', '', $agentUuid), 0, 12);
            } else {
                $prefix = 'agent-' . ((int) $agentId);
            }
        }
        $base = $prefix;
        $suffixKey = $agentUuid !== '' ? substr(str_replace('-', '', $agentUuid), 0, 8) : (string) ((int) $agentId);
        $attempt = 0;
        $schema = Capsule::schema();
        $hasAgentUuidDest = $schema->hasColumn('s3_cloudbackup_agent_destinations', 'agent_uuid');
        $hasAgentIdDest = $schema->hasColumn('s3_cloudbackup_agent_destinations', 'agent_id');
        while (true) {
            $query = Capsule::table('s3_cloudbackup_agent_destinations')
                ->where('dest_bucket_id', $bucketId)
                ->where('root_prefix', $prefix);
            if ($hasAgentUuidDest) {
                $query->where('agent_uuid', '!=', $agentUuid);
            } elseif ($hasAgentIdDest && $agentId !== null) {
                $query->where('agent_id', '!=', (int) $agentId);
            }
            $conflict = $query->exists();
            if (!$conflict) {
                return $prefix;
            }
            $attempt++;
            $prefix = $base . '-' . $suffixKey . '-' . $attempt;
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

