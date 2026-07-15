<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * Derives and validates MS365 restore destinations from Kopia catalog paths.
 */
final class Ms365RestoreDestinationResolver
{
    public const CLASS_MAILBOX = 'mailbox';
    public const CLASS_ONEDRIVE = 'onedrive';
    public const CLASS_SHAREPOINT = 'sharepoint';
    public const CLASS_TEAMS = 'teams';
    public const CLASS_GROUPS = 'groups';
    public const CLASS_PLANNER = 'planner';
    public const CLASS_ONENOTE = 'onenote';

    public const MODE_ORIGINAL = 'original';
    public const MODE_ALTERNATE = 'alternate';

    /**
     * @param array<string, mixed> $item
     */
    public static function classifyItem(array $item): string
    {
        $path = self::effectivePath($item);
        if ($path === '') {
            return '';
        }

        return self::classifyItemPath($path);
    }

    public static function classifyItemPath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        if ($path === '') {
            return '';
        }

        $lower = strtolower($path);

        if (preg_match('#/users/[^/]+/onedrive/content(?:/|$)#', $lower) === 1) {
            return self::CLASS_ONEDRIVE;
        }
        if (preg_match('#/users/[^/]+/(?:mail|calendar|calendars|contacts|tasks)(?:/|$)#', $lower) === 1) {
            return self::CLASS_MAILBOX;
        }
        if (preg_match('#/groups/[^/]+/(?:mail|calendar|calendars)(?:/|$)#', $lower) === 1) {
            return self::CLASS_GROUPS;
        }
        if (preg_match('#/sites/[^/]+/(?:drives|lists)(?:/|$)#', $lower) === 1) {
            return self::CLASS_SHAREPOINT;
        }
        if (preg_match('#/teams/[^/]+(?:/|$)#', $lower) === 1) {
            return self::CLASS_TEAMS;
        }
        if (preg_match('#/planner(?:/|$)#', $lower) === 1) {
            return self::CLASS_PLANNER;
        }
        if (preg_match('#/onenote(?:/|$)#', $lower) === 1) {
            return self::CLASS_ONENOTE;
        }
        if (preg_match('#/drives/[^/]+/content(?:/|$)#', $lower) === 1) {
            return self::CLASS_ONEDRIVE;
        }

        return '';
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param list<array<string, mixed>> $inventoryResources
     * @return list<array<string, mixed>>
     */
    public static function deriveOriginalTargets(array $items, array $inventoryResources = []): array
    {
        $targetsByKey = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $path = self::effectivePath($item);
            $class = self::classifyItemPath($path);
            if ($class === '') {
                throw new \RuntimeException('Could not determine the original restore location for one or more selected items.');
            }

            $parsed = self::parsePathIdentity($path, $class);
            $childRunId = trim((string) ($item['child_run_id'] ?? ''));
            $targetKey = $childRunId !== ''
                ? $childRunId . '|' . $parsed['identity_key']
                : $parsed['identity_key'];

            if (isset($targetsByKey[$targetKey])) {
                continue;
            }

            $target = self::buildTargetFromParsed($parsed, $class, $inventoryResources);
            if ($childRunId !== '') {
                $target['child_run_id'] = $childRunId;
            }

            $targetsByKey[$targetKey] = $target;
        }

        if ($targetsByKey === []) {
            throw new \RuntimeException('Could not determine restore destinations for the selected items.');
        }

        return array_values($targetsByKey);
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<string>
     */
    public static function selectionClasses(array $items): array
    {
        $classes = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $class = self::classifyItem($item);
            if ($class !== '') {
                $classes[$class] = true;
            }
        }

        return array_keys($classes);
    }

    public static function canUseAlternateDestination(array $items): bool
    {
        $classes = self::selectionClasses($items);
        if (count($classes) !== 1) {
            return false;
        }

        return $classes[0] !== '';
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param list<array<string, mixed>> $targets
     * @param list<array<string, mixed>> $inventoryResources
     */
    public static function assertSelectionCompatible(
        array $items,
        array $targets,
        string $destinationMode,
        array $inventoryResources = [],
    ): void {
        $destinationMode = self::normalizeDestinationMode($destinationMode);

        if ($destinationMode === self::MODE_ORIGINAL) {
            $derived = self::deriveOriginalTargets($items, $inventoryResources);
            if ($targets !== []) {
                self::assertDerivedTargetsMatch($derived, $targets);
            }

            return;
        }

        if (count($targets) !== 1) {
            throw new \RuntimeException('Select exactly one restore destination.');
        }

        if (!self::canUseAlternateDestination($items)) {
            throw new \RuntimeException(
                'Alternate destination is only available when all selected items are the same workload type.'
            );
        }

        $target = $targets[0];
        if (!is_array($target)) {
            throw new \RuntimeException('Invalid restore destination.');
        }

        $class = self::selectionClasses($items)[0] ?? '';
        $resourceType = strtolower(trim((string) ($target['resource_type'] ?? '')));
        $allowed = self::compatibleResourceTypes($class);
        if ($resourceType === '' || !in_array($resourceType, $allowed, true)) {
            throw new \RuntimeException(self::incompatibleMessage($class, $resourceType));
        }

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $itemClass = self::classifyItem($item);
            if ($itemClass === '' || $itemClass !== $class) {
                throw new \RuntimeException('Selected items are not compatible with the chosen restore destination.');
            }
        }
    }

    /**
     * @param list<array<string, mixed>> $inventoryResources
     * @return list<array<string, mixed>>
     */
    public static function filterAlternateTargets(array $inventoryResources, string $selectionClass): array
    {
        $allowed = self::compatibleResourceTypes($selectionClass);
        if ($allowed === []) {
            return [];
        }

        return array_values(array_filter($inventoryResources, static function ($resource) use ($allowed) {
            if (!is_array($resource)) {
                return false;
            }
            $type = strtolower(trim((string) ($resource['resource_type'] ?? '')));

            return $type !== '' && in_array($type, $allowed, true);
        }));
    }

    public static function normalizeDestinationMode(string $mode): string
    {
        $mode = strtolower(trim($mode));

        return $mode === self::MODE_ALTERNATE ? self::MODE_ALTERNATE : self::MODE_ORIGINAL;
    }

    /**
     * @return list<string>
     */
    public static function compatibleResourceTypes(string $selectionClass): array
    {
        return match ($selectionClass) {
            self::CLASS_MAILBOX => [TenantResource::TYPE_USER, TenantResource::TYPE_MAILBOX],
            self::CLASS_ONEDRIVE => [TenantResource::TYPE_USER, TenantResource::TYPE_USER_ONEDRIVE],
            self::CLASS_SHAREPOINT => [TenantResource::TYPE_SHAREPOINT_SITE],
            self::CLASS_TEAMS => [TenantResource::TYPE_TEAM],
            self::CLASS_GROUPS => [TenantResource::TYPE_M365_GROUP],
            self::CLASS_PLANNER => [TenantResource::TYPE_PLANNER_PLAN],
            self::CLASS_ONENOTE => [TenantResource::TYPE_ONENOTE_NOTEBOOK],
            default => [],
        };
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function effectivePath(array $item): string
    {
        $path = trim((string) ($item['path'] ?? ''));
        if ($path !== '') {
            return $path;
        }

        return trim((string) ($item['path_prefix'] ?? ''));
    }

    /**
     * @return array{identity_key: string, graph_id: string, drive_id?: string}
     */
    private static function parsePathIdentity(string $path, string $class): array
    {
        $path = trim(str_replace('\\', '/', $path), '/');

        if ($class === self::CLASS_MAILBOX
            && preg_match('#/users/([^/]+)/(?:mail|calendar|calendars|contacts|tasks)(?:/|$)#i', $path, $m) === 1) {
            return [
                'identity_key' => 'user:' . $m[1],
                'graph_id' => $m[1],
            ];
        }

        if ($class === self::CLASS_ONEDRIVE) {
            if (preg_match('#/users/([^/]+)/onedrive/content(?:/|$)#i', $path, $m) === 1) {
                return [
                    'identity_key' => 'user:' . $m[1],
                    'graph_id' => $m[1],
                ];
            }
            if (preg_match('#/drives/([^/]+)/content(?:/|$)#i', $path, $m) === 1) {
                return [
                    'identity_key' => 'drive:' . $m[1],
                    'graph_id' => $m[1],
                    'drive_id' => $m[1],
                ];
            }
        }

        if ($class === self::CLASS_SHAREPOINT
            && preg_match('#/sites/([^/]+)/#i', $path, $m) === 1) {
            $identity = [
                'identity_key' => 'site:' . $m[1],
                'graph_id' => $m[1],
            ];
            if (preg_match('#/drives/([^/]+)/#i', $path, $driveMatch) === 1) {
                $identity['drive_id'] = $driveMatch[1];
            }

            return $identity;
        }

        if ($class === self::CLASS_TEAMS
            && preg_match('#/teams/([^/]+)(?:/|$)#i', $path, $m) === 1) {
            return [
                'identity_key' => 'team:' . $m[1],
                'graph_id' => $m[1],
            ];
        }

        if ($class === self::CLASS_GROUPS
            && preg_match('#/groups/([^/]+)/(?:mail|calendar|calendars)(?:/|$)#i', $path, $m) === 1) {
            return [
                'identity_key' => 'group:' . $m[1],
                'graph_id' => $m[1],
            ];
        }

        if ($class === self::CLASS_PLANNER
            && preg_match('#/planner/([^/]+)(?:/|$)#i', $path, $m) === 1) {
            return [
                'identity_key' => 'planner:' . $m[1],
                'graph_id' => $m[1],
            ];
        }

        if ($class === self::CLASS_ONENOTE
            && preg_match('#/onenote/([^/]+)(?:/|$)#i', $path, $m) === 1) {
            return [
                'identity_key' => 'onenote:' . $m[1],
                'graph_id' => $m[1],
            ];
        }

        throw new \RuntimeException('Could not determine the original restore location for one or more selected items.');
    }

    /**
     * @param array{identity_key: string, graph_id: string, drive_id?: string} $parsed
     * @param list<array<string, mixed>> $inventoryResources
     * @return array<string, mixed>
     */
    private static function buildTargetFromParsed(array $parsed, string $class, array $inventoryResources): array
    {
        $graphId = self::resolveGraphId($parsed['graph_id'], $class, $inventoryResources);
        $resourceType = self::resourceTypeForClass($class);
        $resourceId = TenantResource::makeId($resourceType, $graphId);

        $target = [
            'resource_id' => $resourceId,
            'graph_id' => $graphId,
            'resource_type' => $resourceType,
        ];

        if (!empty($parsed['drive_id'])) {
            $target['drive_id'] = (string) $parsed['drive_id'];
        }

        return $target;
    }

    /**
     * @param list<array<string, mixed>> $inventoryResources
     */
    private static function resolveGraphId(string $segment, string $class, array $inventoryResources): string
    {
        $segment = trim($segment);
        if ($segment === '') {
            throw new \RuntimeException('Could not determine the original restore location for one or more selected items.');
        }

        if ($class === self::CLASS_MAILBOX || $class === self::CLASS_ONEDRIVE) {
            return $segment;
        }

        foreach ($inventoryResources as $resource) {
            if (!is_array($resource)) {
                continue;
            }
            $resourceType = strtolower(trim((string) ($resource['resource_type'] ?? '')));
            if (!in_array($resourceType, self::compatibleResourceTypes($class), true)) {
                continue;
            }
            $graphId = trim((string) ($resource['graph_id'] ?? ''));
            if ($graphId === $segment) {
                return $graphId;
            }
            if (PhysicalKeyHelper::storageSafeId($graphId) === $segment) {
                return $graphId;
            }
            $resourceId = trim((string) ($resource['id'] ?? ''));
            if ($resourceId !== '' && TenantResource::graphIdFromResourceId($resourceId) === $segment) {
                return $graphId !== '' ? $graphId : TenantResource::graphIdFromResourceId($resourceId);
            }
            if ($resourceId !== '' && PhysicalKeyHelper::storageSafeId(TenantResource::graphIdFromResourceId($resourceId)) === $segment) {
                return $graphId !== '' ? $graphId : TenantResource::graphIdFromResourceId($resourceId);
            }
        }

        return $segment;
    }

    private static function resourceTypeForClass(string $class): string
    {
        return match ($class) {
            self::CLASS_MAILBOX => TenantResource::TYPE_USER,
            self::CLASS_ONEDRIVE => TenantResource::TYPE_USER,
            self::CLASS_SHAREPOINT => TenantResource::TYPE_SHAREPOINT_SITE,
            self::CLASS_TEAMS => TenantResource::TYPE_TEAM,
            self::CLASS_GROUPS => TenantResource::TYPE_M365_GROUP,
            self::CLASS_PLANNER => TenantResource::TYPE_PLANNER_PLAN,
            self::CLASS_ONENOTE => TenantResource::TYPE_ONENOTE_NOTEBOOK,
            default => TenantResource::TYPE_USER,
        };
    }

    /**
     * @param list<array<string, mixed>> $derived
     * @param list<array<string, mixed>> $submitted
     */
    private static function assertDerivedTargetsMatch(array $derived, array $submitted): void
    {
        $normalize = static function (array $target): string {
            $parts = [
                strtolower(trim((string) ($target['resource_type'] ?? ''))),
                strtolower(trim((string) ($target['graph_id'] ?? ''))),
                strtolower(trim((string) ($target['resource_id'] ?? ''))),
                strtolower(trim((string) ($target['child_run_id'] ?? ''))),
            ];

            return implode('|', $parts);
        };

        $derivedKeys = [];
        foreach ($derived as $target) {
            $derivedKeys[$normalize($target)] = true;
        }

        foreach ($submitted as $target) {
            if (!is_array($target)) {
                throw new \RuntimeException('Invalid restore destination.');
            }
            if (!isset($derivedKeys[$normalize($target)])) {
                throw new \RuntimeException('Restore destination does not match the original item locations.');
            }
        }
    }

    private static function incompatibleMessage(string $selectionClass, string $resourceType): string
    {
        return match ($selectionClass) {
            self::CLASS_MAILBOX, self::CLASS_ONEDRIVE => 'Mailbox and OneDrive items can only be restored to a user mailbox.',
            self::CLASS_SHAREPOINT => 'SharePoint files can only be restored to a SharePoint site.',
            self::CLASS_TEAMS => 'Teams items can only be restored to a Team.',
            self::CLASS_GROUPS => 'Group mail and calendar items can only be restored to a Microsoft 365 group.',
            default => 'The selected restore destination is not compatible with the selected items.',
        };
    }
}
