<?php
declare(strict_types=1);

namespace Ms365Backup;

final class DocumentLibraryBackupService
{
    private const ITEM_SELECT = 'id,name,size,file,folder,parentReference,lastModifiedDateTime,createdDateTime,eTag,webUrl';

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
     *   items_seen: int,
     *   files_metadata: int,
     *   folders_metadata: int,
     *   files_downloaded: int,
     *   files_skipped: int,
     *   bytes_downloaded: int,
     *   removed: int,
     *   resynced: bool
     * }
     */
    public function backupDrive(string $driveId, DriveItemStorage $itemStorage, string $logContext = 'drive'): array
    {
        if ($driveId === '') {
            throw new \InvalidArgumentException('Drive ID is required');
        }

        $stats = [
            'items_seen' => 0,
            'files_metadata' => 0,
            'folders_metadata' => 0,
            'files_downloaded' => 0,
            'files_skipped' => 0,
            'bytes_downloaded' => 0,
            'removed' => 0,
            'resynced' => false,
        ];

        $state = new DeltaSyncState($this->storage, $itemStorage->deltaStatePath());
        $store = new DriveItemStore($this->storage, $itemStorage, $this->runId);
        $deltaPath = "drives/{$driveId}/root/delta";
        $query = ['$select' => self::ITEM_SELECT, '$top' => '200'];

        $runSync = function (bool $resync) use ($state, $store, $deltaPath, $query, $driveId, $logContext, &$stats): void {
            if ($resync) {
                $state->clear();
                $stats['resynced'] = true;
            }
            $resume = $state->hasToken() ? $state->deltaLink() : null;
            $mode = $resume !== null && $resume !== '' ? 'delta' : 'initial';
            $this->logger->info("Document library sync ({$mode})", [
                'context' => $logContext,
                'drive_id' => $driveId,
            ]);

            $outcome = new DeltaPaginationOutcome();
            $monitor = PaginationMonitor::forBackup($this->logger, $logContext . ':' . $driveId);
            foreach ($this->graph->paginateDelta($deltaPath, $query, [], $resume, $monitor, $outcome) as $item) {
                $this->cancellation?->check();
                $stats['items_seen']++;
                $this->processDeltaItem($driveId, $store, $item, $stats);
            }
            if ($outcome->deltaLink !== '') {
                $state->saveDeltaLink($outcome->deltaLink, $this->runId);
            }
        };

        try {
            $runSync(false);
        } catch (GraphDeltaResetException $e) {
            $this->logger->warn('Document library delta reset, resyncing', [
                'context' => $logContext,
                'drive_id' => $driveId,
                'error' => $e->getMessage(),
            ]);
            $runSync(true);
        }

        return $stats;
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, int|bool> $stats
     */
    private function processDeltaItem(string $driveId, DriveItemStore $store, array $item, array &$stats): void
    {
        if (isset($item['@removed']) && is_array($item['@removed'])) {
            $result = $store->writeRemoved($item);
            if ($result['action'] === 'removed') {
                $stats['removed']++;
            }

            return;
        }

        $metaResult = $store->writeMetadata($item);
        if ($metaResult['action'] === 'skip') {
            return;
        }

        if ($metaResult['is_file']) {
            $stats['files_metadata']++;
            $this->downloadFileContent($driveId, $store, $item, $stats);
        } else {
            $stats['folders_metadata']++;
        }
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, int|bool> $stats
     */
    private function downloadFileContent(string $driveId, DriveItemStore $store, array $item, array &$stats): void
    {
        if (!isset($item['file']) || !is_array($item['file'])) {
            $stats['files_skipped']++;

            return;
        }

        $itemId = (string) ($item['id'] ?? '');
        if ($itemId === '') {
            $stats['files_skipped']++;

            return;
        }

        $name = (string) ($item['name'] ?? '');
        $fileName = DriveItemStore::sanitizeFileName($name !== '' ? $name : $itemId . '.bin');
        $destPath = $store->contentPathForItem($itemId, $fileName);

        try {
            $download = $this->graph->downloadToFile(
                "drives/{$driveId}/items/{$itemId}/content",
                $destPath,
            );
            $store->attachContent($itemId, $destPath, $download['bytes'], $download['sha256']);
            $stats['files_downloaded']++;
            $stats['bytes_downloaded'] += $download['bytes'];
        } catch (GraphApiException $e) {
            if ($e->statusCode === 429) {
                $this->logger->warn('File download throttled (429), skipping', [
                    'item_id' => $itemId,
                    'name' => $name,
                ]);
            } else {
                $this->logger->error('File download failed', [
                    'item_id' => $itemId,
                    'name' => $name,
                    'error' => $e->getMessage(),
                    'status' => $e->statusCode,
                ]);
            }
            $stats['files_skipped']++;
        } catch (\Throwable $e) {
            $this->logger->error('File download failed', [
                'item_id' => $itemId,
                'name' => $name,
                'error' => $e->getMessage(),
            ]);
            $stats['files_skipped']++;
        }
    }
}
