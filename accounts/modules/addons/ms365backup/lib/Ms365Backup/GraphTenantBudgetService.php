<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;

/**
 * Coordinates per-Entra-tenant Graph HTTP concurrency across the worker fleet (no Redis).
 */
final class GraphTenantBudgetService
{
    /** Seconds without new 429s before budget/recent count decay toward recovery. */
    private const DECAY_WINDOW_SECONDS = 600;

    public static function tableReady(): bool
    {
        return class_exists(Capsule::class) && Capsule::schema()->hasTable('ms365_graph_tenant_budget');
    }

    public static function workerShare(int $tenantRecordId, string $azureTenantId): int
    {
        $azureTenantId = trim($azureTenantId);
        if ($azureTenantId === '') {
            return Ms365EngineConfig::perTenantMaxConcurrent();
        }
        $budget = self::currentBudget($azureTenantId);
        $workers = max(1, self::activeWorkerNodesForTenant($tenantRecordId));

        return max(1, (int) floor($budget / $workers));
    }

    public static function currentBudget(string $azureTenantId): int
    {
        $azureTenantId = trim($azureTenantId);
        $max = Ms365EngineConfig::perTenantMaxConcurrent();
        if ($azureTenantId === '' || !self::tableReady()) {
            return $max;
        }
        self::applyTimeDecay($azureTenantId, time());
        $row = Capsule::table('ms365_graph_tenant_budget')
            ->where('azure_tenant_id', $azureTenantId)
            ->first(['graph_budget', 'recent_429_count']);
        if ($row === null) {
            return $max;
        }
        $budget = (int) ($row->graph_budget ?? $max);
        $floor = self::minBudget($max, (int) ($row->recent_429_count ?? 0));

        return max($floor, min($max, $budget));
    }

    /**
     * Record new Graph 429 hits for a tenant using per-run deltas (not cumulative child totals).
     */
    public static function recordTenant429(int $tenantRecordId, string $azureTenantId, int $delta429): void
    {
        $azureTenantId = trim($azureTenantId);
        if ($azureTenantId === '' || !self::tableReady()) {
            return;
        }
        if ($delta429 > 0) {
            self::shrinkBudget($azureTenantId, $delta429);
        }
        self::touchActiveWorkloads($tenantRecordId, $azureTenantId);
    }

    private static function minBudget(int $max, int $recent429Count = 0): int
    {
        $baseFloor = max(2, (int) floor($max / 4));
        if ($recent429Count >= 20) {
            return 1;
        }
        if ($recent429Count >= 10) {
            return min(2, $baseFloor);
        }

        return $baseFloor;
    }

    private static function growStep(int $max, int $budget): int
    {
        if ($budget >= $max) {
            return 0;
        }

        return 1;
    }

    private static function shrinkStep(int $budget, int $delta429, int $floor): int
    {
        $byDelta = min(8, max(2, $delta429 * 2));
        $byRatio = max(2, (int) floor($budget * 0.25));

        return min($byDelta, $byRatio, max(0, $budget - $floor));
    }

    private static function hasLast429AtColumn(): bool
    {
        static $has = null;
        if ($has === null) {
            $has = self::tableReady() && Capsule::schema()->hasColumn('ms365_graph_tenant_budget', 'last_429_at');
        }

        return $has;
    }

    private static function applyTimeDecay(string $azureTenantId, int $now): void
    {
        if (!self::hasLast429AtColumn()) {
            return;
        }
        $row = Capsule::table('ms365_graph_tenant_budget')
            ->where('azure_tenant_id', $azureTenantId)
            ->first(['graph_budget', 'recent_429_count', 'last_429_at']);
        if ($row === null) {
            return;
        }
        $last429At = (int) ($row->last_429_at ?? 0);
        if ($last429At <= 0) {
            return;
        }
        $elapsed = $now - $last429At;
        if ($elapsed < self::DECAY_WINDOW_SECONDS) {
            return;
        }
        $windows = (int) floor($elapsed / self::DECAY_WINDOW_SECONDS);
        if ($windows <= 0) {
            return;
        }

        $max = Ms365EngineConfig::perTenantMaxConcurrent();
        $recent429 = (int) ($row->recent_429_count ?? 0);
        $floor = self::minBudget($max, $recent429);
        $budget = (int) ($row->graph_budget ?? $max);
        $recent429 = (int) ($row->recent_429_count ?? 0);
        $recent429 = max(0, $recent429 - $windows);
        $grow = 0;
        if (!self::recentlyThrottled($azureTenantId, $now, self::DECAY_WINDOW_SECONDS)) {
            $grow = self::growStep($max, $budget);
        }
        if ($grow > 0) {
            $budget = min($max, $budget + $grow * $windows);
        }
        $budget = max($floor, $budget);

        Capsule::table('ms365_graph_tenant_budget')
            ->where('azure_tenant_id', $azureTenantId)
            ->update([
                'graph_budget' => $budget,
                'recent_429_count' => $recent429,
                'updated_at' => $now,
            ]);
    }

    private static function shrinkBudget(string $azureTenantId, int $delta429): void
    {
        $now = time();
        $max = Ms365EngineConfig::perTenantMaxConcurrent();
        $row = Capsule::table('ms365_graph_tenant_budget')->where('azure_tenant_id', $azureTenantId)->first();
        if ($row === null) {
            $floor = self::minBudget($max, $delta429);
            $insert = [
                'azure_tenant_id' => $azureTenantId,
                'graph_budget' => max($floor, $max - max(4, min(8, $delta429) * 2)),
                'recent_429_count' => $delta429,
                'updated_at' => $now,
            ];
            if (self::hasLast429AtColumn()) {
                $insert['last_429_at'] = $now;
            }
            Capsule::table('ms365_graph_tenant_budget')->insert($insert);

            return;
        }
        $recent429 = (int) ($row->recent_429_count ?? 0) + $delta429;
        $floor = self::minBudget($max, $recent429);
        $budget = (int) ($row->graph_budget ?? $max);
        $shrinkBy = self::shrinkStep($budget, $delta429, $floor);
        $budget = max($floor, $budget - $shrinkBy);
        $update = [
            'graph_budget' => $budget,
            'recent_429_count' => (int) ($row->recent_429_count ?? 0) + $delta429,
            'updated_at' => $now,
        ];
        if (self::hasLast429AtColumn()) {
            $update['last_429_at'] = $now;
        }
        Capsule::table('ms365_graph_tenant_budget')
            ->where('azure_tenant_id', $azureTenantId)
            ->update($update);
    }

    private static function touchActiveWorkloads(int $tenantRecordId, string $azureTenantId): void
    {
        if ($tenantRecordId <= 0 || !self::tableReady()) {
            return;
        }
        $now = time();
        if (!Capsule::table('ms365_graph_tenant_budget')->where('azure_tenant_id', $azureTenantId)->exists()) {
            $insert = [
                'azure_tenant_id' => $azureTenantId,
                'graph_budget' => Ms365EngineConfig::perTenantMaxConcurrent(),
                'recent_429_count' => 0,
                'updated_at' => $now,
            ];
            if (self::hasLast429AtColumn()) {
                $insert['last_429_at'] = 0;
            }
            Capsule::table('ms365_graph_tenant_budget')->insert($insert);
        } else {
            Capsule::table('ms365_graph_tenant_budget')
                ->where('azure_tenant_id', $azureTenantId)
                ->update(['updated_at' => $now]);
        }
    }

    /**
     * True when the tenant had Graph 429 activity within $window seconds (fleet-wide signal).
     */
    public static function recentlyThrottled(string $azureTenantId, int $now, int $window): bool
    {
        $azureTenantId = trim($azureTenantId);
        if ($azureTenantId === '' || $window <= 0 || !self::hasLast429AtColumn()) {
            return false;
        }
        $row = Capsule::table('ms365_graph_tenant_budget')
            ->where('azure_tenant_id', $azureTenantId)
            ->first(['last_429_at']);
        if ($row === null) {
            return false;
        }
        $last429At = (int) ($row->last_429_at ?? 0);

        return $last429At > 0 && ($now - $last429At) <= $window;
    }

    public static function activeWorkerNodesForTenant(int $tenantRecordId): int
    {
        if ($tenantRecordId <= 0 || !Capsule::schema()->hasTable('ms365_job_queue')) {
            return 1;
        }
        $now = time();
        $nodes = Capsule::table('ms365_job_queue as q')
            ->join('ms365_backup_runs as r', 'r.id', '=', 'q.run_id')
            ->where('r.tenant_record_id', $tenantRecordId)
            ->where('q.status', 'running')
            ->where('r.status', 'running')
            ->where(function ($query) use ($now) {
                $query->where('q.lease_expires_at', '>', $now)
                    ->orWhere('r.updated_at', '>=', $now - 180);
            })
            ->whereNotNull('q.worker_node_id')
            ->distinct()
            ->count('q.worker_node_id');

        return max(1, (int) $nodes);
    }
}
