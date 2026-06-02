<?php
declare(strict_types=1);

namespace Ms365Backup;

final class TeamsMetadataBackupService
{
    private const TEAM_SELECT = 'id,displayName,description,webUrl,createdDateTime';
    private const CHANNEL_SELECT = 'id,displayName,description,membershipType,webUrl,createdDateTime';
    private const MEMBER_SELECT = 'id,roles,displayName,email,userId';

    public function __construct(
        private readonly GraphClient $graph,
        private readonly StorageLayout $storage,
        private readonly ProgressLogger $logger,
        private readonly ?RunCancellation $cancellation = null,
    ) {
    }

    /**
     * @param list<string>|null $channelIds null = all channels
     * @return array{channels: int, tabs: int, members: int, owners: int}
     */
    public function backupTeamMetadata(string $groupId, ?array $channelIds = null): array
    {
        $stats = ['channels' => 0, 'tabs' => 0, 'members' => 0, 'owners' => 0];

        $this->cancellation?->check();
        try {
            $team = $this->graph->get(GraphTeamPaths::teamPath($groupId), ['$select' => self::TEAM_SELECT]);
            $this->storage->writeJson($this->storage->teamMetadataPath($groupId), [
                'fetched_at' => gmdate('c'),
                'value' => $team,
            ]);
        } catch (\Throwable $e) {
            $this->logger->warn('Teams profile fetch failed', ['team_id' => $groupId, 'error' => $e->getMessage()]);
        }

        $members = [];
        try {
            $monitor = PaginationMonitor::forBackup($this->logger, 'teams.members:' . $groupId);
            foreach ($this->graph->paginate(
                GraphTeamPaths::teamPath($groupId, 'members'),
                ['$select' => self::MEMBER_SELECT, '$top' => '999'],
                [],
                $monitor,
            ) as $member) {
                $this->cancellation?->check();
                $members[] = $member;
            }
            $stats['members'] = count($members);
            $this->storage->writeJson($this->storage->teamMembersPath($groupId), [
                'fetched_at' => gmdate('c'),
                'value' => $members,
            ]);
        } catch (\Throwable $e) {
            $this->logger->warn('Teams members fetch failed', ['team_id' => $groupId, 'error' => $e->getMessage()]);
        }

        $owners = [];
        try {
            $monitor = PaginationMonitor::forBackup($this->logger, 'teams.owners:' . $groupId);
            foreach ($this->graph->paginate(
                "groups/{$groupId}/owners",
                ['$select' => 'id,displayName,mail', '$top' => '999'],
                [],
                $monitor,
            ) as $owner) {
                $this->cancellation?->check();
                $owners[] = $owner;
            }
            $stats['owners'] = count($owners);
            $this->storage->writeJson($this->storage->teamOwnersPath($groupId), [
                'fetched_at' => gmdate('c'),
                'value' => $owners,
            ]);
        } catch (\Throwable $e) {
            $this->logger->warn('Teams owners fetch failed', ['team_id' => $groupId, 'error' => $e->getMessage()]);
        }

        $channels = [];
        $monitor = PaginationMonitor::forBackup($this->logger, 'teams.channels:' . $groupId);
        foreach ($this->graph->paginate(
            GraphTeamPaths::teamPath($groupId, 'channels'),
            ['$select' => self::CHANNEL_SELECT, '$top' => '100'],
            [],
            $monitor,
        ) as $channel) {
            $this->cancellation?->check();
            $channels[] = $channel;
        }
        $stats['channels'] = count($channels);
        $this->storage->writeJson($this->storage->teamChannelsCatalogPath($groupId), [
            'fetched_at' => gmdate('c'),
            'value' => $channels,
        ]);

        $allowed = $channelIds === null ? null : array_fill_keys($channelIds, true);
        foreach ($channels as $channel) {
            $channelId = (string) ($channel['id'] ?? '');
            if ($channelId === '') {
                continue;
            }
            if ($allowed !== null && !isset($allowed[$channelId])) {
                continue;
            }
            try {
                $stats['tabs'] += $this->backupChannelTabs($groupId, $channelId);
            } catch (\Throwable $e) {
                $this->logger->warn('Teams channel tabs fetch failed', [
                    'channel_id' => $channelId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    private function backupChannelTabs(string $groupId, string $channelId): int
    {
        $tabs = [];
        $monitor = PaginationMonitor::forBackup($this->logger, 'teams.tabs:' . $channelId);
        foreach ($this->graph->paginate(
            GraphTeamPaths::channelPath($groupId, $channelId, 'tabs'),
            ['$top' => '100'],
            [],
            $monitor,
        ) as $tab) {
            $this->cancellation?->check();
            $tabs[] = $tab;
        }
        $this->storage->writeJson($this->storage->teamChannelTabsPath($groupId, $channelId), [
            'fetched_at' => gmdate('c'),
            'value' => $tabs,
        ]);

        return count($tabs);
    }
}
