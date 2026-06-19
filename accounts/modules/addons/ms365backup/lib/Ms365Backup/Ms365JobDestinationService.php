<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\HelperController;
use WHMCS\Module\Addon\CloudStorage\Client\Ms365StorageBootstrapService;

/**
 * Resolves MS365 backup destination (bucket + Kopia repo) from job context.
 */
final class Ms365JobDestinationService
{
    /**
     * @param array<string, mixed> $run ms365_backup_runs row
     * @param array<string, mixed> $tenantRecord
     * @return array{endpoint: string, region: string, bucket: string, prefix: string, access_key: string, secret_key: string, repo_password: string, kopia_repo_id: string, e3_job_id: string, legacy_shared_bucket: bool}
     */
    public static function resolveForRun(array $run, array $tenantRecord): array
    {
        $e3JobId = trim((string) ($run['e3_job_id'] ?? ''));
        if ($e3JobId !== '') {
            return self::resolveForJobId($e3JobId, $tenantRecord);
        }

        return self::resolveLegacyTenantBucket($tenantRecord);
    }

    /**
     * @param array<string, mixed> $tenantRecord
     * @return array{endpoint: string, region: string, bucket: string, prefix: string, access_key: string, secret_key: string, repo_password: string, kopia_repo_id: string, e3_job_id: string, legacy_shared_bucket: bool}
     */
    public static function resolveForJobId(string $e3JobId, array $tenantRecord): array
    {
        $job = self::loadJobRow($e3JobId);
        if ($job === null) {
            return self::resolveLegacyTenantBucket($tenantRecord);
        }

        $legacy = self::isLegacySharedBucket($job, $tenantRecord);
        $bucket = self::bucketForJob($job, $tenantRecord);
        $recordForRepo = self::tenantRecordWithBucket($tenantRecord, $bucket);

        $repoMeta = $legacy
            ? KopiaRepoBootstrapService::ensureForTenantRecord($recordForRepo)
            : KopiaRepoBootstrapService::ensureForJob($recordForRepo, $e3JobId, self::retentionTierForJob($job));

        return self::buildDestination($recordForRepo, $repoMeta, $e3JobId, $legacy);
    }

    /**
     * @param array<string, mixed> $tenantRecord
     * @return array{endpoint: string, region: string, bucket: string, prefix: string, access_key: string, secret_key: string, repo_password: string, kopia_repo_id: string, e3_job_id: string, legacy_shared_bucket: bool}
     */
    public static function resolveLegacyTenantBucket(array $tenantRecord): array
    {
        $repoMeta = KopiaRepoBootstrapService::ensureForTenantRecord($tenantRecord);

        return self::buildDestination($tenantRecord, $repoMeta, '', true);
    }

    public static function loadJobRow(string $e3JobId): ?object
    {
        if (!self::isUuid($e3JobId) || !Capsule::schema()->hasTable('s3_cloudbackup_jobs')) {
            return null;
        }

        return Capsule::table('s3_cloudbackup_jobs')
            ->whereRaw('job_id = UUID_TO_BIN(?)', [strtolower($e3JobId)])
            ->where('source_type', Ms365CustomerJobService::SOURCE_TYPE)
            ->where('status', '!=', 'deleted')
            ->first();
    }

    /**
     * @param object $job
     * @param array<string, mixed> $tenantRecord
     */
    public static function isLegacySharedBucket(object $job, array $tenantRecord): bool
    {
        $jobBucketId = (int) ($job->dest_bucket_id ?? 0);
        $tenantBucketId = (int) ($tenantRecord['s3_bucket_id'] ?? 0);
        if ($jobBucketId > 0 && $tenantBucketId > 0) {
            return $jobBucketId === $tenantBucketId;
        }

        $jobBucketName = self::bucketNameForJob($job);
        $tenantBucketName = trim((string) ($tenantRecord['s3_bucket_name'] ?? $tenantRecord['s3_bucket'] ?? ''));
        if ($jobBucketName !== '' && $tenantBucketName !== '') {
            return strcasecmp($jobBucketName, $tenantBucketName) === 0;
        }

        $backupUserId = (int) ($job->backup_user_id ?? $tenantRecord['backup_user_id'] ?? 0);
        if ($backupUserId > 0 && $jobBucketName !== '') {
            return $jobBucketName === Ms365StorageBootstrapService::stableMs365BucketName($backupUserId);
        }

        return true;
    }

    /**
     * @param object $job
     * @param array<string, mixed> $tenantRecord
     * @return array{id: int, name: string, user_id: int}
     */
    public static function bucketForJob(object $job, array $tenantRecord): array
    {
        $destBucketId = (int) ($job->dest_bucket_id ?? 0);
        if ($destBucketId > 0) {
            $row = Capsule::table('s3_buckets')->where('id', $destBucketId)->where('is_active', 1)->first();
            if ($row !== null) {
                return [
                    'id' => (int) $row->id,
                    'name' => (string) $row->name,
                    'user_id' => (int) $row->user_id,
                ];
            }
        }

        $name = self::bucketNameForJob($job);
        if ($name !== '') {
            $row = Capsule::table('s3_buckets')->where('name', $name)->where('is_active', 1)->first();
            if ($row !== null) {
                return [
                    'id' => (int) $row->id,
                    'name' => (string) $row->name,
                    'user_id' => (int) $row->user_id,
                ];
            }
        }

        return [
            'id' => (int) ($tenantRecord['s3_bucket_id'] ?? 0),
            'name' => trim((string) ($tenantRecord['s3_bucket_name'] ?? $tenantRecord['s3_bucket'] ?? '')),
            'user_id' => (int) ($tenantRecord['s3_user_id'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $tenantRecord
     * @param array{id: int, name: string, user_id: int} $bucket
     * @return array<string, mixed>
     */
    public static function tenantRecordWithBucket(array $tenantRecord, array $bucket): array
    {
        $out = $tenantRecord;
        if ($bucket['name'] !== '') {
            $out['s3_bucket_name'] = $bucket['name'];
            $out['s3_bucket'] = $bucket['name'];
        }
        if ($bucket['id'] > 0) {
            $out['s3_bucket_id'] = $bucket['id'];
        }
        if ($bucket['user_id'] > 0) {
            $out['s3_user_id'] = $bucket['user_id'];
        }

        return $out;
    }

    public static function retentionTierForJob(object $job): string
    {
        $ms365 = [];
        if (!empty($job->schedule_json)) {
            $decoded = json_decode((string) $job->schedule_json, true);
            if (is_array($decoded)) {
                $ms365 = $decoded;
            }
        }
        if (!empty($ms365['retention_tier'])) {
            return (string) $ms365['retention_tier'];
        }
        if (!empty($job->retention_json)) {
            $ret = json_decode((string) $job->retention_json, true);

            return Ms365RetentionTierPolicyService::tierFromRetentionJson(is_array($ret) ? $ret : null);
        }

        return Ms365RetentionTierPolicyService::DEFAULT_TIER;
    }

    /**
     * @param object $job
     */
    private static function bucketNameForJob(object $job): string
    {
        $destBucketId = (int) ($job->dest_bucket_id ?? 0);
        if ($destBucketId > 0) {
            $name = Capsule::table('s3_buckets')->where('id', $destBucketId)->value('name');
            if (is_string($name) && $name !== '') {
                return $name;
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $record
     * @param array<string, mixed> $repoMeta
     * @return array{endpoint: string, region: string, bucket: string, prefix: string, access_key: string, secret_key: string, repo_password: string, kopia_repo_id: string, e3_job_id: string, legacy_shared_bucket: bool}
     */
    private static function buildDestination(array $record, array $repoMeta, string $e3JobId, bool $legacy): array
    {
        $bucket = trim((string) ($record['s3_bucket_name'] ?? $record['s3_bucket'] ?? ''));
        $settings = Capsule::table('tbladdonmodules')->where('module', 'cloudstorage')->pluck('value', 'setting');
        $endpoint = trim((string) ($settings['s3_endpoint'] ?? ''));
        $region = trim((string) ($settings['s3_region'] ?? 'us-east-1'));

        $ownerUserId = (int) ($record['s3_user_id'] ?? 0);
        $accessKey = '';
        $secretKey = '';
        if ($ownerUserId > 0) {
            $keyRow = Capsule::table('s3_user_access_keys')->where('user_id', $ownerUserId)->orderByDesc('id')->first();
            if ($keyRow) {
                $encKeyPrimary = trim((string) ($settings['cloudbackup_encryption_key'] ?? ''));
                $encKeySecondary = trim((string) ($settings['encryption_key'] ?? ''));
                foreach ([$encKeyPrimary, $encKeySecondary] as $encKey) {
                    if ($encKey === '') {
                        continue;
                    }
                    $accessKey = (string) HelperController::decryptKey((string) $keyRow->access_key, $encKey);
                    $secretKey = (string) HelperController::decryptKey((string) $keyRow->secret_key, $encKey);
                    if ($accessKey !== '' && $secretKey !== '') {
                        break;
                    }
                }
            }
        }

        return [
            'endpoint' => $endpoint,
            'region' => $region,
            'bucket' => $bucket,
            'prefix' => 'kopia/',
            'access_key' => $accessKey,
            'secret_key' => $secretKey,
            'repo_password' => (string) ($repoMeta['repo_password'] ?? ''),
            'kopia_repo_id' => (string) ($repoMeta['repository_id'] ?? ''),
            'e3_job_id' => $e3JobId,
            'legacy_shared_bucket' => $legacy,
        ];
    }

    private static function isUuid(string $id): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id);
    }
}
