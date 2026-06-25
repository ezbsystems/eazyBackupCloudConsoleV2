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
        $silenceCutoff = $now - Ms365BatchRunRepository::STALE_SILENCE_SECONDS;

        $query = Capsule::table('ms365_job_queue')
            ->where('worker_node_id', $nodeId)
            ->where('status', 'running');

        if (Capsule::schema()->hasTable('ms365_backup_runs')
            && Capsule::schema()->hasColumn('ms365_backup_runs', 'last_progress_at')) {
            $staleBackupRunIds = Capsule::table('ms365_job_queue as q')
                ->join('ms365_backup_runs as r', 'r.id', '=', 'q.run_id')
                ->where('q.worker_node_id', $nodeId)
                ->where('q.status', 'running')
                ->where(function ($sub) use ($silenceCutoff) {
                    $sub->where(function ($fresh) use ($silenceCutoff) {
                        $fresh->whereNotNull('r.last_progress_at')
                            ->where('r.last_progress_at', '<', $silenceCutoff);
                    })->orWhere(function ($fallback) use ($silenceCutoff) {
                        $fallback->whereNull('r.last_progress_at')
                            ->where('r.updated_at', '<', $silenceCutoff);
                    });
                })
                ->pluck('q.run_id')
                ->map(static fn ($id) => (string) $id)
                ->all();
            if ($staleBackupRunIds !== []) {
                $query->whereNotIn('run_id', $staleBackupRunIds);
            }
        }

        return $query->update(['lease_expires_at' => $lease]);
    }

    public static function renewForBatch(string $batchRunId, ?string $workerNodeId = null): bool
    {
        $batchRunId = trim($batchRunId);
        if ($batchRunId === '' || !Ms365BatchClaimRepository::tableReady()) {
            return false;
        }

        $now = time();
        $lease = $now + Ms365EngineConfig::leaseSeconds();
        $renewThreshold = $lease - self::MIN_RUN_RENEW_INTERVAL;
        $query = Capsule::table('ms365_batch_claims')
            ->where('batch_run_id', $batchRunId)
            ->where('status', 'running')
            ->where(function ($q) use ($renewThreshold) {
                $q->whereNull('lease_expires_at')
                    ->orWhere('lease_expires_at', '<', $renewThreshold);
            });
        if ($workerNodeId !== null && $workerNodeId !== '') {
            $query->where('worker_node_id', $workerNodeId);
        }

        $renewed = $query->update([
            'lease_expires_at' => $lease,
            'last_heartbeat_at' => $now,
            'updated_at' => $now,
        ]) > 0;

        return $renewed;
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
