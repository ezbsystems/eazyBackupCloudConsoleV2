<?php
declare(strict_types=1);

/**
 * Run: php accounts/modules/addons/ms365backup/tests/ms365_list_shard_range_test.php
 */

$root = dirname(__DIR__, 4);
require_once $root . '/init.php';
require_once dirname(__DIR__, 2) . '/cloudstorage/lib/Ms365BackupBootstrap.php';
cloudstorage_load_ms365backup();

use Ms365Backup\ListShardRangeHelper;

$failures = 0;

function assert_true(bool $cond, string $message): void
{
    global $failures;
    if (!$cond) {
        echo "FAIL: {$message}\n";
        ++$failures;
        return;
    }
    echo "OK: {$message}\n";
}

$ranges = ListShardRangeHelper::rangesForItemCount(600000, 100000, 16);
assert_true(count($ranges) === 6, '600k items produce 6 shards at 100k target');
assert_true(($ranges[0]['start'] ?? '') === ListShardRangeHelper::DEFAULT_START, 'first range starts at default');
assert_true(ListShardRangeHelper::parseSegment(ListShardRangeHelper::segmentForRange('2020-01-01T00:00:00Z', '2021-01-01T00:00:00Z')) !== null, 'segment round-trip');

if ($failures > 0) {
    echo "\n{$failures} test(s) failed.\n";
    exit(1);
}

echo "\nAll ms365_list_shard_range tests passed.\n";
exit(0);
