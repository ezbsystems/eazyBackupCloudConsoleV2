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
    private const DECAY_WINDOW_SECONDS = 120;

    /**
     * Upper bound on recent_429_count. Without a cap it accumulated unbounded
     * during long batches (observed 24, 67) because trickle 429s keep
     * last_429_at fresh and block decay, which used to pin the budget floor to 1
     * and serialize the whole tenant for hours.
     */
    private const RECENT_429_CAP = 12;

    public static function tableReady(): bool
    {
        return class_exists(Capsule::class) && Capsule::schema()->hasTable('ms365_graph_tenant_budget');
    }

    /** Advisory Graph HTTP ceiling for a tenant (not divided across workers). */
    public static function workerShare(int $tenantRecordId, string $azureTenantId): int
    {
        unset($tenantRecordId);

        return self::currentBudget($azureTenantId);
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

    private static function hasSharedCumulativeColumn(): bool
    {
        static $has = null;
        if ($has === null) {
            $has = self::tableReady() && Capsule::schema()->hasColumn('ms365_graph_tenant_budget', 'last_seen_429_cumulative');
        }

        return $has;
    }

    /**
     * Record a batch's shared Graph client 429 activity exactly once per tenant.
     *
     * In batch mode all children share one graph.Client, so each child reports
     * the same cumulative 429 count. Recording a per-child delta multiplied each
     * real 429 by the number of active children, pinning recent_429_count to its
     * cap and continuously refreshing last_429_at (blocking decay/recovery).
     * Here we track the shared cumulative high-water mark and only shrink for the
     * genuine increment.
     */
    public static function recordSharedThrottle(int $tenantRecordId, string $azureTenantId, int $cumulative429): void
    {
        $azureTenantId = trim($azureTenantId);
        if ($azureTenantId === '' || !self::tableReady()) {
            return;
        }
        if (!self::hasSharedCumulativeColumn()) {
            // Column missing (pre-migration): fall back to never over-counting by
            // treating the cumulative as a single-unit signal.
            self::touchActiveWorkloads($tenantRecordId, $azureTenantId);
            return;
        }
        if ($cumulative429 < 0) {
            $cumulative429 = 0;
        }

        $row = Capsule::table('ms365_graph_tenant_budget')
            ->where('azure_tenant_id', $azureTenantId)
            ->first(['last_seen_429_cumulative']);
        $lastSeen = $row === null ? 0 : (int) ($row->last_seen_429_cumulative ?? 0);

        // A lower cumulative means the shared client was recreated (worker
        // restart / new batch); count its 429s from zero.
        if ($cumulative429 < $lastSeen) {
            $lastSeen = 0;
        }
        $delta = $cumulative429 - $lastSeen;

        if ($delta > 0) {
            self::shrinkBudget($azureTenantId, $delta);
        }
        // Persist the new high-water mark and keep the row warm.
        if (Capsule::table('ms365_graph_tenant_budget')->where('azure_tenant_id', $azureTenantId)->exists()) {
            Capsule::table('ms365_graph_tenant_budget')
                ->where('azure_tenant_id', $azureTenantId)
                ->update(['last_seen_429_cumulative' => $cumulative429, 'updated_at' => time()]);
        } else {
            self::touchActiveWorkloads($tenantRecordId, $azureTenantId);
            Capsule::table('ms365_graph_tenant_budget')
                ->where('azure_tenant_id', $azureTenantId)
                ->update(['last_seen_429_cumulative' => $cumulative429]);
        }
    }

    private static function minBudget(int $max, int $recent429Count = 0): int
    {
        unset($recent429Count);

        // Never serialize a tenant. The worker's per-request adaptive controller
        // (AIMD + Retry-After backoff) is the correct fine-grained throttle
        // response; the fleet budget is only a coordination ceiling. Flooring
        // the budget to 1 forced the worker tenant controller ceiling to 1,
        // which serialized every workload in the batch through a single Graph
        // request (~0 CPU "stall") and prevented the worker AIMD from recovering.
        // Keep enough headroom for the controller to operate and climb back.
        return max(2, (int) floor($max / 4));
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
                'recent_429_count' => min(self::RECENT_429_CAP, $delta429),
                'updated_at' => $now,
            ];
            if (self::hasLast429AtColumn()) {
                $insert['last_429_at'] = $now;
            }
            Capsule::table('ms365_graph_tenant_budget')->insert($insert);

            return;
        }
        $recent429 = min(self::RECENT_429_CAP, (int) ($row->recent_429_count ?? 0) + $delta429);
        $floor = self::minBudget($max, $recent429);
        $budget = (int) ($row->graph_budget ?? $max);
        $shrinkBy = self::shrinkStep($budget, $delta429, $floor);
        $budget = max($floor, $budget - $shrinkBy);
        $update = [
            'graph_budget' => $budget,
            'recent_429_count' => $recent429,
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
}
