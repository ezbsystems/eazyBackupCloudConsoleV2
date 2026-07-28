<?php
declare(strict_types=1);

/**
 * Aggregate parent key for SharePoint drive shards.
 *
 * Run: php accounts/modules/addons/ms365backup/tests/ms365_physical_key_aggregate_test.php
 */

$root = dirname(__DIR__, 4);
require_once $root . '/init.php';
require_once dirname(__DIR__, 2) . '/cloudstorage/lib/Ms365BackupBootstrap.php';
cloudstorage_load_ms365backup();

use Ms365Backup\PhysicalKeyHelper;

$failures = 0;

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

$siteId = 'thedeetken.sharepoint.com,53358f2e-5be1-4680-93ec-56cb916d12bd,dedb5d2f-766b-4abc-ac66-3d2f6a96176e';
$driveId = 'b!Lo81U-FbgEaT7FbLkW0SvS9d295rdrxKrGY9L2qWF24Sz0cFE5BDR7H4es0msZTd';
$pk = 'drive:' . $driveId . '#shard:3';

$agg = PhysicalKeyHelper::aggregateParentKey($pk, [
    'scope_json' => json_encode([
        'files' => true,
        '_site_id' => $siteId,
        '_drive_id' => $driveId,
        '_shard' => [
            'parent_physical_key' => 'drive:' . $driveId,
            'index' => 3,
            'total' => 32,
        ],
    ], JSON_THROW_ON_ERROR),
]);

assert_eq('site:' . $siteId, $agg, 'drive shard with drive-keyed parent uses _site_id for site hub');

$aggSiteParent = PhysicalKeyHelper::aggregateParentKey($pk, [
    'scope_json' => json_encode([
        'files' => true,
        '_site_id' => $siteId,
        '_shard' => [
            'parent_physical_key' => 'site:' . $siteId,
            'index' => 0,
            'total' => 1,
        ],
    ], JSON_THROW_ON_ERROR),
]);

assert_eq('site:' . $siteId, $aggSiteParent, 'drive with site-keyed shard parent keeps site parent');

if ($failures > 0) {
    echo "\n{$failures} test(s) failed.\n";
    exit(1);
}

echo "\nAll ms365_physical_key_aggregate tests passed.\n";
exit(0);
