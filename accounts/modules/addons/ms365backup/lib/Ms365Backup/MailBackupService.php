<?php
declare(strict_types=1);

namespace Ms365Backup;

final class MailBackupService
{
    private const MESSAGE_SELECT = 'id,subject,receivedDateTime,sentDateTime,from,toRecipients,ccRecipients,bccRecipients,body,bodyPreview,parentFolderId,conversationId,internetMessageId,hasAttachments,importance,isRead,isDraft,flag,categories';

    public function __construct(
        private readonly GraphClient $graph,
        private readonly StorageLayout $storage,
        private readonly ProgressLogger $logger,
        private readonly string $runId = '',
        private readonly ?RunCancellation $cancellation = null,
    ) {
    }

    /**
     * @return array{
     *   folders: int,
     *   messages: int,
     *   messages_created: int,
     *   messages_updated: int,
     *   messages_removed: int,
     *   folders_full: int,
     *   folders_delta: int
     * }
     */
    public function backupUser(string $userId): array
    {
        return $this->backupMailbox(GraphMailboxOwner::user($userId));
    }

  /**
     * @return array{
     *   folders: int,
     *   messages: int,
     *   messages_created: int,
     *   messages_updated: int,
     *   messages_removed: int,
     *   folders_full: int,
     *   folders_delta: int
     * }
     */
    public function backupMailbox(GraphMailboxOwner $owner): array
    {
        $ownerId = $owner->id();
        $this->logger->info('Starting mail backup', [
            'mailbox_id' => $ownerId,
            'mailbox_type' => $owner->isGroup() ? 'group' : 'user',
            'endpoint' => 'mailFolders + mailFolders/{id}/messages/delta',
        ]);

        $stats = [
            'folders' => 0,
            'messages' => 0,
            'messages_created' => 0,
            'messages_updated' => 0,
            'messages_removed' => 0,
            'folders_full' => 0,
            'folders_delta' => 0,
        ];

        $folderMonitor = PaginationMonitor::forBackup($this->logger, 'mail.folders');
        $folders = [];
        try {
            foreach ($this->graph->paginate($owner->graphPath('mailFolders'), ['$top' => '100'], [], $folderMonitor) as $folder) {
                $this->cancellation?->check();
                $folders[] = $folder;
            }
        } catch (GraphApiException $e) {
            $result = ResourceAccessClassifier::classify($e);
            if ($result->skippable) {
                throw ResourceUnavailableException::fromGraph('mail', $e);
            }
            throw $e;
        }
        $this->storage->writeJson($this->storage->mailDir($owner) . '/folders.json', [
            'fetched_at' => gmdate('c'),
            'value' => $folders,
        ]);
        $stats['folders'] = count($folders);
        $this->logger->info('Mail folders listed', ['count' => count($folders)]);

        foreach ($folders as $folder) {
            $this->cancellation?->check();
            $folderId = (string) ($folder['id'] ?? '');
            $folderName = (string) ($folder['displayName'] ?? $folderId);
            if ($folderId === '') {
                continue;
            }
            try {
                $folderStats = $this->syncMailFolder($owner, $folderId, $folderName);
                $stats['messages'] += $folderStats['messages'];
                $stats['messages_created'] += $folderStats['created'];
                $stats['messages_updated'] += $folderStats['updated'];
                $stats['messages_removed'] += $folderStats['removed'];
                if ($folderStats['mode'] === 'delta') {
                    $stats['folders_delta']++;
                } else {
                    $stats['folders_full']++;
                }
                $this->logger->info("Mail folder backed up: {$folderName}", $folderStats);
            } catch (GraphPaginationException $e) {
                $this->logger->error("Mail folder skipped (pagination safety): {$folderName}", [
                    'error' => $e->getMessage(),
                ]);
            } catch (\Throwable $e) {
                $this->logger->error("Mail folder failed: {$folderName}", ['error' => $e->getMessage()]);
            }
        }

        $this->logger->info('Mail backup finished', $stats);

        return $stats;
    }

    /**
     * @return array{messages: int, created: int, updated: int, removed: int, mode: string}
     */
    private function syncMailFolder(GraphMailboxOwner $owner, string $folderId, string $folderName): array
    {
        $state = new DeltaSyncState($this->storage, $this->storage->mailFolderDeltaStatePath($owner, $folderId));
        $itemsDir = $this->storage->messageDir($owner, $folderId);
        $writer = new DeltaItemWriter($this->storage, $itemsDir, $this->runId);
        $deltaPath = $owner->graphPath("mailFolders/{$folderId}/messages/delta");
        $query = ['$select' => self::MESSAGE_SELECT, '$top' => '100'];

        $stats = ['messages' => 0, 'created' => 0, 'updated' => 0, 'removed' => 0, 'mode' => 'initial'];

        $runSync = function (bool $resync) use ($state, $writer, $deltaPath, $query, $folderName, &$stats): void {
            if ($resync) {
                $state->clear();
                $stats['mode'] = 'resync';
            }
            $resume = $state->hasToken() ? $state->deltaLink() : null;
            if (!$resync && $resume !== null && $resume !== '') {
                $stats['mode'] = 'delta';
            } elseif (!$resync) {
                $stats['mode'] = 'initial';
            }
            $this->logger->info("Mail folder sync ({$stats['mode']}): {$folderName}");

            $outcome = new DeltaPaginationOutcome();
            $monitor = PaginationMonitor::forBackup($this->logger, 'mail:' . $folderName);
            foreach ($this->graph->paginateDelta($deltaPath, $query, [], $resume, $monitor, $outcome) as $item) {
                $this->cancellation?->check();
                $result = $writer->writeItem($item);
                if ($result['action'] === 'skip') {
                    continue;
                }
                $stats['messages']++;
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
            $this->logger->warn("Mail folder delta reset, resyncing: {$folderName}", ['error' => $e->getMessage()]);
            $runSync(true);
        }

        return $stats;
    }
}
