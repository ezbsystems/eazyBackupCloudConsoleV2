<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\KopiaRetentionPolicyService;
use WHMCS\Module\Addon\CloudStorage\Client\KopiaRetentionRepositoryService;

/**
 * Registers e3ms365-* buckets as Kopia repositories for MS365 backup worker snapshots.
 */
final class KopiaRepoBootstrapService
{
    /** @param array<string, mixed> $tenantRow */
    public static function ensureForTenantRecord(array $tenantRow): array
    {
        return self::ensureInternal($tenantRow, null, null);
    }

    /** @param array<string, mixed> $tenantRow */
    public static function ensureForJob(array $tenantRow, string $jobUuid, string $retentionTier): array
    {
        return self::ensureInternal($tenantRow, $jobUuid, $retentionTier);
    }

    public static function latestManifestForSource(int $tenantRecordId, string $physicalKey, ?string $e3JobId = null): string
    {
        if ($tenantRecordId <= 0 || $physicalKey === '' || !class_exists(Capsule::class)) {
            return '';
        }
        if (!Capsule::schema()->hasColumn('ms365_backup_runs', 'manifest_id')) {
            return '';
        }

        $q = Capsule::table('ms365_backup_runs')
            ->where('tenant_record_id', $tenantRecordId)
            ->where('physical_key', $physicalKey)
            ->where('status', 'success')
            ->where('manifest_id', '!=', '');

        if ($e3JobId !== null && $e3JobId !== '' && Capsule::schema()->hasColumn('ms365_backup_runs', 'e3_job_id')) {
            $legacy = Ms365JobDestinationService::loadJobRow($e3JobId);
            $tenant = TenantRecordRepository::getById($tenantRecordId);
            if ($legacy !== null && $tenant !== null && Ms365JobDestinationService::isLegacySharedBucket($legacy, $tenant)) {
                $q->where(function ($sub) use ($e3JobId): void {
                    $sub->where('e3_job_id', $e3JobId)->orWhereNull('e3_job_id')->orWhere('e3_job_id', '');
                });
            } else {
                $q->where('e3_job_id', $e3JobId);
            }
        }

        $row = $q->orderByDesc('finished_at')->first();

        return $row ? (string) $row->manifest_id : '';
    }

    /**
     * @param array<string, mixed> $tenantRow
     * @return array{repository_id: string, repo_password: string, bucket_name: string, bucket_id: int, policy_version_id: int}
     */
    private static function ensureInternal(array $tenantRow, ?string $jobUuid, ?string $retentionTier): array
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
        $jobScoped = $jobUuid !== null && $jobUuid !== '';
        $repoPassword = $jobScoped
            ? self::deriveRepoPasswordV2($tenantRow, $repositoryId, $jobUuid)
            : self::deriveRepoPasswordV1($tenantRow, $repositoryId);

        $policyVersionId = 0;
        if ($jobScoped && $retentionTier !== null && class_exists(KopiaRetentionRepositoryService::class)) {
            $policyVersionId = (int) (KopiaRetentionRepositoryService::ensurePolicyVersionFromDocument(
                Ms365RetentionTierPolicyService::tierToPolicyDocument($retentionTier)
            ) ?? 0);
        }

        if (class_exists(KopiaRetentionRepositoryService::class)) {
            $hints = [
                'client_id' => $clientId,
                'tenant_id' => $tenantRecordId > 0 ? $tenantRecordId : null,
                'bucket_id' => $bucketId,
            ];
            if ($policyVersionId > 0) {
                $hints['policy_version_id'] = $policyVersionId;
            }
            KopiaRetentionRepositoryService::ensureRepoRecordForRepositoryId($repositoryId, $hints);
        }

        return [
            'repository_id' => $repositoryId,
            'repo_password' => $repoPassword,
            'bucket_name' => $bucketName,
            'bucket_id' => $bucketId,
            'policy_version_id' => $policyVersionId,
        ];
    }

    /**
     * Update pinned policy when job retention tier changes.
     */
    public static function pinRetentionTierForJob(array $tenantRow, string $jobUuid, string $retentionTier): int
    {
        $meta = self::ensureForJob($tenantRow, $jobUuid, $retentionTier);

        return (int) ($meta['policy_version_id'] ?? 0);
    }

    /**
     * @return array{hourly: int, daily: int, weekly: int, monthly: int, yearly: int}
     */
    public static function effectivePolicyForRepositoryId(string $repositoryId): array
    {
        if (!class_exists(Capsule::class) || !Capsule::schema()->hasTable('s3_kopia_repos')) {
            return Ms365RetentionTierPolicyService::tierToCometMap(Ms365RetentionTierPolicyService::DEFAULT_TIER);
        }

        $repo = Capsule::table('s3_kopia_repos')->where('repository_id', $repositoryId)->first();
        if ($repo === null || empty($repo->vault_policy_version_id)) {
            return Ms365RetentionTierPolicyService::tierToCometMap(Ms365RetentionTierPolicyService::DEFAULT_TIER);
        }

        $policyRow = Capsule::table('s3_kopia_policy_versions')
            ->where('id', (int) $repo->vault_policy_version_id)
            ->first();
        if ($policyRow === null || empty($policyRow->policy_json)) {
            return Ms365RetentionTierPolicyService::tierToCometMap(Ms365RetentionTierPolicyService::DEFAULT_TIER);
        }

        $decoded = json_decode((string) $policyRow->policy_json, true);

        return KopiaRetentionPolicyService::resolveEffectivePolicy(null, is_array($decoded) ? $decoded : [], 'active');
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
    private static function deriveRepoPasswordV1(array $tenantRow, string $repositoryId): string
    {
        $bucket = trim((string) ($tenantRow['s3_bucket_name'] ?? $tenantRow['s3_bucket'] ?? ''));
        $tenantId = (int) ($tenantRow['id'] ?? 0);
        $clientId = (int) ($tenantRow['whmcs_client_id'] ?? 0);

        return hash('sha256', implode('|', [$repositoryId, $bucket, $tenantId, $clientId, 'ms365-kopia-v1']));
    }

    /** @param array<string, mixed> $tenantRow */
    private static function deriveRepoPasswordV2(array $tenantRow, string $repositoryId, string $jobUuid): string
    {
        $bucket = trim((string) ($tenantRow['s3_bucket_name'] ?? $tenantRow['s3_bucket'] ?? ''));
        $tenantId = (int) ($tenantRow['id'] ?? 0);
        $clientId = (int) ($tenantRow['whmcs_client_id'] ?? 0);
        $jobUuid = strtolower(trim($jobUuid));

        return hash('sha256', implode('|', [$repositoryId, $bucket, $tenantId, $clientId, $jobUuid, 'ms365-kopia-v2']));
    }
}
