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
     * @param bool $lightweight Skip Planner/OneNote and SharePoint list item counts (faster for large tenants).
     * @return array<string, mixed>
     */
    public function refresh(bool $lightweight = false): array
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

        $siteIdsForProbe = [];
        foreach ($rawSites as $site) {
            if (!is_array($site)) {
                continue;
            }
            $siteId = (string) ($site['id'] ?? '');
            if ($siteId === '') {
                continue;
            }
            $resourceId = TenantResource::makeId(TenantResource::TYPE_SHAREPOINT_SITE, $siteId);
            $siteIdsForProbe[] = ['site_id' => $siteId, 'resource_id' => $resourceId];
            $resources[$resourceId] = TenantResource::build(
                TenantResource::TYPE_SHAREPOINT_SITE,
                $siteId,
                (string) ($site['displayName'] ?? $site['name'] ?? $siteId),
                null,
                [
                    'id' => $resourceId,
                    'email' => (string) ($site['webUrl'] ?? ''),
                    'access' => [],
                    'meta' => [
                        'web_url' => (string) ($site['webUrl'] ?? ''),
                        'site_collection' => $site['siteCollection'] ?? null,
                        'drives' => $this->listSiteDrives($siteId),
                        'lists' => $this->listSiteLists($siteId, !$lightweight),
                    ],
                ],
            );
        }

        $accessCheckedAt = null;
        if ($siteIdsForProbe !== []) {
            $accessService = new ResourceAccessService($this->graph, $this->storage);
            $siteTotal = count($siteIdsForProbe);
            $this->writeRefreshProgress(
                'site_access',
                'Checking SharePoint site access…',
                $discoveryCounts,
            );
            foreach ($siteIdsForProbe as $siteIndex => $siteInfo) {
                if ($siteIndex === 0 || ($siteIndex + 1) % 5 === 0 || $siteIndex === $siteTotal - 1) {
                    $this->writeRefreshProgress(
                        'site_access',
                        'Checking SharePoint site access…',
                        $discoveryCounts,
                        sprintf('Site %d of %d', $siteIndex + 1, $siteTotal),
                    );
                }
                $probe = $accessService->probeSite($siteInfo['site_id']);
                $resourceId = $siteInfo['resource_id'];
                if (isset($resources[$resourceId]) && is_array($resources[$resourceId])) {
                    $resources[$resourceId]['access'] = $probe;
                }
            }
            $accessCheckedAt = gmdate('c');
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

        if (!$lightweight) {
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
        }

        $dirId = TenantResource::makeId(TenantResource::TYPE_DIRECTORY_BASELINE, 'tenant');
        $resources[$dirId] = TenantResource::build(
            TenantResource::TYPE_DIRECTORY_BASELINE,
            'tenant',
            'Directory baseline',
            null,
            ['id' => $dirId, 'meta' => []],
        );

        $this->enrichTeamAndGroupMembers($resources, $discoveryCounts);

        $resourceList = array_values($resources);
        $resolver = new RelationshipResolver();
        $relationships = $resolver->build($resourceList);

        $warnings = [];
        if ($this->discovery->isSharePointUnavailableFromCache()) {
            $warnings[] = 'SharePoint sites were not included because this Microsoft 365 tenant does not have a SharePoint Online license. User mail, calendars, OneDrive, and Teams can still be backed up.';
        }

        $enrichedResources = TenantResource::enrichSharePointDisplayMetadata($resourceList, $relationships);
        $displayCounts = TenantResource::displayCounts($enrichedResources);
        $discoveryCounts['sites'] = $displayCounts['sites'];

        $this->writeRefreshProgress(
            'assembling',
            'Finalizing inventory…',
            $discoveryCounts,
            null,
            $resourceList,
            $relationships,
        );

        $inventory = [
            'fetched_at' => gmdate('c'),
            'resources' => $resourceList,
            'relationships' => $relationships,
            'counts' => TenantResource::countByType($resourceList),
            'display_counts' => TenantResource::displayCounts(
                TenantResource::enrichSharePointDisplayMetadata($resourceList, $relationships),
            ),
            'warnings' => $warnings,
        ];
        if ($accessCheckedAt !== null) {
            $inventory['access_checked_at'] = $accessCheckedAt;
        }

        $inventoryPath = $this->storage->inventoryPath();

        $this->storage->writeJson($inventoryPath, $inventory);

        $this->writeRefreshProgress('complete', 'Inventory ready', $discoveryCounts, null, $resourceList, $relationships);

        return $inventory;
    }

    /**
     * @param list<array<string, mixed>> $resources
     * @param list<array{from_id: string, rel: string, to_id: string, physical_key: string}> $relationships
     * @param array<string, int> $counts
     */
    private function writeRefreshProgress(
        string $phase,
        string $message,
        array $counts,
        ?string $detail = null,
        array $resources = [],
        array $relationships = [],
    ): void {
        $payload = [
            'phase' => $phase,
            'message' => $message,
            'counts' => $counts,
            'updated_at' => gmdate('c'),
        ];
        if ($detail !== null && $detail !== '') {
            $payload['detail'] = $detail;
        }
        if ($phase === 'complete' && $resources !== []) {
            $enriched = TenantResource::enrichSharePointDisplayMetadata($resources, $relationships);
            $payload['display_counts'] = TenantResource::displayCounts($enriched);
            $payload['counts']['sites'] = $payload['display_counts']['sites'];
        }
        $this->storage->writeJson($this->storage->discoveryDir() . '/progress.json', $payload);
    }

    /**
     * @param array<string, array<string, mixed>> $resources
     * @param array<string, int> $discoveryCounts
     */
    private function enrichTeamAndGroupMembers(array &$resources, array &$discoveryCounts): void
    {
        $memberTargets = [];
        foreach ($resources as $resource) {
            $type = (string) ($resource['resource_type'] ?? '');
            if (!in_array($type, [TenantResource::TYPE_TEAM, TenantResource::TYPE_M365_GROUP], true)) {
                continue;
            }
            $id = (string) ($resource['id'] ?? '');
            $groupId = (string) ($resource['graph_id'] ?? '');
            if ($id === '' || $groupId === '') {
                continue;
            }
            $memberTargets[] = ['id' => $id, 'group_id' => $groupId, 'type' => $type];
        }

        if ($memberTargets === []) {
            return;
        }

        $total = count($memberTargets);
        $inventoryStub = ['resources' => array_values($resources)];
        foreach ($memberTargets as $index => $target) {
            if ($index === 0 || ($index + 1) % 5 === 0 || $index === $total - 1) {
                $this->writeRefreshProgress(
                    'group_members',
                    'Loading team and group members…',
                    $discoveryCounts,
                    sprintf('Members: %d of %d', $index + 1, $total),
                );
            }

            $groupId = $target['group_id'];
            $resourceId = $target['id'];
            try {
                $rows = $target['type'] === TenantResource::TYPE_M365_GROUP
                    ? $this->discovery->listGroupMembers($groupId)
                    : $this->discovery->listTeamMembers($groupId);
                $billable = ProtectedUserResolver::billableIdsFromMemberRows($rows, $inventoryStub);
            } catch (\Throwable $_) {
                $billable = [];
            }

            if (!isset($resources[$resourceId]['meta']) || !is_array($resources[$resourceId]['meta'])) {
                $resources[$resourceId]['meta'] = [];
            }
            $resources[$resourceId]['meta']['member_azure_ids'] = $billable;
            $resources[$resourceId]['meta']['member_count'] = count($billable);
            $resources[$resourceId]['meta']['members_fetched_at'] = gmdate('c');
        }
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
     * @return list<array<string, mixed>>
     */
    private function listSiteDrives(string $siteId): array
    {
        $drives = [];
        try {
            foreach ($this->graph->paginate('sites/' . rawurlencode($siteId) . '/drives', ['$top' => '50']) as $drive) {
                if (!is_array($drive)) {
                    continue;
                }
                $driveId = trim((string) ($drive['id'] ?? ''));
                if ($driveId === '') {
                    continue;
                }
                $quota = is_array($drive['quota'] ?? null) ? $drive['quota'] : [];
                $drives[] = [
                    'id' => $driveId,
                    'name' => (string) ($drive['name'] ?? $driveId),
                    'size_bytes' => max(0, (int) ($quota['used'] ?? 0)),
                    'item_count' => max(0, (int) ($drive['item_count'] ?? 0)),
                ];
            }
        } catch (\Throwable $_) {
            return [];
        }

        return $drives;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listSiteLists(string $siteId, bool $probeItemCounts = true): array
    {
        $lists = [];
        $maxCountProbes = $probeItemCounts ? 200 : 0;
        $probed = 0;
        try {
            foreach ($this->graph->paginate('sites/' . rawurlencode($siteId) . '/lists', [
                '$select' => 'id,displayName,list,webUrl',
                '$top' => '100',
            ]) as $list) {
                if (!is_array($list)) {
                    continue;
                }
                $listId = trim((string) ($list['id'] ?? ''));
                if ($listId === '') {
                    continue;
                }
                $itemCount = null;
                if ($probed < $maxCountProbes) {
                    $itemCount = $this->listItemCount($siteId, $listId);
                    ++$probed;
                }
                $lists[] = [
                    'id' => $listId,
                    'display_name' => (string) ($list['displayName'] ?? $listId),
                    'item_count' => $itemCount,
                ];
            }
        } catch (\Throwable $_) {
            return [];
        }

        return $lists;
    }

    private function listItemCount(string $siteId, string $listId): ?int
    {
        try {
            $path = 'sites/' . rawurlencode($siteId) . '/lists/' . rawurlencode($listId) . '/items/$count';
            $data = $this->graph->get($path, []);
            if (isset($data['@odata.count'])) {
                return max(0, (int) $data['@odata.count']);
            }
        } catch (\Throwable $_) {
            return null;
        }

        return null;
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

    /**
     * Populate shard metadata (drives, lists, size hints) for selected resources before backup planning.
     *
     * @param array<string, mixed> $inventory
     * @param list<string> $selectedIds
     */
    public function enrichResourcesForPlanning(array &$inventory, array $selectedIds, bool $lightweight = false): void
    {
        if ($selectedIds === [] || !is_array($inventory['resources'] ?? null)) {
            return;
        }

        $selected = array_fill_keys($selectedIds, true);
        $resources = $inventory['resources'];
        $byId = [];
        foreach ($resources as $idx => $resource) {
            if (!is_array($resource)) {
                continue;
            }
            $id = (string) ($resource['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $byId[$id] = $idx;
        }

        foreach ($selectedIds as $resourceId) {
            if (!isset($byId[$resourceId])) {
                continue;
            }
            $idx = $byId[$resourceId];
            $resource = $resources[$idx];
            if (!is_array($resource)) {
                continue;
            }

            $type = (string) ($resource['resource_type'] ?? '');
            $graphId = TenantResource::graphIdFromResourceId($resourceId);

            if ($type === TenantResource::TYPE_SHAREPOINT_SITE && $graphId !== '') {
                $resources[$idx] = $this->enrichSharePointSiteResource($resource, $graphId, $lightweight);
                continue;
            }

            if ($type === TenantResource::TYPE_USER_ONEDRIVE && $graphId !== '') {
                $resources[$idx] = $this->enrichOneDriveResource($resource, $graphId);
                continue;
            }

            if (in_array($type, [TenantResource::TYPE_USER, TenantResource::TYPE_MAILBOX], true)) {
                $onedriveId = TenantResource::makeId(TenantResource::TYPE_USER_ONEDRIVE, $graphId);
                if (isset($byId[$onedriveId])) {
                    $odIdx = $byId[$onedriveId];
                    $odResource = is_array($resources[$odIdx] ?? null) ? $resources[$odIdx] : null;
                    if ($odResource !== null) {
                        $odResource = $this->enrichOneDriveResource($odResource, $graphId);
                        $resources[$odIdx] = $odResource;
                        $resources[$idx] = $this->mergeOneDriveHintsOntoUser($resource, $odResource);
                    }
                }
            }
        }

        $inventory['resources'] = $resources;
    }

    /**
     * @param array<string, mixed> $resource
     * @return array<string, mixed>
     */
    private function enrichSharePointSiteResource(array $resource, string $siteId, bool $lightweight = false): array
    {
        $meta = is_array($resource['meta'] ?? null) ? $resource['meta'] : [];
        $drives = $meta['drives'] ?? null;
        if (!is_array($drives) || $drives === []) {
            $meta['drives'] = $this->listSiteDrives($siteId);
        }
        $lists = $meta['lists'] ?? null;
        if (!is_array($lists) || $lists === []) {
            $meta['lists'] = $this->listSiteLists($siteId, !$lightweight);
        }

        $sizeBytes = 0;
        $itemCount = 0;
        foreach ($meta['drives'] as $drive) {
            if (!is_array($drive)) {
                continue;
            }
            $sizeBytes += max(0, (int) ($drive['size_bytes'] ?? 0));
            $itemCount += max(0, (int) ($drive['item_count'] ?? 0));
        }
        if ($sizeBytes > 0) {
            $meta['size_bytes'] = $sizeBytes;
        }
        if ($itemCount > 0) {
            $meta['item_count'] = $itemCount;
        }
        $resource['meta'] = $meta;

        return $resource;
    }

    /**
     * @param array<string, mixed> $resource
     * @return array<string, mixed>
     */
    private function enrichOneDriveResource(array $resource, string $userGraphId): array
    {
        $meta = is_array($resource['meta'] ?? null) ? $resource['meta'] : [];
        if ((int) ($meta['size_bytes'] ?? 0) > 0) {
            return $resource;
        }

        try {
            $drive = $this->graph->get('users/' . rawurlencode($userGraphId) . '/drive', [
                '$select' => self::ONEDRIVE_SELECT,
            ]);
        } catch (\Throwable $_) {
            return $resource;
        }

        if (!is_array($drive)) {
            return $resource;
        }

        $quota = is_array($drive['quota'] ?? null) ? $drive['quota'] : [];
        $meta['drive_id'] = (string) ($meta['drive_id'] ?? $drive['id'] ?? '');
        $meta['size_bytes'] = max(0, (int) ($quota['used'] ?? 0));
        $resource['meta'] = $meta;

        return $resource;
    }

    /**
     * @param array<string, mixed> $userResource
     * @param array<string, mixed> $onedriveResource
     * @return array<string, mixed>
     */
    private function mergeOneDriveHintsOntoUser(array $userResource, array $onedriveResource): array
    {
        $userMeta = is_array($userResource['meta'] ?? null) ? $userResource['meta'] : [];
        $odMeta = is_array($onedriveResource['meta'] ?? null) ? $onedriveResource['meta'] : [];
        if ((int) ($odMeta['size_bytes'] ?? 0) > 0) {
            $userMeta['size_bytes'] = (int) $odMeta['size_bytes'];
        }
        if ((int) ($odMeta['item_count'] ?? 0) > 0) {
            $userMeta['item_count'] = (int) $odMeta['item_count'];
        }
        $driveId = trim((string) ($odMeta['drive_id'] ?? ''));
        if ($driveId !== '') {
            $userMeta['drive_id'] = $driveId;
        }
        $userResource['meta'] = $userMeta;

        return $userResource;
    }
}
