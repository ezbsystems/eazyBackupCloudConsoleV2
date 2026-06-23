<?php
declare(strict_types=1);

namespace Ms365Backup;

use Aws\S3\S3Client;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\BucketController;
use WHMCS\Module\Addon\CloudStorage\Client\CloudBackupBootstrapService;

/**
 * Writes backup objects to a cloudstorage-provisioned RGW bucket using the backup owner key.
 * Admin credentials cannot access tenant-scoped buckets on multi-tenant RGW.
 */
final class CloudStorageBackupStorage implements BackupStorageInterface
{
    public function __construct(
        private readonly S3Client $s3,
        private readonly string $bucket,
        private readonly string $azureTenantId,
    ) {
    }

    /** @param array<string, mixed> $tenantRow ms365_tenant_records row */
    public static function fromTenantRecord(array $tenantRow): self
    {
        $bucketName = trim((string) ($tenantRow['s3_bucket_name'] ?? $tenantRow['s3_bucket'] ?? ''));
        $azureTenantId = trim((string) ($tenantRow['azure_tenant_id'] ?? $tenantRow['tenant_id'] ?? ''));
        if ($bucketName === '' || $azureTenantId === '') {
            throw new \RuntimeException('MS365 cloud bucket is not linked for this tenant.');
        }

        if (!class_exists(Capsule::class)) {
            throw new \RuntimeException('Database not available for cloud storage.');
        }

        $clientId = (int) ($tenantRow['whmcs_client_id'] ?? 0);
        $ownerUserId = (int) ($tenantRow['s3_user_id'] ?? 0);
        $isMs365Bucket = str_starts_with(strtolower($bucketName), 'e3ms365-');

        if ($isMs365Bucket) {
            if (!class_exists(\WHMCS\Module\Addon\CloudStorage\Client\Ms365PlatformStorageService::class)) {
                throw new \RuntimeException('MS365 platform storage is not available.');
            }
            $ownerRes = \WHMCS\Module\Addon\CloudStorage\Client\Ms365PlatformStorageService::ensurePlatformOwner();
            if (($ownerRes['status'] ?? '') !== 'success' || empty($ownerRes['owner_user'])) {
                throw new \RuntimeException((string) ($ownerRes['message'] ?? 'MS365 platform storage owner is not available.'));
            }
            $ownerUserId = (int) $ownerRes['owner_user']->id;
        } elseif ($clientId > 0) {
            $ownerRes = CloudBackupBootstrapService::ensureBackupOwnerUser($clientId);
            if (($ownerRes['status'] ?? '') !== 'success' || empty($ownerRes['owner_user'])) {
                throw new \RuntimeException((string) ($ownerRes['message'] ?? 'Backup owner user is not available.'));
            }
            $ownerUserId = (int) $ownerRes['owner_user']->id;
        }

        if ($ownerUserId <= 0) {
            throw new \RuntimeException('MS365 backup owner is not linked for this tenant.');
        }

        $settings = Capsule::table('tbladdonmodules')
            ->where('module', 'cloudstorage')
            ->pluck('value', 'setting');

        $endpoint = trim((string) ($settings['s3_endpoint'] ?? ''));
        $accessKey = trim((string) ($settings['ceph_access_key'] ?? ''));
        $secretKey = trim((string) ($settings['ceph_secret_key'] ?? ''));
        $region = trim((string) ($settings['s3_region'] ?? 'us-east-1'));
        $adminUser = trim((string) ($settings['ceph_admin_user'] ?? ''));

        if ($endpoint === '' || $accessKey === '' || $secretKey === '') {
            throw new \RuntimeException('Cloud storage admin credentials are not configured.');
        }

        $encryptionKey = trim((string) ($settings['cloudbackup_encryption_key'] ?? $settings['encryption_key'] ?? ''));
        if ($encryptionKey === '') {
            throw new \RuntimeException('Cloud storage encryption key is not configured.');
        }

        $bc = new BucketController($endpoint, $adminUser, $accessKey, $secretKey, $region);
        $conn = $bc->connectS3Client($ownerUserId, $encryptionKey);
        if (($conn['status'] ?? '') !== 'success' || empty($conn['s3client'])) {
            throw new \RuntimeException((string) ($conn['message'] ?? 'Failed to connect backup owner S3 client.'));
        }

        return new self($conn['s3client'], $bucketName, $azureTenantId);
    }

    public function writeJson(string $absolutePath, array $data): void
    {
        $body = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if (str_ends_with($absolutePath, 'inventory.json')) {
            // #region agent log
            Ms365AgentDebugLog::write(
                'CloudStorageBackupStorage::writeJson',
                'inventory S3 put starting',
                [
                    'body_bytes' => strlen((string) $body),
                    'json_error' => json_last_error_msg(),
                ],
                'E',
            );
            // #endregion
        }
        $this->putObject($this->objectKey($absolutePath), (string) $body, 'application/json');
    }

    public function readJson(string $absolutePath): ?array
    {
        try {
            $result = $this->s3->getObject([
                'Bucket' => $this->bucket,
                'Key' => $this->objectKey($absolutePath),
            ]);
            $body = (string) ($result['Body'] ?? '');
            $decoded = json_decode($body, true);

            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable $_) {
            return null;
        }
    }

    public function exists(string $absolutePath): bool
    {
        try {
            $this->s3->headObject([
                'Bucket' => $this->bucket,
                'Key' => $this->objectKey($absolutePath),
            ]);

            return true;
        } catch (\Throwable $_) {
            return false;
        }
    }

    public function ensureDir(string $absolutePath): void
    {
    }

    public function writeStream(string $absolutePath, $stream): void
    {
        $contents = stream_get_contents($stream);
        if ($contents === false) {
            throw new \RuntimeException('Failed to read stream for cloud upload.');
        }
        $this->putObject($this->objectKey($absolutePath), $contents, 'application/octet-stream');
    }

    private function putObject(string $key, string $body, string $contentType): void
    {
        $this->s3->putObject([
            'Bucket' => $this->bucket,
            'Key' => $key,
            'Body' => $body,
            'ContentType' => $contentType,
        ]);
    }

    private function objectKey(string $absolutePath): string
    {
        $base = rtrim(StorageLayout::BASE_PATH, '/');
        $rel = str_starts_with($absolutePath, $base . '/')
            ? substr($absolutePath, strlen($base) + 1)
            : ltrim($absolutePath, '/');

        $parts = explode('/', $rel, 2);
        $suffix = $parts[1] ?? '';

        return $suffix !== ''
            ? $this->azureTenantId . '/' . $suffix
            : $this->azureTenantId;
    }
}
