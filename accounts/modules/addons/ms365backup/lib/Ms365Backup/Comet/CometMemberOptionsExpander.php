<?php
declare(strict_types=1);

namespace Ms365Backup\Comet;

use Ms365Backup\DiscoveryService;
use Ms365Backup\GraphClient;
use Ms365Backup\TenantResource;

/**
 * Live-fetches Microsoft 365 group/team members for Comet MemberBackupOptions roots.
 */
final class CometMemberOptionsExpander
{
    /**
     * @param array<string, int> $memberBackupOptions
     * @param list<array<string, mixed>> $inventoryResources
     * @return array{
     *   members_by_root: array<string, list<string>>,
     *   errors: array<string, string>,
     *   stats: array{
     *     roots_total: int,
     *     roots_resolved: int,
     *     roots_live_fetched: int,
     *     roots_cache_fallback: int,
     *     roots_unresolved: int,
     *     member_ids_live: int,
     *     unique_member_ids: int
     *   }
     * }
     */
    public static function expand(
        GraphClient $graph,
        array $memberBackupOptions,
        array $inventoryResources,
    ): array {
        $byGraph = [];
        foreach ($inventoryResources as $resource) {
            if (!is_array($resource)) {
                continue;
            }
            $gid = strtolower(trim((string) ($resource['graph_id'] ?? '')));
            if ($gid === '') {
                continue;
            }
            $byGraph[$gid][] = $resource;
        }

        $membersByRoot = [];
        $errors = [];
        $rootsResolved = 0;
        $rootsLive = 0;
        $rootsCache = 0;
        $rootsUnresolved = 0;
        $liveIdCount = 0;
        $unique = [];

        foreach ($memberBackupOptions as $rootId => $_) {
            $rootId = (string) $rootId;
            if ($rootId === '') {
                continue;
            }

            $root = self::resolveRoot($rootId, $byGraph);
            if ($root === null) {
                ++$rootsUnresolved;
                $errors[$rootId] = 'MemberBackupOptions root not found in inventory.';
                continue;
            }
            ++$rootsResolved;

            $type = (string) ($root['resource_type'] ?? '');
            $graphId = trim((string) ($root['graph_id'] ?? $rootId));
            $memberIds = [];
            $source = '';

            try {
                $memberIds = self::fetchLiveMemberIds($graph, $type, $graphId);
                if ($memberIds !== []) {
                    $source = 'live';
                }
            } catch (\Throwable $e) {
                $errors[$rootId] = $e->getMessage();
            }

            if ($memberIds === []) {
                $meta = is_array($root['meta'] ?? null) ? $root['meta'] : [];
                $cached = is_array($meta['member_azure_ids'] ?? null)
                    ? array_values(array_filter(array_map('strval', $meta['member_azure_ids'])))
                    : [];
                if ($cached !== []) {
                    $memberIds = $cached;
                    $source = 'cache';
                }
            }

            if ($source === 'live') {
                ++$rootsLive;
                $liveIdCount += count($memberIds);
            } elseif ($source === 'cache') {
                ++$rootsCache;
            }

            $membersByRoot[$rootId] = $memberIds;
            $membersByRoot[strtolower($rootId)] = $memberIds;
            foreach ($memberIds as $mid) {
                $unique[strtolower($mid)] = true;
            }
        }

        return [
            'members_by_root' => $membersByRoot,
            'errors' => $errors,
            'stats' => [
                'roots_total' => count($memberBackupOptions),
                'roots_resolved' => $rootsResolved,
                'roots_live_fetched' => $rootsLive,
                'roots_cache_fallback' => $rootsCache,
                'roots_unresolved' => $rootsUnresolved,
                'member_ids_live' => $liveIdCount,
                'unique_member_ids' => count($unique),
            ],
        ];
    }

    /**
     * @param array<string, list<array<string, mixed>>> $byGraph
     * @return array<string, mixed>|null
     */
    private static function resolveRoot(string $rootId, array $byGraph): ?array
    {
        $candidates = $byGraph[strtolower(trim($rootId))] ?? [];
        foreach ([
            TenantResource::TYPE_TEAM,
            TenantResource::TYPE_M365_GROUP,
            TenantResource::TYPE_SHAREPOINT_SITE,
        ] as $want) {
            foreach ($candidates as $candidate) {
                if ((string) ($candidate['resource_type'] ?? '') === $want) {
                    return $candidate;
                }
            }
        }

        return $candidates[0] ?? null;
    }

    /** @return list<string> */
    private static function fetchLiveMemberIds(GraphClient $graph, string $resourceType, string $graphId): array
    {
        $graphId = trim($graphId);
        if ($graphId === '') {
            return [];
        }

        // Prefer the type-native endpoint, then the sibling team/group API.
        // Inventory often classifies the same Azure AD group as a team; either
        // endpoint can return members depending on licensing / Teamification.
        $paths = match ($resourceType) {
            TenantResource::TYPE_TEAM => [
                'teams/' . rawurlencode($graphId) . '/members',
                'groups/' . rawurlencode($graphId) . '/members',
            ],
            TenantResource::TYPE_M365_GROUP => [
                'groups/' . rawurlencode($graphId) . '/members',
                'teams/' . rawurlencode($graphId) . '/members',
            ],
            // Site permissions are heavier; ImportService can still use inventory cache for sites.
            TenantResource::TYPE_SHAREPOINT_SITE => [],
            default => [],
        };

        $lastError = null;
        foreach ($paths as $path) {
            try {
                $ids = self::paginateMemberIds($graph, $path);
                if ($ids !== []) {
                    return $ids;
                }
            } catch (\Throwable $e) {
                $lastError = $e;
            }
        }

        if ($lastError !== null && $paths !== []) {
            throw $lastError;
        }

        return [];
    }

    /** @return list<string> */
    private static function paginateMemberIds(GraphClient $graph, string $path): array
    {
        $ids = [];
        $query = [
            '$select' => 'id,displayName,userPrincipalName,mail,userType',
        ];
        foreach ($graph->paginate($path, $query) as $member) {
            if (!is_array($member) || !DiscoveryService::isGraphUserMember($member)) {
                continue;
            }
            $id = trim((string) ($member['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $ids[$id] = true;
        }

        return array_keys($ids);
    }
}
