<?php
declare(strict_types=1);

namespace Ms365Backup;

final class ContactsBackupService
{
    private const CONTACT_SELECT = 'id,displayName,emailAddresses,phones,companyName,jobTitle,parentFolderId,createdDateTime,lastModifiedDateTime';

    public function __construct(
        private readonly GraphClient $graph,
        private readonly StorageLayout $storage,
        private readonly ProgressLogger $logger,
        private readonly string $runId,
        private readonly ?RunCancellation $cancellation = null,
    ) {
    }

    /**
     * @return array{folders: int, contacts: int, created: int, updated: int, removed: int, folders_resynced: int}
     */
    public function backupUser(string $userId): array
    {
        $this->logger->info('Starting contacts backup', ['user_id' => $userId]);

        $stats = [
            'folders' => 0,
            'contacts' => 0,
            'created' => 0,
            'updated' => 0,
            'removed' => 0,
            'folders_resynced' => 0,
        ];

        $folderMonitor = PaginationMonitor::forBackup($this->logger, 'contacts.folders');
        $folders = [];
        try {
            foreach ($this->graph->paginate("users/{$userId}/contactFolders", ['$top' => '100'], [], $folderMonitor) as $folder) {
                $this->cancellation?->check();
                $folders[] = $folder;
            }
        } catch (GraphApiException $e) {
            $result = ResourceAccessClassifier::classify($e);
            if ($result->skippable) {
                throw ResourceUnavailableException::fromGraph('contacts', $e);
            }
            throw $e;
        }

        $this->storage->writeJson($this->storage->contactsFoldersPath($userId), [
            'fetched_at' => gmdate('c'),
            'value' => $folders,
        ]);
        $stats['folders'] = count($folders);

        foreach ($folders as $folder) {
            $this->cancellation?->check();
            $folderId = (string) ($folder['id'] ?? '');
            $folderName = (string) ($folder['displayName'] ?? $folderId);
            if ($folderId === '') {
                continue;
            }
            try {
                $folderStats = $this->syncContactFolder($userId, $folderId, $folderName);
                $stats['contacts'] += $folderStats['contacts'];
                $stats['created'] += $folderStats['created'];
                $stats['updated'] += $folderStats['updated'];
                $stats['removed'] += $folderStats['removed'];
                if ($folderStats['resynced']) {
                    $stats['folders_resynced']++;
                }
            } catch (\Throwable $e) {
                $this->logger->error("Contact folder failed: {$folderName}", ['error' => $e->getMessage()]);
            }
        }

        $this->logger->info('Contacts backup finished', $stats);

        return $stats;
    }

    /**
     * @return array{contacts: int, created: int, updated: int, removed: int, resynced: bool}
     */
    private function syncContactFolder(string $userId, string $folderId, string $folderName): array
    {
        $state = new DeltaSyncState($this->storage, $this->storage->contactFolderDeltaStatePath($userId, $folderId));
        $itemsDir = $this->storage->contactItemsDir($userId, $folderId);
        $writer = new DeltaItemWriter($this->storage, $itemsDir, $this->runId);
        $deltaPath = "users/{$userId}/contactFolders/{$folderId}/contacts/delta";
        $query = ['$select' => self::CONTACT_SELECT, '$top' => '100'];

        $stats = ['contacts' => 0, 'created' => 0, 'updated' => 0, 'removed' => 0, 'resynced' => false];

        $runSync = function (bool $resync) use ($state, $writer, $deltaPath, $query, $folderName, &$stats): void {
            if ($resync) {
                $state->clear();
                $stats['resynced'] = true;
            }
            $resume = $state->hasToken() ? $state->deltaLink() : null;
            $mode = $resume !== null && $resume !== '' ? 'delta' : 'initial';
            $this->logger->info("Contact folder sync ({$mode}): {$folderName}");

            $outcome = new DeltaPaginationOutcome();
            $monitor = PaginationMonitor::forBackup($this->logger, 'contacts:' . $folderName);
            foreach ($this->graph->paginateDelta($deltaPath, $query, [], $resume, $monitor, $outcome) as $item) {
                $this->cancellation?->check();
                $result = $writer->writeItem($item);
                if ($result['action'] === 'skip') {
                    continue;
                }
                $stats['contacts']++;
                if ($result['action'] === 'created') {
                    $stats['created']++;
                } elseif ($result['action'] === 'updated') {
                    $stats['updated']++;
                } elseif ($result['action'] === 'removed') {
                    $stats['removed']++;
                }
            }
            if ($outcome->deltaLink !== '') {
                $state->saveDeltaLink($outcome->deltaLink, $this->runId);
            }
        };

        try {
            $runSync(false);
        } catch (GraphDeltaResetException $e) {
            $this->logger->warn("Contact folder delta reset, resyncing: {$folderName}", ['error' => $e->getMessage()]);
            $runSync(true);
        }

        return $stats;
    }
}
