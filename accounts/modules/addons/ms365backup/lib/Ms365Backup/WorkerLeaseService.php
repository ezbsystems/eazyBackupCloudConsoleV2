<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;

/**
 * Renews ms365_job_queue leases while workers report progress or heartbeat.
 */
final class WorkerLeaseService
{
    /**
     * Minimum spacing between per-run lease writes. Progress posts can arrive
     * frequently; renewing the lease (a committed write) on every post created an
     * fsync convoy that stalled the whole database. The lease window is hours, and
     * the 30s node heartbeat renews every running lease via renewForNode(), so a
     * per-run renewal cadence of ~60s is more than sufficient.
     */
    private const MIN_RUN_RENEW_INTERVAL = 60;

    public static function renewForRun(string $runId, ?string $workerNodeId = null): bool
    {
        $runId = trim($runId);
        if ($runId === '') {
            return false;
        }

        $now = time();
        $lease = $now + Ms365EngineConfig::leaseSeconds();
        // Only write when the lease was last renewed more than MIN_RUN_RENEW_INTERVAL
        // ago. A 0-row conditional UPDATE writes no redo/binlog, so the common case
        // (frequent progress posts) avoids a synchronous commit entirely.
        $renewThreshold = $lease - self::MIN_RUN_RENEW_INTERVAL;
        $query = Capsule::table('ms365_job_queue')
            ->where('run_id', $runId)
            ->where('status', 'running')
            ->where(function ($q) use ($renewThreshold) {
                $q->whereNull('lease_expires_at')
                    ->orWhere('lease_expires_at', '<', $renewThreshold);
            });
        if ($workerNodeId !== null && $workerNodeId !== '') {
            $query->where('worker_node_id', $workerNodeId);
        }

        return $query->update(['lease_expires_at' => $lease]) > 0;
    }

    /** @return int leases renewed */
    public static function renewForNode(string $nodeId): int
    {
        $nodeId = trim($nodeId);
        if ($nodeId === '') {
            return 0;
        }

        $now = time();
        $lease = $now + Ms365EngineConfig::leaseSeconds();

        return Capsule::table('ms365_job_queue')
            ->where('worker_node_id', $nodeId)
            ->where('status', 'running')
            ->update(['lease_expires_at' => $lease]);
    }

    public static function leaseExpiresAt(string $runId): int
    {
        if ($runId === '') {
            return 0;
        }

        return (int) Capsule::table('ms365_job_queue')
            ->where('run_id', $runId)
            ->value('lease_expires_at');
    }
}
