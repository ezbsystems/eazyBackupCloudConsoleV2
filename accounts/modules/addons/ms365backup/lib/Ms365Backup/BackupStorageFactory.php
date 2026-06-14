<?php
declare(strict_types=1);

namespace Ms365Backup;

final class BackupStorageFactory
{
    public static function createDefault(): BackupStorageInterface
    {
        $row = TenantRepository::get();
        if ($row === null) {
            return new LocalFilesystemBackupStorage();
        }

        return self::createFromTenantRow($row);
    }

    public static function createForTenantRecord(?array $tenantRecord): BackupStorageInterface
    {
        if ($tenantRecord === null) {
            return new LocalFilesystemBackupStorage();
        }

        $bucketLinked = trim((string) ($tenantRecord['s3_bucket_name'] ?? $tenantRecord['s3_bucket'] ?? '')) !== ''
            && trim((string) ($tenantRecord['azure_tenant_id'] ?? $tenantRecord['tenant_id'] ?? '')) !== '';

        if ($bucketLinked && class_exists(\WHMCS\Module\Addon\CloudStorage\Client\BucketController::class)) {
            try {
                return CloudStorageBackupStorage::fromTenantRecord($tenantRecord);
            } catch (\Throwable $e) {
                logActivity('MS365 CloudStorageBackupStorage fallback: ' . $e->getMessage());
            }
        }

        return self::createFromTenantRow($tenantRecord);
    }

    /** @param array<string, mixed> $row ms365_tenant_config or ms365_tenant_records row */
    public static function createFromTenantRow(array $row): BackupStorageInterface
    {
        $endpoint = trim((string) ($row['s3_endpoint'] ?? ''));
        $bucket = trim((string) ($row['s3_bucket'] ?? $row['s3_bucket_name'] ?? ''));
        if ($endpoint === '' || $bucket === '') {
            return new LocalFilesystemBackupStorage();
        }

        $key = TenantRepository::decryptSecret($row['s3_access_key_enc'] ?? null);
        $secret = TenantRepository::decryptSecret($row['s3_secret_key_enc'] ?? null);
        if ($key === '' || $secret === '') {
            return new LocalFilesystemBackupStorage();
        }

        return new S3CompatibleBackupStorage(
            $endpoint,
            $bucket,
            $key,
            $secret,
            (string) ($row['s3_region'] ?? 'us-east-1'),
        );
    }
}
