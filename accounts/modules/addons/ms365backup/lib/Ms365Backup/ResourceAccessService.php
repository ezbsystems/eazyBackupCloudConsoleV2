<?php
declare(strict_types=1);

namespace Ms365Backup;

final class ResourceAccessService
{
    public function __construct(
        private readonly GraphClient $graph,
        private readonly StorageLayout $storage,
    ) {
    }

    /**
     * @return array{
     *   mail: string,
     *   calendar: string,
     *   contacts: string,
     *   tasks: string,
     *   mail_reason: string,
     *   calendar_reason: string,
     *   contacts_reason: string,
     *   tasks_reason: string,
     *   checked_at: string
     * }
     */
    public function probeUser(string $userId): array
    {
        $checkedAt = gmdate('c');
        $mail = $this->probeUserMail($userId);
        $calendar = $this->probeUserCalendar($userId);
        $contacts = $this->probeUserContacts($userId);
        $tasks = $this->probeUserTasks($userId);

        return [
            'mail' => $mail->status,
            'calendar' => $calendar->status,
            'contacts' => $contacts->status,
            'tasks' => $tasks->status,
            'mail_reason' => $mail->reason,
            'calendar_reason' => $calendar->reason,
            'contacts_reason' => $contacts->reason,
            'tasks_reason' => $tasks->reason,
            'checked_at' => $checkedAt,
        ];
    }

    public function probeUserMail(string $userId): AccessResult
    {
        try {
            $this->graph->get("users/{$userId}/mailFolders", ['$top' => '1']);
            return ResourceAccessClassifier::available();
        } catch (GraphApiException $e) {
            return ResourceAccessClassifier::classify($e);
        }
    }

    public function probeUserCalendar(string $userId): AccessResult
    {
        try {
            $this->graph->get("users/{$userId}/calendars", ['$top' => '1']);
            return ResourceAccessClassifier::available();
        } catch (GraphApiException $e) {
            return ResourceAccessClassifier::classify($e);
        }
    }

    public function probeUserContacts(string $userId): AccessResult
    {
        try {
            $this->graph->get("users/{$userId}/contactFolders", ['$top' => '1']);
            return ResourceAccessClassifier::available();
        } catch (GraphApiException $e) {
            return ResourceAccessClassifier::classify($e);
        }
    }

    public function probeUserTasks(string $userId): AccessResult
    {
        try {
            $this->graph->get("users/{$userId}/todo/lists", ['$top' => '1']);
            return ResourceAccessClassifier::available();
        } catch (GraphApiException $e) {
            return ResourceAccessClassifier::classify($e);
        }
    }

    public function probeDrive(string $driveId): AccessResult
    {
        try {
            $this->graph->get("drives/{$driveId}/root", ['$select' => 'id']);
            return ResourceAccessClassifier::available();
        } catch (GraphApiException $e) {
            return ResourceAccessClassifier::classify($e);
        }
    }

    /**
     * @return array{
     *   status: string,
     *   reason: string,
     *   files: string,
     *   lists: string,
     *   files_reason: string,
     *   lists_reason: string,
     *   checked_at: string
     * }
     */
    public function probeSite(string $siteId): array
    {
        $checkedAt = gmdate('c');
        $sitePath = GraphSitePaths::sitePath($siteId);
        try {
            $this->graph->get($sitePath, ['$select' => 'id,displayName,webUrl']);
        } catch (GraphApiException $e) {
            $result = ResourceAccessClassifier::classify($e);

            return [
                'status' => $result->status,
                'reason' => $result->reason,
                'files' => $result->status,
                'lists' => $result->status,
                'files_reason' => $result->reason,
                'lists_reason' => $result->reason,
                'checked_at' => $checkedAt,
            ];
        }

        $files = $this->probeSiteFiles($siteId);
        $lists = $this->probeSiteLists($siteId);

        return [
            'status' => $files->status === AccessResult::STATUS_AVAILABLE
                || $lists->status === AccessResult::STATUS_AVAILABLE
                ? AccessResult::STATUS_AVAILABLE
                : $files->status,
            'reason' => '',
            'files' => $files->status,
            'lists' => $lists->status,
            'files_reason' => $files->reason,
            'lists_reason' => $lists->reason,
            'checked_at' => $checkedAt,
        ];
    }

    public function probeSiteFiles(string $siteId): AccessResult
    {
        try {
            $this->graph->get(GraphSitePaths::sitePath($siteId, 'drives'), ['$top' => '1']);

            return ResourceAccessClassifier::available();
        } catch (GraphApiException $e) {
            return ResourceAccessClassifier::classify($e);
        }
    }

    public function probeSiteLists(string $siteId): AccessResult
    {
        try {
            $this->graph->get(GraphSitePaths::sitePath($siteId, 'lists'), ['$top' => '1']);

            return ResourceAccessClassifier::available();
        } catch (GraphApiException $e) {
            return ResourceAccessClassifier::classify($e);
        }
    }

    /**
     * @return array{
     *   status: string,
     *   reason: string,
     *   metadata: string,
     *   messages: string,
     *   metadata_reason: string,
     *   messages_reason: string,
     *   checked_at: string
     * }
     */
    public function probeTeam(string $groupId): array
    {
        $checkedAt = gmdate('c');
        $metadata = $this->probeTeamMetadata($groupId);
        $channelId = $this->resolveProbeChannelId($groupId);
        $messages = $channelId !== ''
            ? $this->probeTeamMessages($groupId, $channelId)
            : ResourceAccessClassifier::classify(
                new GraphApiException('No channel available to probe messages', 404),
            );

        return [
            'status' => $metadata->status === AccessResult::STATUS_AVAILABLE
                || $messages->status === AccessResult::STATUS_AVAILABLE
                ? AccessResult::STATUS_AVAILABLE
                : $metadata->status,
            'reason' => '',
            'metadata' => $metadata->status,
            'messages' => $messages->status,
            'metadata_reason' => $metadata->reason,
            'messages_reason' => $messages->reason,
            'checked_at' => $checkedAt,
        ];
    }

    public function probeTeamMetadata(string $groupId): AccessResult
    {
        try {
            $this->graph->get(GraphTeamPaths::teamPath($groupId), ['$select' => 'id,displayName']);

            return ResourceAccessClassifier::available();
        } catch (GraphApiException $e) {
            return ResourceAccessClassifier::classify($e);
        }
    }

    public function probeTeamMessages(string $groupId, string $channelId): AccessResult
    {
        try {
            $this->graph->get(
                GraphTeamPaths::channelPath($groupId, $channelId, 'messages'),
                ['$top' => '1'],
            );

            return ResourceAccessClassifier::available();
        } catch (GraphApiException $e) {
            return ResourceAccessClassifier::classify($e);
        }
    }

    private function resolveProbeChannelId(string $groupId): string
    {
        try {
            $data = $this->graph->get(
                GraphTeamPaths::teamPath($groupId, 'channels'),
                ['$top' => '1', '$select' => 'id'],
            );
            $channels = $data['value'] ?? [];
            if (is_array($channels) && isset($channels[0]) && is_array($channels[0])) {
                return (string) ($channels[0]['id'] ?? '');
            }
        } catch (\Throwable $_) {
        }

        return '';
    }

    /**
     * @return array{total: int, processed: int, done: bool, unavailable_count: int}
     */
    public function checkUsersBatch(int $offset, int $limit): array
    {
        $cache = $this->loadDiscoveryCache('users');
        if ($cache === null) {
            throw new \RuntimeException('No cached users. Use Load users or Refresh from Graph first.');
        }
        $users = $cache['value'] ?? [];
        if (!is_array($users)) {
            $users = [];
        }
        $total = count($users);
        $slice = array_slice($users, $offset, $limit);
        $unavailableInBatch = 0;

        foreach ($slice as $i => $user) {
            $userId = (string) ($user['id'] ?? '');
            if ($userId === '') {
                continue;
            }
            $access = $this->probeUser($userId);
            $users[$offset + $i]['access'] = $access;
            if ($this->isUserAccessProblematic($access)) {
                $unavailableInBatch++;
            }
        }

        $cache['value'] = $users;
        $cache['access_checked_at'] = gmdate('c');
        $this->writeDiscoveryCache('users', $cache);

        $processed = min($offset + count($slice), $total);

        return [
            'total' => $total,
            'processed' => $processed,
            'done' => $processed >= $total,
            'unavailable_count' => $unavailableInBatch,
        ];
    }

    /**
     * @return array{total: int, processed: int, done: bool, unavailable_count: int}
     */
    public function checkSitesBatch(int $offset, int $limit): array
    {
        $cache = $this->loadDiscoveryCache('sites');
        if ($cache === null) {
            throw new \RuntimeException('No cached sites. Use Load cached or Refresh from Graph first.');
        }
        $sites = $cache['value'] ?? [];
        if (!is_array($sites)) {
            $sites = [];
        }
        $total = count($sites);
        $slice = array_slice($sites, $offset, $limit);
        $unavailableInBatch = 0;

        foreach ($slice as $i => $site) {
            $siteId = (string) ($site['id'] ?? '');
            if ($siteId === '') {
                continue;
            }
            $access = $this->probeSite($siteId);
            $sites[$offset + $i]['access'] = $access;
            if ($access['status'] !== AccessResult::STATUS_AVAILABLE) {
                $unavailableInBatch++;
            }
        }

        $cache['value'] = $sites;
        $cache['access_checked_at'] = gmdate('c');
        $this->writeDiscoveryCache('sites', $cache);

        $processed = min($offset + count($slice), $total);

        return [
            'total' => $total,
            'processed' => $processed,
            'done' => $processed >= $total,
            'unavailable_count' => $unavailableInBatch,
        ];
    }

    /**
     * @param array<string, mixed> $accessPatch
     */
    public function updateUserAccessInCache(string $userId, array $accessPatch): void
    {
        $cache = $this->loadDiscoveryCache('users');
        if ($cache === null || !isset($cache['value']) || !is_array($cache['value'])) {
            return;
        }
        foreach ($cache['value'] as $i => $user) {
            if ((string) ($user['id'] ?? '') !== $userId) {
                continue;
            }
            $existing = is_array($user['access'] ?? null) ? $user['access'] : [];
            $cache['value'][$i]['access'] = array_merge($existing, $accessPatch, [
                'checked_at' => gmdate('c'),
            ]);
            $this->writeDiscoveryCache('users', $cache);
            return;
        }
    }

    /**
     * @param array<string, mixed> $accessPatch
     */
    public function updateOneDriveAccessInInventory(string $driveId, array $accessPatch): void
    {
        $path = $this->storage->inventoryPath();
        if (!is_file($path)) {
            return;
        }
        $inventory = json_decode((string) file_get_contents($path), true);
        if (!is_array($inventory) || !is_array($inventory['resources'] ?? null)) {
            return;
        }
        foreach ($inventory['resources'] as $i => $resource) {
            if (!is_array($resource)) {
                continue;
            }
            if ((string) ($resource['resource_type'] ?? '') !== TenantResource::TYPE_USER_ONEDRIVE) {
                continue;
            }
            $meta = is_array($resource['meta'] ?? null) ? $resource['meta'] : [];
            $resourceDriveId = (string) ($meta['drive_id'] ?? $resource['graph_id'] ?? '');
            if ($resourceDriveId !== $driveId) {
                continue;
            }
            $existing = is_array($resource['access'] ?? null) ? $resource['access'] : [];
            $inventory['resources'][$i]['access'] = array_merge($existing, $accessPatch, [
                'checked_at' => gmdate('c'),
            ]);
            $this->storage->writeJson($path, $inventory);

            return;
        }
    }

    public static function recordDriveAccessFromException(string $driveId, ResourceUnavailableException $e): void
    {
        try {
            $creds = TenantRepository::credentials();
            $storage = new StorageLayout($creds['tenant_id']);
            $tokens = new TokenProvider(
                $creds['region'],
                $creds['tenant_id'],
                $creds['client_id'],
                $creds['client_secret'],
            );
            $graph = new GraphClient($tokens, $creds['region']);
            $service = new self($graph, $storage);
            $service->updateOneDriveAccessInInventory($driveId, [
                'onedrive' => $e->accessResult->status,
                'onedrive_reason' => $e->accessResult->reason,
            ]);
        } catch (\Throwable $_) {
        }
    }

    public static function recordUserAccessFromException(string $userId, string $phase, ResourceUnavailableException $e): void
    {
        try {
            $creds = TenantRepository::credentials();
            $storage = new StorageLayout($creds['tenant_id']);
            $tokens = new TokenProvider(
                $creds['region'],
                $creds['tenant_id'],
                $creds['client_id'],
                $creds['client_secret'],
            );
            $graph = new GraphClient($tokens, $creds['region']);
            $service = new self($graph, $storage);
            $key = match ($phase) {
                'calendar' => 'calendar',
                'contacts' => 'contacts',
                'tasks' => 'tasks',
                default => 'mail',
            };
            $reasonKey = $key . '_reason';
            $service->updateUserAccessInCache($userId, [
                $key => $e->accessResult->status,
                $reasonKey => $e->accessResult->reason,
            ]);
        } catch (\Throwable $_) {
        }
    }

    /** @param array<string, mixed> $access */
    public static function isUserAccessProblematic(array $access): bool
    {
        $mail = (string) ($access['mail'] ?? '');
        $calendar = (string) ($access['calendar'] ?? '');
        return in_array($mail, [AccessResult::STATUS_UNAVAILABLE, AccessResult::STATUS_LOCKED, AccessResult::STATUS_ERROR], true)
            || in_array($calendar, [AccessResult::STATUS_UNAVAILABLE, AccessResult::STATUS_LOCKED, AccessResult::STATUS_ERROR], true);
    }

    /** @return array<string, mixed>|null */
    private function loadDiscoveryCache(string $type): ?array
    {
        $file = $this->storage->discoveryDir() . '/' . $type . '.json';
        if (!is_file($file)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($file), true);
        return is_array($data) ? $data : null;
    }

    /** @param array<string, mixed> $data */
    private function writeDiscoveryCache(string $type, array $data): void
    {
        $this->storage->writeJson($this->storage->discoveryDir() . '/' . $type . '.json', $data);
    }

    /**
     * @param array<string, mixed> $accessPatch
     */
    public function updateSiteAccessInInventory(string $siteId, array $accessPatch): void
    {
        $path = $this->storage->inventoryPath();
        if (!is_file($path)) {
            return;
        }
        $inventory = json_decode((string) file_get_contents($path), true);
        if (!is_array($inventory) || !is_array($inventory['resources'] ?? null)) {
            return;
        }
        foreach ($inventory['resources'] as $i => $resource) {
            if (!is_array($resource)) {
                continue;
            }
            if ((string) ($resource['resource_type'] ?? '') !== TenantResource::TYPE_SHAREPOINT_SITE) {
                continue;
            }
            if ((string) ($resource['graph_id'] ?? '') !== $siteId) {
                continue;
            }
            $existing = is_array($resource['access'] ?? null) ? $resource['access'] : [];
            $inventory['resources'][$i]['access'] = array_merge($existing, $accessPatch, [
                'checked_at' => gmdate('c'),
            ]);
            $this->storage->writeJson($path, $inventory);

            return;
        }
    }

    public static function recordSiteAccessFromException(string $siteId, string $phase, GraphApiException $e): void
    {
        try {
            $creds = TenantRepository::credentials();
            $storage = new StorageLayout($creds['tenant_id']);
            $tokens = new TokenProvider(
                $creds['region'],
                $creds['tenant_id'],
                $creds['client_id'],
                $creds['client_secret'],
            );
            $graph = new GraphClient($tokens, $creds['region']);
            $service = new self($graph, $storage);
            $result = ResourceAccessClassifier::classify($e);
            $key = $phase === 'lists' ? 'lists' : 'files';
            $service->updateSiteAccessInInventory($siteId, [
                $key => $result->status,
                $key . '_reason' => $result->reason,
            ]);
        } catch (\Throwable $_) {
        }
    }

    /**
     * @param array<string, mixed> $accessPatch
     */
    public function updateTeamAccessInInventory(string $groupId, array $accessPatch): void
    {
        $path = $this->storage->inventoryPath();
        if (!is_file($path)) {
            return;
        }
        $inventory = json_decode((string) file_get_contents($path), true);
        if (!is_array($inventory) || !is_array($inventory['resources'] ?? null)) {
            return;
        }
        foreach ($inventory['resources'] as $i => $resource) {
            if (!is_array($resource)) {
                continue;
            }
            if ((string) ($resource['resource_type'] ?? '') !== TenantResource::TYPE_TEAM) {
                continue;
            }
            $meta = is_array($resource['meta'] ?? null) ? $resource['meta'] : [];
            $resourceGroupId = (string) ($meta['group_id'] ?? $resource['graph_id'] ?? '');
            if ($resourceGroupId !== $groupId) {
                continue;
            }
            $existing = is_array($resource['access'] ?? null) ? $resource['access'] : [];
            $inventory['resources'][$i]['access'] = array_merge($existing, $accessPatch, [
                'checked_at' => gmdate('c'),
            ]);
            $this->storage->writeJson($path, $inventory);

            return;
        }
    }

    public static function recordTeamAccessFromException(string $groupId, string $phase, GraphApiException $e): void
    {
        try {
            $creds = TenantRepository::credentials();
            $storage = new StorageLayout($creds['tenant_id']);
            $tokens = new TokenProvider(
                $creds['region'],
                $creds['tenant_id'],
                $creds['client_id'],
                $creds['client_secret'],
            );
            $graph = new GraphClient($tokens, $creds['region']);
            $service = new self($graph, $storage);
            $result = ResourceAccessClassifier::classify($e);
            $key = $phase === 'messages' ? 'messages' : 'metadata';
            $service->updateTeamAccessInInventory($groupId, [
                $key => $result->status,
                $key . '_reason' => $result->reason,
            ]);
        } catch (\Throwable $_) {
        }
    }

    private function encodeSiteId(string $siteId): string
    {
        return GraphSitePaths::encodeSiteId($siteId);
    }
}
