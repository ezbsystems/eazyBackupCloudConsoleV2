<?php
declare(strict_types=1);

/**
 * Unit tests for Ms365ArchiveDownloadService readiness helper.
 *
 * Run: php accounts/modules/addons/ms365backup/tests/ms365_archive_download_service_test.php
 */

$root = dirname(__DIR__, 4);
require_once $root . '/init.php';
require_once dirname(__DIR__, 2) . '/cloudstorage/lib/Ms365BackupBootstrap.php';
cloudstorage_load_ms365backup();

use Ms365Backup\Ms365ArchiveDownloadService;

$failures = 0;
$now = time();

function assert_true(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        echo "FAIL: {$message}\n";
        ++$failures;
        return;
    }
    echo "OK: {$message}\n";
}

function assert_eq(mixed $expected, mixed $actual, string $message): void
{
    global $failures;
    if ($expected !== $actual) {
        echo "FAIL: {$message}\n";
        echo '  expected: ' . var_export($expected, true) . "\n";
        echo '  actual:   ' . var_export($actual, true) . "\n";
        ++$failures;
        return;
    }
    echo "OK: {$message}\n";
}

$default = Ms365ArchiveDownloadService::defaultPayload();
assert_eq(false, $default['archive_restore'], 'default payload is not archive restore');
assert_eq(false, $default['archive_download_ready'], 'default payload is not download ready');

$tenantChild = [
    'id' => 'child-tenant',
    'restore_mode' => 'tenant',
    'status' => 'success',
    'archive_object_key' => 'exports/foo.zip',
    'archive_expires_at' => $now + 86400,
];
$tenantPayload = Ms365ArchiveDownloadService::fromRestoreChild($tenantChild, $now);
assert_eq(false, $tenantPayload['archive_restore'], 'tenant restore returns default archive flags');

$readyChild = [
    'id' => 'child-archive-ready',
    'restore_mode' => 'archive',
    'status' => 'success',
    'archive_object_key' => 'exports/foo.zip',
    'archive_expires_at' => $now + 86400,
    'archive_size_bytes' => 4096,
];
$readyPayload = Ms365ArchiveDownloadService::fromRestoreChild($readyChild, $now);
assert_true($readyPayload['archive_restore'], 'archive child is flagged as archive restore');
assert_true($readyPayload['archive_download_ready'], 'successful archive with key and future expiry is ready');
assert_eq(false, $readyPayload['archive_expired'], 'future expiry is not expired');
assert_eq('child-archive-ready', $readyPayload['restore_run_id'], 'restore_run_id is child id');
assert_eq(4096, $readyPayload['archive_size_bytes'], 'archive_size_bytes is preserved');

$partialChild = $readyChild;
$partialChild['id'] = 'child-partial';
$partialChild['status'] = 'partial_success';
$partialPayload = Ms365ArchiveDownloadService::fromRestoreChild($partialChild, $now);
assert_true($partialPayload['archive_download_ready'], 'partial_success with object key is downloadable');

$missingKeyChild = $readyChild;
$missingKeyChild['id'] = 'child-no-key';
$missingKeyChild['archive_object_key'] = '';
$missingKeyPayload = Ms365ArchiveDownloadService::fromRestoreChild($missingKeyChild, $now);
assert_eq(false, $missingKeyPayload['archive_download_ready'], 'missing object key is not ready');

$expiredChild = $readyChild;
$expiredChild['id'] = 'child-expired';
$expiredChild['archive_expires_at'] = $now - 60;
$expiredPayload = Ms365ArchiveDownloadService::fromRestoreChild($expiredChild, $now);
assert_true($expiredPayload['archive_expired'], 'past expiry is marked expired');
assert_eq(false, $expiredPayload['archive_download_ready'], 'expired archive is not download ready');

$children = [
    ['id' => 'tenant-child', 'restore_mode' => 'tenant', 'status' => 'success'],
    $readyChild,
];
$batchPayload = Ms365ArchiveDownloadService::forRestoreChildren($children);
assert_true($batchPayload['archive_download_ready'], 'forRestoreChildren finds archive child among siblings');

assert_true(Ms365ArchiveDownloadService::isDownloadableStatus('success'), 'success is downloadable status');
assert_true(Ms365ArchiveDownloadService::isDownloadableStatus('partial_success'), 'partial_success is downloadable status');
assert_eq(false, Ms365ArchiveDownloadService::isDownloadableStatus('running'), 'running is not downloadable status');
assert_true(Ms365ArchiveDownloadService::isExpired($now - 1, $now), 'isExpired true when now >= expires');
assert_eq(false, Ms365ArchiveDownloadService::isExpired($now + 1, $now), 'isExpired false when expiry in future');
assert_eq(false, Ms365ArchiveDownloadService::isExpired(null, $now), 'null expiry is not expired');

if ($failures > 0) {
    echo "\n{$failures} test(s) failed.\n";
    exit(1);
}

echo "\nAll tests passed.\n";
exit(0);
