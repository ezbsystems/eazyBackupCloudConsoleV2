<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Maintained last_heartbeat_at on s3_cloudbackup_runs for sargable staleness queries.
 */
class RunHeartbeatSupport
{
    private static ?bool $hasColumn = null;

    public static function hasColumn(): bool
    {
        if (self::$hasColumn === null) {
            self::$hasColumn = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'last_heartbeat_at');
        }
        return self::$hasColumn;
    }

    /**
     * Merge heartbeat timestamp fields into a run UPDATE/INSERT payload.
     *
     * @param array<string,mixed> $update
     * @return array<string,mixed>
     */
    public static function mergeHeartbeat(array $update, bool $touchUpdatedAt = true): array
    {
        $now = Capsule::raw('NOW()');
        if (self::hasColumn()) {
            $update['last_heartbeat_at'] = $now;
        }
        if ($touchUpdatedAt && Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'updated_at')) {
            $update['updated_at'] = $now;
        }
        return $update;
    }

    /**
     * Sargable upper bound: rows with heartbeat older than $secondsAgo are stale.
     */
    public static function applyStaleOlderThan($query, int $secondsAgo, string $alias = 'r'): void
    {
        if (self::hasColumn()) {
            $query->where("{$alias}.last_heartbeat_at", '<', Capsule::raw('NOW() - INTERVAL ' . (int) $secondsAgo . ' SECOND'));
            return;
        }

        $hasUpdatedAt = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'updated_at');
        $heartbeatExpr = $hasUpdatedAt
            ? "COALESCE({$alias}.updated_at, {$alias}.started_at, {$alias}.created_at)"
            : "COALESCE({$alias}.started_at, {$alias}.created_at)";
        $query->whereRaw("TIMESTAMPDIFF(SECOND, {$heartbeatExpr}, NOW()) > ?", [$secondsAgo]);
    }

    /**
     * Reclaim window: heartbeat between grace and watchdog (exclusive upper for watchdog).
     */
    public static function applyReclaimWindow($query, int $graceSeconds, int $watchdogSeconds, string $alias = 'r'): void
    {
        if (self::hasColumn()) {
            $query->where("{$alias}.last_heartbeat_at", '<', Capsule::raw('NOW() - INTERVAL ' . (int) $graceSeconds . ' SECOND'));
            $query->where("{$alias}.last_heartbeat_at", '>=', Capsule::raw('NOW() - INTERVAL ' . (int) $watchdogSeconds . ' SECOND'));
            return;
        }

        $hasUpdatedAt = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'updated_at');
        $heartbeatExpr = $hasUpdatedAt
            ? "COALESCE({$alias}.updated_at, {$alias}.started_at, {$alias}.created_at)"
            : "COALESCE({$alias}.started_at, {$alias}.created_at)";
        $query->whereRaw("TIMESTAMPDIFF(SECOND, {$heartbeatExpr}, NOW()) >= ?", [$graceSeconds]);
        $query->whereRaw("TIMESTAMPDIFF(SECOND, {$heartbeatExpr}, NOW()) < ?", [$watchdogSeconds]);
    }

    public static function selectHeartbeatColumn(string $alias = 'r'): string
    {
        if (self::hasColumn()) {
            return "{$alias}.last_heartbeat_at";
        }
        $hasUpdatedAt = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'updated_at');
        return $hasUpdatedAt
            ? "COALESCE({$alias}.updated_at, {$alias}.started_at, {$alias}.created_at)"
            : "COALESCE({$alias}.started_at, {$alias}.created_at)";
    }
}
