<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Admin\AdminOps;

/**
 * Global platform-owned RGW user for all MS365 backup buckets (isolated from customer storage billing).
 */
class Ms365PlatformStorageService
{
    private const MODULE = 'cloudstorage';
    private const SYSTEM_KEY = 'ms365_platform_owner';
    private const BASE_UID = 'e3ms365plat';

    /**
     * @return array{status: string, owner_user?: object, message?: string}
     */
    public static function ensurePlatformOwner(): array
    {
        try {
            $existing = Capsule::table('s3_users')
                ->where('system_key', self::SYSTEM_KEY)
                ->where('is_system_managed', 1)
                ->whereNull('parent_id')
                ->orderBy('id', 'asc')
                ->first();

            $tenantId = self::resolvePlatformTenantId();
            $baseUid = self::BASE_UID;
            if ($existing !== null) {
                $existingCeph = trim((string) ($existing->ceph_uid ?? ''));
                if ($existingCeph !== '') {
                    $baseUid = $existingCeph;
                }
            }

            $baseUid = preg_replace('/[^a-z0-9-]+/', '', strtolower($baseUid));
            if ($baseUid === '') {
                $baseUid = self::BASE_UID;
            }

            $rgwUid = $tenantId !== null ? ($tenantId . '$' . $baseUid) : $baseUid;
            $adminCfg = self::getAdminSettings();
            if (($adminCfg['ok'] ?? false) !== true) {
                return ['status' => 'fail', 'message' => 'Cloud storage admin settings are not configured.'];
            }

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
                    return ['status' => 'fail', 'message' => 'Failed to create MS365 platform storage user.'];
                }
            }

            AdminOps::setUserQuota($adminCfg['endpoint'], $adminCfg['access_key'], $adminCfg['secret_key'], [
                'uid' => $baseUid,
                'tenant' => $tenantId,
                'enabled' => true,
                'max_size_kb' => -1,
                'max_objects' => -1,
            ]);

            $payload = [
                'name' => 'MS365 Platform Storage',
                'username' => $rgwUid,
                'ceph_uid' => $baseUid,
                'tenant_id' => $tenantId,
                'parent_id' => null,
                'is_active' => 1,
                'is_system_managed' => 1,
                'system_key' => self::SYSTEM_KEY,
                'manage_locked' => 1,
                'deleted_at' => null,
            ];
            $payload = self::filterExistingColumns('s3_users', $payload);

            if ($existing) {
                Capsule::table('s3_users')->where('id', (int) $existing->id)->update($payload);
                $ownerId = (int) $existing->id;
            } else {
                if (!array_key_exists('created_at', $payload)) {
                    $payload['created_at'] = Capsule::raw('NOW()');
                }
                $ownerId = (int) Capsule::table('s3_users')->insertGetId($payload);
            }

            $owner = Capsule::table('s3_users')->where('id', $ownerId)->first();
            if (!$owner) {
                return ['status' => 'fail', 'message' => 'Failed to persist MS365 platform storage user.'];
            }

            $keyRes = self::ensureOwnerAccessKey($owner, $adminCfg);
            if (($keyRes['status'] ?? 'fail') !== 'success') {
                return ['status' => 'fail', 'message' => $keyRes['message'] ?? 'Failed to ensure platform storage access key.'];
            }

            return ['status' => 'success', 'owner_user' => $owner];
        } catch (\Throwable $e) {
            logModuleCall(self::MODULE, __FUNCTION__, [], $e->getMessage());

            return ['status' => 'fail', 'message' => 'Failed to ensure MS365 platform storage owner.'];
        }
    }

    public static function isPlatformOwnerRow(object $user): bool
    {
        return (string) ($user->system_key ?? '') === self::SYSTEM_KEY;
    }

    public static function isMs365BillingExemptBucketName(string $bucketName): bool
    {
        return str_starts_with(strtolower(trim($bucketName)), 'e3ms365-');
    }

  /** @return int|null */
    private static function resolvePlatformTenantId(): ?int
    {
        try {
            $raw = Capsule::table('tbladdonmodules')
                ->where('module', 'ms365backup')
                ->where('setting', 'ms365_platform_tenant_id')
                ->value('value');
            $tenantId = trim((string) $raw);
            if ($tenantId !== '' && ctype_digit($tenantId)) {
                return (int) $tenantId;
            }
        } catch (\Throwable $_) {
        }

        return null;
    }

    /** @return array{ok: bool, endpoint?: string, access_key?: string, secret_key?: string, region?: string} */
    private static function getAdminSettings(): array
    {
        $rows = Capsule::table('tbladdonmodules')
            ->where('module', 'cloudstorage')
            ->pluck('value', 'setting');
        $endpoint = trim((string) ($rows['s3_endpoint'] ?? ''));
        $accessKey = trim((string) ($rows['ceph_access_key'] ?? ''));
        $secretKey = trim((string) ($rows['ceph_secret_key'] ?? ''));
        $region = trim((string) ($rows['s3_region'] ?? ''));
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
        ];
    }

    /** @param array<string, mixed> $adminCfg */
    private static function ensureOwnerAccessKey(object $owner, array $adminCfg): array
    {
        $ownerId = (int) ($owner->id ?? 0);
        if ($ownerId <= 0) {
            return ['status' => 'fail', 'message' => 'Invalid platform owner.'];
        }

        $encryptionKey = self::moduleEncryptionKey();
        if ($encryptionKey === '') {
            return ['status' => 'fail', 'message' => 'Cloud storage encryption key is not configured.'];
        }

        if (!Capsule::schema()->hasTable('s3_user_access_keys')) {
            return ['status' => 'fail', 'message' => 'Access keys table is not available.'];
        }

        $existing = Capsule::table('s3_user_access_keys')
            ->where('user_id', $ownerId)
            ->orderByDesc('id')
            ->first(['id', 'access_key', 'secret_key']);
        if ($existing && !empty($existing->access_key) && !empty($existing->secret_key)) {
            $accessKey = HelperController::decryptKeyWithFallback((string) $existing->access_key, $encryptionKey);
            $secretKey = HelperController::decryptKeyWithFallback((string) $existing->secret_key, $encryptionKey);
            if ($accessKey !== '' && $secretKey !== '') {
                return ['status' => 'success'];
            }
        }

        $adminUser = trim((string) Capsule::table('tbladdonmodules')
            ->where('module', 'cloudstorage')
            ->where('setting', 'ceph_admin_user')
            ->value('value'));
        $region = trim((string) ($adminCfg['region'] ?? 'us-east-1'));
        if ($region === '') {
            $region = 'us-east-1';
        }

        $controller = new BucketController(
            (string) $adminCfg['endpoint'],
            $adminUser,
            (string) $adminCfg['access_key'],
            (string) $adminCfg['secret_key'],
            $region,
        );

        $username = trim((string) ($owner->username ?? ''));
        if ($username === '') {
            $username = trim((string) ($owner->ceph_uid ?? ''));
        }

        $update = $controller->updateUserAccessKey($username, $ownerId, $encryptionKey);
        if (!is_array($update) || ($update['status'] ?? '') !== 'success') {
            return ['status' => 'fail', 'message' => $update['message'] ?? 'Unable to create platform storage access key.'];
        }

        return ['status' => 'success'];
    }

    private static function moduleEncryptionKey(): string
    {
        $primary = trim((string) Capsule::table('tbladdonmodules')
            ->where('module', 'cloudstorage')
            ->where('setting', 'cloudbackup_encryption_key')
            ->value('value'));
        if ($primary !== '') {
            return $primary;
        }

        return trim((string) Capsule::table('tbladdonmodules')
            ->where('module', 'cloudstorage')
            ->where('setting', 'encryption_key')
            ->value('value'));
    }

    /** @param array<string, mixed> $payload */
    private static function filterExistingColumns(string $table, array $payload): array
    {
        $out = [];
        foreach ($payload as $col => $val) {
            try {
                if (Capsule::schema()->hasColumn($table, $col)) {
                    $out[$col] = $val;
                }
            } catch (\Throwable $_) {
            }
        }

        return $out;
    }
}
