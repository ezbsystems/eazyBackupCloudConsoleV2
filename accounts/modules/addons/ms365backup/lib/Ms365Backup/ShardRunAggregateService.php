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
                    'primary' => $child,
                    'shards' => [],
                ];
            }
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
            } elseif ($groups[$parentKey]['primary']['id'] !== $child['id']) {
                $groups[$parentKey]['primary'] = $child;
            }
        }

        $out = [];
        foreach ($groups as $group) {
            $primary = $group['primary'];
            $parentKey = (string) $group['parent_physical_key'];
            $shards = $group['shards'];
            usort($shards, static fn (array $a, array $b) => ($a['shard_index'] ?? 0) <=> ($b['shard_index'] ?? 0));

            $manifestId = (string) ($primary['manifest_id'] ?? '');
            if ($shards !== []) {
                foreach ($shards as $shardRow) {
                    if (trim((string) ($shardRow['manifest_id'] ?? '')) !== '') {
                        $manifestId = (string) $shardRow['manifest_id'];
                        break;
                    }
                }
            }

            $entry = [
                'run_id' => (string) ($primary['id'] ?? ''),
                'physical_key' => $parentKey,
                'resource_type' => (string) ($primary['resource_type'] ?? ''),
                'graph_id' => (string) ($primary['graph_id'] ?? $primary['user_id'] ?? ''),
                'manifest_id' => $manifestId,
                'display_name' => trim((string) ($primary['user_display_name'] ?? $parentKey)),
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
}
