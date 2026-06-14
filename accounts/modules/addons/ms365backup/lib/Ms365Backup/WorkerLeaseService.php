<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;

/**
 * Renews ms365_job_queue leases while workers report progress or heartbeat.
 */
final class WorkerLeaseService
{
    public static function renewForRun(string $runId, ?string $workerNodeId = null): bool
    {
        $runId = trim($runId);
        if ($runId === '') {
            return false;
        }

        $now = time();
        $lease = $now + Ms365EngineConfig::leaseSeconds();
        $query = Capsule::table('ms365_job_queue')
            ->where('run_id', $runId)
            ->where('status', 'running');
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
