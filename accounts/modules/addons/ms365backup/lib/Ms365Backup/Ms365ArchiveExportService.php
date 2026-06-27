<?php
declare(strict_types=1);

namespace Ms365Backup;

use Aws\S3\S3Client;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\BucketController;
use WHMCS\Module\Addon\CloudStorage\Client\BucketLifecycleService;
use WHMCS\Module\Addon\CloudStorage\Client\HelperController;
use WHMCS\Module\Addon\CloudStorage\Client\Ms365PlatformStorageService;

/**
 * MS365 restore archive exports: lifecycle on exports/ prefix and presigned downloads.
 */
final class Ms365ArchiveExportService
{
    private const LIFECYCLE_RULE_ID = 'ms365-archive-exports';
    private const EXPORT_PREFIX = 'exports/';

    public static function archiveExportTtlDays(): int
    {
        $raw = '';
        try {
            $raw = (string) Capsule::table('tbladdonmodules')
                ->where('module', 'ms365backup')
                ->where('setting', 'ms365_archive_export_ttl_days')
                ->value('value');
        } catch (\Throwable $_) {
        }
        if ($raw === '') {
            $raw = '7';
        }
        $days = (int) $raw;
        if ($days < 1) {
            $days = 1;
        }
        if ($days > 365) {
            $days = 365;
        }

        return $days;
    }

    public static function precomputedObjectKey(string $restoreRunId): string
    {
        return self::EXPORT_PREFIX . $restoreRunId . '/ms365-restore-' . time() . '.zip';
    }

    public static function ensureLifecycleRule(int $clientId, int $backupUserId, string $jobId, int $ttlDays): void
    {
        $record = TenantRecordRepository::getForBackupUser($clientId, $backupUserId);
        if ($record === null) {
            $record = TenantRecordRepository::getPrimaryForClient($clientId);
        }
        if ($record === null) {
            throw new \RuntimeException('Microsoft 365 is not connected.');
        }

        $dest = Ms365JobDestinationService::resolveForJobId($jobId, $record);
        $bucketName = trim((string) ($dest['bucket'] ?? ''));
        if ($bucketName === '') {
            throw new \RuntimeException('MS365 job storage bucket is not configured.');
        }

        $ownerRes = Ms365PlatformStorageService::ensurePlatformOwner();
        if (($ownerRes['status'] ?? '') !== 'success' || empty($ownerRes['owner_user'])) {
            throw new \RuntimeException('Unable to resolve MS365 platform storage owner.');
        }
        /** @var object $owner */
        $owner = $ownerRes['owner_user'];

        $settings = Capsule::table('tbladdonmodules')
            ->where('module', 'cloudstorage')
            ->pluck('value', 'setting');
        $endpoint = trim((string) ($settings['s3_endpoint'] ?? ''));
        $region = trim((string) ($settings['s3_region'] ?? 'us-east-1'));
        $adminAccessKey = trim((string) ($settings['ceph_access_key'] ?? ''));
        $adminSecretKey = trim((string) ($settings['ceph_secret_key'] ?? ''));

        if ($endpoint === '' || $adminAccessKey === '' || $adminSecretKey === '') {
            throw new \RuntimeException('Cloud storage admin settings are not configured.');
        }

        $ttlDays = max(1, min(365, $ttlDays));
        $service = new BucketLifecycleService($endpoint, $region);
        $cur = $service->getWithTempKey($bucketName, $owner, $adminAccessKey, $adminSecretKey);
        if (($cur['status'] ?? 'fail') !== 'success') {
            throw new \RuntimeException('Unable to fetch bucket lifecycle configuration.');
        }

        $currentRules = $cur['data']['rules'] ?? [];
        if (!is_array($currentRules)) {
            $currentRules = [];
        }

        $newRule = [
            'ID' => self::LIFECYCLE_RULE_ID,
            'Status' => 'Enabled',
            'Filter' => ['Prefix' => self::EXPORT_PREFIX],
            'Expiration' => ['Days' => $ttlDays],
        ];

        $merged = [];
        $replaced = false;
        foreach ($currentRules as $rule) {
            if (!is_array($rule)) {
                continue;
            }
            $ruleId = isset($rule['ID']) ? (string) $rule['ID'] : '';
            if ($ruleId === self::LIFECYCLE_RULE_ID) {
                $merged[] = $newRule;
                $replaced = true;
            } else {
                $merged[] = $rule;
            }
        }
        if (!$replaced) {
            $merged[] = $newRule;
        }

        $put = $service->putWithTempKey($bucketName, $merged, $owner, $adminAccessKey, $adminSecretKey);
        if (($put['status'] ?? 'fail') !== 'success') {
            throw new \RuntimeException('Unable to save archive export lifecycle rule.');
        }
    }

    /**
     * @param array<string, mixed> $restoreRunRow
     */
    public static function presignDownload(array $restoreRunRow, int $ttlSeconds = 3600): string
    {
        $objectKey = trim((string) ($restoreRunRow['archive_object_key'] ?? ''));
        $bucketName = trim((string) ($restoreRunRow['archive_bucket'] ?? ''));
        if ($objectKey === '' || $bucketName === '') {
            return '';
        }

        $bucket = Capsule::table('s3_buckets')
            ->where('name', $bucketName)
            ->where('is_active', 1)
            ->first();
        if ($bucket === null) {
            return '';
        }

        $s3UserId = (int) ($bucket->user_id ?? 0);
        if ($s3UserId <= 0) {
            return '';
        }

        $settings = Capsule::table('tbladdonmodules')
            ->where('module', 'cloudstorage')
            ->pluck('value', 'setting')
            ->all();
        $endpoint = trim((string) ($settings['cloudbackup_agent_s3_endpoint'] ?? ''));
        if ($endpoint === '') {
            $endpoint = trim((string) ($settings['s3_endpoint'] ?? ''));
        }
        if ($endpoint === '') {
            $endpoint = 'https://s3.ca-central-1.eazybackup.com';
        }
        $region = trim((string) ($settings['cloudbackup_agent_s3_region'] ?? ($settings['s3_region'] ?? '')));

        [$decAk, $decSk] = self::decryptAccessKeysForUser($s3UserId, $settings);
        if ($decAk === '' || $decSk === '') {
            return '';
        }

        $ctx = [
            'dest_endpoint' => $endpoint,
            'dest_region' => $region,
            'dest_access_key' => $decAk,
            'dest_secret_key' => $decSk,
        ];
        $s3 = self::connectS3ClientForContext($ctx);
        if (!$s3 instanceof S3Client) {
            return '';
        }

        try {
            $cmd = $s3->getCommand('GetObject', [
                'Bucket' => $bucketName,
                'Key' => $objectKey,
            ]);
            $request = $s3->createPresignedRequest($cmd, '+' . max(60, $ttlSeconds) . ' seconds');

            return (string) $request->getUri();
        } catch (\Throwable $_) {
            return '';
        }
    }

    /**
     * @param array<string, mixed> $settings
     * @return array{0: string, 1: string}
     */
    private static function decryptAccessKeysForUser(int $s3UserId, array $settings): array
    {
        if ($s3UserId <= 0) {
            return ['', ''];
        }

        $keys = Capsule::table('s3_user_access_keys')
            ->where('user_id', $s3UserId)
            ->orderByDesc('id')
            ->first();
        if ($keys === null) {
            return ['', ''];
        }

        $encKeyPrimary = trim((string) ($settings['cloudbackup_encryption_key'] ?? ''));
        $encKeySecondary = trim((string) ($settings['encryption_key'] ?? ''));
        $accessKeyRaw = (string) ($keys->access_key ?? '');
        $secretKeyRaw = (string) ($keys->secret_key ?? '');

        $decryptWith = static function (?string $key) use ($accessKeyRaw, $secretKeyRaw): array {
            $ak = $accessKeyRaw;
            $sk = $secretKeyRaw;
            if ($key !== null && $key !== '' && $ak !== '') {
                $ak = HelperController::decryptKey($ak, $key);
            }
            if ($key !== null && $key !== '' && $sk !== '') {
                $sk = HelperController::decryptKey($sk, $key);
            }

            return [is_string($ak) ? $ak : '', is_string($sk) ? $sk : ''];
        };

        [$decAk, $decSk] = $decryptWith($encKeyPrimary);
        if ($decAk === '' || $decSk === '') {
            [$decAk2, $decSk2] = $decryptWith($encKeySecondary);
            if ($decAk2 !== '' && $decSk2 !== '') {
                return [$decAk2, $decSk2];
            }
        }

        return [$decAk, $decSk];
    }

    /**
     * @param array<string, mixed> $ctx
     */
    private static function connectS3ClientForContext(array $ctx): ?S3Client
    {
        $bucketCtl = new BucketController(
            (string) ($ctx['dest_endpoint'] ?? ''),
            null,
            null,
            null,
            (string) ($ctx['dest_region'] ?? '')
        );
        $conn = $bucketCtl->connectS3ClientWithCredentials(
            (string) ($ctx['dest_access_key'] ?? ''),
            (string) ($ctx['dest_secret_key'] ?? '')
        );
        if (!is_array($conn) || ($conn['status'] ?? 'fail') !== 'success' || !($conn['s3client'] ?? null) instanceof S3Client) {
            return null;
        }

        return $conn['s3client'];
    }
}
