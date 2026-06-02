<?php
declare(strict_types=1);

namespace Ms365Backup;

final class TeamsMessagesBackupService
{
    private const MESSAGE_SELECT = 'id,replyToId,from,body,createdDateTime,lastModifiedDateTime,messageType,importance,deletedDateTime';

    public function __construct(
        private readonly GraphClient $graph,
        private readonly StorageLayout $storage,
        private readonly ProgressLogger $logger,
        private readonly string $runId,
        private readonly ?RunCancellation $cancellation = null,
    ) {
    }

    /**
     * @param list<string>|null $channelIds null = all channels from channels.json catalog
     * @return array{
     *   channels: int,
     *   messages_seen: int,
     *   messages_stored: int,
     *   messages_updated: int,
     *   replies_stored: int,
     *   removed: int,
     *   channels_resynced: int
     * }
     */
    public function backupTeamMessages(string $groupId, ?array $channelIds = null): array
    {
        $stats = [
            'channels' => 0,
            'messages_seen' => 0,
            'messages_stored' => 0,
            'messages_updated' => 0,
            'replies_stored' => 0,
            'removed' => 0,
            'channels_resynced' => 0,
        ];

        $channels = $this->loadChannelCatalog($groupId, $channelIds);
        $stats['channels'] = count($channels);

        foreach ($channels as $channel) {
            $channelId = (string) ($channel['id'] ?? '');
            $channelName = (string) ($channel['displayName'] ?? $channelId);
            if ($channelId === '') {
                continue;
            }
            try {
                $channelStats = $this->syncChannel($groupId, $channelId, $channelName);
                $stats['messages_seen'] += $channelStats['messages_seen'];
                $stats['messages_stored'] += $channelStats['messages_stored'];
                $stats['messages_updated'] += $channelStats['messages_updated'];
                $stats['replies_stored'] += $channelStats['replies_stored'];
                $stats['removed'] += $channelStats['removed'];
                if ($channelStats['resynced']) {
                    $stats['channels_resynced']++;
                }
            } catch (\Throwable $e) {
                $this->logger->error("Teams channel messages failed: {$channelName}", ['error' => $e->getMessage()]);
            }
        }

        return $stats;
    }

    /**
     * @return array{
     *   messages_seen: int,
     *   messages_stored: int,
     *   messages_updated: int,
     *   replies_stored: int,
     *   removed: int,
     *   resynced: bool
     * }
     */
    public function backupChannelMessages(string $groupId, string $channelId, string $channelName = ''): array
    {
        return $this->syncChannel($groupId, $channelId, $channelName !== '' ? $channelName : $channelId);
    }

    /**
     * @param list<string>|null $channelIds
     * @return list<array<string, mixed>>
     */
    private function loadChannelCatalog(string $groupId, ?array $channelIds): array
    {
        $path = $this->storage->teamChannelsCatalogPath($groupId);
        $channels = [];
        if (is_file($path)) {
            $data = json_decode((string) file_get_contents($path), true);
            if (is_array($data['value'] ?? null)) {
                $channels = $data['value'];
            }
        }

        if ($channels === []) {
            $channels = [];
            $monitor = PaginationMonitor::forBackup($this->logger, 'teams.channels:' . $groupId);
            foreach ($this->graph->paginate(
                GraphTeamPaths::teamPath($groupId, 'channels'),
                ['$select' => 'id,displayName,membershipType', '$top' => '100'],
                [],
                $monitor,
            ) as $channel) {
                $channels[] = $channel;
            }
            $this->storage->writeJson($path, [
                'fetched_at' => gmdate('c'),
                'value' => $channels,
            ]);
        }

        if ($channelIds === null) {
            return $channels;
        }

        $allowed = array_fill_keys($channelIds, true);
        $filtered = [];
        foreach ($channels as $channel) {
            $id = (string) ($channel['id'] ?? '');
            if ($id !== '' && isset($allowed[$id])) {
                $filtered[] = $channel;
            }
        }

        return $filtered;
    }

    /**
     * @return array{
     *   messages_seen: int,
     *   messages_stored: int,
     *   messages_updated: int,
     *   replies_stored: int,
     *   removed: int,
     *   resynced: bool
     * }
     */
    private function syncChannel(string $groupId, string $channelId, string $channelName): array
    {
        $state = new DeltaSyncState(
            $this->storage,
            $this->storage->teamChannelDeltaStatePath($groupId, $channelId),
        );
        $store = new ChannelMessageStore($this->storage, $groupId, $channelId, $this->runId);
        $deltaPath = GraphTeamPaths::channelPath($groupId, $channelId, 'messages/delta');
        $query = ['$select' => self::MESSAGE_SELECT, '$top' => '50'];

        $stats = [
            'messages_seen' => 0,
            'messages_stored' => 0,
            'messages_updated' => 0,
            'replies_stored' => 0,
            'removed' => 0,
            'resynced' => false,
        ];

        $runSync = function (bool $resync) use ($state, $store, $deltaPath, $query, $groupId, $channelId, $channelName, &$stats): void {
            if ($resync) {
                $state->clear();
                $stats['resynced'] = true;
            }
            $resume = $state->hasToken() ? $state->deltaLink() : null;
            $mode = $resume !== null && $resume !== '' ? 'delta' : 'initial';
            $this->logger->info("Teams channel messages sync ({$mode}): {$channelName}");

            $outcome = new DeltaPaginationOutcome();
            $monitor = PaginationMonitor::forBackup($this->logger, 'teams.messages:' . $channelName);
            foreach ($this->graph->paginateDelta($deltaPath, $query, [], $resume, $monitor, $outcome) as $message) {
                $this->cancellation?->check();
                $stats['messages_seen']++;
                if (isset($message['@removed']) && is_array($message['@removed'])) {
                    $result = $store->writeRemoved($message);
                    if ($result['action'] === 'removed') {
                        $stats['removed']++;
                    }
                    continue;
                }

                $result = $store->writeMessage($message);
                if ($result['action'] === 'created') {
                    $stats['messages_stored']++;
                } elseif ($result['action'] === 'updated') {
                    $stats['messages_updated']++;
                }

                $messageId = (string) ($message['id'] ?? '');
                if ($messageId !== '' && $this->shouldFetchReplies($message)) {
                    $stats['replies_stored'] += $this->syncReplies($groupId, $channelId, $messageId);
                }
            }

            if ($outcome->deltaLink !== '') {
                $state->saveDeltaLink($outcome->deltaLink, $this->runId);
            }
        };

        try {
            $runSync(false);
        } catch (GraphDeltaResetException $e) {
            $this->logger->warn("Teams channel delta reset, resyncing: {$channelName}", ['error' => $e->getMessage()]);
            $runSync(true);
        }

        return $stats;
    }

    /**
     * @param array<string, mixed> $message
     */
    private function shouldFetchReplies(array $message): bool
    {
        $messageType = strtolower((string) ($message['messageType'] ?? ''));
        if ($messageType === 'systemeventmessage' || $messageType === 'unknownfuturevalue') {
            return false;
        }

        return true;
    }

    private function syncReplies(string $groupId, string $channelId, string $messageId): int
    {
        $store = new ChannelMessageStore($this->storage, $groupId, $channelId, $this->runId);
        $path = GraphTeamPaths::channelPath($groupId, $channelId, "messages/{$messageId}/replies");
        $stored = 0;
        $monitor = PaginationMonitor::forBackup($this->logger, 'teams.replies:' . $messageId);

        try {
            foreach ($this->graph->paginate($path, ['$top' => '50'], [], $monitor) as $reply) {
                $this->cancellation?->check();
                $result = $store->writeReply($messageId, $reply);
                if ($result['action'] === 'created' || $result['action'] === 'updated') {
                    $stored++;
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warn('Teams message replies fetch failed', [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);
        }

        return $stored;
    }
}
