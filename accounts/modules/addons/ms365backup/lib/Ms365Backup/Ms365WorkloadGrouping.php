<?php

declare(strict_types=1);

namespace Ms365Backup;

/**
 * Groups MS365 batch child runs into customer-facing logical workloads.
 *
 * SharePoint sites may spawn separate site-level and drive-level child runs;
 * this helper merges them for aggregate counts and live UI rows.
 */
final class Ms365WorkloadGrouping
{
    /**
     * @param list<array<string, mixed>> $children
     * @return list<list<array<string, mixed>>>
     */
    public static function groupChildren(array $children): array
    {
        $groups = [];
        foreach ($children as $index => $child) {
            $groups[self::groupKey($child, $index)][] = $child;
        }

        return array_values($groups);
    }

    /**
     * @param list<list<array<string, mixed>>> $groups
     * @return array{
     *   total_workloads: int,
     *   completed_workloads: int,
     *   queued_workloads: int,
     *   active_running_workloads: int
     * }
     */
    public static function aggregateGroupedCounts(array $groups): array
    {
        $total = count($groups);
        $completed = 0;
        $queued = 0;
        $activeRunning = 0;

        foreach ($groups as $group) {
            $status = self::mergeGroupStatus($group);
            if (in_array($status, ['success', 'skipped', 'cancelled'], true)) {
                ++$completed;
            }
            if ($status === 'queued') {
                ++$queued;
            }
            if (in_array($status, ['running', 'starting'], true)) {
                ++$activeRunning;
            }
        }

        return [
            'total_workloads' => $total,
            'completed_workloads' => $completed,
            'queued_workloads' => $queued,
            'active_running_workloads' => $activeRunning,
        ];
    }

    /**
     * Progress contribution for one grouped workload (0.0–1.0).
     *
     * @param list<array<string, mixed>> $group
     */
    public static function groupedProgressUnit(array $group): float
    {
        $mergedStatus = self::mergeGroupStatus($group);
        if (in_array($mergedStatus, ['success', 'skipped', 'cancelled', 'error', 'failed'], true)) {
            return 1.0;
        }
        if (!in_array($mergedStatus, ['running', 'starting'], true)) {
            return 0.0;
        }

        [, , $percent] = self::mergeGroupProgress($group, $mergedStatus);
        if ($percent > 0) {
            return min(1.0, $percent / 100.0);
        }

        $sum = 0.0;
        foreach ($group as $child) {
            $sum += self::childProgressUnit($child);
        }

        return $group !== [] ? ($sum / count($group)) : 0.0;
    }

    /**
     * @param array<string, mixed> $child
     */
    public static function childProgressUnit(array $child): float
    {
        $status = (string) ($child['status'] ?? '');
        if (in_array($status, ['success', 'skipped', 'cancelled', 'error', 'failed'], true)) {
            return 1.0;
        }
        if ($status !== 'running' && $status !== 'starting') {
            return 0.0;
        }

        $phase = strtolower(trim((string) ($child['phase'] ?? '')));
        $childPercent = (float) ($child['percent'] ?? 0);
        $childItemsTotal = max(0, (int) ($child['items_total'] ?? 0));
        $childItemsDone = max(0, (int) ($child['items_done'] ?? 0));
        if ($childItemsTotal > 0) {
            return min(1.0, $childItemsDone / $childItemsTotal);
        }
        if ($childPercent > 1.0) {
            return min(1.0, $childPercent / 100.0);
        }
        if ($childPercent > 0) {
            return min(1.0, $childPercent / 100.0);
        }
        if ($phase === 'kopia_upload' || $phase === 'upload') {
            return 0.5;
        }
        if ($phase === 'graph_sync' || $phase === 'prior_snapshot') {
            return 0.15;
        }

        return 0.05;
    }

    /** @param array<string, mixed> $child */
    public static function groupKey(array $child, int $index = 0): string
    {
        $resourceType = strtolower((string) ($child['resource_type'] ?? 'workload'));
        $logicalKey = self::logicalKey($child, $index);

        return $resourceType . "\0" . $logicalKey;
    }

    /** @param array<string, mixed> $child */
    public static function logicalKey(array $child, int $index = 0): string
    {
        $scope = self::decodeScopeJson($child);
        $siteId = trim((string) ($scope['_site_id'] ?? ''));
        if ($siteId !== '') {
            return 'site:' . strtolower($siteId);
        }

        $physicalKey = (string) ($child['physical_key'] ?? '');
        $parentKey = PhysicalKeyHelper::aggregateParentKey($physicalKey, $child);
        if ($parentKey !== '') {
            return strtolower($parentKey);
        }

        $graphId = trim((string) ($child['target_graph_id'] ?? $child['graph_id'] ?? ''));
        if ($graphId !== '') {
            return strtolower($graphId);
        }

        $displayName = strtolower(trim((string) ($child['user_display_name'] ?? '')));
        if ($displayName !== '') {
            return $displayName;
        }

        $id = trim((string) ($child['id'] ?? ''));
        if ($id !== '') {
            return 'run:' . strtolower($id);
        }

        return 'child:' . $index;
    }

    /** @param array<string, mixed> $child */
    public static function decodeScopeJson(array $child): array
    {
        $raw = $child['scope_json'] ?? null;
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    public static function childStatusRank(string $status): int
    {
        return match (strtolower($status)) {
            'running' => 0,
            'starting' => 1,
            'queued' => 2,
            'warning', 'partial_success' => 3,
            'failed', 'error' => 4,
            'success' => 5,
            'cancelled' => 6,
            default => 7,
        };
    }

    /**
     * @param list<array<string, mixed>> $children
     */
    public static function mergeGroupStatus(array $children): string
    {
        $bestRank = PHP_INT_MAX;
        $bestStatus = '';
        foreach ($children as $child) {
            $status = strtolower((string) ($child['status'] ?? ''));
            $rank = self::childStatusRank($status);
            if ($rank < $bestRank) {
                $bestRank = $rank;
                $bestStatus = $status;
            }
        }

        return $bestStatus !== '' ? $bestStatus : 'unknown';
    }

    /**
     * @param list<array<string, mixed>> $children
     * @return array{0: int, 1: int, 2: float}
     */
    public static function mergeGroupProgress(array $children, string $mergedStatus): array
    {
        $itemsDone = 0;
        $itemsTotal = 0;
        foreach ($children as $child) {
            $itemsDone += max(0, (int) ($child['items_done'] ?? 0));
            $itemsTotal += max(0, (int) ($child['items_total'] ?? 0));
        }

        $percent = 0.0;
        if ($itemsTotal > 0) {
            $percent = min(100.0, ($itemsDone / $itemsTotal) * 100);
        } elseif (count($children) === 1) {
            $only = $children[0];
            $percent = isset($only['percent']) ? (float) $only['percent'] : 0.0;
        } else {
            $parts = [];
            foreach ($children as $child) {
                if (isset($child['percent'])) {
                    $parts[] = (float) $child['percent'];
                }
            }
            if ($parts !== []) {
                $percent = array_sum($parts) / count($parts);
            }
        }

        if ($mergedStatus === 'success') {
            $percent = 100.0;
        }

        return [$itemsDone, $itemsTotal, $percent];
    }
}
