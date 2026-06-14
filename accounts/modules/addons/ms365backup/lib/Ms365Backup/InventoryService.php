<?php
declare(strict_types=1);

namespace Ms365Backup;

final class InventoryService
{
    private const ONEDRIVE_SELECT = 'id,name,driveType,quota,lastModifiedDateTime,webUrl';

    public function __construct(
        private readonly GraphClient $graph,
        private readonly StorageLayout $storage,
        private readonly DiscoveryService $discovery,
    ) {
    }

    /** @return array<string, mixed> */
    public function load(): ?array
    {
        $data = $this->storage->readJson($this->storage->inventoryPath());

        return is_array($data) ? $data : null;
    }

    /**
     * Full inventory refresh: legacy caches + inventory.json.
     *
     * @return array<string, mixed>
     */
    public function refresh(): array
    {
        $discoveryCounts = [];
        $this->writeRefreshProgress('users', 'Discovering users and mailboxes…', $discoveryCounts);

        $rawUsers = $this->discovery->listUsers();
        $discoveryCounts['users'] = count($rawUsers);
        $this->writeRefreshProgress('sites', 'Discovering SharePoint sites…', $discoveryCounts);

        $rawSites = $this->discovery->listSites();
        $discoveryCounts['sites'] = count($rawSites);
        $this->writeRefreshProgress('teams', 'Discovering Teams…', $discoveryCounts);

        $rawTeams = $this->discovery->listTeams();
        $discoveryCounts['teams'] = count($rawTeams);
        $this->writeRefreshProgress('groups', 'Discovering Microsoft 365 groups…', $discoveryCounts);

        $rawGroups = $this->listM365Groups();
        $discoveryCounts['groups'] = count($rawGroups);

        $previous = $this->load();
        $accessByGraphUserId = $this->extractUserAccessFromPrevious($previous, $rawUsers);

        $resources = [];
        $userGraphIds = [];

        foreach ($rawUsers as $user) {
            if (!is_array($user)) {
                continue;
            }
            $graphId = (string) ($user['id'] ?? '');
            if ($graphId === '') {
                continue;
            }
            $userGraphIds[] = $graphId;

            $resourceType = TenantResource::classifyGraphUser($user);
            $parentId = null;
            $resourceId = TenantResource::makeId($resourceType, $graphId);
            $email = trim((string) ($user['userPrincipalName'] ?? $user['mail'] ?? ''));

            $access = $accessByGraphUserId[$graphId] ?? (is_array($user['access'] ?? null) ? $user['access'] : []);

            $resources[$resourceId] = TenantResource::build(
                $resourceType,
                $graphId,
                (string) ($user['displayName'] ?? $email ?: $graphId),
                $parentId,
                [
                    'id' => $resourceId,
                    'email' => $email,
                    'access' => $access,
                    'meta' => [
                        'mail' => (string) ($user['mail'] ?? ''),
                        'user_type' => (string) ($user['userType'] ?? ''),
                    ],
                ],
            );
        }

        $oneDriveTotal = count($userGraphIds);
        foreach ($userGraphIds as $oneDriveIndex => $userGraphId) {
            if ($oneDriveIndex === 0 || ($oneDriveIndex + 1) % 10 === 0 || $oneDriveIndex === $oneDriveTotal - 1) {
                $this->writeRefreshProgress(
                    'onedrive',
                    'Checking OneDrive libraries…',
                    $discoveryCounts,
                    sprintf('OneDrive: %d of %d users', $oneDriveIndex + 1, $oneDriveTotal),
                );
            }
            $driveResource = $this->discoverUserOneDrive($userGraphId, $resources);
            if ($driveResource !== null) {
                $resources[$driveResource['id']] = $driveResource;
            }
        }

        $this->writeRefreshProgress(
            'details',
            'Loading channels, Planner, and OneNote…',
            $discoveryCounts,
            'This can take a few minutes on large tenants.',
        );

        foreach ($rawSites as $site) {
            if (!is_array($site)) {
                continue;
            }
            $siteId = (string) ($site['id'] ?? '');
            if ($siteId === '') {
                continue;
            }
            $resourceId = TenantResource::makeId(TenantResource::TYPE_SHAREPOINT_SITE, $siteId);
            $access = is_array($site['access'] ?? null) ? $site['access'] : [];
            $resources[$resourceId] = TenantResource::build(
                TenantResource::TYPE_SHAREPOINT_SITE,
                $siteId,
                (string) ($site['displayName'] ?? $site['name'] ?? $siteId),
                null,
                [
                    'id' => $resourceId,
                    'email' => (string) ($site['webUrl'] ?? ''),
                    'access' => $access,
                    'meta' => [
                        'web_url' => (string) ($site['webUrl'] ?? ''),
                        'site_collection' => $site['siteCollection'] ?? null,
                    ],
                ],
            );
        }

        foreach ($rawTeams as $team) {
            if (!is_array($team)) {
                continue;
            }
            $groupId = (string) ($team['id'] ?? '');
            if ($groupId === '') {
                continue;
            }

            $teamMeta = ['group_id' => $groupId];
            $rootSiteId = $this->resolveGroupRootSiteId($groupId);
            if ($rootSiteId !== '') {
                $teamMeta['sharepoint_site_id'] = $rootSiteId;
            }

            $teamResourceId = TenantResource::makeId(TenantResource::TYPE_TEAM, $groupId);
            $resources[$teamResourceId] = TenantResource::build(
                TenantResource::TYPE_TEAM,
                $groupId,
                (string) ($team['displayName'] ?? $groupId),
                null,
                [
                    'id' => $teamResourceId,
                    'email' => (string) ($team['mail'] ?? ''),
                    'meta' => $teamMeta,
                ],
            );

            foreach ($this->listTeamChannels($groupId) as $channel) {
                if (!is_array($channel)) {
                    continue;
                }
                $channelId = (string) ($channel['id'] ?? '');
                if ($channelId === '') {
                    continue;
                }
                $channelMeta = [
                    'membership_type' => (string) ($channel['membershipType'] ?? ''),
                ];
                $channelSiteId = $this->resolveChannelSiteId($groupId, $channelId);
                if ($channelSiteId !== '') {
                    $channelMeta['channel_site_id'] = $channelSiteId;
                }

                $channelResourceId = TenantResource::makeId(TenantResource::TYPE_TEAM_CHANNEL, $groupId . ':' . $channelId);
                $resources[$channelResourceId] = TenantResource::build(
                    TenantResource::TYPE_TEAM_CHANNEL,
                    $channelId,
                    (string) ($channel['displayName'] ?? $channelId),
                    $teamResourceId,
                    [
                        'id' => $channelResourceId,
                        'meta' => $channelMeta,
                    ],
                );
            }
        }

        foreach ($rawGroups as $group) {
            if (!is_array($group)) {
                continue;
            }
            $groupId = (string) ($group['id'] ?? '');
            if ($groupId === '') {
                continue;
            }
            $groupMeta = [];
            $siteId = $this->resolveGroupRootSiteId($groupId);
            if ($siteId !== '') {
                $groupMeta['sharepoint_site_id'] = $siteId;
            }

            $groupResourceId = TenantResource::makeId(TenantResource::TYPE_M365_GROUP, $groupId);
            $resources[$groupResourceId] = TenantResource::build(
                TenantResource::TYPE_M365_GROUP,
                $groupId,
                (string) ($group['displayName'] ?? $groupId),
                null,
                [
                    'id' => $groupResourceId,
                    'email' => (string) ($group['mail'] ?? ''),
                    'meta' => $groupMeta,
                ],
            );
        }

        foreach ($rawGroups as $group) {
            if (!is_array($group)) {
                continue;
            }
            $groupId = (string) ($group['id'] ?? '');
            if ($groupId === '') {
                continue;
            }
            foreach ($this->discoverPlannerPlansForGroup($groupId) as $planResource) {
                $resources[$planResource['id']] = $planResource;
            }
            foreach ($this->discoverOneNoteNotebooks('groups', $groupId) as $nbResource) {
                $resources[$nbResource['id']] = $nbResource;
            }
        }

        foreach ($rawTeams as $team) {
            if (!is_array($team)) {
                continue;
            }
            $groupId = (string) ($team['id'] ?? '');
            if ($groupId === '') {
                continue;
            }
            foreach ($this->discoverPlannerPlansForGroup($groupId) as $planResource) {
                $resources[$planResource['id']] = $planResource;
            }
        }

        foreach ($userGraphIds as $userGraphId) {
            foreach ($this->discoverOneNoteNotebooks('users', $userGraphId) as $nbResource) {
                $resources[$nbResource['id']] = $nbResource;
            }
        }

        foreach ($rawSites as $site) {
            if (!is_array($site)) {
                continue;
            }
            $siteId = (string) ($site['id'] ?? '');
            if ($siteId === '') {
                continue;
            }
            foreach ($this->discoverOneNoteNotebooks('sites', $siteId) as $nbResource) {
                $resources[$nbResource['id']] = $nbResource;
            }
        }

        $dirId = TenantResource::makeId(TenantResource::TYPE_DIRECTORY_BASELINE, 'tenant');
        $resources[$dirId] = TenantResource::build(
            TenantResource::TYPE_DIRECTORY_BASELINE,
            'tenant',
            'Directory baseline',
            null,
            ['id' => $dirId, 'meta' => []],
        );

        $resourceList = array_values($resources);
        $resolver = new RelationshipResolver();
        $relationships = $resolver->build($resourceList);

        $warnings = [];
        if ($this->discovery->isSharePointUnavailableFromCache()) {
            $warnings[] = 'SharePoint sites were not included because this Microsoft 365 tenant does not have a SharePoint Online license. User mail, calendars, OneDrive, and Teams can still be backed up.';
        }

        $this->writeRefreshProgress('assembling', 'Finalizing inventory…', $discoveryCounts);

        $inventory = [
            'fetched_at' => gmdate('c'),
            'resources' => $resourceList,
            'relationships' => $relationships,
            'counts' => TenantResource::countByType($resourceList),
            'warnings' => $warnings,
        ];

        $this->storage->writeJson($this->storage->inventoryPath(), $inventory);
        $this->writeRefreshProgress('complete', 'Inventory ready', $discoveryCounts);

        return $inventory;
    }

    /** @param array<string, int> $counts */
    private function writeRefreshProgress(string $phase, string $message, array $counts, ?string $detail = null): void
    {
        $payload = [
            'phase' => $phase,
            'message' => $message,
            'counts' => $counts,
            'updated_at' => gmdate('c'),
        ];
        if ($detail !== null && $detail !== '') {
            $payload['detail'] = $detail;
        }
        $this->storage->writeJson($this->storage->discoveryDir() . '/progress.json', $payload);
    }

    /** @return list<array<string, mixed>> */
    private function listM365Groups(): array
    {
        $groups = [];
        $query = [
            '$filter' => "NOT (resourceProvisioningOptions/Any(x:x eq 'Team'))",
            '$select' => 'id,displayName,description,mail,mailEnabled,securityEnabled,resourceProvisioningOptions',
            '$top' => '999',
        ];
        try {
            foreach ($this->graph->paginate('groups', $query) as $group) {
                $groups[] = $group;
            }
        } catch (\Throwable $e) {
            foreach ($this->graph->paginate('groups', ['$top' => '999']) as $group) {
                $opts = $group['resourceProvisioningOptions'] ?? [];
                if (is_array($opts) && in_array('Team', $opts, true)) {
                    continue;
                }
                $groups[] = $group;
            }
        }

        return $groups;
    }

    /** @return list<array<string, mixed>> */
    private function discoverPlannerPlansForGroup(string $groupId): array
    {
        $plans = [];
        try {
            foreach ($this->graph->paginate("groups/{$groupId}/planner/plans", ['$top' => '100']) as $plan) {
                if (!is_array($plan)) {
                    continue;
                }
                $planId = (string) ($plan['id'] ?? '');
                if ($planId === '') {
                    continue;
                }
                $resourceId = TenantResource::makeId(TenantResource::TYPE_PLANNER_PLAN, $planId);
                $plans[] = TenantResource::build(
                    TenantResource::TYPE_PLANNER_PLAN,
                    $planId,
                    (string) ($plan['title'] ?? $planId),
                    TenantResource::makeId(TenantResource::TYPE_M365_GROUP, $groupId),
                    [
                        'id' => $resourceId,
                        'meta' => [
                            'group_id' => $groupId,
                            'owner' => (string) ($plan['owner'] ?? ''),
                        ],
                    ],
                );
            }
        } catch (\Throwable $_) {
        }

        return $plans;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function discoverOneNoteNotebooks(string $ownerKind, string $ownerId): array
    {
        $notebooks = [];
        try {
            foreach ($this->graph->paginate("{$ownerKind}/{$ownerId}/onenote/notebooks", ['$top' => '100']) as $nb) {
                if (!is_array($nb)) {
                    continue;
                }
                $notebookId = (string) ($nb['id'] ?? '');
                if ($notebookId === '') {
                    continue;
                }
                $resourceId = TenantResource::makeId(TenantResource::TYPE_ONENOTE_NOTEBOOK, $notebookId);
                $notebooks[] = TenantResource::build(
                    TenantResource::TYPE_ONENOTE_NOTEBOOK,
                    $notebookId,
                    (string) ($nb['displayName'] ?? $notebookId),
                    null,
                    [
                        'id' => $resourceId,
                        'meta' => [
                            'owner_kind' => $ownerKind,
                            'owner_id' => $ownerId,
                            'notebook_id' => $notebookId,
                        ],
                    ],
                );
            }
        } catch (\Throwable $_) {
        }

        return $notebooks;
    }

    /** @return list<array<string, mixed>> */
    private function listTeamChannels(string $teamId): array
    {
        $channels = [];
        try {
            foreach ($this->graph->paginate("teams/{$teamId}/channels", ['$top' => '100']) as $channel) {
                $channels[] = $channel;
            }
        } catch (\Throwable $e) {
            return [];
        }

        return $channels;
    }

  /**
     * @param array<string, array<string, mixed>> $resources
     * @return array<string, mixed>|null
     */
    private function discoverUserOneDrive(string $userGraphId, array $resources): ?array
    {
        $userResourceId = TenantResource::makeId(TenantResource::TYPE_USER, $userGraphId);
        if (!isset($resources[$userResourceId])) {
            $mailboxResourceId = TenantResource::makeId(TenantResource::TYPE_MAILBOX, $userGraphId);
            $userResourceId = isset($resources[$mailboxResourceId]) ? $mailboxResourceId : null;
        }
        if ($userResourceId === null) {
            return null;
        }

        try {
            $drive = $this->graph->get("users/{$userGraphId}/drive", [
                '$select' => self::ONEDRIVE_SELECT,
            ]);
        } catch (\Throwable $e) {
            return null;
        }

        if (!is_array($drive)) {
            return null;
        }

        $driveId = (string) ($drive['id'] ?? '');
        if ($driveId === '') {
            return null;
        }

        $driveType = (string) ($drive['driveType'] ?? '');
        if ($driveType !== '' && strtolower($driveType) !== 'business' && strtolower($driveType) !== 'personal') {
            return null;
        }

        $quota = is_array($drive['quota'] ?? null) ? $drive['quota'] : [];
        $used = (int) ($quota['used'] ?? 0);
        $userDisplay = (string) ($resources[$userResourceId]['display_name'] ?? 'User');

        $resourceId = TenantResource::makeId(TenantResource::TYPE_USER_ONEDRIVE, $userGraphId);

        return TenantResource::build(
            TenantResource::TYPE_USER_ONEDRIVE,
            $driveId,
            $userDisplay . ' OneDrive',
            $userResourceId,
            [
                'id' => $resourceId,
                'meta' => [
                    'drive_id' => $driveId,
                    'drive_type' => $driveType,
                    'size_bytes' => $used,
                    'last_modified' => (string) ($drive['lastModifiedDateTime'] ?? ''),
                    'web_url' => (string) ($drive['webUrl'] ?? ''),
                    'owner_user_id' => $userGraphId,
                ],
            ],
        );
    }

    private function resolveGroupRootSiteId(string $groupId): string
    {
        try {
            $site = $this->graph->get("groups/{$groupId}/sites/root", [
                '$select' => 'id,webUrl,displayName',
            ]);
            if (is_array($site)) {
                return (string) ($site['id'] ?? '');
            }
        } catch (\Throwable $e) {
        }

        return '';
    }

    private function resolveChannelSiteId(string $teamId, string $channelId): string
    {
        try {
            $folder = $this->graph->get("teams/{$teamId}/channels/{$channelId}/filesFolder", [
                '$select' => 'id,parentReference,webUrl',
            ]);
            if (!is_array($folder)) {
                return '';
            }
            $parent = is_array($folder['parentReference'] ?? null) ? $folder['parentReference'] : [];
            $siteId = (string) ($parent['siteId'] ?? '');
            if ($siteId !== '') {
                return $siteId;
            }
        } catch (\Throwable $e) {
        }

        return '';
    }

    /**
     * @param array<string, mixed>|null $previous
     * @param list<array<string, mixed>> $rawUsers
     * @return array<string, array<string, mixed>>
     */
    private function extractUserAccessFromPrevious(?array $previous, array $rawUsers): array
    {
        $map = [];
        if ($previous !== null && is_array($previous['resources'] ?? null)) {
            foreach ($previous['resources'] as $resource) {
                if (!is_array($resource)) {
                    continue;
                }
                $type = (string) ($resource['resource_type'] ?? '');
                if (!in_array($type, [TenantResource::TYPE_USER, TenantResource::TYPE_MAILBOX], true)) {
                    continue;
                }
                $graphId = (string) ($resource['graph_id'] ?? '');
                if ($graphId === '' || !is_array($resource['access'] ?? null)) {
                    continue;
                }
                $map[$graphId] = $resource['access'];
            }
        }

        foreach ($rawUsers as $user) {
            if (!is_array($user)) {
                continue;
            }
            $graphId = (string) ($user['id'] ?? '');
            if ($graphId === '' || !is_array($user['access'] ?? null)) {
                continue;
            }
            $map[$graphId] = $user['access'];
        }

        return $map;
    }

    /**
     * Merge user access probes into inventory.json (chunked).
     *
     * @return array{total: int, processed: int, done: bool, unavailable_count: int}
     */
    public function checkInventoryUserAccessBatch(int $offset, int $limit, ResourceAccessService $access): array
    {
        $inventory = $this->load();
        if ($inventory === null) {
            throw new \RuntimeException('No inventory cache. Refresh resource inventory from Graph first.');
        }

        $resources = is_array($inventory['resources'] ?? null) ? $inventory['resources'] : [];
        $userResources = array_values(array_filter(
            $resources,
            static function ($r): bool {
                if (!is_array($r)) {
                    return false;
                }

                return in_array(
                    (string) ($r['resource_type'] ?? ''),
                    [TenantResource::TYPE_USER, TenantResource::TYPE_MAILBOX],
                    true,
                );
            },
        ));

        $total = count($userResources);
        $slice = array_slice($userResources, $offset, $limit);
        $unavailableInBatch = 0;

        $byId = [];
        foreach ($resources as $i => $resource) {
            if (!is_array($resource)) {
                continue;
            }
            $byId[(string) ($resource['id'] ?? '')] = $i;
        }

        foreach ($slice as $resource) {
            $graphId = (string) ($resource['graph_id'] ?? '');
            if ($graphId === '') {
                continue;
            }
            $probe = $access->probeUser($graphId);
            $resourceId = (string) ($resource['id'] ?? '');
            if ($resourceId === '' || !isset($byId[$resourceId])) {
                continue;
            }
            $resources[$byId[$resourceId]]['access'] = $probe;
            if (ResourceAccessService::isUserAccessProblematic($probe)) {
                $unavailableInBatch++;
            }
        }

        $inventory['resources'] = $resources;
        $inventory['access_checked_at'] = gmdate('c');
        $this->storage->writeJson($this->storage->inventoryPath(), $inventory);

        $processed = min($offset + count($slice), $total);

        return [
            'total' => $total,
            'processed' => $processed,
            'done' => $processed >= $total,
            'unavailable_count' => $unavailableInBatch,
        ];
    }

    /**
     * Merge OneDrive access probes into inventory.json (chunked).
     *
     * @return array{total: int, processed: int, done: bool, unavailable_count: int}
     */
    public function checkInventoryOneDriveAccessBatch(int $offset, int $limit, ResourceAccessService $access): array
    {
        $inventory = $this->load();
        if ($inventory === null) {
            throw new \RuntimeException('No inventory cache. Refresh resource inventory from Graph first.');
        }

        $resources = is_array($inventory['resources'] ?? null) ? $inventory['resources'] : [];
        $odResources = array_values(array_filter(
            $resources,
            static fn ($r): bool => is_array($r)
                && (string) ($r['resource_type'] ?? '') === TenantResource::TYPE_USER_ONEDRIVE,
        ));

        $total = count($odResources);
        $slice = array_slice($odResources, $offset, $limit);
        $unavailableInBatch = 0;

        $byId = [];
        foreach ($resources as $i => $resource) {
            if (!is_array($resource)) {
                continue;
            }
            $byId[(string) ($resource['id'] ?? '')] = $i;
        }

        foreach ($slice as $resource) {
            $meta = is_array($resource['meta'] ?? null) ? $resource['meta'] : [];
            $driveId = (string) ($meta['drive_id'] ?? $resource['graph_id'] ?? '');
            if ($driveId === '') {
                continue;
            }
            $probeResult = $access->probeDrive($driveId);
            $probe = [
                'onedrive' => $probeResult->status,
                'onedrive_reason' => $probeResult->reason,
                'checked_at' => gmdate('c'),
            ];
            $resourceId = (string) ($resource['id'] ?? '');
            if ($resourceId === '' || !isset($byId[$resourceId])) {
                continue;
            }
            $resources[$byId[$resourceId]]['access'] = array_merge(
                is_array($resources[$byId[$resourceId]]['access'] ?? null)
                    ? $resources[$byId[$resourceId]]['access']
                    : [],
                $probe,
            );
            if ($probeResult->status !== AccessResult::STATUS_AVAILABLE) {
                $unavailableInBatch++;
            }
        }

        $inventory['resources'] = $resources;
        $inventory['access_checked_at'] = gmdate('c');
        $this->storage->writeJson($this->storage->inventoryPath(), $inventory);

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
    public function checkInventorySiteAccessBatch(int $offset, int $limit, ResourceAccessService $access): array
    {
        $inventory = $this->load();
        if ($inventory === null) {
            throw new \RuntimeException('No inventory cache. Refresh resource inventory from Graph first.');
        }

        $resources = is_array($inventory['resources'] ?? null) ? $inventory['resources'] : [];
        $siteResources = array_values(array_filter(
            $resources,
            static function ($r): bool {
                return is_array($r)
                    && (string) ($r['resource_type'] ?? '') === TenantResource::TYPE_SHAREPOINT_SITE;
            },
        ));

        $total = count($siteResources);
        $slice = array_slice($siteResources, $offset, $limit);
        $unavailableInBatch = 0;

        $byId = [];
        foreach ($resources as $i => $resource) {
            if (!is_array($resource)) {
                continue;
            }
            $byId[(string) ($resource['id'] ?? '')] = $i;
        }

        foreach ($slice as $resource) {
            $graphId = (string) ($resource['graph_id'] ?? '');
            if ($graphId === '') {
                continue;
            }
            $probe = $access->probeSite($graphId);
            $resourceId = (string) ($resource['id'] ?? '');
            if ($resourceId === '' || !isset($byId[$resourceId])) {
                continue;
            }
            $resources[$byId[$resourceId]]['access'] = $probe;
            if (($probe['status'] ?? '') !== AccessResult::STATUS_AVAILABLE) {
                $unavailableInBatch++;
            }
        }

        $inventory['resources'] = $resources;
        $inventory['access_checked_at'] = gmdate('c');
        $this->storage->writeJson($this->storage->inventoryPath(), $inventory);

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
    public function checkInventoryTeamAccessBatch(int $offset, int $limit, ResourceAccessService $access): array
    {
        $inventory = $this->load();
        if ($inventory === null) {
            throw new \RuntimeException('No inventory cache. Refresh resource inventory from Graph first.');
        }

        $resources = is_array($inventory['resources'] ?? null) ? $inventory['resources'] : [];
        $teamResources = array_values(array_filter(
            $resources,
            static function ($r): bool {
                return is_array($r)
                    && (string) ($r['resource_type'] ?? '') === TenantResource::TYPE_TEAM;
            },
        ));

        $total = count($teamResources);
        $slice = array_slice($teamResources, $offset, $limit);
        $unavailableInBatch = 0;

        $byId = [];
        foreach ($resources as $i => $resource) {
            if (!is_array($resource)) {
                continue;
            }
            $byId[(string) ($resource['id'] ?? '')] = $i;
        }

        foreach ($slice as $resource) {
            $meta = is_array($resource['meta'] ?? null) ? $resource['meta'] : [];
            $groupId = (string) ($meta['group_id'] ?? $resource['graph_id'] ?? '');
            if ($groupId === '') {
                continue;
            }
            $probe = $access->probeTeam($groupId);
            $resourceId = (string) ($resource['id'] ?? '');
            if ($resourceId === '' || !isset($byId[$resourceId])) {
                continue;
            }
            $resources[$byId[$resourceId]]['access'] = $probe;
            if (($probe['status'] ?? '') !== AccessResult::STATUS_AVAILABLE) {
                $unavailableInBatch++;
            }
        }

        $inventory['resources'] = $resources;
        $inventory['access_checked_at'] = gmdate('c');
        $this->storage->writeJson($this->storage->inventoryPath(), $inventory);

        $processed = min($offset + count($slice), $total);

        return [
            'total' => $total,
            'processed' => $processed,
            'done' => $processed >= $total,
            'unavailable_count' => $unavailableInBatch,
        ];
    }
}
