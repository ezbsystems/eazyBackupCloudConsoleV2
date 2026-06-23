<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * Normalizes e3 job wizard selection (resource IDs + per-resource scope) for BackupPlanner.
 */
final class CustomerSelectionCodec
{
    /**
     * @param list<string> $selectedIds
     * @param array<string, array<string, bool>>|null $scopeOverrides
     * @param array<string, mixed> $inventory
     * @return array{
     *   selected_resource_ids: list<string>,
     *   scope_overrides: array<string, array<string, bool>>,
     *   plan: array<string, mixed>
     * }
     */
    public static function planSelection(array $selectedIds, ?array $scopeOverrides, array $inventory): array
    {
        $selectedIds = self::normalizeIds($selectedIds);
        $scopeOverrides = self::normalizeScopeOverrides($scopeOverrides ?? []);

        $planner = new BackupPlanner();
        $plan = $planner->plan($selectedIds, $inventory, $scopeOverrides);

        return [
            'selected_resource_ids' => $selectedIds,
            'scope_overrides' => $scopeOverrides,
            'plan' => $plan,
        ];
    }

    /**
     * @param list<string> $selectedIds
     * @param array<string, array<string, bool>>|null $scopeOverrides
     * @param array<string, mixed> $inventory
     */
    public static function validate(array $selectedIds, ?array $scopeOverrides, array $inventory): void
    {
        self::validateSiteSelectability($selectedIds, $scopeOverrides, $inventory);
        $result = self::planSelection($selectedIds, $scopeOverrides, $inventory);
        $summary = $result['plan']['summary'] ?? [];
        if (($summary['runnable'] ?? 0) === 0) {
            throw new \RuntimeException('No runnable backup workloads match the selected resources.');
        }
    }

    /**
     * Infer scope overrides for jobs saved before hierarchical selection shipped.
     *
     * @param list<string> $selectedIds
     * @param array<string, mixed> $inventory
     * @return array<string, array<string, bool>>
     */
    public static function fromLegacyJob(array $selectedIds, array $inventory): array
    {
        $selectedIds = self::normalizeIds($selectedIds);
        $byId = self::resourcesById($inventory);
        $overrides = [];

        foreach ($selectedIds as $id) {
            $resource = $byId[$id] ?? null;
            if ($resource === null) {
                continue;
            }
            $type = (string) ($resource['resource_type'] ?? '');
            if (in_array($type, [TenantResource::TYPE_USER, TenantResource::TYPE_MAILBOX], true)) {
                $overrides[$id] = [
                    BackupScope::MAIL => true,
                    BackupScope::CALENDAR => true,
                    BackupScope::CONTACTS => false,
                    BackupScope::TASKS => false,
                ];
            } elseif ($type === TenantResource::TYPE_USER_ONEDRIVE) {
                $overrides[$id] = [
                    BackupScope::ONEDRIVE => true,
                    BackupScope::FILES => true,
                ];
            }
        }

        return $overrides;
    }

    /**
     * @param list<string> $selectedIds
     * @param array<string, array<string, bool>>|null $scopeOverrides
     * @param array<string, mixed> $inventory
     * @return array{selected_resource_ids: list<string>, scope_overrides: array<string, array<string, bool>>}
     */
    public static function resolveForExecution(array $selectedIds, ?array $scopeOverrides, array $inventory): array
    {
        $selectedIds = self::normalizeIds($selectedIds);
        $scopeOverrides = self::normalizeScopeOverrides($scopeOverrides ?? []);
        if ($scopeOverrides === []) {
            $scopeOverrides = self::fromLegacyJob($selectedIds, $inventory);
        }

        return self::pruneInaccessibleSiteSelection($selectedIds, $scopeOverrides, $inventory);
    }

    /**
     * Drop or narrow SharePoint selections the backup app cannot access.
     *
     * @param list<string> $selectedIds
     * @param array<string, array<string, bool>> $scopeOverrides
     * @param array<string, mixed> $inventory
     * @return array{selected_resource_ids: list<string>, scope_overrides: array<string, array<string, bool>>}
     */
    public static function pruneInaccessibleSiteSelection(
        array $selectedIds,
        array $scopeOverrides,
        array $inventory,
    ): array {
        $byId = self::resourcesById($inventory);
        $prunedIds = [];
        $prunedOverrides = $scopeOverrides;

        foreach ($selectedIds as $id) {
            $resource = $byId[$id] ?? null;
            if ($resource === null) {
                $prunedIds[] = $id;
                continue;
            }
            if ((string) ($resource['resource_type'] ?? '') !== TenantResource::TYPE_SHAREPOINT_SITE) {
                $prunedIds[] = $id;
                continue;
            }

            $selectability = TenantResource::siteSelectability($resource);
            $flags = $scopeOverrides[$id] ?? [];
            $filesSelected = array_key_exists(BackupScope::FILES, $flags)
                ? (bool) $flags[BackupScope::FILES]
                : $flags === [];
            $listsSelected = array_key_exists(BackupScope::LISTS, $flags)
                ? (bool) $flags[BackupScope::LISTS]
                : $flags === [];

            $filesAccessible = (bool) ($selectability['capability_access']['files'] ?? true);
            $listsAccessible = (bool) ($selectability['capability_access']['lists'] ?? true);
            $filesRunnable = $filesSelected && $filesAccessible;
            $listsRunnable = $listsSelected && $listsAccessible;

            if (!$filesRunnable && !$listsRunnable) {
                unset($prunedOverrides[$id]);
                continue;
            }

            $prunedIds[] = $id;
            $prunedOverrides[$id] = [
                BackupScope::FILES => $filesRunnable,
                BackupScope::LISTS => $listsRunnable,
            ];
        }

        return [
            'selected_resource_ids' => $prunedIds,
            'scope_overrides' => $prunedOverrides,
        ];
    }

    /**
     * @param mixed $raw
     * @return array<string, array<string, bool>>
     */
    public static function normalizeScopeOverrides(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $resourceId => $flags) {
            if (!is_string($resourceId) || $resourceId === '' || !is_array($flags)) {
                continue;
            }
            $normalized = [];
            foreach ($flags as $key => $value) {
                if (is_string($key)) {
                    $normalized[$key] = (bool) $value;
                }
            }
            if ($normalized !== []) {
                $out[$resourceId] = $normalized;
            }
        }

        return $out;
    }

    /**
     * @param mixed $raw
     * @return list<string>
     */
    public static function normalizeIds(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('strval', $raw))));
    }

    /**
     * @param array<string, mixed> $inventory
     * @return array<string, array<string, mixed>>
     */
    private static function resourcesById(array $inventory): array
    {
        $byId = [];
        $resources = is_array($inventory['resources'] ?? null) ? $inventory['resources'] : [];
        foreach ($resources as $resource) {
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

    /**
     * @param list<string> $selectedIds
     * @param array<string, array<string, bool>>|null $scopeOverrides
     * @param array<string, mixed> $inventory
     */
    private static function validateSiteSelectability(
        array $selectedIds,
        ?array $scopeOverrides,
        array $inventory,
    ): void {
        $byId = self::resourcesById($inventory);
        $overrides = self::normalizeScopeOverrides($scopeOverrides ?? []);

        foreach (self::normalizeIds($selectedIds) as $id) {
            $resource = $byId[$id] ?? null;
            if ($resource === null) {
                continue;
            }
            if ((string) ($resource['resource_type'] ?? '') !== TenantResource::TYPE_SHAREPOINT_SITE) {
                continue;
            }

            $selectability = TenantResource::siteSelectability($resource);
            $name = (string) ($resource['display_name'] ?? $id);
            $flags = $overrides[$id] ?? [];
            $filesSelected = array_key_exists(BackupScope::FILES, $flags)
                ? (bool) $flags[BackupScope::FILES]
                : $flags === [];
            $listsSelected = array_key_exists(BackupScope::LISTS, $flags)
                ? (bool) $flags[BackupScope::LISTS]
                : $flags === [];

            if ($filesSelected && !($selectability['capability_access']['files'] ?? true)) {
                throw new \RuntimeException(
                    "Site '{$name}' is not accessible to the backup app (Files)",
                );
            }
            if ($listsSelected && !($selectability['capability_access']['lists'] ?? true)) {
                throw new \RuntimeException(
                    "Site '{$name}' is not accessible to the backup app (Lists)",
                );
            }
            if (!$selectability['selectable'] && ($filesSelected || $listsSelected)) {
                $reason = trim((string) ($selectability['disabled_reason'] ?? ''));
                $message = $reason !== ''
                    ? "Site '{$name}' is not accessible to the backup app: {$reason}"
                    : "Site '{$name}' is not accessible to the backup app";
                throw new \RuntimeException($message);
            }
        }
    }
}
