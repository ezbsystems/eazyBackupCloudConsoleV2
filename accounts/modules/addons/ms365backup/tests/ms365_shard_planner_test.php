<?php
declare(strict_types=1);

/**
 * Unit tests for ResourceShardPlanner whale-scale expansion.
 *
 * Run: php accounts/modules/addons/ms365backup/tests/ms365_shard_planner_test.php
 */

$root = dirname(__DIR__, 4);
require_once $root . '/init.php';
require_once dirname(__DIR__, 2) . '/cloudstorage/lib/Ms365BackupBootstrap.php';
cloudstorage_load_ms365backup();

use Ms365Backup\BackupScope;
use Ms365Backup\PhysicalBackupJob;
use Ms365Backup\PhysicalKeyHelper;
use Ms365Backup\ResourceShardPlanner;
use Ms365Backup\ShardRunAggregateService;
use Ms365Backup\TenantResource;

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

$siteResource = [
    'id' => 'sharepoint_site:site-abc',
    'resource_type' => TenantResource::TYPE_SHAREPOINT_SITE,
    'graph_id' => 'site-abc',
    'display_name' => 'Test Site',
    'meta' => [
        'drives' => [
            ['id' => 'drive-1', 'name' => 'Documents', 'size_bytes' => 10, 'item_count' => 5],
            ['id' => 'drive-2', 'name' => 'Archive', 'size_bytes' => 20, 'item_count' => 8],
        ],
    ],
];

$scope = new BackupScope([
    BackupScope::FILES => true,
    BackupScope::LISTS => true,
]);

$siteJob = new PhysicalBackupJob(
    'site:site-abc',
    $siteResource,
    [['id' => 'sharepoint_site:site-abc', 'resource_type' => TenantResource::TYPE_SHAREPOINT_SITE]],
    $scope,
    PhysicalBackupJob::STATUS_RUNNABLE,
);

$planner = new ResourceShardPlanner();
$expanded = $planner->expand(['site:site-abc' => $siteJob], ['sharepoint_site:site-abc' => $siteResource]);

assert_true(isset($expanded['drive:drive-1']), 'drive-1 job exists');
assert_true(isset($expanded['drive:drive-2']), 'drive-2 job exists');
assert_true(isset($expanded['site:site-abc']), 'lists-only site job remains');

$driveJob = $expanded['drive:drive-1'];
assert_true($driveJob->parentPhysicalKey() === 'site:site-abc', 'drive parent is site');
assert_true($driveJob->scope->isEnabled(BackupScope::FILES), 'drive job has files');
assert_true(!$driveJob->scope->isEnabled(BackupScope::LISTS), 'drive job excludes lists');

$listsJob = $expanded['site:site-abc'];
assert_true(!$listsJob->scope->isEnabled(BackupScope::FILES), 'site lists job excludes files');
assert_true($listsJob->scope->isEnabled(BackupScope::LISTS), 'site lists job has lists');

$kopiaPath = PhysicalKeyHelper::kopiaSourcePath('tenant-guid', 'drive:drive-1', ['_site_id' => 'site-abc']);
assert_true(str_contains($kopiaPath, '/sites/'), 'sharepoint drive kopia path under sites');

$agg = ShardRunAggregateService::aggregateForRestore([
    [
        'id' => 'run-1',
        'physical_key' => 'drive:drive-1',
        'scope_json' => json_encode(['_site_id' => 'site-abc', 'files' => true]),
        'graph_id' => 'site-abc',
        'resource_type' => TenantResource::TYPE_SHAREPOINT_SITE,
    ],
]);
assert_true(($agg[0]['physical_key'] ?? '') === 'site:site-abc', 'drive run aggregates under site parent');

$listSiteResource = $siteResource;
$listSiteResource['meta']['lists'] = [
    ['id' => 'list-small', 'display_name' => 'Small', 'item_count' => 1000],
    ['id' => 'list-medium', 'display_name' => 'Medium', 'item_count' => 60000],
    ['id' => 'list-whale', 'display_name' => 'Whale', 'item_count' => 600000],
];
$listSiteJob = new PhysicalBackupJob(
    'site:site-abc',
    $listSiteResource,
    [['id' => 'sharepoint_site:site-abc', 'resource_type' => TenantResource::TYPE_SHAREPOINT_SITE]],
    new BackupScope([BackupScope::LISTS => true]),
    PhysicalBackupJob::STATUS_RUNNABLE,
);
$listExpanded = $planner->expand(['site:site-abc' => $listSiteJob], ['sharepoint_site:site-abc' => $listSiteResource]);

assert_true(isset($listExpanded['list:list-medium']), '60k list gets dedicated job');
assert_true(isset($listExpanded['list:list-whale#shard:0']), '600k list gets time-range shards');
assert_true(!isset($listExpanded['list:list-small']), 'small list stays on site job');
$siteListsJob = $listExpanded['site:site-abc'];
$excluded = $siteListsJob->primaryResource['meta']['excluded_list_ids'] ?? [];
assert_true(in_array('list-medium', $excluded, true) && in_array('list-whale', $excluded, true), 'large lists excluded from site job');

$listKopia = PhysicalKeyHelper::kopiaSourcePath('tenant-guid', 'list:list-medium', ['_site_id' => 'site-abc']);
assert_true(str_contains($listKopia, '/lists/list-medium'), 'list job kopia path');

if ($failures > 0) {
    echo "\n{$failures} test(s) failed.\n";
    exit(1);
}

echo "\nAll ms365_shard_planner tests passed.\n";
exit(0);
