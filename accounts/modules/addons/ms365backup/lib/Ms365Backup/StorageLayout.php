<?php
declare(strict_types=1);

namespace Ms365Backup;

final class StorageLayout
{
    public const BASE_PATH = '/var/www/eazybackup/ms365';

    public function __construct(
        private readonly string $tenantId,
        private readonly ?BackupStorageInterface $backupStorage = null,
        private readonly int $backupUserId = 0,
    ) {
    }

    public static function ensureBase(): void
    {
        if (!is_dir(self::BASE_PATH)) {
            if (!@mkdir(self::BASE_PATH, 0770, true) && !is_dir(self::BASE_PATH)) {
                throw new \RuntimeException('Failed to create backup base directory: ' . self::BASE_PATH);
            }
        }
    }

    public function tenantRoot(): string
    {
        return self::BASE_PATH . '/' . $this->sanitize($this->tenantId);
    }

    public function discoveryDir(): string
    {
        $dir = $this->tenantRoot() . '/discovery';
        $this->ensureDir($dir);
        return $dir;
    }

    public function inventoryPath(): string
    {
        if ($this->backupUserId > 0) {
            $dir = $this->tenantRoot() . '/backup_users/' . $this->sanitize((string) $this->backupUserId);
            $this->ensureDir($dir);

            return $dir . '/inventory.json';
        }

        return $this->discoveryDir() . '/inventory.json';
    }

    public function userRoot(string $userId): string
    {
        $dir = $this->tenantRoot() . '/users/' . $this->sanitize($userId);
        $this->ensureDir($dir);
        return $dir;
    }

    public function resourceRoot(string $resourceType, string $graphId): string
    {
        $subdir = match ($resourceType) {
            TenantResource::TYPE_SHAREPOINT_SITE => 'sites',
            TenantResource::TYPE_USER_ONEDRIVE => 'drives',
            TenantResource::TYPE_TEAM, TenantResource::TYPE_TEAM_CHANNEL => 'teams',
            TenantResource::TYPE_M365_GROUP => 'groups',
            TenantResource::TYPE_PLANNER_PLAN => 'planner',
            TenantResource::TYPE_ONENOTE_NOTEBOOK => 'onenote',
            TenantResource::TYPE_DIRECTORY_BASELINE => 'directory',
            default => 'users',
        };
        $dir = $this->tenantRoot() . '/' . $subdir . '/' . $this->sanitize($graphId);
        $this->ensureDir($dir);

        return $dir;
    }

    public function runDirForJob(string $physicalKey, string $runId): string
    {
        $parsed = self::parsePhysicalKey($physicalKey);
        $root = match ($parsed['resource_type']) {
            TenantResource::TYPE_TEAM => $this->teamRoot($parsed['graph_id']),
            TenantResource::TYPE_TEAM_CHANNEL => $this->teamRoot(self::teamGroupIdFromComposite($parsed['graph_id'])),
            default => $this->resourceRoot($parsed['resource_type'], $parsed['graph_id']),
        };
        $dir = $root . '/runs/' . $this->sanitize($runId);
        $this->ensureDir($dir);

        return $dir;
    }

    /**
     * @return array{resource_type: string, graph_id: string}
     */
    public static function parsePhysicalKey(string $physicalKey): array
    {
        $physicalKey = PhysicalKeyHelper::baseKey($physicalKey);
        $pos = strpos($physicalKey, ':');
        if ($pos === false) {
            return ['resource_type' => TenantResource::TYPE_USER, 'graph_id' => $physicalKey];
        }

        $prefix = substr($physicalKey, 0, $pos);
        $graphId = substr($physicalKey, $pos + 1);

        if ($prefix === 'channel') {
            return [
                'resource_type' => TenantResource::TYPE_TEAM_CHANNEL,
                'graph_id' => $graphId,
            ];
        }

        $resourceType = match ($prefix) {
            'site' => TenantResource::TYPE_SHAREPOINT_SITE,
            'onedrive', 'drive' => TenantResource::TYPE_USER_ONEDRIVE,
            'team' => TenantResource::TYPE_TEAM,
            'group' => TenantResource::TYPE_M365_GROUP,
            'planner' => TenantResource::TYPE_PLANNER_PLAN,
            'onenote' => TenantResource::TYPE_ONENOTE_NOTEBOOK,
            'directory' => TenantResource::TYPE_DIRECTORY_BASELINE,
            default => TenantResource::TYPE_USER,
        };

        return ['resource_type' => $resourceType, 'graph_id' => $graphId];
    }

    public static function teamGroupIdFromComposite(string $compositeGraphId): string
    {
        $pos = strpos($compositeGraphId, ':');

        return $pos === false ? $compositeGraphId : substr($compositeGraphId, 0, $pos);
    }

    public static function channelIdFromComposite(string $compositeGraphId): string
    {
        $pos = strpos($compositeGraphId, ':');

        return $pos === false ? '' : substr($compositeGraphId, $pos + 1);
    }

    public function teamRoot(string $groupId): string
    {
        $dir = $this->tenantRoot() . '/teams/' . $this->sanitize($groupId);
        $this->ensureDir($dir);

        return $dir;
    }

    public function teamMetadataPath(string $groupId): string
    {
        return $this->teamRoot($groupId) . '/team.json';
    }

    public function teamMembersPath(string $groupId): string
    {
        return $this->teamRoot($groupId) . '/members.json';
    }

    public function teamOwnersPath(string $groupId): string
    {
        return $this->teamRoot($groupId) . '/owners.json';
    }

    public function teamChannelsCatalogPath(string $groupId): string
    {
        return $this->teamRoot($groupId) . '/channels.json';
    }

    public function teamChannelDir(string $groupId, string $channelId): string
    {
        $dir = $this->teamRoot($groupId) . '/channels/' . $this->sanitize($channelId);
        $this->ensureDir($dir);

        return $dir;
    }

    public function teamChannelTabsPath(string $groupId, string $channelId): string
    {
        return $this->teamChannelDir($groupId, $channelId) . '/tabs.json';
    }

    public function teamChannelDeltaStatePath(string $groupId, string $channelId): string
    {
        return $this->teamChannelDir($groupId, $channelId) . '/delta_state.json';
    }

    public function teamChannelMessagesDir(string $groupId, string $channelId): string
    {
        $dir = $this->teamChannelDir($groupId, $channelId) . '/messages';
        $this->ensureDir($dir);

        return $dir;
    }

    public function teamChannelMessagePath(string $groupId, string $channelId, string $messageId): string
    {
        return $this->teamChannelMessagesDir($groupId, $channelId) . '/' . $this->sanitize($messageId) . '.json';
    }

    public function teamChannelMessageRemovedPath(string $groupId, string $channelId, string $messageId): string
    {
        return $this->teamChannelMessagesDir($groupId, $channelId) . '/' . $this->sanitize($messageId) . '.removed.json';
    }

    public function teamChannelMessageRepliesDir(string $groupId, string $channelId, string $messageId): string
    {
        $dir = $this->teamChannelMessagesDir($groupId, $channelId) . '/' . $this->sanitize($messageId) . '/replies';
        $this->ensureDir($dir);

        return $dir;
    }

    public function teamChannelReplyPath(string $groupId, string $channelId, string $messageId, string $replyId): string
    {
        return $this->teamChannelMessageRepliesDir($groupId, $channelId, $messageId)
            . '/' . $this->sanitize($replyId) . '.json';
    }

    public function runDir(string $userId, string $runId): string
    {
        return $this->runDirForJob('user:' . $userId, $runId);
    }

    public function groupRoot(string $groupId): string
    {
        return $this->resourceRoot(TenantResource::TYPE_M365_GROUP, $groupId);
    }

    public function plannerPlanRoot(string $planId): string
    {
        $dir = $this->tenantRoot() . '/planner/' . $this->sanitize($planId);
        $this->ensureDir($dir);

        return $dir;
    }

    public function plannerBucketTasksDir(string $planId, string $bucketId): string
    {
        $dir = $this->plannerPlanRoot($planId) . '/tasks/' . $this->sanitize($bucketId);
        $this->ensureDir($dir);

        return $dir;
    }

    public function plannerTaskPath(string $planId, string $bucketId, string $taskId): string
    {
        return $this->plannerBucketTasksDir($planId, $bucketId) . '/' . $this->sanitize($taskId) . '.json';
    }

    public function onenoteNotebookRoot(string $notebookId): string
    {
        $dir = $this->tenantRoot() . '/onenote/' . $this->sanitize($notebookId);
        $this->ensureDir($dir);

        return $dir;
    }

    public function onenoteSectionDir(string $notebookId, string $sectionId): string
    {
        $dir = $this->onenoteNotebookRoot($notebookId) . '/sections/' . $this->sanitize($sectionId);
        $this->ensureDir($dir);

        return $dir;
    }

    public function onenotePagePath(string $notebookId, string $sectionId, string $pageId): string
    {
        return $this->onenoteSectionDir($notebookId, $sectionId) . '/' . $this->sanitize($pageId) . '.json';
    }

    public function directoryRoot(): string
    {
        $dir = $this->tenantRoot() . '/directory';
        $this->ensureDir($dir);

        return $dir;
    }

    public function mailboxRoot(GraphMailboxOwner|string $owner): string
    {
        if ($owner instanceof GraphMailboxOwner) {
            return $owner->isGroup()
                ? $this->groupRoot($owner->id())
                : $this->userRoot($owner->id());
        }

        return $this->userRoot($owner);
    }

    public function mailDir(GraphMailboxOwner|string $owner): string
    {
        $dir = $this->mailboxRoot($owner) . '/mail';
        $this->ensureDir($dir);
        return $dir;
    }

    public function messageDir(GraphMailboxOwner|string $owner, string $folderId): string
    {
        $dir = $this->mailDir($owner) . '/messages/' . $this->sanitize($folderId);
        $this->ensureDir($dir);
        return $dir;
    }

    public function mailFolderDeltaStatePath(GraphMailboxOwner|string $owner, string $folderId): string
    {
        return $this->messageDir($owner, $folderId) . '/delta_state.json';
    }

    public function contactsDir(string $userId): string
    {
        $dir = $this->userRoot($userId) . '/contacts';
        $this->ensureDir($dir);
        return $dir;
    }

    public function contactsFoldersPath(string $userId): string
    {
        return $this->contactsDir($userId) . '/folders.json';
    }

    public function contactFolderDir(string $userId, string $folderId): string
    {
        $dir = $this->contactsDir($userId) . '/folders/' . $this->sanitize($folderId);
        $this->ensureDir($dir);
        return $dir;
    }

    public function contactItemsDir(string $userId, string $folderId): string
    {
        $dir = $this->contactFolderDir($userId, $folderId) . '/contacts';
        $this->ensureDir($dir);
        return $dir;
    }

    public function contactFolderDeltaStatePath(string $userId, string $folderId): string
    {
        return $this->contactFolderDir($userId, $folderId) . '/delta_state.json';
    }

    public function todoDir(string $userId): string
    {
        $dir = $this->userRoot($userId) . '/todo';
        $this->ensureDir($dir);
        return $dir;
    }

    public function todoListsPath(string $userId): string
    {
        return $this->todoDir($userId) . '/lists.json';
    }

    public function todoListDir(string $userId, string $listId): string
    {
        $dir = $this->todoDir($userId) . '/lists/' . $this->sanitize($listId);
        $this->ensureDir($dir);
        return $dir;
    }

    public function todoTasksDir(string $userId, string $listId): string
    {
        $dir = $this->todoListDir($userId, $listId) . '/tasks';
        $this->ensureDir($dir);
        return $dir;
    }

    public function todoListDeltaStatePath(string $userId, string $listId): string
    {
        return $this->todoListDir($userId, $listId) . '/delta_state.json';
    }

    public function driveRoot(string $driveId): string
    {
        return $this->resourceRoot(TenantResource::TYPE_USER_ONEDRIVE, $driveId);
    }

    public function driveDeltaStatePath(string $driveId): string
    {
        return $this->driveRoot($driveId) . '/delta_state.json';
    }

    public function driveItemsDir(string $driveId): string
    {
        $dir = $this->driveRoot($driveId) . '/items';
        $this->ensureDir($dir);
        return $dir;
    }

    public function driveContentDir(string $driveId, string $itemId): string
    {
        $dir = $this->driveRoot($driveId) . '/content/' . $this->sanitize($itemId);
        $this->ensureDir($dir);
        return $dir;
    }

    public function driveItemMetadataPath(string $driveId, string $itemId): string
    {
        return $this->driveItemsDir($driveId) . '/' . $this->sanitize($itemId) . '.json';
    }

    public function driveItemRemovedPath(string $driveId, string $itemId): string
    {
        return $this->driveItemsDir($driveId) . '/' . $this->sanitize($itemId) . '.removed.json';
    }

    public function siteRoot(string $siteId): string
    {
        return $this->resourceRoot(TenantResource::TYPE_SHAREPOINT_SITE, $siteId);
    }

    public function siteMetadataPath(string $siteId): string
    {
        return $this->siteRoot($siteId) . '/site.json';
    }

    public function siteDrivesCatalogPath(string $siteId): string
    {
        return $this->siteRoot($siteId) . '/drives.json';
    }

    public function siteDriveRoot(string $siteId, string $driveId): string
    {
        $dir = $this->siteRoot($siteId) . '/drives/' . $this->sanitize($driveId);
        $this->ensureDir($dir);

        return $dir;
    }

    public function siteDriveDeltaStatePath(string $siteId, string $driveId): string
    {
        return $this->siteDriveRoot($siteId, $driveId) . '/delta_state.json';
    }

    public function siteDriveItemsDir(string $siteId, string $driveId): string
    {
        $dir = $this->siteDriveRoot($siteId, $driveId) . '/items';
        $this->ensureDir($dir);

        return $dir;
    }

    public function siteDriveItemMetadataPath(string $siteId, string $driveId, string $itemId): string
    {
        return $this->siteDriveItemsDir($siteId, $driveId) . '/' . $this->sanitize($itemId) . '.json';
    }

    public function siteDriveItemRemovedPath(string $siteId, string $driveId, string $itemId): string
    {
        return $this->siteDriveItemsDir($siteId, $driveId) . '/' . $this->sanitize($itemId) . '.removed.json';
    }

    public function siteDriveContentDir(string $siteId, string $driveId, string $itemId): string
    {
        $dir = $this->siteDriveRoot($siteId, $driveId) . '/content/' . $this->sanitize($itemId);
        $this->ensureDir($dir);

        return $dir;
    }

    public function siteListsCatalogPath(string $siteId): string
    {
        return $this->siteRoot($siteId) . '/lists/lists.json';
    }

    public function siteListRoot(string $siteId, string $listId): string
    {
        $dir = $this->siteRoot($siteId) . '/lists/' . $this->sanitize($listId);
        $this->ensureDir($dir);

        return $dir;
    }

    public function siteListDeltaStatePath(string $siteId, string $listId): string
    {
        return $this->siteListRoot($siteId, $listId) . '/delta_state.json';
    }

    public function siteListItemsDir(string $siteId, string $listId): string
    {
        $dir = $this->siteListRoot($siteId, $listId) . '/items';
        $this->ensureDir($dir);

        return $dir;
    }

    public function siteListItemPath(string $siteId, string $listId, string $itemId): string
    {
        return $this->siteListItemsDir($siteId, $listId) . '/' . $this->sanitize($itemId) . '.json';
    }

    public function siteListItemRemovedPath(string $siteId, string $listId, string $itemId): string
    {
        return $this->siteListItemsDir($siteId, $listId) . '/' . $this->sanitize($itemId) . '.removed.json';
    }

    public function calendarDir(GraphMailboxOwner|string $owner, string $calendarId): string
    {
        $dir = $this->mailboxRoot($owner) . '/calendars/' . $this->sanitize($calendarId);
        $this->ensureDir($dir);
        return $dir;
    }

    public function calendarEventsDir(GraphMailboxOwner|string $owner, string $calendarId): string
    {
        $dir = $this->calendarDir($owner, $calendarId) . '/events';
        $this->ensureDir($dir);
        return $dir;
    }

    public function calendarSeriesDir(GraphMailboxOwner|string $owner, string $calendarId): string
    {
        $dir = $this->calendarDir($owner, $calendarId) . '/series';
        $this->ensureDir($dir);
        return $dir;
    }

    public function calendarBackupStatePath(GraphMailboxOwner|string $owner, string $calendarId): string
    {
        return $this->calendarDir($owner, $calendarId) . '/backup_state.json';
    }

    public function calendarEventFilePath(GraphMailboxOwner|string $owner, string $calendarId, string $immutableEventId): string
    {
        $safeId = preg_replace('/[^a-zA-Z0-9._-]/', '_', $immutableEventId) ?: 'unknown';
        return $this->calendarEventsDir($owner, $calendarId) . '/' . $safeId . '.json';
    }

    public function calendarSeriesFilePath(GraphMailboxOwner|string $owner, string $calendarId, string $seriesMasterId): string
    {
        $safeMaster = preg_replace('/[^a-zA-Z0-9._-]/', '_', $seriesMasterId) ?: 'unknown';
        return $this->calendarSeriesDir($owner, $calendarId) . '/' . $safeMaster . '.json';
    }

    public function runDirForMailbox(GraphMailboxOwner $owner, string $runId): string
    {
        $physicalKey = ($owner->isGroup() ? 'group:' : 'user:') . $owner->id();

        return $this->runDirForJob($physicalKey, $runId);
    }

    public function writeJson(string $path, array $data): void
    {
        $this->storageAdapter()->writeJson($path, $data);
    }

    /** @return array<string, mixed>|null */
    public function readJson(string $path): ?array
    {
        $adapter = $this->storageAdapter();
        if ($adapter->exists($path)) {
            return $adapter->readJson($path);
        }
        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    public function appendLog(string $path, string $line): void
    {
        if (!$this->usesLocalFilesystem()) {
            return;
        }
        $this->ensureDir(dirname($path));
        file_put_contents($path, '[' . gmdate('c') . '] ' . $line . PHP_EOL, FILE_APPEND);
    }

    private function storageAdapter(): BackupStorageInterface
    {
        return $this->backupStorage ?? BackupStorageFactory::createDefault();
    }

    private function usesLocalFilesystem(): bool
    {
        return $this->storageAdapter() instanceof LocalFilesystemBackupStorage;
    }

    private function ensureDir(string $dir): void
    {
        if (!$this->usesLocalFilesystem()) {
            return;
        }
        if (is_dir($dir)) {
            return;
        }
        if (!@mkdir($dir, 0770, true) && !is_dir($dir)) {
            $err = error_get_last();
            throw new \RuntimeException(
                'Failed to create directory: ' . $dir . ($err ? ' — ' . ($err['message'] ?? '') : '')
            );
        }
    }

    private function sanitize(string $id): string
    {
        return preg_replace('/[^a-zA-Z0-9._-]/', '_', $id) ?: 'unknown';
    }
}
