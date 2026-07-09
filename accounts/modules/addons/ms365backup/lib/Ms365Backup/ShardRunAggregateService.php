<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * Groups shard child runs into logical resources for batch/restore views.
 */
final class ShardRunAggregateService
{
    /**
     * @param list<array<string, mixed>> $children
     * @return list<array<string, mixed>>
     */
    public static function aggregateForRestore(array $children): array
    {
        $groups = [];
        foreach ($children as $child) {
            $physicalKey = (string) ($child['physical_key'] ?? '');
            $parentKey = PhysicalKeyHelper::aggregateParentKey($physicalKey, $child);
            if (!isset($groups[$parentKey])) {
                $groups[$parentKey] = [
                    'parent_physical_key' => $parentKey,
                    'members' => [],
                    'shards' => [],
                ];
            }
            $groups[$parentKey]['members'][] = $child;
            $shard = PhysicalKeyHelper::parseShard($physicalKey);
            if ($shard !== null) {
                $groups[$parentKey]['shards'][] = [
                    'run_id' => (string) ($child['id'] ?? ''),
                    'physical_key' => $physicalKey,
                    'manifest_id' => (string) ($child['manifest_id'] ?? ''),
                    'shard_index' => (int) ($shard['index'] ?? 0),
                    'shard_kind' => (string) ($shard['kind'] ?? ''),
                    'shard_segment' => (string) ($shard['segment'] ?? ''),
                ];
            }
        }

        $out = [];
        foreach ($groups as $group) {
            $members = $group['members'];
            $parentKey = (string) $group['parent_physical_key'];
            $shards = $group['shards'];
            usort($shards, static fn (array $a, array $b) => ($a['shard_index'] ?? 0) <=> ($b['shard_index'] ?? 0));

            $primary = self::pickPrimaryChild($members);
            $manifestId = self::resolveAggregateManifestId($members, $primary, $shards);
            $displayName = self::resolveAggregateDisplayName($members, $primary, $parentKey);

            $entry = [
                'run_id' => (string) ($primary['id'] ?? ''),
                'physical_key' => $parentKey,
                'resource_type' => (string) ($primary['resource_type'] ?? ''),
                'graph_id' => (string) ($primary['graph_id'] ?? $primary['user_id'] ?? ''),
                'manifest_id' => $manifestId,
                'display_name' => $displayName,
                'is_sharded' => $shards !== [],
                'shard_count' => $shards !== [] ? count($shards) : 1,
            ];
            if ($shards !== []) {
                $entry['shard_runs'] = $shards;
            }
            $out[] = $entry;
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $children
     * @return list<array<string, mixed>>
     */
    public static function aggregateForBatchDisplay(array $children): array
    {
        return self::aggregateForRestore($children);
    }

    /**
     * Prefer a files-capable child (drive or site with files scope) over lists-only site runs.
     *
     * @param list<array<string, mixed>> $members
     * @return array<string, mixed>
     */
    private static function pickPrimaryChild(array $members): array
    {
        $best = $members[0] ?? [];
        $bestScore = self::primaryChildScore($best);
        foreach ($members as $member) {
            $score = self::primaryChildScore($member);
            if ($score > $bestScore) {
                $best = $member;
                $bestScore = $score;
            }
        }

        return $best;
    }

    /** @param array<string, mixed> $child */
    private static function primaryChildScore(array $child): int
    {
        $score = 0;
        $scope = BackupScope::fromLegacyRun($child);
        if ($scope->isEnabled(BackupScope::FILES)) {
            $score += 100;
        }
        if ($scope->isEnabled(BackupScope::LISTS)) {
            $score += 10;
        }
        $physicalKey = PhysicalKeyHelper::baseKey((string) ($child['physical_key'] ?? ''));
        if (str_starts_with($physicalKey, 'drive:')) {
            $score += 50;
        }
        if (str_starts_with($physicalKey, 'site:')) {
            $score += 20;
        }

        $statsRaw = (string) ($child['stats_json'] ?? '');
        if ($statsRaw !== '') {
            $stats = json_decode($statsRaw, true);
            if (is_array($stats)) {
                $files = (int) ($stats['files'] ?? 0);
                if ($files > 0) {
                    $score += min(40, (int) log10($files + 1) * 10);
                }
            }
        }

        if (trim((string) ($child['manifest_id'] ?? '')) !== '') {
            $score += 1;
        }

        return $score;
    }

    /**
     * @param list<array<string, mixed>> $members
     * @param list<array<string, mixed>> $shards
     */
    private static function resolveAggregateManifestId(array $members, array $primary, array $shards): string
    {
        if ($shards !== []) {
            foreach ($shards as $shardRow) {
                if (trim((string) ($shardRow['manifest_id'] ?? '')) !== '') {
                    return (string) $shardRow['manifest_id'];
                }
            }
        }

        $bestManifest = '';
        $bestFiles = -1;
        foreach ($members as $member) {
            $scope = BackupScope::fromLegacyRun($member);
            if (!$scope->isEnabled(BackupScope::FILES)) {
                continue;
            }
            $manifest = trim((string) ($member['manifest_id'] ?? ''));
            if ($manifest === '') {
                continue;
            }
            $files = 0;
            $statsRaw = (string) ($member['stats_json'] ?? '');
            if ($statsRaw !== '') {
                $stats = json_decode($statsRaw, true);
                if (is_array($stats)) {
                    $files = (int) ($stats['files'] ?? 0);
                }
            }
            if ($files > $bestFiles) {
                $bestFiles = $files;
                $bestManifest = $manifest;
            }
        }
        if ($bestManifest !== '') {
            return $bestManifest;
        }

        return trim((string) ($primary['manifest_id'] ?? ''));
    }

    /**
     * @param list<array<string, mixed>> $members
     */
    private static function resolveAggregateDisplayName(array $members, array $primary, string $parentKey): string
    {
        if (str_starts_with($parentKey, 'site:')) {
            foreach ($members as $member) {
                $physicalKey = PhysicalKeyHelper::baseKey((string) ($member['physical_key'] ?? ''));
                if (!str_starts_with($physicalKey, 'site:')) {
                    continue;
                }
                $name = trim((string) ($member['user_display_name'] ?? ''));
                if ($name !== '') {
                    return $name;
                }
            }
        }

        $name = trim((string) ($primary['user_display_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        return $parentKey;
    }
}
