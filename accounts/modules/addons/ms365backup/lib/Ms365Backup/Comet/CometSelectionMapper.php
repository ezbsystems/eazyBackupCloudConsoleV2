<?php
declare(strict_types=1);

namespace Ms365Backup\Comet;

use Ms365Backup\BackupScope;
use Ms365Backup\CustomerSelectionCodec;
use Ms365Backup\TenantResource;

/**
 * Maps Comet Office365 selection onto e3 inventory resource IDs + scope overrides.
 */
final class CometSelectionMapper
{
    /**
     * @param array{
     *   organization?: bool,
     *   whole_org?: bool,
     *   backup_options?: array<string, int>,
     *   member_backup_options?: array<string, int>
     * } $parsed
     * @param array<string, mixed> $inventory
     * @return array{
     *   selected_resource_ids: list<string>,
     *   scope_overrides: array<string, array<string, bool>>,
     *   report: array{
     *     matched_users: int,
     *     matched_sites: int,
     *     matched_teams: int,
     *     matched_groups: int,
     *     unmatched_backup_option_keys: list<string>,
     *     unmatched_member_roots: list<string>,
     *     missing_onedrive_children: list<string>,
     *     backup_options_total: int,
     *     whole_org: bool
     *   }
     * }
     */
    public static function map(array $parsed, array $inventory): array
    {
        $wholeOrg = (bool) ($parsed['organization'] ?? false) || (bool) ($parsed['whole_org'] ?? false);
        if ($wholeOrg) {
            $all = CustomerSelectionCodec::selectAllFromInventory($inventory);
            $resources = is_array($inventory['resources'] ?? null) ? $inventory['resources'] : [];
            $counts = TenantResource::displayCounts(array_values(array_filter($resources, 'is_array')));

            return [
                'selected_resource_ids' => $all['selected_resource_ids'],
                'scope_overrides' => $all['scope_overrides'],
                'report' => [
                    'matched_users' => (int) ($counts['users'] ?? 0),
                    'matched_sites' => (int) ($counts['sites'] ?? 0),
                    'matched_teams' => (int) ($counts['teams'] ?? 0),
                    'matched_groups' => (int) ($counts['groups'] ?? 0),
                    'unmatched_backup_option_keys' => [],
                    'unmatched_member_roots' => [],
                    'missing_onedrive_children' => [],
                    'backup_options_total' => count($parsed['backup_options'] ?? []),
                    'whole_org' => true,
                ],
            ];
        }

        $resources = is_array($inventory['resources'] ?? null) ? $inventory['resources'] : [];
        $byId = [];
        $byGraph = [];
        $onedriveByParent = [];
        foreach ($resources as $resource) {
            if (!is_array($resource)) {
                continue;
            }
            $id = (string) ($resource['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $byId[$id] = $resource;
            $graphId = strtolower(trim((string) ($resource['graph_id'] ?? '')));
            if ($graphId !== '') {
                $byGraph[$graphId][] = $resource;
            }
            $type = (string) ($resource['resource_type'] ?? '');
            if ($type === TenantResource::TYPE_USER_ONEDRIVE) {
                $parent = (string) ($resource['parent_id'] ?? '');
                if ($parent !== '') {
                    $onedriveByParent[$parent] = $resource;
                }
            }
        }

        $selected = [];
        $scopes = [];
        $matchedUsers = 0;
        $matchedSites = 0;
        $matchedTeams = 0;
        $matchedGroups = 0;
        $unmatchedBackup = [];
        $unmatchedMembers = [];
        $missingOd = [];
        $seenUser = [];
        $seenSite = [];
        $seenTeam = [];
        $seenGroup = [];

        $backupOptions = is_array($parsed['backup_options'] ?? null) ? $parsed['backup_options'] : [];
        foreach ($backupOptions as $cometId => $mask) {
            $cometId = (string) $cometId;
            $mask = (int) $mask;
            $ok = self::applyBackupOption(
                $cometId,
                $mask,
                $byId,
                $byGraph,
                $onedriveByParent,
                $selected,
                $scopes,
                $matchedUsers,
                $matchedSites,
                $matchedTeams,
                $matchedGroups,
                $missingOd,
                $seenUser,
                $seenSite,
                $seenTeam,
                $seenGroup,
            );
            if (!$ok) {
                $unmatchedBackup[] = $cometId;
            }
        }

        $memberOptions = is_array($parsed['member_backup_options'] ?? null) ? $parsed['member_backup_options'] : [];
        foreach ($memberOptions as $rootId => $mask) {
            $rootId = (string) $rootId;
            $mask = (int) $mask;
            $root = self::resolvePlainGuid($rootId, $byGraph, [
                TenantResource::TYPE_TEAM,
                TenantResource::TYPE_M365_GROUP,
                TenantResource::TYPE_SHAREPOINT_SITE,
            ]);
            if ($root === null) {
                $unmatchedMembers[] = $rootId;
                continue;
            }

            $rootType = (string) ($root['resource_type'] ?? '');
            if ($rootType === TenantResource::TYPE_TEAM) {
                self::selectTeam($root, $mask, $selected, $scopes, $matchedTeams, $seenTeam);
            } elseif ($rootType === TenantResource::TYPE_M365_GROUP) {
                self::selectGroup($root, $mask, $selected, $scopes, $matchedGroups, $seenGroup);
            } elseif ($rootType === TenantResource::TYPE_SHAREPOINT_SITE) {
                self::selectSite($root, $mask, $selected, $scopes, $matchedSites, $seenSite);
            }

            $meta = is_array($root['meta'] ?? null) ? $root['meta'] : [];
            $memberIds = is_array($meta['member_azure_ids'] ?? null) ? $meta['member_azure_ids'] : [];
            foreach ($memberIds as $memberGraphId) {
                $memberGraphId = (string) $memberGraphId;
                if ($memberGraphId === '') {
                    continue;
                }
                self::applyBackupOption(
                    $memberGraphId,
                    $mask,
                    $byId,
                    $byGraph,
                    $onedriveByParent,
                    $selected,
                    $scopes,
                    $matchedUsers,
                    $matchedSites,
                    $matchedTeams,
                    $matchedGroups,
                    $missingOd,
                    $seenUser,
                    $seenSite,
                    $seenTeam,
                    $seenGroup,
                );
            }
        }

        $ids = array_keys($selected);
        sort($ids);

        return [
            'selected_resource_ids' => array_values($ids),
            'scope_overrides' => $scopes,
            'report' => [
                'matched_users' => $matchedUsers,
                'matched_sites' => $matchedSites,
                'matched_teams' => $matchedTeams,
                'matched_groups' => $matchedGroups,
                'unmatched_backup_option_keys' => array_values($unmatchedBackup),
                'unmatched_member_roots' => array_values($unmatchedMembers),
                'missing_onedrive_children' => array_values(array_unique($missingOd)),
                'backup_options_total' => count($backupOptions),
                'whole_org' => false,
            ],
        ];
    }

    /**
     * @param array<string, true> $selected
     * @param array<string, array<string, bool>> $scopes
     * @param array<string, array<string, mixed>> $byId
     * @param array<string, list<array<string, mixed>>> $byGraph
     * @param array<string, array<string, mixed>> $onedriveByParent
     * @param list<string> $missingOd
     * @param array<string, true> $seenUser
     * @param array<string, true> $seenSite
     * @param array<string, true> $seenTeam
     * @param array<string, true> $seenGroup
     */
    private static function applyBackupOption(
        string $cometId,
        int $mask,
        array $byId,
        array $byGraph,
        array $onedriveByParent,
        array &$selected,
        array &$scopes,
        int &$matchedUsers,
        int &$matchedSites,
        int &$matchedTeams,
        int &$matchedGroups,
        array &$missingOd,
        array &$seenUser,
        array &$seenSite,
        array &$seenTeam,
        array &$seenGroup,
    ): bool {
        if (str_contains($cometId, ',')) {
            $site = self::resolveSite($cometId, $byGraph);
            if ($site === null) {
                return false;
            }

            return self::selectSite($site, $mask, $selected, $scopes, $matchedSites, $seenSite);
        }

        $resource = self::resolvePlainGuid($cometId, $byGraph, [
            TenantResource::TYPE_USER,
            TenantResource::TYPE_MAILBOX,
            TenantResource::TYPE_TEAM,
            TenantResource::TYPE_M365_GROUP,
            TenantResource::TYPE_SHAREPOINT_SITE,
        ]);
        if ($resource === null) {
            return false;
        }

        $type = (string) ($resource['resource_type'] ?? '');
        if (in_array($type, [TenantResource::TYPE_USER, TenantResource::TYPE_MAILBOX], true)) {
            self::selectUser(
                $resource,
                $mask,
                $onedriveByParent,
                $selected,
                $scopes,
                $matchedUsers,
                $missingOd,
                $seenUser,
            );

            return true;
        }
        if ($type === TenantResource::TYPE_TEAM) {
            self::selectTeam($resource, $mask, $selected, $scopes, $matchedTeams, $seenTeam);

            return true;
        }
        if ($type === TenantResource::TYPE_M365_GROUP) {
            self::selectGroup($resource, $mask, $selected, $scopes, $matchedGroups, $seenGroup);

            return true;
        }
        if ($type === TenantResource::TYPE_SHAREPOINT_SITE) {
            return self::selectSite($resource, $mask, $selected, $scopes, $matchedSites, $seenSite);
        }

        return false;
    }

    /**
     * @param array<string, list<array<string, mixed>>> $byGraph
     * @param list<string> $preferredTypes
     * @return array<string, mixed>|null
     */
    private static function resolvePlainGuid(string $cometId, array $byGraph, array $preferredTypes): ?array
    {
        $key = strtolower(trim($cometId));
        $candidates = $byGraph[$key] ?? [];
        if ($candidates === []) {
            return null;
        }

        foreach ($preferredTypes as $want) {
            foreach ($candidates as $candidate) {
                if ((string) ($candidate['resource_type'] ?? '') === $want) {
                    return $candidate;
                }
            }
        }

        return $candidates[0] ?? null;
    }

    /**
     * @param array<string, list<array<string, mixed>>> $byGraph
     * @return array<string, mixed>|null
     */
    private static function resolveSite(string $cometId, array $byGraph): ?array
    {
        $key = strtolower(trim($cometId));
        foreach ($byGraph[$key] ?? [] as $candidate) {
            if ((string) ($candidate['resource_type'] ?? '') === TenantResource::TYPE_SHAREPOINT_SITE) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $resource
     * @param array<string, array<string, mixed>> $onedriveByParent
     * @param array<string, true> $selected
     * @param array<string, array<string, bool>> $scopes
     * @param list<string> $missingOd
     * @param array<string, true> $seenUser
     */
    private static function selectUser(
        array $resource,
        int $mask,
        array $onedriveByParent,
        array &$selected,
        array &$scopes,
        int &$matchedUsers,
        array &$missingOd,
        array &$seenUser,
    ): void {
        $flags = CometServiceMask::decode($mask);
        $id = (string) ($resource['id'] ?? '');
        $type = (string) ($resource['resource_type'] ?? TenantResource::TYPE_USER);
        if ($id === '') {
            return;
        }

        $needParent = $flags['mail'] || $flags['calendar'] || $flags['contacts'] || $flags['onedrive'];
        if (!$needParent) {
            return;
        }

        if (!isset($seenUser[$id])) {
            $seenUser[$id] = true;
            ++$matchedUsers;
        }

        $override = BackupScope::emptyCapabilityTemplate($type)->toArray();
        $override[BackupScope::MAIL] = $flags['mail'];
        $override[BackupScope::CALENDAR] = $flags['calendar'];
        $override[BackupScope::CONTACTS] = $flags['contacts'];
        if (array_key_exists(BackupScope::TASKS, $override)) {
            $override[BackupScope::TASKS] = false;
        }
        self::mergeSelect($id, $override, $selected, $scopes);

        if ($flags['onedrive']) {
            $od = $onedriveByParent[$id] ?? null;
            if ($od === null) {
                $missingOd[] = $id;
            } else {
                $odId = (string) ($od['id'] ?? '');
                if ($odId !== '') {
                    $odOverride = BackupScope::emptyCapabilityTemplate(TenantResource::TYPE_USER_ONEDRIVE)->toArray();
                    $odOverride[BackupScope::ONEDRIVE] = true;
                    $odOverride[BackupScope::FILES] = true;
                    self::mergeSelect($odId, $odOverride, $selected, $scopes);
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $resource
     * @param array<string, true> $selected
     * @param array<string, array<string, bool>> $scopes
     * @param array<string, true> $seenSite
     */
    private static function selectSite(
        array $resource,
        int $mask,
        array &$selected,
        array &$scopes,
        int &$matchedSites,
        array &$seenSite,
    ): bool {
        $id = (string) ($resource['id'] ?? '');
        if ($id === '') {
            return false;
        }
        $selectability = TenantResource::siteSelectability($resource);
        if (($selectability['selectable'] ?? true) === false) {
            return false;
        }

        $flags = CometServiceMask::decode($mask);
        $override = BackupScope::emptyCapabilityTemplate(TenantResource::TYPE_SHAREPOINT_SITE)->toArray();
        if ($flags['sharepoint']) {
            $override[BackupScope::FILES] = true;
            $override[BackupScope::LISTS] = true;
        } elseif ($flags['onedrive']) {
            $override[BackupScope::FILES] = true;
            $override[BackupScope::LISTS] = false;
        } else {
            return false;
        }

        $cap = is_array($selectability['capability_access'] ?? null) ? $selectability['capability_access'] : [];
        if (!($cap['files'] ?? true)) {
            $override[BackupScope::FILES] = false;
        }
        if (!($cap['lists'] ?? true)) {
            $override[BackupScope::LISTS] = false;
        }
        if (!($override[BackupScope::FILES] ?? false) && !($override[BackupScope::LISTS] ?? false)) {
            return false;
        }

        if (!isset($seenSite[$id])) {
            $seenSite[$id] = true;
            ++$matchedSites;
        }
        self::mergeSelect($id, $override, $selected, $scopes);

        return true;
    }

    /**
     * @param array<string, mixed> $resource
     * @param array<string, true> $selected
     * @param array<string, array<string, bool>> $scopes
     * @param array<string, true> $seenTeam
     */
    private static function selectTeam(
        array $resource,
        int $mask,
        array &$selected,
        array &$scopes,
        int &$matchedTeams,
        array &$seenTeam,
    ): void {
        $id = (string) ($resource['id'] ?? '');
        if ($id === '' || $mask === 0) {
            return;
        }
        if (!isset($seenTeam[$id])) {
            $seenTeam[$id] = true;
            ++$matchedTeams;
        }
        $override = BackupScope::emptyCapabilityTemplate(TenantResource::TYPE_TEAM)->toArray();
        $override[BackupScope::TEAMS_METADATA] = true;
        $override[BackupScope::TEAMS_MESSAGES] = true;
        $override[BackupScope::FILES] = true;
        self::mergeSelect($id, $override, $selected, $scopes);
    }

    /**
     * @param array<string, mixed> $resource
     * @param array<string, true> $selected
     * @param array<string, array<string, bool>> $scopes
     * @param array<string, true> $seenGroup
     */
    private static function selectGroup(
        array $resource,
        int $mask,
        array &$selected,
        array &$scopes,
        int &$matchedGroups,
        array &$seenGroup,
    ): void {
        $id = (string) ($resource['id'] ?? '');
        if ($id === '' || $mask === 0) {
            return;
        }
        $flags = CometServiceMask::decode($mask);
        if (!isset($seenGroup[$id])) {
            $seenGroup[$id] = true;
            ++$matchedGroups;
        }
        $override = BackupScope::emptyCapabilityTemplate(TenantResource::TYPE_M365_GROUP)->toArray();
        $override[BackupScope::MAIL] = $flags['mail'];
        $override[BackupScope::CALENDAR] = $flags['calendar'];
        $override[BackupScope::FILES] = $flags['sharepoint'] || $flags['onedrive'] || $flags['mail'];
        self::mergeSelect($id, $override, $selected, $scopes);
    }

    /**
     * @param array<string, bool> $override
     * @param array<string, true> $selected
     * @param array<string, array<string, bool>> $scopes
     */
    private static function mergeSelect(string $id, array $override, array &$selected, array &$scopes): void
    {
        $selected[$id] = true;
        if (!isset($scopes[$id])) {
            $scopes[$id] = $override;
            return;
        }
        foreach ($override as $key => $enabled) {
            if ($enabled) {
                $scopes[$id][$key] = true;
            } elseif (!array_key_exists($key, $scopes[$id])) {
                $scopes[$id][$key] = false;
            }
        }
    }
}
