<?php
declare(strict_types=1);

namespace Ms365Backup;

final class SharePointListsBackupService
{
    private const LIST_SELECT = 'id,displayName,list,webUrl';
    private const ITEM_SELECT = 'id,fields,createdDateTime,lastModifiedDateTime,contentType';

    public function __construct(
        private readonly GraphClient $graph,
        private readonly StorageLayout $storage,
        private readonly ProgressLogger $logger,
        private readonly string $runId,
        private readonly ?RunCancellation $cancellation = null,
    ) {
    }

    /**
     * @return array{
     *   lists: int,
     *   items_seen: int,
     *   items_stored: int,
     *   items_updated: int,
     *   removed: int,
     *   lists_resynced: int
     * }
     */
    public function backupSiteLists(string $siteId): array
    {
        $stats = [
            'lists' => 0,
            'items_seen' => 0,
            'items_stored' => 0,
            'items_updated' => 0,
            'removed' => 0,
            'lists_resynced' => 0,
        ];

        $sitePath = GraphSitePaths::sitePath($siteId);
        $lists = [];
        $monitor = PaginationMonitor::forBackup($this->logger, 'sharepoint.lists:' . $siteId);
        foreach ($this->graph->paginate($sitePath . '/lists', ['$select' => self::LIST_SELECT, '$top' => '100'], [], $monitor) as $list) {
            $this->cancellation?->check();
            $lists[] = $list;
        }

        $this->storage->writeJson($this->storage->siteListsCatalogPath($siteId), [
            'fetched_at' => gmdate('c'),
            'value' => $lists,
        ]);
        $stats['lists'] = count($lists);

        foreach ($lists as $list) {
            $listId = (string) ($list['id'] ?? '');
            $listName = (string) ($list['displayName'] ?? $listId);
            if ($listId === '') {
                continue;
            }
            try {
                $listStats = $this->syncList($siteId, $listId, $listName);
                $stats['items_seen'] += $listStats['items_seen'];
                $stats['items_stored'] += $listStats['items_stored'];
                $stats['items_updated'] += $listStats['items_updated'];
                $stats['removed'] += $listStats['removed'];
                if ($listStats['resynced']) {
                    $stats['lists_resynced']++;
                }
            } catch (\Throwable $e) {
                $this->logger->error("SharePoint list failed: {$listName}", ['error' => $e->getMessage()]);
            }
        }

        return $stats;
    }

    /**
     * @return array{items_seen: int, items_stored: int, items_updated: int, removed: int, resynced: bool}
     */
    private function syncList(string $siteId, string $listId, string $listName): array
    {
        $state = new DeltaSyncState($this->storage, $this->storage->siteListDeltaStatePath($siteId, $listId));
        $store = new ListItemStore($this->storage, $siteId, $listId, $this->runId);
        $deltaPath = GraphSitePaths::sitePath($siteId, "lists/{$listId}/items/delta");
        $query = ['$select' => self::ITEM_SELECT, '$top' => '200'];

        $stats = ['items_seen' => 0, 'items_stored' => 0, 'items_updated' => 0, 'removed' => 0, 'resynced' => false];

        $runSync = function (bool $resync) use ($state, $store, $deltaPath, $query, $listName, &$stats): void {
            if ($resync) {
                $state->clear();
                $stats['resynced'] = true;
            }
            $resume = $state->hasToken() ? $state->deltaLink() : null;
            $mode = $resume !== null && $resume !== '' ? 'delta' : 'initial';
            $this->logger->info("SharePoint list sync ({$mode}): {$listName}");

            $outcome = new DeltaPaginationOutcome();
            $monitor = PaginationMonitor::forBackup($this->logger, 'sharepoint.list:' . $listName);
            foreach ($this->graph->paginateDelta($deltaPath, $query, [], $resume, $monitor, $outcome) as $item) {
                $this->cancellation?->check();
                $stats['items_seen']++;
                if (isset($item['@removed']) && is_array($item['@removed'])) {
                    $result = $store->writeRemoved($item);
                    if ($result['action'] === 'removed') {
                        $stats['removed']++;
                    }
                    continue;
                }
                $result = $store->writeItem($item);
                if ($result['action'] === 'created') {
                    $stats['items_stored']++;
                } elseif ($result['action'] === 'updated') {
                    $stats['items_updated']++;
                }
            }
            if ($outcome->deltaLink !== '') {
                $state->saveDeltaLink($outcome->deltaLink, $this->runId);
            }
        };

        try {
            $runSync(false);
        } catch (GraphDeltaResetException $e) {
            $this->logger->warn("SharePoint list delta reset, resyncing: {$listName}", ['error' => $e->getMessage()]);
            $runSync(true);
        }

        return $stats;
    }
}
