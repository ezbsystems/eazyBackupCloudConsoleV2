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

// SharePoint planner guard: tiny library with huge shared quota stays unsharded.
$quotaBytes = 200 * 1024 * 1024 * 1024;
$guardSiteResource = [
    'id' => 'sharepoint_site:site-guard',
    'resource_type' => TenantResource::TYPE_SHAREPOINT_SITE,
    'graph_id' => 'site-guard',
    'display_name' => 'Guard Site',
    'meta' => [
        'drives' => [
            [
                'id' => 'drive-tiny',
                'name' => 'Documents',
                'size_bytes' => $quotaBytes,
                'item_count' => 3,
                'item_count_reliable' => true,
            ],
            [
                'id' => 'drive-whale',
                'name' => 'Clients',
                'size_bytes' => $quotaBytes,
                'item_count' => 500000,
                'item_count_reliable' => true,
            ],
        ],
    ],
];
$guardSiteJob = new PhysicalBackupJob(
    'site:site-guard',
    $guardSiteResource,
    [['id' => 'sharepoint_site:site-guard', 'resource_type' => TenantResource::TYPE_SHAREPOINT_SITE]],
    new BackupScope([BackupScope::FILES => true]),
    PhysicalBackupJob::STATUS_RUNNABLE,
);
$guardExpanded = $planner->expand(
    ['site:site-guard' => $guardSiteJob],
    ['sharepoint_site:site-guard' => $guardSiteResource],
);
assert_true(isset($guardExpanded['drive:drive-tiny']), 'tiny drive job exists');
assert_true(!isset($guardExpanded['drive:drive-tiny#shard:0']), '3-item library is not sharded despite huge quota');
assert_true(isset($guardExpanded['drive:drive-whale#shard:0']), 'large reliable library still shards');

// Drive-scoped hint must not inherit sibling counts from parent meta.drives[].
$scopedDriveResource = [
    'meta' => [
        'drive_id' => 'drive-tiny',
        'item_count' => 0,
        'item_count_reliable' => true,
        'drives' => [
            ['item_count' => 500000],
        ],
    ],
];
assert_true(
    PhysicalKeyHelper::itemCountHint($scopedDriveResource) === 0,
    'drive_id scope ignores sibling drive sums',
);
$zeroDriveExpanded = $guardExpanded['drive:drive-tiny'];
assert_true(
    ($zeroDriveExpanded->primaryResource['meta']['item_count'] ?? -1) === 3,
    'tiny drive keeps its own item_count after expansion',
);
assert_true(
    !array_key_exists('drives', $zeroDriveExpanded->primaryResource['meta'] ?? []),
    'per-drive jobs strip parent meta.drives[]',
);

// Reliable 162k-item library produces bounded count-derived shards.
$largeDriveResource = [
    'id' => 'sharepoint_site:site-large',
    'resource_type' => TenantResource::TYPE_SHAREPOINT_SITE,
    'graph_id' => 'site-large',
    'display_name' => 'Large Library Site',
    'meta' => [
        'drives' => [
            [
                'id' => 'drive-large',
                'name' => 'Archive',
                'size_bytes' => 10,
                'item_count' => 162000,
                'item_count_reliable' => true,
            ],
        ],
    ],
];
$largeSiteJob = new PhysicalBackupJob(
    'site:site-large',
    $largeDriveResource,
    [['id' => 'sharepoint_site:site-large', 'resource_type' => TenantResource::TYPE_SHAREPOINT_SITE]],
    new BackupScope([BackupScope::FILES => true]),
    PhysicalBackupJob::STATUS_RUNNABLE,
);
$largeExpanded = $planner->expand(
    ['site:site-large' => $largeSiteJob],
    ['sharepoint_site:site-large' => $largeDriveResource],
);
$largeShardCount = 0;
foreach (array_keys($largeExpanded) as $key) {
    if (str_starts_with($key, 'drive:drive-large#shard:')) {
        ++$largeShardCount;
    }
}
assert_true($largeShardCount === 11, '162k reliable items produce 11 bounded shards');

// Unknown count stays conservative (quota alone does not shard).
$unknownSiteResource = [
    'id' => 'sharepoint_site:site-unknown',
    'resource_type' => TenantResource::TYPE_SHAREPOINT_SITE,
    'graph_id' => 'site-unknown',
    'display_name' => 'Unknown Count Site',
    'meta' => [
        'drives' => [
            [
                'id' => 'drive-unknown',
                'name' => 'Mystery',
                'size_bytes' => $quotaBytes,
                'item_count' => 0,
                'item_count_reliable' => false,
            ],
        ],
    ],
];
$unknownSiteJob = new PhysicalBackupJob(
    'site:site-unknown',
    $unknownSiteResource,
    [['id' => 'sharepoint_site:site-unknown', 'resource_type' => TenantResource::TYPE_SHAREPOINT_SITE]],
    new BackupScope([BackupScope::FILES => true]),
    PhysicalBackupJob::STATUS_RUNNABLE,
);
$unknownExpanded = $planner->expand(
    ['site:site-unknown' => $unknownSiteJob],
    ['sharepoint_site:site-unknown' => $unknownSiteResource],
);
assert_true(isset($unknownExpanded['drive:drive-unknown']), 'unknown-count drive job exists');
assert_true(!isset($unknownExpanded['drive:drive-unknown#shard:0']), 'unknown count stays unsharded');

// OneDrive byte sharding remains unchanged.
$oneDriveResource = [
    'id' => 'user_onedrive:user-1',
    'resource_type' => TenantResource::TYPE_USER_ONEDRIVE,
    'graph_id' => 'drive-od',
    'display_name' => 'User OneDrive',
    'meta' => [
        'drive_id' => 'drive-od',
        'size_bytes' => 150 * 1024 * 1024 * 1024,
        'item_count' => 0,
    ],
];
$oneDriveJob = new PhysicalBackupJob(
    'drive:drive-od',
    $oneDriveResource,
    [['id' => 'user_onedrive:user-1', 'resource_type' => TenantResource::TYPE_USER_ONEDRIVE]],
    new BackupScope([BackupScope::FILES => true]),
    PhysicalBackupJob::STATUS_RUNNABLE,
);
$oneDriveExpanded = $planner->expand(
    ['drive:drive-od' => $oneDriveJob],
    ['user_onedrive:user-1' => $oneDriveResource],
);
assert_true(isset($oneDriveExpanded['drive:drive-od#shard:0']), 'OneDrive still shards on byte threshold');

$remainderSegment = PhysicalKeyHelper::MAIL_REMAINDER_SEGMENT;
$smallMailUserResource = [
    'id' => 'user:u-small',
    'resource_type' => TenantResource::TYPE_USER,
    'graph_id' => 'u-small',
    'display_name' => 'Small Mailbox',
    'meta' => [
        'size_bytes' => 10 * 1024 * 1024 * 1024,
    ],
];
$smallMailUserJob = new PhysicalBackupJob(
    'user:u-small',
    $smallMailUserResource,
    [['id' => 'user:u-small', 'resource_type' => TenantResource::TYPE_USER]],
    new BackupScope([BackupScope::MAIL => true]),
    PhysicalBackupJob::STATUS_RUNNABLE,
);
$smallMailExpanded = $planner->expand(
    ['user:u-small' => $smallMailUserJob],
    ['user:u-small' => $smallMailUserResource],
);
assert_true(
    count($smallMailExpanded) === 1 && isset($smallMailExpanded['user:u-small']),
    'below-threshold mailbox stays a single user job',
);

$largeMailUserResource = [
    'id' => 'user:u-large',
    'resource_type' => TenantResource::TYPE_USER,
    'graph_id' => 'u-large',
    'display_name' => 'Large Mailbox',
    'meta' => [
        'size_bytes' => 200 * 1024 * 1024 * 1024,
    ],
];
$largeMailUserJob = new PhysicalBackupJob(
    'user:u-large',
    $largeMailUserResource,
    [['id' => 'user:u-large', 'resource_type' => TenantResource::TYPE_USER]],
    new BackupScope([BackupScope::MAIL => true]),
    PhysicalBackupJob::STATUS_RUNNABLE,
);
$largeMailExpanded = $planner->expand(
    ['user:u-large' => $largeMailUserJob],
    ['user:u-large' => $largeMailUserResource],
);
assert_true(count($largeMailExpanded) === 4, 'large mailbox expands to four mail shards');
foreach (['inbox', 'sentitems', 'archive', $remainderSegment] as $segment) {
    $key = 'user:u-large#mail:' . $segment;
    assert_true(isset($largeMailExpanded[$key]), 'mail shard exists: ' . $segment);
    assert_true($largeMailExpanded[$key]->shardTotal === 4, 'mail shard total includes remainder: ' . $segment);
}
assert_true(
    ($largeMailExpanded['user:u-large#mail:' . $remainderSegment]->shardIndex ?? -1) === 3,
    'remainder shard is last',
);

$opaqueMailUserResource = [
    'id' => 'user:u-opaque',
    'resource_type' => TenantResource::TYPE_USER,
    'graph_id' => 'u-opaque',
    'display_name' => 'Opaque Folders Mailbox',
    'meta' => [
        'size_bytes' => 200 * 1024 * 1024 * 1024,
        'mail_folders' => [
            ['id' => 'folder-big-1', 'displayName' => 'Big 1', 'size_bytes' => 120 * 1024 * 1024 * 1024],
            ['id' => 'folder-small', 'displayName' => 'Small', 'size_bytes' => 1024],
        ],
    ],
];
$opaqueMailUserJob = new PhysicalBackupJob(
    'user:u-opaque',
    $opaqueMailUserResource,
    [['id' => 'user:u-opaque', 'resource_type' => TenantResource::TYPE_USER]],
    new BackupScope([BackupScope::MAIL => true]),
    PhysicalBackupJob::STATUS_RUNNABLE,
);
$opaqueMailExpanded = $planner->expand(
    ['user:u-opaque' => $opaqueMailUserJob],
    ['user:u-opaque' => $opaqueMailUserResource],
);
assert_true(count($opaqueMailExpanded) === 2, 'inventory mail_folders yields one large shard plus remainder');
assert_true(isset($opaqueMailExpanded['user:u-opaque#mail:folder-big-1']), 'large inventory folder gets dedicated shard');
assert_true(isset($opaqueMailExpanded['user:u-opaque#mail:' . $remainderSegment]), 'inventory path still emits remainder');
assert_true(!isset($opaqueMailExpanded['user:u-opaque#mail:folder-small']), 'small inventory folder is not a dedicated shard');

if ($failures > 0) {
    echo "\n{$failures} test(s) failed.\n";
    exit(1);
}

echo "\nAll ms365_shard_planner tests passed.\n";
exit(0);
