<?php
declare(strict_types=1);

/**
 * MS365 workload grouping — unit checks for site+drive merge and aggregate alignment.
 *
 * Run: php accounts/modules/addons/ms365backup/tests/ms365_workload_grouping_test.php
 */

$root = dirname(__DIR__, 4);
require_once $root . '/init.php';
require_once dirname(__DIR__, 2) . '/cloudstorage/lib/Ms365BackupBootstrap.php';
cloudstorage_load_ms365backup();

use Ms365Backup\Ms365BatchRunRepository;
use Ms365Backup\Ms365WorkloadGrouping;

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

function assert_near(float $actual, float $expected, float $tolerance, string $message): void
{
    global $failures;
    if (abs($actual - $expected) > $tolerance) {
        echo "FAIL: {$message} (expected ~{$expected}, got {$actual})\n";
        ++$failures;
        return;
    }
    echo "OK: {$message}\n";
}

$siteA = 'stchf.sharepoint.com,4258a7df-79cf-40d0-8f64-54b9c55a0af8,e7593a82-5d61-48a6-8b40-cd5f8b654dcf';
$siteB = 'stchf.sharepoint.com,297208e1-3eaf-45b4-b29c-40a5125d68ff,346e6a93-6fd8-4655-b0e4-acf995cd05eb';

$sharePointBatch = [
    [
        'id' => '10e7fc2f-e418-41be-9a65-55019462f7ab',
        'resource_type' => 'sharepoint_site',
        'status' => 'success',
        'physical_key' => 'site:' . $siteA,
        'user_display_name' => 'Communication site',
        'percent' => 100.0,
    ],
    [
        'id' => '17d1205f-cf1d-45cf-9a6f-f268b6a86c23',
        'resource_type' => 'user',
        'status' => 'success',
        'physical_key' => 'user:086a956e-63af-4a32-8cc4-0b760bbf5bd3',
        'user_display_name' => 'Sue Timanson',
        'percent' => 100.0,
    ],
    [
        'id' => '3d18ad00-72ad-40f5-bbb1-efad43e94c28',
        'resource_type' => 'sharepoint_site',
        'status' => 'success',
        'physical_key' => 'site:' . $siteB,
        'user_display_name' => 'STCHF Admin',
        'percent' => 100.0,
    ],
    [
        'id' => '4113aa75-8601-45e1-89e8-04d0b53217b6',
        'resource_type' => 'user',
        'status' => 'success',
        'physical_key' => 'user:d697400b-ebec-458c-a403-8abb17307fbf',
        'user_display_name' => 'STCHF Admin',
        'percent' => 100.0,
    ],
    [
        'id' => 'b77b8076-754b-4961-b1a0-dc7bdfc3db86',
        'resource_type' => 'sharepoint_site',
        'status' => 'success',
        'physical_key' => 'drive:b!36dYQs950ECPZFS5xVoK-II6WedhXaZIi0DNX4tlTc-MxP7KsdrFTbLFK16rjc9K',
        'user_display_name' => 'Communication site',
        'scope_json' => json_encode(['_site_id' => $siteA]),
        'percent' => 100.0,
    ],
    [
        'id' => 'fd5b4b19-a1d8-40ab-bfe3-77e5f7eaa37a',
        'resource_type' => 'sharepoint_site',
        'status' => 'success',
        'physical_key' => 'drive:b!4QhyKa8-tEWynEClEl1o_5NqbjTYb1VGsOSs-ZXNBet47NJxJZINR4Q_sTH8rPRj',
        'user_display_name' => 'STCHF Admin',
        'scope_json' => json_encode(['_site_id' => $siteB]),
        'percent' => 100.0,
    ],
];

$groups = Ms365WorkloadGrouping::groupChildren($sharePointBatch);
assert_true(count($groups) === 4, 'SharePoint batch groups 6 child runs into 4 workloads');
assert_true(count($sharePointBatch) === 6, 'Fixture has 6 child runs');

$groupedCounts = Ms365WorkloadGrouping::aggregateGroupedCounts($groups);
assert_true((int) $groupedCounts['total_workloads'] === 4, 'Grouped total workloads is 4');
assert_true((int) $groupedCounts['completed_workloads'] === 4, 'All grouped workloads complete');

$agg = Ms365BatchRunRepository::computeAggregates($sharePointBatch);
assert_true((int) ($agg['total_workloads'] ?? 0) === 4, 'computeAggregates total_workloads matches grouped count');
assert_true((int) ($agg['completed_workloads'] ?? 0) === 4, 'computeAggregates completed_workloads matches grouped count');
assert_near((float) ($agg['progress_pct'] ?? 0), 100.0, 0.01, 'All-complete grouped batch shows 100% progress');

$inProgressSiteA = [
    [
        'id' => 'site-a-site',
        'resource_type' => 'sharepoint_site',
        'status' => 'success',
        'physical_key' => 'site:' . $siteA,
        'percent' => 100.0,
    ],
    [
        'id' => 'site-a-drive',
        'resource_type' => 'sharepoint_site',
        'status' => 'running',
        'physical_key' => 'drive:b!test',
        'scope_json' => json_encode(['_site_id' => $siteA]),
        'percent' => 50.0,
        'phase' => 'kopia_upload',
    ],
];
$inProgressGroups = Ms365WorkloadGrouping::groupChildren($inProgressSiteA);
assert_true(count($inProgressGroups) === 1, 'Site+drive in-progress children merge to one group');
$inProgressUnit = Ms365WorkloadGrouping::groupedProgressUnit($inProgressGroups[0]);
assert_near($inProgressUnit, 0.75, 0.01, 'Grouped progress unit averages site success with drive at 50%');

$singleGroupAgg = Ms365BatchRunRepository::computeAggregates($inProgressSiteA);
assert_near((float) ($singleGroupAgg['progress_pct'] ?? 0), 75.0, 0.5, 'Single grouped workload uses grouped progress percent');

$anonymousChildren = array_fill(0, 213, ['status' => 'queued']);
$anonymousGroups = Ms365WorkloadGrouping::groupChildren($anonymousChildren);
assert_true(count($anonymousGroups) === 213, 'Anonymous test children remain one group per child');

exit($failures > 0 ? 1 : 0);
