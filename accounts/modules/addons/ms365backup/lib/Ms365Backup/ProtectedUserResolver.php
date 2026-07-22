<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * Resolves billable Protected Object Azure IDs from job selection + inventory.
 *
 * One Azure object ID counts at most once (personal + team/group + site membership).
 */
final class ProtectedUserResolver
{
    /** @var list<string> */
    private const PERSONAL_TYPES = [
        TenantResource::TYPE_USER,
        TenantResource::TYPE_MAILBOX,
        TenantResource::TYPE_USER_ONEDRIVE,
    ];

    /** @var list<string> */
    private const PERSONAL_SCOPE_KEYS = [
        BackupScope::MAIL,
        BackupScope::CALENDAR,
        BackupScope::CONTACTS,
        BackupScope::TASKS,
        BackupScope::ONEDRIVE,
        BackupScope::FILES,
    ];

    /** @var list<string> */
    private const MEMBER_SOURCE_TYPES = [
        TenantResource::TYPE_TEAM,
        TenantResource::TYPE_TEAM_CHANNEL,
        TenantResource::TYPE_M365_GROUP,
        TenantResource::TYPE_SHAREPOINT_SITE,
    ];

    /**
     * @param array<string, mixed> $inventory
     * @param list<string> $selectedIds
     * @param array<string, array<string, bool>> $scopeOverrides
     * @return array{
     *   protected_azure_ids: list<string>,
     *   sources: array<string, list<string>>,
     *   breakdown: list<array{resource_id: string, label: string, member_count: int}>,
     *   member_resolution_pending: bool,
     *   reconciliation: array{
     *     direct_appearances: int,
     *     membership_appearances: int,
     *     duplicate_appearances_removed: int,
     *     protected_objects: int
     *   }
     * }
     */
    public static function resolve(
        array $inventory,
        array $selectedIds,
        array $scopeOverrides,
        ?DiscoveryService $discovery = null,
    ): array {
        $selectedIds = CustomerSelectionCodec::normalizeIds($selectedIds);
        $byId = self::resourcesById($inventory);
        $userIndex = self::buildUserIndex($inventory);
        $protected = [];
        $sources = [];
        $breakdown = [];
        $memberResolutionPending = false;
        $resolvedGroupFetches = [];

        foreach ($selectedIds as $resourceId) {
            $resource = $byId[$resourceId] ?? null;
            if ($resource === null) {
                continue;
            }
            $type = (string) ($resource['resource_type'] ?? '');

            if (in_array($type, self::PERSONAL_TYPES, true)) {
                if (!self::hasEnabledPersonalScope($type, $resourceId, $scopeOverrides, $inventory)) {
                    continue;
                }
                $azureUserId = self::azureUserIdForResource($resource, $type);
                if ($azureUserId === '' || !self::isBillableMember($azureUserId, $userIndex)) {
                    continue;
                }
                $protected[$azureUserId] = true;
                $sources[$resourceId] = [$azureUserId];
                continue;
            }

            if (!in_array($type, self::MEMBER_SOURCE_TYPES, true)) {
                continue;
            }
            if (!self::hasEnabledSharedScope($type, $resourceId, $scopeOverrides, $inventory)) {
                continue;
            }

            if ($type === TenantResource::TYPE_SHAREPOINT_SITE) {
                $siteGraphId = (string) ($resource['graph_id']
                    ?? TenantResource::graphIdFromResourceId((string) ($resource['id'] ?? '')));
                $fetchKey = 'site:' . $siteGraphId;
                if (!isset($resolvedGroupFetches[$fetchKey])) {
                    $resolvedGroupFetches[$fetchKey] = self::resolveSiteMembers(
                        $siteGraphId,
                        $resource,
                        $byId,
                        $userIndex,
                        $discovery,
                        $memberResolutionPending,
                    );
                }

                $memberIds = $resolvedGroupFetches[$fetchKey]['ids'];
                if ($resolvedGroupFetches[$fetchKey]['pending']) {
                    $memberResolutionPending = true;
                }

                $fromThisResource = [];
                foreach ($memberIds as $memberId) {
                    $protected[$memberId] = true;
                    $fromThisResource[] = $memberId;
                }
                $sources[$resourceId] = $fromThisResource;
                $breakdown[] = [
                    'resource_id' => $resourceId,
                    'label' => (string) ($resource['display_name'] ?? $resourceId),
                    'member_count' => count($fromThisResource),
                ];
                continue;
            }

            $groupId = self::groupIdForMemberResource($resource, $type, $byId);
            if ($groupId === '') {
                $memberResolutionPending = true;
                continue;
            }

            $fetchKey = $groupId . ':' . ($type === TenantResource::TYPE_M365_GROUP ? 'group' : 'team');
            if (!isset($resolvedGroupFetches[$fetchKey])) {
                $resolvedGroupFetches[$fetchKey] = self::resolveGroupMembers(
                    $groupId,
                    $type === TenantResource::TYPE_M365_GROUP ? TenantResource::TYPE_M365_GROUP : TenantResource::TYPE_TEAM,
                    $byId,
                    $userIndex,
                    $discovery,
                    $memberResolutionPending,
                );
            }

            $memberIds = $resolvedGroupFetches[$fetchKey]['ids'];
            if ($resolvedGroupFetches[$fetchKey]['pending']) {
                $memberResolutionPending = true;
            }

            $fromThisResource = [];
            foreach ($memberIds as $memberId) {
                $protected[$memberId] = true;
                $fromThisResource[] = $memberId;
            }
            $sources[$resourceId] = $fromThisResource;
            $breakdown[] = [
                'resource_id' => $resourceId,
                'label' => (string) ($resource['display_name'] ?? $resourceId),
                'member_count' => count($fromThisResource),
            ];
        }

        $directAppearances = 0;
        $membershipAppearances = 0;
        foreach ($sources as $resourceId => $memberIds) {
            $resource = $byId[$resourceId] ?? null;
            if ($resource === null) {
                continue;
            }
            $type = (string) ($resource['resource_type'] ?? '');
            $count = count($memberIds);
            if (in_array($type, self::PERSONAL_TYPES, true)) {
                $directAppearances += $count;
            } elseif (in_array($type, self::MEMBER_SOURCE_TYPES, true)) {
                $membershipAppearances += $count;
            }
        }
        $protectedCount = count($protected);

        return [
            'protected_azure_ids' => array_keys($protected),
            'sources' => $sources,
            'breakdown' => $breakdown,
            'member_resolution_pending' => $memberResolutionPending,
            'reconciliation' => [
                'direct_appearances' => $directAppearances,
                'membership_appearances' => $membershipAppearances,
                'duplicate_appearances_removed' => $directAppearances + $membershipAppearances - $protectedCount,
                'protected_objects' => $protectedCount,
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $memberRows
     * @param array<string, array<string, mixed>> $inventory
     * @return list<string>
     */
    public static function billableIdsFromMemberRows(array $memberRows, array $inventory): array
    {
        $userIndex = self::buildUserIndex($inventory);
        $ids = [];
        foreach ($memberRows as $member) {
            if (!is_array($member)) {
                continue;
            }
            $azureId = trim((string) ($member['id'] ?? ''));
            if ($azureId === '' || !self::isBillableMember($azureId, $userIndex, $member)) {
                continue;
            }
            $ids[$azureId] = true;
        }

        return array_keys($ids);
    }

    /**
     * @param list<string> $azureIds
     * @param array<string, mixed> $inventory
     * @return list<string>
     */
    public static function filterBillableIds(array $azureIds, array $inventory): array
    {
        $userIndex = self::buildUserIndex($inventory);
        $ids = [];
        foreach ($azureIds as $azureId) {
            $azureId = trim((string) $azureId);
            if ($azureId !== '' && self::isBillableMember($azureId, $userIndex)) {
                $ids[$azureId] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * @param array<string, array{user_type: string, upn: string, resource_type: string}> $userIndex
     * @param array<string, mixed>|null $memberRow
     */
    public static function isBillableMember(string $azureId, array $userIndex, ?array $memberRow = null): bool
    {
        if ($azureId === '') {
            return false;
        }

        $userType = '';
        $upn = '';
        $resourceType = '';

        if (isset($userIndex[$azureId])) {
            $userType = strtolower((string) ($userIndex[$azureId]['user_type'] ?? ''));
            $upn = strtolower((string) ($userIndex[$azureId]['upn'] ?? ''));
            $resourceType = (string) ($userIndex[$azureId]['resource_type'] ?? '');
        }
        if ($memberRow !== null) {
            if ($userType === '') {
                $userType = strtolower((string) ($memberRow['userType'] ?? ''));
            }
            if ($upn === '') {
                $upn = strtolower((string) ($memberRow['userPrincipalName'] ?? $memberRow['mail'] ?? ''));
            }
        }

        // Protected Objects: Member users, guests, shared/room/equipment mailboxes all bill.
        // Devices / service principals are filtered before rows reach here (Graph member helpers).
        return true;
    }

    /** @return array<string, array{user_type: string, upn: string, resource_type: string}> */
    private static function buildUserIndex(array $inventory): array
    {
        $index = [];
        foreach ($inventory['resources'] ?? [] as $resource) {
            if (!is_array($resource)) {
                continue;
            }
            $type = (string) ($resource['resource_type'] ?? '');
            if (!in_array($type, [TenantResource::TYPE_USER, TenantResource::TYPE_MAILBOX], true)) {
                continue;
            }
            $graphId = (string) ($resource['graph_id'] ?? TenantResource::graphIdFromResourceId((string) ($resource['id'] ?? '')));
            if ($graphId === '') {
                continue;
            }
            $meta = is_array($resource['meta'] ?? null) ? $resource['meta'] : [];
            $index[$graphId] = [
                'user_type' => (string) ($meta['user_type'] ?? ''),
                'upn' => strtolower((string) ($resource['email'] ?? $meta['mail'] ?? '')),
                'resource_type' => $type,
            ];
        }

        return $index;
    }

    /** @param array<string, array<string, mixed>> $byId */
    private static function groupIdForMemberResource(array $resource, string $type, array $byId): string
    {
        $meta = is_array($resource['meta'] ?? null) ? $resource['meta'] : [];
        if ($type === TenantResource::TYPE_M365_GROUP) {
            return (string) ($resource['graph_id'] ?? TenantResource::graphIdFromResourceId((string) ($resource['id'] ?? '')));
        }

        if ($type === TenantResource::TYPE_TEAM) {
            return (string) ($meta['group_id'] ?? $resource['graph_id'] ?? TenantResource::graphIdFromResourceId((string) ($resource['id'] ?? '')));
        }

        $parentId = (string) ($resource['parent_id'] ?? '');
        if ($parentId !== '' && isset($byId[$parentId])) {
            $parent = $byId[$parentId];
            $parentMeta = is_array($parent['meta'] ?? null) ? $parent['meta'] : [];

            return (string) ($parentMeta['group_id'] ?? $parent['graph_id'] ?? '');
        }

        $parsed = GraphTeamPaths::parseChannelResourceId((string) ($resource['id'] ?? ''));

        return (string) ($parsed['group_id'] ?? '');
    }

    /**
     * @param array<string, array<string, mixed>> $byId
     * @param array<string, array{user_type: string, upn: string, resource_type: string}> $userIndex
     * @return array{ids: list<string>, pending: bool}
     */
    private static function resolveGroupMembers(
        string $groupId,
        string $sourceType,
        array $byId,
        array $userIndex,
        ?DiscoveryService $discovery,
        bool &$memberResolutionPending,
    ): array {
        $resourceId = TenantResource::makeId($sourceType, $groupId);
        $resource = $byId[$resourceId] ?? null;
        if ($resource !== null) {
            $meta = is_array($resource['meta'] ?? null) ? $resource['meta'] : [];
            $cached = $meta['member_azure_ids'] ?? null;
            if (is_array($cached) && $cached !== []) {
                return [
                    'ids' => self::filterBillableIds(array_map('strval', $cached), ['resources' => array_values($byId)]),
                    'pending' => false,
                ];
            }
            // Empty array cache with members_fetched_at means "resolved, zero members"
            if (is_array($cached) && isset($meta['members_fetched_at'])) {
                return ['ids' => [], 'pending' => false];
            }
        }

        if ($discovery === null) {
            $memberResolutionPending = true;

            return ['ids' => [], 'pending' => true];
        }

        try {
            $rows = $sourceType === TenantResource::TYPE_M365_GROUP
                ? $discovery->listGroupMembers($groupId)
                : $discovery->listTeamMembers($groupId);
            $inventory = ['resources' => array_values($byId)];

            return [
                'ids' => self::billableIdsFromMemberRows($rows, $inventory),
                'pending' => false,
            ];
        } catch (\Throwable $_) {
            $memberResolutionPending = true;

            return ['ids' => [], 'pending' => true];
        }
    }

    /**
     * @param array<string, array<string, mixed>> $byId
     * @param array<string, array{user_type: string, upn: string, resource_type: string}> $userIndex
     * @return array{ids: list<string>, pending: bool}
     */
    private static function resolveSiteMembers(
        string $siteGraphId,
        array $resource,
        array $byId,
        array $userIndex,
        ?DiscoveryService $discovery,
        bool &$memberResolutionPending,
    ): array {
        $meta = is_array($resource['meta'] ?? null) ? $resource['meta'] : [];
        $cached = $meta['member_azure_ids'] ?? null;
        if (is_array($cached) && $cached !== []) {
            return [
                'ids' => self::filterBillableIds(array_map('strval', $cached), ['resources' => array_values($byId)]),
                'pending' => false,
            ];
        }
        // Empty array cache with members_fetched_at means "resolved, zero members"
        if (is_array($cached) && isset($meta['members_fetched_at'])) {
            return ['ids' => [], 'pending' => false];
        }
        if ($discovery === null || $siteGraphId === '') {
            $memberResolutionPending = true;

            return ['ids' => [], 'pending' => true];
        }
        try {
            $rows = $discovery->listSiteMembers($siteGraphId);
            $ids = self::billableIdsFromMemberRows($rows, ['resources' => array_values($byId)]);

            return ['ids' => $ids, 'pending' => false];
        } catch (\Throwable $_) {
            $memberResolutionPending = true;

            return ['ids' => [], 'pending' => true];
        }
    }

    /** @param array<string, mixed> $inventory */
    private static function hasEnabledPersonalScope(
        string $type,
        string $resourceId,
        array $scopeOverrides,
        array $inventory,
    ): bool {
        $resolved = CustomerSelectionCodec::resolveForExecution(
            [$resourceId],
            $scopeOverrides,
            $inventory,
        );
        $flags = $resolved['scope_overrides'][$resourceId] ?? [];
        if ($flags === []) {
            return in_array($type, self::PERSONAL_TYPES, true);
        }
        foreach (self::PERSONAL_SCOPE_KEYS as $key) {
            if (!empty($flags[$key])) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $inventory */
    private static function hasEnabledSharedScope(
        string $type,
        string $resourceId,
        array $scopeOverrides,
        array $inventory,
    ): bool {
        $resolved = CustomerSelectionCodec::resolveForExecution(
            [$resourceId],
            $scopeOverrides,
            $inventory,
        );
        $flags = $resolved['scope_overrides'][$resourceId] ?? [];
        if ($flags === []) {
            $defaults = BackupScope::forResourceType($type)->toArray();
            foreach ($defaults as $enabled) {
                if ($enabled) {
                    return true;
                }
            }

            return false;
        }
        foreach ($flags as $enabled) {
            if ($enabled) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $resource */
    private static function azureUserIdForResource(array $resource, string $type): string
    {
        if ($type === TenantResource::TYPE_USER_ONEDRIVE) {
            $owner = (string) ($resource['meta']['owner_user_id'] ?? '');
            if ($owner !== '') {
                return $owner;
            }

            return TenantResource::graphIdFromResourceId((string) ($resource['id'] ?? ''));
        }

        return (string) ($resource['graph_id'] ?? TenantResource::graphIdFromResourceId((string) ($resource['id'] ?? '')));
    }

    /** @param array<string, mixed> $inventory */
    /** @return array<string, array<string, mixed>> */
    private static function resourcesById(array $inventory): array
    {
        $byId = [];
        foreach ($inventory['resources'] ?? [] as $resource) {
            if (!is_array($resource)) {
                continue;
            }
            $id = (string) ($resource['id'] ?? '');
            if ($id !== '') {
                $byId[$id] = $resource;
            }
        }

        return $byId;
    }
}
