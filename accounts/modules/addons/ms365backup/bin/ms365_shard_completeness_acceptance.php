#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Production acceptance checks for SharePoint shard completeness (plan step 6).
 *
 * Usage:
 *   php modules/addons/ms365backup/bin/ms365_shard_completeness_acceptance.php [batch_run_id]
 *
 * Defaults to batch 57aaa06e-f872-4388-849d-98a4bff3b792 (Deetken tenant 6).
 */

$init = dirname(__DIR__, 4) . '/init.php';
if (!is_file($init)) {
    fwrite(STDERR, "WHMCS init.php not found\n");
    exit(1);
}
require_once $init;
require_once dirname(__DIR__) . '/ms365backup.php';
require_once dirname(__DIR__) . '/ms365backup_autoload.php';

use Ms365Backup\BackupScope;
use Ms365Backup\Fleet\ReleaseRepository;
use Ms365Backup\Fleet\BrowseBinaryInstaller;
use Ms365Backup\KopiaSnapshotBrowseService;
use Ms365Backup\Ms365BatchRunRepository;
use Ms365Backup\Ms365EngineConfig;
use Ms365Backup\PhysicalBackupJob;
use Ms365Backup\PhysicalKeyHelper;
use Ms365Backup\ResourceShardPlanner;
use Ms365Backup\RestoreJobService;
use Ms365Backup\RestoreTreeBrowseService;
use Ms365Backup\SharePointShardSourceResolver;
use Ms365Backup\ShardRunAggregateService;
use Ms365Backup\TenantResource;
use WHMCS\Database\Capsule;

$batchId = trim((string) ($argv[1] ?? '57aaa06e-f872-4388-849d-98a4bff3b792'));
if ($batchId === '') {
    fwrite(STDERR, "batch_run_id required\n");
    exit(1);
}

/** @var list<array{name: string, status: string, detail: string}> */
$results = [];
$pass = 0;
$fail = 0;
$blocked = 0;
$skip = 0;

$record = static function (string $name, string $status, string $detail = '') use (&$results, &$pass, &$fail, &$blocked, &$skip): void {
    $results[] = ['name' => $name, 'status' => $status, 'detail' => $detail];
    match ($status) {
        'pass' => ++$pass,
        'fail' => ++$fail,
        'blocked' => ++$blocked,
        default => ++$skip,
    };
};

// --- release prerequisites ---
$addonConfig = function_exists('ms365backup_config') ? ms365backup_config() : [];
$addonVersion = (string) ($addonConfig['version'] ?? '');
if ($addonVersion === '') {
    $addonVersion = (string) (Capsule::table('tbladdonmodules')->where('module', 'ms365backup')->where('setting', 'version')->value('value') ?? '');
}
$phpReady = class_exists(SharePointShardSourceResolver::class)
    && method_exists(KopiaSnapshotBrowseService::class, 'listDirectoryMultiSource');
$cacheNs = 'unknown';
if (class_exists(RestoreTreeBrowseService::class)) {
    $ref = new ReflectionClass(RestoreTreeBrowseService::class);
    $cacheNs = (string) ($ref->getConstant('BROWSE_CACHE_NAMESPACE') ?: 'unknown');
}
$minWorker = method_exists(Ms365EngineConfig::class, 'multiSourceBrowseMinWorkerVersion')
    ? Ms365EngineConfig::multiSourceBrowseMinWorkerVersion()
    : '0.4.28';
$browseStatus = BrowseBinaryInstaller::status();
$installedWorker = trim((string) ($browseStatus['installed_version'] ?? ''));
$workerReady = $installedWorker !== ''
    && $minWorker !== ''
    && ReleaseRepository::compareVersions($installedWorker, $minWorker) >= 0;
$phpReady = $phpReady && $cacheNs === 'v25-sharepoint-shard-sources';

$record(
    'release_addon_version',
    $addonVersion >= '1.52.41' ? 'pass' : 'fail',
    'addon=' . ($addonVersion !== '' ? $addonVersion : 'unknown')
);
$record(
    'release_browse_cache_namespace',
    $cacheNs === 'v25-sharepoint-shard-sources' ? 'pass' : 'fail',
    'namespace=' . $cacheNs
);
$record(
    'release_worker_binary',
    $workerReady ? 'pass' : 'blocked',
    'installed=' . ($installedWorker !== '' ? $installedWorker : 'missing')
        . ' required>=' . $minWorker
        . ' status=' . ($browseStatus['status'] ?? 'unknown')
);
$record(
    'release_php_classes',
    $phpReady ? 'pass' : 'blocked',
    $phpReady ? 'SharePointShardSourceResolver ready' : 'PHP shard completeness code not deployed'
);

// --- batch context ---
$children = Ms365BatchRunRepository::getChildrenForBatch($batchId);
if ($children === []) {
    $record('batch_exists', 'fail', 'no children for batch ' . $batchId);
    echo json_encode([
        'batch_run_id' => $batchId,
        'summary' => compact('pass', 'fail', 'blocked', 'skip'),
        'checks' => $results,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit($fail > 0 ? 1 : 0);
}
$record('batch_exists', 'pass', 'children=' . count($children));

$tenantId = (int) ($children[0]['tenant_record_id'] ?? 0);
$tenant = (array) Capsule::table('ms365_tenant_records')->where('id', $tenantId)->first();
if ($tenant === []) {
    $record('tenant_record', 'fail', 'tenant_record_id=' . $tenantId);
    exit(1);
}

$insightDriveBase = 'drive:b!Lo81U-FbgEaT7FbLkW0SvS9d295rdrxKrGY9L2qWF27VFCLA9iThR71yC3ptv_6o';
$insightShards = [5, 11, 31];
$insightChild = null;
foreach ($children as $child) {
    if ((string) ($child['physical_key'] ?? '') === $insightDriveBase . '#shard:5') {
        $insightChild = $child;
        break;
    }
}
if ($insightChild === null) {
    $record('insight_documents_child', 'fail', 'shard:5 child missing for Deetken Insight Documents');
} else {
    $record('insight_documents_child', 'pass', 'child_run_id=' . ($insightChild['id'] ?? ''));
}

// --- shard group resolution ---
if ($phpReady && $insightChild !== null) {
    $group = SharePointShardSourceResolver::resolveDriveGroupsForBatch($batchId);
    $matched = null;
    foreach ($group as $g) {
        if (($g['drive_id'] ?? '') === 'b!Lo81U-FbgEaT7FbLkW0SvS9d295rdrxKrGY9L2qWF27VFCLA9iThR71yC3ptv_6o') {
            $matched = $g;
            break;
        }
    }
    if ($matched === null) {
        $record('insight_shard_group', 'fail', 'Documents drive group not resolved');
    } else {
        $indices = array_map(static fn (array $m): int => (int) ($m['shard_index'] ?? -1), $matched['members'] ?? []);
        sort($indices);
        $record(
            'insight_shard_group',
            ($matched['is_sharded'] ?? false) && count($indices) === 32 ? 'pass' : 'fail',
            'shards=' . count($indices) . ' sample=' . implode(',', array_slice($indices, 0, 5)) . '…'
        );
        $ctx = SharePointShardSourceResolver::buildBrowseContext(
            $batchId,
            $insightChild,
            $tenant,
            PhysicalKeyHelper::kopiaSourcePath(
                (string) ($tenant['azure_tenant_id'] ?? ''),
                $insightDriveBase,
                ['_site_id' => 'thedeetken.sharepoint.com,53358f2e-5be1-4680-93ec-56cb916d12bd,dedb5d2f-766b-4abc-ac66-3d2f6a96176e'],
            ) . '/content',
            (string) ($insightChild['manifest_id'] ?? ''),
        );
        $record(
            'insight_multi_source_context',
            ($ctx['use_multi_source'] ?? false) && count($ctx['sources'] ?? []) === 32
                ? ($workerReady ? 'pass' : 'blocked')
                : 'fail',
            'use_multi=' . (($ctx['use_multi_source'] ?? false) ? 'yes' : 'no')
                . ' sources=' . count($ctx['sources'] ?? [])
                . ($workerReady ? '' : ' (worker<' . $minWorker . ')')
        );
    }
} elseif ($insightChild !== null) {
    $record('insight_shard_group', 'blocked', 'PHP resolver not deployed');
}

// --- browse union: Deetken Insight Documents content ---
if ($phpReady && $workerReady && $insightChild !== null) {
    $contentPath = PhysicalKeyHelper::kopiaSourcePath(
        (string) ($tenant['azure_tenant_id'] ?? ''),
        $insightDriveBase,
        ['_site_id' => 'thedeetken.sharepoint.com,53358f2e-5be1-4680-93ec-56cb916d12bd,dedb5d2f-766b-4abc-ac66-3d2f6a96176e'],
    ) . '/content';
    try {
        $browse = RestoreTreeBrowseService::list(
            $tenant,
            (string) ($insightChild['manifest_id'] ?? ''),
            $contentPath,
            $insightChild,
            $batchId,
            500,
            0,
        );
        $files = array_values(array_filter(
            $browse['entries'] ?? [],
            static fn (array $e): bool => ($e['type'] ?? '') === 'file',
        ));
        $shardRefCount = 0;
        foreach ($files as $file) {
            $refs = $file['source_refs'] ?? [];
            if (is_array($refs) && $refs !== []) {
                ++$shardRefCount;
            }
        }
        $record(
            'insight_documents_union_files',
            count($files) >= 3 ? 'pass' : 'fail',
            'file_count=' . count($files) . ' with_source_refs=' . $shardRefCount
                . ' total=' . ($browse['total_count'] ?? '?')
        );
    } catch (Throwable $e) {
        $record('insight_documents_union_files', 'fail', $e->getMessage());
    }

    // folder selection fan-out
    try {
        $folderItem = [
            'type' => 'folder',
            'path' => $contentPath,
            'source_refs' => array_map(static function (int $idx) use ($children, $insightDriveBase, $contentPath): array {
                foreach ($children as $child) {
                    if ((string) ($child['physical_key'] ?? '') === $insightDriveBase . '#shard:' . $idx) {
                        return [
                            'child_run_id' => (string) ($child['id'] ?? ''),
                            'manifest_id' => (string) ($child['manifest_id'] ?? ''),
                            'source_path' => $contentPath,
                        ];
                    }
                }
                return [];
            }, $insightShards),
        ];
        $folderItem['source_refs'] = array_values(array_filter($folderItem['source_refs']));
        $expanded = RestoreJobService::expandAndValidateSelectionItems($batchId, [$folderItem], $tenant);
        $record(
            'insight_folder_fanout',
            count($expanded) === 3 ? 'pass' : 'fail',
            'expanded_items=' . count($expanded)
        );
    } catch (Throwable $e) {
        $record('insight_folder_fanout', 'fail', $e->getMessage());
    }
} else {
    $record('insight_documents_union_files', 'blocked', 'requires PHP+worker deploy');
    $record('insight_folder_fanout', 'blocked', 'requires PHP+worker deploy');
}

// --- large hub library pagination (Clients) ---
$clientsChild = null;
foreach ($children as $child) {
    if ((string) ($child['physical_key'] ?? '') === 'drive:b!Lo81U-FbgEaT7FbLkW0SvS9d295rdrxKrGY9L2qWF25oTPF0fcbOT5NSyo-X6E33#shard:0') {
        $clientsChild = $child;
        break;
    }
}
if ($phpReady && $workerReady && $clientsChild !== null) {
    $clientsPath = PhysicalKeyHelper::kopiaSourcePath(
        (string) ($tenant['azure_tenant_id'] ?? ''),
        'drive:b!Lo81U-FbgEaT7FbLkW0SvS9d295rdrxKrGY9L2qWF25oTPF0fcbOT5NSyo-X6E33',
        json_decode((string) ($clientsChild['scope_json'] ?? '{}'), true) ?: [],
    ) . '/content';
    try {
        $page1 = RestoreTreeBrowseService::list($tenant, (string) ($clientsChild['manifest_id'] ?? ''), $clientsPath, $clientsChild, $batchId, 50, 0);
        $page2 = RestoreTreeBrowseService::list($tenant, (string) ($clientsChild['manifest_id'] ?? ''), $clientsPath, $clientsChild, $batchId, 50, 50);
        $names1 = array_map(static fn (array $e): string => (string) ($e['name'] ?? ''), $page1['entries'] ?? []);
        $names2 = array_map(static fn (array $e): string => (string) ($e['name'] ?? ''), $page2['entries'] ?? []);
        $overlap = array_intersect($names1, $names2);
        $record(
            'clients_library_pagination',
            ($page1['has_more'] ?? false) && $overlap === [] && count($names1) === 50 ? 'pass' : 'fail',
            'page1=' . count($names1) . ' page2=' . count($names2) . ' overlap=' . count($overlap)
                . ' has_more=' . (($page1['has_more'] ?? false) ? 'yes' : 'no')
        );
    } catch (Throwable $e) {
        $record('clients_library_pagination', 'fail', $e->getMessage());
    }
} else {
    $record('clients_library_pagination', 'blocked', 'requires PHP+worker deploy or Clients shard child');
}

// --- 27 zero-item lists (Deetken Insight site lists) ---
$listsChild = null;
foreach ($children as $child) {
    if ((string) ($child['user_display_name'] ?? '') === 'Deetken Insight'
        && str_starts_with((string) ($child['physical_key'] ?? ''), 'site:')) {
        $listsChild = $child;
        break;
    }
}
if ($phpReady && $workerReady && $listsChild !== null) {
    $listsPath = PhysicalKeyHelper::kopiaSourcePath(
        (string) ($tenant['azure_tenant_id'] ?? ''),
        (string) ($listsChild['physical_key'] ?? ''),
        json_decode((string) ($listsChild['scope_json'] ?? '{}'), true) ?: [],
    ) . '/lists';
    try {
        $listsBrowse = RestoreTreeBrowseService::list(
            $tenant,
            (string) ($listsChild['manifest_id'] ?? ''),
            $listsPath,
            $listsChild,
            $batchId,
            500,
            0,
        );
        $disabled = array_values(array_filter(
            $listsBrowse['entries'] ?? [],
            static fn (array $e): bool => ($e['selectable'] ?? true) === false
                && str_contains((string) ($e['subtitle'] ?? ''), 'catalog captured'),
        ));
        $record(
            'insight_empty_lists_disabled',
            count($disabled) >= 27 ? 'pass' : 'fail',
            'disabled_catalog_lists=' . count($disabled) . ' total_lists=' . count($listsBrowse['entries'] ?? [])
        );
    } catch (Throwable $e) {
        $record('insight_empty_lists_disabled', 'fail', $e->getMessage());
    }
} else {
    $record('insight_empty_lists_disabled', 'blocked', 'requires PHP+worker deploy or lists child');
}

// --- planner dry run (Documents unsharded, Clients sharded) ---
try {
    $siteResource = [
        'id' => 'sharepoint_site:thedeetken.sharepoint.com,53358f2e-5be1-4680-93ec-56cb916d12bd,dedb5d2f-766b-4abc-ac66-3d2f6a96176e',
        'resource_type' => TenantResource::TYPE_SHAREPOINT_SITE,
        'graph_id' => 'thedeetken.sharepoint.com,53358f2e-5be1-4680-93ec-56cb916d12bd,dedb5d2f-766b-4abc-ac66-3d2f6a96176e',
        'display_name' => 'Deetken Insight',
        'meta' => [
            'drives' => [
                [
                    'id' => 'b!Lo81U-FbgEaT7FbLkW0SvS9d295rdrxKrGY9L2qWF27VFCLA9iThR71yC3ptv_6o',
                    'name' => 'Documents',
                    'size_bytes' => 200 * 1024 * 1024 * 1024,
                    'item_count' => 3,
                    'item_count_reliable' => true,
                ],
                [
                    'id' => 'b!Lo81U-FbgEaT7FbLkW0SvS9d295rdrxKrGY9L2qWF25oTPF0fcbOT5NSyo-X6E33',
                    'name' => 'Clients',
                    'size_bytes' => 200 * 1024 * 1024 * 1024,
                    'item_count' => 162000,
                    'item_count_reliable' => true,
                ],
            ],
        ],
    ];
    $siteJob = new PhysicalBackupJob(
        'site:thedeetken.sharepoint.com,53358f2e-5be1-4680-93ec-56cb916d12bd,dedb5d2f-766b-4abc-ac66-3d2f6a96176e',
        $siteResource,
        [['id' => $siteResource['id'], 'resource_type' => TenantResource::TYPE_SHAREPOINT_SITE]],
        new BackupScope([BackupScope::FILES => true]),
        PhysicalBackupJob::STATUS_RUNNABLE,
    );
    $planner = new ResourceShardPlanner();
    $expanded = $planner->expand(
        ['site:' . $siteResource['graph_id'] => $siteJob],
        [$siteResource['id'] => $siteResource],
    );
    $docsSharded = false;
    foreach (array_keys($expanded) as $key) {
        if (str_starts_with($key, 'drive:b!Lo81U-FbgEaT7FbLkW0SvS9d295rdrxKrGY9L2qWF27VFCLA9iThR71yC3ptv_6o#shard:')) {
            $docsSharded = true;
            break;
        }
    }
    $clientsSharded = false;
    foreach (array_keys($expanded) as $key) {
        if (str_starts_with($key, 'drive:b!Lo81U-FbgEaT7FbLkW0SvS9d295rdrxKrGY9L2qWF25oTPF0fcbOT5NSyo-X6E33#shard:')) {
            $clientsSharded = true;
            break;
        }
    }
    $record(
        'planner_documents_unsharded',
        !$docsSharded && isset($expanded['drive:b!Lo81U-FbgEaT7FbLkW0SvS9d295rdrxKrGY9L2qWF27VFCLA9iThR71yC3ptv_6o']) ? 'pass' : 'fail',
        'docs_sharded=' . ($docsSharded ? 'yes' : 'no')
    );
    $record(
        'planner_clients_sharded',
        $clientsSharded ? 'pass' : 'fail',
        'clients_sharded=' . ($clientsSharded ? 'yes' : 'no')
    );
} catch (Throwable $e) {
    $record('planner_documents_unsharded', 'fail', $e->getMessage());
    $record('planner_clients_sharded', 'skip', $e->getMessage());
}

// --- aggregate roots expose shard members ---
if ($phpReady) {
    $agg = ShardRunAggregateService::aggregateForRestore($children);
    $insightAgg = null;
    foreach ($agg as $row) {
        if (str_contains((string) ($row['display_name'] ?? ''), 'Insight')
            && ($row['is_sharded'] ?? false)) {
            $insightAgg = $row;
            break;
        }
    }
    if ($insightAgg !== null) {
        $record(
            'aggregate_shard_members',
            count($insightAgg['shard_members'] ?? []) >= 32 ? 'pass' : 'fail',
            'members=' . count($insightAgg['shard_members'] ?? [])
        );
    } else {
        $record('aggregate_shard_members', 'skip', 'no sharded Insight aggregate found');
    }
} else {
    $record('aggregate_shard_members', 'blocked', 'PHP not deployed');
}

$exit = $fail > 0 ? 1 : 0;
echo json_encode([
    'batch_run_id' => $batchId,
    'tenant_record_id' => $tenantId,
    'summary' => [
        'pass' => $pass,
        'fail' => $fail,
        'blocked' => $blocked,
        'skip' => $skip,
        'overall' => $fail > 0 ? 'fail' : ($blocked > 0 ? 'blocked' : 'pass'),
    ],
    'checks' => $results,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($exit);
