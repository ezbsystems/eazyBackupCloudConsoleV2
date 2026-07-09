<?php
declare(strict_types=1);

/**
 * Unit tests for ShardRunAggregateService primary selection.
 *
 * Run: php accounts/modules/addons/ms365backup/tests/ms365_shard_aggregate_test.php
 */

$root = dirname(__DIR__, 4);
require_once $root . '/init.php';
require_once dirname(__DIR__, 2) . '/cloudstorage/lib/Ms365BackupBootstrap.php';
cloudstorage_load_ms365backup();

use Ms365Backup\ShardRunAggregateService;

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

$siteId = 'stchf.sharepoint.com,297208e1-3eaf-45b4-b29c-40a5125d68ff,346e6a93-6fd8-4655-b0e4-acf995cd05eb';
$agg = ShardRunAggregateService::aggregateForRestore([
    [
        'id' => 'site-lists',
        'physical_key' => 'site:' . $siteId,
        'scope_json' => json_encode(['files' => false, 'lists' => true]),
        'user_display_name' => 'STCHF Admin',
        'manifest_id' => 'lists-manifest',
        'stats_json' => json_encode(['files' => 0]),
    ],
    [
        'id' => 'drive-files',
        'physical_key' => 'drive:b!drive-files',
        'scope_json' => json_encode([
            'files' => true,
            'lists' => false,
            '_site_id' => $siteId,
            '_drive_display_name' => 'Documents',
        ]),
        'user_display_name' => 'Documents',
        'manifest_id' => 'files-manifest',
        'stats_json' => json_encode(['files' => 4821]),
    ],
]);

assert_eq('site:' . $siteId, $agg[0]['physical_key'] ?? '', 'aggregates under site parent');
assert_eq('drive-files', $agg[0]['run_id'] ?? '', 'prefers files drive child as primary run');
assert_eq('files-manifest', $agg[0]['manifest_id'] ?? '', 'uses files manifest for restore browse');
assert_eq('STCHF Admin', $agg[0]['display_name'] ?? '', 'site display name wins over drive library name');

if ($failures > 0) {
    echo "\n{$failures} test(s) failed.\n";
    exit(1);
}

echo "\nAll ms365_shard_aggregate tests passed.\n";
exit(0);
