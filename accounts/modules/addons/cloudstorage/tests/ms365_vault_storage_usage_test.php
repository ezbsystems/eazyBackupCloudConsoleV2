<?php

declare(strict_types=1);

/**
 * Vault stored-size helpers and MS365 display-only stats collector smoke tests.
 *
 * Run:
 *   php accounts/modules/addons/cloudstorage/tests/ms365_vault_storage_usage_test.php
 */

$repoRoot = dirname(__DIR__, 4);
require_once $repoRoot . '/init.php';
require_once $repoRoot . '/modules/addons/cloudstorage/lib/Client/Ms365VaultLifecycleService.php';
require_once $repoRoot . '/modules/addons/cloudstorage/lib/Admin/S3Billing.php';

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Admin\S3Billing;
use WHMCS\Module\Addon\CloudStorage\Client\Ms365VaultLifecycleService;

$failures = [];

$emptyFields = Ms365VaultLifecycleService::storageUsageFields(null);
if ($emptyFields['storage_used_bytes'] !== null || $emptyFields['storage_used_display'] !== '—') {
    $failures[] = 'null usage should render em dash';
}

$knownFields = Ms365VaultLifecycleService::storageUsageFields(1536);
if ($knownFields['storage_used_bytes'] !== 1536 || $knownFields['storage_used_display'] !== '1.5 KiB') {
    $failures[] = 'expected 1536 bytes -> 1.5 KiB';
}

$billingRef = new ReflectionClass(S3Billing::class);
$exemptMethod = $billingRef->getMethod('isMs365BillingExemptBucket');
$exemptMethod->setAccessible(true);
if (!$exemptMethod->invoke(null, 'e3ms365-abc123')) {
    $failures[] = 'e3ms365-* should remain billing exempt';
}
if ($exemptMethod->invoke(null, 'e3cb-deadbeef')) {
    $failures[] = 'legacy e3cb-* should not be billing exempt';
}

// Legacy vault with known stats should surface usage in listVaultsForClient legacy rows.
$legacyBucket = Capsule::table('s3_buckets')
    ->where('name', 'e3cb-df0d2dd424b73184')
    ->first(['id', 'name']);

if ($legacyBucket) {
    $usageMap = Ms365VaultLifecycleService::usageMapForBucketIds([(int) $legacyBucket->id]);
    if (!isset($usageMap[(int) $legacyBucket->id]) || $usageMap[(int) $legacyBucket->id] <= 0) {
        $failures[] = 'expected positive usage for legacy bucket e3cb-df0d2dd424b73184';
    }

    $clientId = (int) (Capsule::table('s3_backup_users')->where('username', 'backupuser')->value('client_id') ?? 0);
    if ($clientId > 0) {
        $vaultData = Ms365VaultLifecycleService::listVaultsForClient($clientId, null, false);
        $legacyRows = array_values(array_filter(
            $vaultData['legacy_vaults'] ?? [],
            static fn (array $row): bool => (int) ($row['id'] ?? 0) === (int) $legacyBucket->id
        ));
        if ($legacyRows === []) {
            $failures[] = 'expected legacy vault row for backupuser';
        } elseif (($legacyRows[0]['storage_used_display'] ?? '—') === '—') {
            $failures[] = 'legacy vault row should include storage_used_display';
        }
    }
} else {
    echo "SKIP: legacy bucket e3cb-df0d2dd424b73184 not present on this environment\n";
}

// MS365 collector should write summary rows without throwing.
$module = Capsule::table('tbladdonmodules')->where('module', 'cloudstorage')->get();
if ($module->count() > 0) {
    $settings = [
        's3Endpoint' => (string) $module->where('setting', 's3_endpoint')->pluck('value')->first(),
        'cephAdminUser' => (string) $module->where('setting', 'ceph_admin_user')->pluck('value')->first(),
        'cephAdminAccessKey' => (string) $module->where('setting', 'ceph_access_key')->pluck('value')->first(),
        'cephAdminSecretKey' => (string) $module->where('setting', 'ceph_secret_key')->pluck('value')->first(),
        'encryptionKey' => (string) $module->where('setting', 'encryption_key')->pluck('value')->first(),
    ];

    $billing = new S3Billing();
    $collector = $billingRef->getMethod('collectMs365VaultStatsForDisplay');
    $collector->setAccessible(true);
    $collector->invoke($billing, $settings, date('Y-m-d H:i:s'));

    $ms365StatsCount = Capsule::table('s3_bucket_stats_summary as s')
        ->join('s3_buckets as b', 'b.id', '=', 's.bucket_id')
        ->where('b.name', 'like', 'e3ms365-%')
        ->count();

    if ($ms365StatsCount <= 0) {
        $failures[] = 'MS365 collector should write at least one s3_bucket_stats_summary row';
    } else {
        $sampleBucketId = (int) Capsule::table('s3_buckets')
            ->where('name', 'like', 'e3ms365-%')
            ->whereExists(function ($q) {
                $q->select(Capsule::raw(1))
                    ->from('s3_cloudbackup_jobs as j')
                    ->whereColumn('j.dest_bucket_id', 's3_buckets.id');
            })
            ->value('id');

        if ($sampleBucketId > 0) {
            $vaultData = Ms365VaultLifecycleService::listVaultsForBackupUser(
                (int) Capsule::table('s3_cloudbackup_jobs')->where('dest_bucket_id', $sampleBucketId)->value('client_id'),
                (int) Capsule::table('s3_cloudbackup_jobs')->where('dest_bucket_id', $sampleBucketId)->value('backup_user_id')
            );
            $active = $vaultData['vaults_active'] ?? [];
            $match = array_values(array_filter(
                $active,
                static fn (array $row): bool => (int) ($row['id'] ?? 0) === $sampleBucketId
            ));
            if ($match !== [] && ($match[0]['storage_used_display'] ?? '—') === '—') {
                $failures[] = 'MS365 vault row should include storage_used_display after collector run';
            }
        }
    }
} else {
    $failures[] = 'cloudstorage addon settings missing';
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        echo 'FAIL: ' . $failure . PHP_EOL;
    }
    exit(1);
}

echo "ms365_vault_storage_usage_test: OK\n";
