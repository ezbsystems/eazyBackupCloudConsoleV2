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
