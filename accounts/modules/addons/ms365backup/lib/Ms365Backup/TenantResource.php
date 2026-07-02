<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * Canonical tenant resource types and normalized inventory records.
 */
final class TenantResource
{
    public const TYPE_USER = 'user';
    public const TYPE_MAILBOX = 'mailbox';
    public const TYPE_USER_ONEDRIVE = 'user_onedrive';
    public const TYPE_SHAREPOINT_SITE = 'sharepoint_site';
    public const TYPE_TEAM = 'team';
    public const TYPE_TEAM_CHANNEL = 'team_channel';
    public const TYPE_M365_GROUP = 'm365_group';
    public const TYPE_PLANNER_PLAN = 'planner_plan';
    public const TYPE_ONENOTE_NOTEBOOK = 'onenote_notebook';
    public const TYPE_DIRECTORY_BASELINE = 'directory_baseline';

    /** @var list<string> */
    public const RUNNABLE_BACKUP_TYPES = [self::TYPE_USER, self::TYPE_MAILBOX];

    /** @var array<string, string> */
    private const BADGE_LABELS = [
        self::TYPE_USER => 'User',
        self::TYPE_MAILBOX => 'Mailbox',
        self::TYPE_USER_ONEDRIVE => 'OneDrive',
        self::TYPE_SHAREPOINT_SITE => 'SharePoint Site',
        self::TYPE_TEAM => 'Team',
        self::TYPE_TEAM_CHANNEL => 'Channel',
        self::TYPE_M365_GROUP => 'Group',
        self::TYPE_PLANNER_PLAN => 'Planner',
        self::TYPE_ONENOTE_NOTEBOOK => 'OneNote',
        self::TYPE_DIRECTORY_BASELINE => 'Tenant metadata',
    ];

    /** @var array<string, list<string>> */
    private const CAPABILITY_CHIPS = [
        self::TYPE_USER => ['Mail', 'Calendar', 'Contacts', 'Tasks', 'OneDrive'],
        self::TYPE_MAILBOX => ['Mail', 'Calendar'],
        self::TYPE_USER_ONEDRIVE => ['OneDrive', 'Files'],
        self::TYPE_SHAREPOINT_SITE => ['Files', 'Lists'],
        self::TYPE_TEAM => ['Channels', 'Messages', 'Files via SharePoint', 'Planner', 'OneNote'],
        self::TYPE_TEAM_CHANNEL => ['Messages', 'Files via SharePoint'],
        self::TYPE_M365_GROUP => ['Mail', 'Calendar', 'Files'],
        self::TYPE_PLANNER_PLAN => ['Planner'],
        self::TYPE_ONENOTE_NOTEBOOK => ['OneNote'],
        self::TYPE_DIRECTORY_BASELINE => [],
    ];

    public static function makeId(string $resourceType, string $graphId): string
    {
        $prefix = match ($resourceType) {
            self::TYPE_USER, self::TYPE_MAILBOX => 'user',
            self::TYPE_USER_ONEDRIVE => 'onedrive',
            self::TYPE_SHAREPOINT_SITE => 'site',
            self::TYPE_TEAM => 'team',
            self::TYPE_TEAM_CHANNEL => 'channel',
            self::TYPE_M365_GROUP => 'group',
            self::TYPE_PLANNER_PLAN => 'planner',
            self::TYPE_ONENOTE_NOTEBOOK => 'onenote',
            self::TYPE_DIRECTORY_BASELINE => 'directory',
            default => 'resource',
        };

        return $prefix . ':' . $graphId;
    }

    public static function graphIdFromResourceId(string $resourceId): string
    {
        $pos = strpos($resourceId, ':');
        if ($pos === false) {
            return $resourceId;
        }

        return substr($resourceId, $pos + 1);
    }

    public static function badgeLabel(string $resourceType): string
    {
        return self::BADGE_LABELS[$resourceType] ?? ucfirst(str_replace('_', ' ', $resourceType));
    }

    /** @return list<string> */
    public static function capabilityChips(string $resourceType): array
    {
        return self::CAPABILITY_CHIPS[$resourceType] ?? [];
    }

    public static function isRunnableBackupType(string $resourceType): bool
    {
        return in_array($resourceType, self::RUNNABLE_BACKUP_TYPES, true);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function build(
        string $resourceType,
        string $graphId,
        string $displayName,
        ?string $parentId = null,
        array $overrides = [],
    ): array {
        $id = self::makeId($resourceType, $graphId);
        $backupEnabled = self::isRunnableBackupType($resourceType);

        $resource = [
            'id' => $id,
            'resource_type' => $resourceType,
            'graph_id' => $graphId,
            'display_name' => $displayName,
            'email' => '',
            'parent_id' => $parentId,
            'capabilities' => array_map(
                static fn (string $chip): string => strtolower(str_replace(' ', '_', $chip)),
                self::capabilityChips($resourceType),
            ),
            'capability_chips' => self::capabilityChips($resourceType),
            'badges' => [self::badgeLabel($resourceType)],
            'meta' => [],
            'access' => [],
            'backup_enabled' => $backupEnabled,
        ];

        return array_replace_recursive($resource, $overrides);
    }

    /**
     * Classify a Graph user row as user vs shared mailbox.
     */
    public static function classifyGraphUser(array $user): string
    {
        $userType = strtolower((string) ($user['userType'] ?? ''));
        if ($userType === 'sharedmailbox') {
            return self::TYPE_MAILBOX;
        }

        $upn = trim((string) ($user['userPrincipalName'] ?? ''));
        $mail = trim((string) ($user['mail'] ?? ''));
        if ($mail !== '' && $upn === '') {
            return self::TYPE_MAILBOX;
        }

        $assigned = $user['assignedLicenses'] ?? null;
        if (is_array($assigned) && $assigned === [] && $mail !== '') {
            return self::TYPE_MAILBOX;
        }

        return self::TYPE_USER;
    }

  /**
     * @param array<string, mixed> $access
     */
    public static function siteCapabilityAccessible(array $access, string $capability): bool
    {
        if ($access === []) {
            return true;
        }
        $status = (string) ($access[$capability] ?? '');
        if ($status === '') {
            return true;
        }

        return $status === AccessResult::STATUS_AVAILABLE;
    }

    /**
     * Derive wizard selectability for a SharePoint site resource.
     *
     * @param array<string, mixed> $resource
     * @return array{
     *   selectable: bool,
     *   disabled_reason: string,
     *   capability_access: array{files: bool, lists: bool}
     * }
     */
    public static function siteSelectability(array $resource): array
    {
        $access = is_array($resource['access'] ?? null) ? $resource['access'] : [];
        $filesAccessible = self::siteCapabilityAccessible($access, 'files');
        $listsAccessible = self::siteCapabilityAccessible($access, 'lists');
        $selectable = $filesAccessible || $listsAccessible;

        $disabledReason = '';
        if (!$selectable) {
            $reason = trim((string) (
                $access['reason']
                ?? $access['files_reason']
                ?? $access['lists_reason']
                ?? ''
            ));
            $disabledReason = $reason !== ''
                ? $reason
                : 'Backup app cannot access this site';
        }

        return [
            'selectable' => $selectable,
            'disabled_reason' => $disabledReason,
            'capability_access' => [
                'files' => $filesAccessible,
                'lists' => $listsAccessible,
            ],
        ];
    }

    /**
     * SharePoint system / infrastructure sites that should not appear in customer pickers.
     */
    public static function isInfrastructureSharePointSite(array $resource): bool
    {
        if ((string) ($resource['resource_type'] ?? '') !== self::TYPE_SHAREPOINT_SITE) {
            return false;
        }
        $meta = is_array($resource['meta'] ?? null) ? $resource['meta'] : [];
        $url = strtolower(trim((string) ($meta['web_url'] ?? $resource['email'] ?? '')));
        if ($url === '') {
            return false;
        }

        $patterns = [
            '/sites/contenttypehub',
            '/portals/hub',
            '/sites/search',
            '/sites/appcatalog',
            '/sites/compliancepolicy',
        ];
        foreach ($patterns as $pattern) {
            if (str_contains($url, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $workloadIds
     */
    public static function siteLinkedToWorkloadType(array $workloadIds, string $prefix): bool
    {
        $needle = $prefix . ':';
        foreach ($workloadIds as $workloadId) {
            if (str_starts_with((string) $workloadId, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a SharePoint site should appear in the standalone SharePoint Sites wizard section.
     *
     * Team-backed sites remain visible (files can be selected at site or team level; planner dedupes).
     * M365 group-backed and infrastructure sites are hidden (select via Groups or omitted).
     */
    public static function showInSharePointSection(array $resource): bool
    {
        if ((string) ($resource['resource_type'] ?? '') !== self::TYPE_SHAREPOINT_SITE) {
            return false;
        }
        if (($resource['infrastructure_site'] ?? false) === true) {
            return false;
        }
        if (($resource['workload_group_connected'] ?? false) === true) {
            return false;
        }
        if (($resource['channel_connected'] ?? false) === true) {
            return false;
        }

        return true;
    }

    /**
     * Wizard-facing inventory counts (differs from raw Graph discovery for SharePoint sites).
     *
     * @param list<array<string, mixed>> $resources
     * @return array{users: int, sites: int, teams: int, groups: int}
     */
    public static function displayCounts(array $resources): array
    {
        $typeCounts = self::countByType($resources);
        $sharepointVisible = 0;
        foreach ($resources as $resource) {
            if (!is_array($resource)) {
                continue;
            }
            if (self::showInSharePointSection($resource)) {
                ++$sharepointVisible;
            }
        }

        return [
            'users' => (int) ($typeCounts[self::TYPE_USER] ?? 0) + (int) ($typeCounts[self::TYPE_MAILBOX] ?? 0),
            'sites' => $sharepointVisible,
            'teams' => (int) ($typeCounts[self::TYPE_TEAM] ?? 0),
            'groups' => (int) ($typeCounts[self::TYPE_M365_GROUP] ?? 0),
        ];
    }

    /**
     * Enrich SharePoint site resources with workload linkage and wizard visibility metadata.
     *
     * @param list<array<string, mixed>> $resources
     * @param list<array{from_id: string, rel: string, to_id: string, physical_key: string}> $relationships
     * @return list<array<string, mixed>>
     */
    public static function enrichSharePointDisplayMetadata(array $resources, array $relationships): array
    {
        $links = (new RelationshipResolver())->filesInSiteLinks($relationships);

        $out = [];
        foreach ($resources as $resource) {
            if (!is_array($resource)) {
                continue;
            }
            if ((string) ($resource['resource_type'] ?? '') === self::TYPE_SHAREPOINT_SITE) {
                $id = (string) ($resource['id'] ?? '');
                $workloadIds = $links[$id] ?? [];
                if ($workloadIds !== []) {
                    $resource['linked_workload_ids'] = $workloadIds;
                    $resource['team_connected'] = self::siteLinkedToWorkloadType($workloadIds, 'team');
                    $resource['workload_group_connected'] = self::siteLinkedToWorkloadType($workloadIds, 'group');
                    $resource['channel_connected'] = self::siteLinkedToWorkloadType($workloadIds, 'channel');
                    $resource['group_connected'] = $resource['workload_group_connected'];
                }
                if (self::isInfrastructureSharePointSite($resource)) {
                    $resource['infrastructure_site'] = true;
                }
                $resource['show_in_sharepoint_section'] = self::showInSharePointSection($resource);
            }
            $out[] = $resource;
        }

        return $out;
    }

    /** @deprecated Use enrichSharePointDisplayMetadata() */
    public static function enrichGroupConnectedSites(array $resources, array $relationships): array
    {
        return self::enrichSharePointDisplayMetadata($resources, $relationships);
    }

    /** @return array<string, int> */
    public static function countByType(array $resources): array
    {
        $counts = [];
        foreach ($resources as $resource) {
            if (!is_array($resource)) {
                continue;
            }
            $type = (string) ($resource['resource_type'] ?? '');
            if ($type === '') {
                continue;
            }
            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }

        return $counts;
    }
}
