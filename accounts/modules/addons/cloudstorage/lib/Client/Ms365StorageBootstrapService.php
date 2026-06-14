<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

use WHMCS\Database\Capsule;

/**
 * Provisions a dedicated RGW bucket for Microsoft 365 backup data (e3ms365-{token}).
 */
class Ms365StorageBootstrapService
{
    private static string $module = 'cloudstorage';

    public static function ensureForClient(int $clientId): array
    {
        try {
            $ownerRes = CloudBackupBootstrapService::ensureBackupOwnerUser($clientId);
            if (($ownerRes['status'] ?? '') !== 'success') {
                return $ownerRes;
            }

            $owner = $ownerRes['owner_user'];
            $tenantRecord = self::getActiveTenantRecord($clientId);
            $controllerRes = self::makeBucketController();
            if (($controllerRes['status'] ?? '') !== 'success' || !isset($controllerRes['controller'])) {
                return $controllerRes;
            }
            /** @var BucketController $controller */
            $controller = $controllerRes['controller'];

            $ownerS3Res = self::connectOwnerS3Client($controller, $owner);
            if (($ownerS3Res['status'] ?? '') !== 'success' || empty($ownerS3Res['s3client'])) {
                return ['status' => 'fail', 'message' => $ownerS3Res['message'] ?? 'Unable to connect backup owner to object storage.'];
            }
            /** @var \Aws\S3\S3Client $ownerS3 */
            $ownerS3 = $ownerS3Res['s3client'];

            $resolved = self::resolveMs365Bucket($owner, $clientId, $tenantRecord, $ownerS3);
            $bucket = $resolved['bucket'];
            $bucketName = $resolved['name'];

            if (!$bucket) {
                $create = $controller->createBucketAsAdmin($owner, $bucketName, true, false, 'GOVERNANCE', 1, false);
                if (!is_array($create) || ($create['status'] ?? '') !== 'success') {
                    return ['status' => 'fail', 'message' => $create['message'] ?? 'Failed to create MS365 bucket.'];
                }
                $bucket = Capsule::table('s3_buckets')
                    ->where('name', $bucketName)
                    ->where('user_id', (int) $owner->id)
                    ->where('is_active', 1)
                    ->first();
            }

            if (!$bucket) {
                return ['status' => 'fail', 'message' => 'Unable to resolve MS365 bucket after creation.'];
            }

            $rgw = self::ensureBucketPresentOnRgw($controller, $ownerS3, $bucketName, $owner);
            if (($rgw['status'] ?? '') !== 'success') {
                return $rgw;
            }

            self::ensureKopiaRepoForTenant($clientId, $bucket, $tenantRecord);

            return ['status' => 'success', 'bucket' => $bucket, 'owner_user' => $owner];
        } catch (\Throwable $e) {
            logModuleCall(self::$module, __FUNCTION__, ['client_id' => $clientId], $e->getMessage());

            return ['status' => 'fail', 'message' => 'Failed to ensure MS365 storage bucket.'];
        }
    }

    /**
     * @param \Aws\S3\S3Client $ownerS3 backup-owner scoped client (required for RGW tenant buckets)
     * @return array{bucket: ?object, name: string}
     */
    private static function resolveMs365Bucket(object $owner, int $clientId, ?object $tenantRecord, $ownerS3): array
    {
        $names = [];
        if ($tenantRecord !== null) {
            $linked = trim((string) ($tenantRecord->s3_bucket_name ?? ''));
            if ($linked !== '') {
                $names[] = self::sanitizeBucketName($linked);
            }
        }
        $names[] = self::stableMs365BucketName($owner, $clientId);

        $dbBuckets = Capsule::table('s3_buckets')
            ->where('user_id', (int) $owner->id)
            ->where('is_active', 1)
            ->where('name', 'like', 'e3ms365-%')
            ->orderByDesc('id')
            ->pluck('name')
            ->all();
        foreach ($dbBuckets as $dbName) {
            $names[] = self::sanitizeBucketName((string) $dbName);
        }

        $seen = [];
        foreach ($names as $candidate) {
            if ($candidate === '' || isset($seen[$candidate])) {
                continue;
            }
            $seen[$candidate] = true;
            if (!self::headBucketExists($ownerS3, $candidate)) {
                continue;
            }
            $bucket = Capsule::table('s3_buckets')
                ->where('name', $candidate)
                ->where('user_id', (int) $owner->id)
                ->where('is_active', 1)
                ->first();

            return ['bucket' => $bucket, 'name' => $candidate];
        }

        $fallback = self::stableMs365BucketName($owner, $clientId);

        return ['bucket' => null, 'name' => $fallback];
    }

    private static function stableMs365BucketName(object $owner, int $clientId): string
    {
        $stable = substr(hash('sha256', 'ms365:' . $clientId . ':' . (int) $owner->id), 0, 24);

        return self::sanitizeBucketName('e3ms365-' . $stable);
    }

    private static function getActiveTenantRecord(int $clientId): ?object
    {
        if (!Capsule::schema()->hasTable('ms365_tenant_records')) {
            return null;
        }

        return Capsule::table('ms365_tenant_records')
            ->where('whmcs_client_id', $clientId)
            ->where('is_active', 1)
            ->orderByDesc('id')
            ->first();
    }

    /** @return array{status: string, controller?: BucketController, message?: string} */
    private static function makeBucketController(): array
    {
        $settings = Capsule::table('tbladdonmodules')
            ->where('module', 'cloudstorage')
            ->pluck('value', 'setting');

        $endpoint = trim((string) ($settings['s3_endpoint'] ?? ''));
        $accessKey = trim((string) ($settings['ceph_access_key'] ?? ''));
        $secretKey = trim((string) ($settings['ceph_secret_key'] ?? ''));
        $region = trim((string) ($settings['s3_region'] ?? ''));
        if ($region === '') {
            $region = 'us-east-1';
        }
        $adminUser = trim((string) ($settings['ceph_admin_user'] ?? ''));

        if ($endpoint === '' || $accessKey === '' || $secretKey === '') {
            return ['status' => 'fail', 'message' => 'Bucket controller configuration is incomplete.'];
        }

        return [
            'status' => 'success',
            'controller' => new BucketController($endpoint, $adminUser, $accessKey, $secretKey, $region),
        ];
    }

    /**
     * WHMCS may have an s3_buckets row while the bucket is missing for the owner on RGW.
     *
     * @param \Aws\S3\S3Client $ownerS3 backup-owner scoped client
     * @param object $owner cloudstorage owner user row
     * @return array{status: string, message?: string}
     */
    private static function ensureBucketPresentOnRgw(BucketController $controller, $ownerS3, string $bucketName, object $owner): array
    {
        if (self::headBucketExists($ownerS3, $bucketName)) {
            return ['status' => 'success'];
        }

        logModuleCall(self::$module, 'ensureBucketPresentOnRgw_repair', [
            'bucket' => $bucketName,
            'owner_id' => $owner->id ?? null,
        ], 'Owner-scoped bucket missing on RGW; attempting createBucketAsAdmin repair');

        $create = $controller->createBucketAsAdmin(
            $owner,
            $bucketName,
            true,
            false,
            'GOVERNANCE',
            1,
            false,
            true
        );
        if (!is_array($create) || ($create['status'] ?? '') !== 'success') {
            logModuleCall(self::$module, 'ensureBucketPresentOnRgw_repair_fail', ['bucket' => $bucketName], $create['message'] ?? 'repair failed');

            return ['status' => 'fail', 'message' => $create['message'] ?? 'Failed to create backup storage bucket on object storage.'];
        }

        if (!self::headBucketExists($ownerS3, $bucketName)) {
            return ['status' => 'fail', 'message' => 'Backup storage bucket is not available on object storage.'];
        }

        return ['status' => 'success'];
    }

    /** @return array{status: string, s3client?: \Aws\S3\S3Client, message?: string} */
    private static function connectOwnerS3Client(BucketController $controller, object $owner): array
    {
        $settings = Capsule::table('tbladdonmodules')
            ->where('module', 'cloudstorage')
            ->pluck('value', 'setting');
        $encryptionKey = trim((string) ($settings['cloudbackup_encryption_key'] ?? $settings['encryption_key'] ?? ''));
        if ($encryptionKey === '') {
            return ['status' => 'fail', 'message' => 'Cloud storage encryption key is not configured.'];
        }

        return $controller->connectS3Client((int) ($owner->id ?? 0), $encryptionKey);
    }

    private static function headBucketExists(\Aws\S3\S3Client $s3, string $bucketName): bool
    {
        try {
            $s3->headBucket(['Bucket' => $bucketName]);

            return true;
        } catch (\Throwable $_) {
            return false;
        }
    }

    private static function ensureKopiaRepoForTenant(int $clientId, ?object $bucket, ?object $tenantRecord): void
    {
        if (!class_exists(\Ms365Backup\KopiaRepoBootstrapService::class)) {
            return;
        }
        $row = [
            'id' => $tenantRecord->id ?? 0,
            'whmcs_client_id' => $clientId,
            's3_bucket_name' => $bucket->name ?? '',
            's3_bucket' => $bucket->name ?? '',
            's3_user_id' => $bucket->user_id ?? 0,
        ];
        if ($tenantRecord !== null) {
            $row['id'] = (int) ($tenantRecord->id ?? 0);
            $row['azure_tenant_id'] = (string) ($tenantRecord->azure_tenant_id ?? $tenantRecord->tenant_id ?? '');
        }
        try {
            \Ms365Backup\KopiaRepoBootstrapService::ensureForTenantRecord($row);
        } catch (\Throwable $e) {
            logModuleCall(self::$module, 'ensureKopiaRepoForTenant', ['client_id' => $clientId], $e->getMessage());
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
}
