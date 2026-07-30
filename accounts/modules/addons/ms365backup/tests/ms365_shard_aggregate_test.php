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

function assert_true(bool $value, string $message): void
{
    assert_eq(true, $value, $message);
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

$driveId = 'b!shard-drive-id';
$shardedAgg = ShardRunAggregateService::aggregateForRestore([
    [
        'id' => 'shard-5',
        'status' => 'success',
        'physical_key' => 'drive:' . $driveId . '#shard:5',
        'scope_json' => json_encode(['_site_id' => $siteId, '_drive_id' => $driveId]),
        'manifest_id' => 'manifest-5',
    ],
    [
        'id' => 'shard-11',
        'status' => 'success',
        'physical_key' => 'drive:' . $driveId . '#shard:11',
        'scope_json' => json_encode(['_site_id' => $siteId, '_drive_id' => $driveId]),
        'manifest_id' => 'manifest-11',
    ],
    [
        'id' => 'shard-31',
        'status' => 'success',
        'physical_key' => 'drive:' . $driveId . '#shard:31',
        'scope_json' => json_encode(['_site_id' => $siteId, '_drive_id' => $driveId]),
        'manifest_id' => 'manifest-31',
    ],
    [
        'id' => 'shard-failed',
        'status' => 'failed',
        'physical_key' => 'drive:' . $driveId . '#shard:99',
        'scope_json' => json_encode(['_site_id' => $siteId, '_drive_id' => $driveId]),
        'manifest_id' => 'manifest-99',
    ],
    [
        'id' => 'shard-blank',
        'status' => 'success',
        'physical_key' => 'drive:' . $driveId . '#shard:7',
        'scope_json' => json_encode(['_site_id' => $siteId, '_drive_id' => $driveId]),
        'manifest_id' => '',
    ],
]);

assert_eq(1, count($shardedAgg), 'sharded drive members aggregate to one site group');
$entry = $shardedAgg[0] ?? [];
assert_true((bool) ($entry['is_sharded'] ?? false), 'three successful shards mark group as sharded');
assert_eq(3, (int) ($entry['shard_count'] ?? 0), 'shard_count reflects successful shards only');
assert_eq(
    ['manifest-5', 'manifest-11', 'manifest-31'],
    $entry['manifest_ids'] ?? [],
    'manifest_ids retains every successful shard manifest'
);
assert_eq(3, count($entry['shard_runs'] ?? []), 'shard_runs excludes failed and blank-manifest shards');
assert_eq(3, count($entry['shard_members'] ?? []), 'shard_members lists all successful shard children');
assert_eq('manifest-5', $entry['shard_members'][0]['manifest_id'] ?? '', 'shard_members sorted by shard index');
assert_eq(5, (int) ($entry['shard_members'][0]['shard_index'] ?? -1), 'first shard member index preserved');
assert_eq('manifest-31', $entry['shard_members'][2]['manifest_id'] ?? '', 'last shard member manifest retained');

if ($failures > 0) {
    echo "\n{$failures} test(s) failed.\n";
    exit(1);
}

echo "\nAll ms365_shard_aggregate tests passed.\n";
exit(0);
