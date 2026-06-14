<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\KopiaRetentionRepositoryService;

/**
 * Registers e3ms365-* buckets as Kopia repositories for MS365 backup worker snapshots.
 */
final class KopiaRepoBootstrapService
{
    /** @param array<string, mixed> $tenantRow */
    public static function ensureForTenantRecord(array $tenantRow): array
    {
        $bucketName = trim((string) ($tenantRow['s3_bucket_name'] ?? $tenantRow['s3_bucket'] ?? ''));
        $clientId = (int) ($tenantRow['whmcs_client_id'] ?? 0);
        $tenantRecordId = (int) ($tenantRow['id'] ?? 0);
        $bucketId = 0;

        if ($bucketName !== '' && class_exists(Capsule::class)) {
            $bucket = Capsule::table('s3_buckets')
                ->where('name', $bucketName)
                ->where('is_active', 1)
                ->first();
            if ($bucket) {
                $bucketId = (int) $bucket->id;
            }
        }

        $repositoryId = self::repositoryIdForBucket($bucketName, $tenantRecordId);
        $repoPassword = self::deriveRepoPassword($tenantRow, $repositoryId);

        if (class_exists(KopiaRetentionRepositoryService::class)) {
            KopiaRetentionRepositoryService::ensureRepoRecordForRepositoryId($repositoryId, [
                'client_id' => $clientId,
                'tenant_id' => $tenantRecordId > 0 ? $tenantRecordId : null,
                'bucket_id' => $bucketId,
            ]);
        }

        return [
            'repository_id' => $repositoryId,
            'repo_password' => $repoPassword,
            'bucket_name' => $bucketName,
            'bucket_id' => $bucketId,
        ];
    }

    public static function latestManifestForSource(int $tenantRecordId, string $physicalKey): string
    {
        if ($tenantRecordId <= 0 || $physicalKey === '' || !class_exists(Capsule::class)) {
            return '';
        }
        if (!Capsule::schema()->hasColumn('ms365_backup_runs', 'manifest_id')) {
            return '';
        }
        $row = Capsule::table('ms365_backup_runs')
            ->where('tenant_record_id', $tenantRecordId)
            ->where('physical_key', $physicalKey)
            ->where('status', 'success')
            ->where('manifest_id', '!=', '')
            ->orderByDesc('finished_at')
            ->first();

        return $row ? (string) $row->manifest_id : '';
    }

    private static function repositoryIdForBucket(string $bucketName, int $tenantRecordId): string
    {
        if ($bucketName !== '') {
            return 'ms365:' . $bucketName;
        }
        if ($tenantRecordId > 0) {
            return 'ms365:tenant:' . $tenantRecordId;
        }

        return 'ms365:unknown';
    }

    /** @param array<string, mixed> $tenantRow */
    private static function deriveRepoPassword(array $tenantRow, string $repositoryId): string
    {
        $bucket = trim((string) ($tenantRow['s3_bucket_name'] ?? $tenantRow['s3_bucket'] ?? ''));
        $tenantId = (int) ($tenantRow['id'] ?? 0);
        $clientId = (int) ($tenantRow['whmcs_client_id'] ?? 0);

        return hash('sha256', implode('|', [$repositoryId, $bucket, $tenantId, $clientId, 'ms365-kopia-v1']));
    }
}
