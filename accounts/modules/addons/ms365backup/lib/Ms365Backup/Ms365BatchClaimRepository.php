<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;

/**
 * Tenant-batch claim lease bookkeeping (ms365_batch_claims).
 */
final class Ms365BatchClaimRepository
{
    public static function tableReady(): bool
    {
        return class_exists(Capsule::class) && Capsule::schema()->hasTable('ms365_batch_claims');
    }

    public static function enqueueBatch(string $batchRunId, int $tenantRecordId, int $priority = 100): void
    {
        if (!self::tableReady() || $batchRunId === '' || $tenantRecordId <= 0) {
            return;
        }
        $now = time();
        $exists = Capsule::table('ms365_batch_claims')
            ->where('batch_run_id', $batchRunId)
            ->exists();
        if ($exists) {
            return;
        }
        Capsule::table('ms365_batch_claims')->insert([
            'batch_run_id' => $batchRunId,
            'tenant_record_id' => $tenantRecordId,
            'status' => 'queued',
            'attempts' => 0,
            'max_attempts' => Ms365EngineConfig::batchMaxAttempts(),
            'priority' => $priority,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public static function countRunningForNode(string $nodeId): int
    {
        if (!self::tableReady() || $nodeId === '') {
            return 0;
        }

        return (int) Capsule::table('ms365_batch_claims')
            ->where('worker_node_id', $nodeId)
            ->where('status', 'running')
            ->count();
    }

    /** @return list<string> */
    public static function activeBatchRunIdsForNode(string $nodeId): array
    {
        if (!self::tableReady() || $nodeId === '') {
            return [];
        }

        return Capsule::table('ms365_batch_claims')
            ->where('worker_node_id', $nodeId)
            ->where('status', 'running')
            ->pluck('batch_run_id')
            ->map(static fn ($id) => (string) $id)
            ->all();
    }

    public static function countPlatformRunning(): int
    {
        if (!self::tableReady()) {
            return 0;
        }

        return (int) Capsule::table('ms365_batch_claims')
            ->where('status', 'running')
            ->count();
    }

    /** @return array<string, mixed>|null */
    public static function getRunningForNode(string $nodeId): ?array
    {
        if (!self::tableReady() || $nodeId === '') {
            return null;
        }
        $row = Capsule::table('ms365_batch_claims')
            ->where('worker_node_id', $nodeId)
            ->where('status', 'running')
            ->orderBy('claimed_at')
            ->first();

        return $row !== null ? (array) $row : null;
    }

    /**
     * Atomically claim one queued batch for a worker node (per-tenant GET_LOCK).
     *
     * @return array<string, mixed>|null claimed batch row
     */
    public static function claimForNode(string $nodeId): ?array
    {
        if (!self::tableReady() || $nodeId === '') {
            return null;
        }

        $candidates = self::fetchQueuedCandidates();
        foreach ($candidates as $candidate) {
            $batchRunId = (string) ($candidate->batch_run_id ?? '');
            $tenantRecordId = (int) ($candidate->tenant_record_id ?? 0);
            if ($batchRunId === '' || $tenantRecordId <= 0) {
                continue;
            }
            if (self::tenantHasRunningBatch($tenantRecordId, $batchRunId)) {
                continue;
            }
            if (!self::acquireTenantClaimLock($tenantRecordId)) {
                continue;
            }
            try {
                if (self::tenantHasRunningBatch($tenantRecordId, $batchRunId)) {
                    continue;
                }
                $claimed = self::tryClaimBatch($batchRunId, $tenantRecordId, $nodeId, (int) ($candidate->priority ?? 100));
                if ($claimed !== null) {
                    return $claimed;
                }
            } finally {
                self::releaseTenantClaimLock($tenantRecordId);
            }
        }

        return null;
    }

    public static function renew(string $batchRunId, string $nodeId): bool
    {
        if (!self::tableReady() || $batchRunId === '' || $nodeId === '') {
            return false;
        }
        $now = time();
        $lease = $now + Ms365EngineConfig::leaseSeconds();

        return Capsule::table('ms365_batch_claims')
            ->where('batch_run_id', $batchRunId)
            ->where('worker_node_id', $nodeId)
            ->where('status', 'running')
            ->update([
                'lease_expires_at' => $lease,
                'last_heartbeat_at' => $now,
                'updated_at' => $now,
            ]) > 0;
    }

    public static function recordProgress(string $batchRunId, string $nodeId): bool
    {
        if (!self::tableReady() || $batchRunId === '' || $nodeId === '') {
            return false;
        }
        $now = time();

        return Capsule::table('ms365_batch_claims')
            ->where('batch_run_id', $batchRunId)
            ->where('worker_node_id', $nodeId)
            ->where('status', 'running')
            ->update([
                'last_progress_at' => $now,
                'last_heartbeat_at' => $now,
                'updated_at' => $now,
            ]) > 0;
    }

    public static function complete(string $batchRunId, string $nodeId): bool
    {
        if (!self::tableReady() || $batchRunId === '' || $nodeId === '') {
            return false;
        }
        $now = time();

        return Capsule::table('ms365_batch_claims')
            ->where('batch_run_id', $batchRunId)
            ->where('worker_node_id', $nodeId)
            ->where('status', 'running')
            ->update([
                'status' => 'done',
                'worker_node_id' => null,
                'running_tenant_key' => null,
                'lease_expires_at' => null,
                'updated_at' => $now,
            ]) > 0;
    }

    /**
     * Complete any running batch claim on this node whose children are all in a
     * terminal state (no queued/running children remain).
     *
     * A batch claim could otherwise stay 'running' forever after the batch
     * finished — e.g. the worker process died/restarted before sending a
     * terminal batch_complete, or the children were cancelled out-of-band. The
     * node heartbeat renews such a claim's lease every cycle (it is still
     * status='running'), so the stale-heartbeat reaper never fires and the
     * zombie claim permanently occupies the per-node / per-tenant batch slot,
     * blocking every subsequent batch for that tenant.
     *
     * @return int number of finished claims completed
     */
    public static function completeFinishedClaimsForNode(string $nodeId): int
    {
        if (!self::tableReady() || $nodeId === '') {
            return 0;
        }
        $batchRunIds = self::activeBatchRunIdsForNode($nodeId);
        $completed = 0;
        foreach ($batchRunIds as $batchRunId) {
            if (self::batchHasActiveChildren($batchRunId)) {
                continue;
            }
            if (self::complete($batchRunId, $nodeId)) {
                ++$completed;
            }
        }

        return $completed;
    }

    private static function batchHasActiveChildren(string $batchRunId): bool
    {
        if ($batchRunId === '' || !Capsule::schema()->hasTable('ms365_backup_runs')) {
            // Without a children table we cannot prove the batch is finished;
            // err on the safe side and treat it as still active.
            return true;
        }

        return Capsule::table('ms365_backup_runs')
            ->where('e3_batch_run_id', $batchRunId)
            ->whereIn('status', ['queued', 'running'])
            ->exists();
    }

    public static function fail(string $batchRunId, string $nodeId, string $message): bool
    {
        if (!self::tableReady() || $batchRunId === '' || $nodeId === '') {
            return false;
        }
        $now = time();
        $row = Capsule::table('ms365_batch_claims')
            ->where('batch_run_id', $batchRunId)
            ->where('worker_node_id', $nodeId)
            ->where('status', 'running')
            ->first(['attempts', 'max_attempts']);
        if ($row === null) {
            return false;
        }
        $attempts = (int) ($row->attempts ?? 0);
        $maxAttempts = (int) ($row->max_attempts ?? 0) > 0
            ? (int) $row->max_attempts
            : Ms365EngineConfig::batchMaxAttempts();
        $terminal = $attempts >= $maxAttempts;

        return Capsule::table('ms365_batch_claims')
            ->where('batch_run_id', $batchRunId)
            ->where('worker_node_id', $nodeId)
            ->where('status', 'running')
            ->update([
                'status' => $terminal ? 'failed' : 'queued',
                'worker_node_id' => null,
                'running_tenant_key' => null,
                'claimed_at' => null,
                'lease_expires_at' => null,
                'error_message' => mb_substr($message, 0, 500),
                'updated_at' => $now,
            ]) > 0;
    }

    /**
     * Release a running batch back to queued (drain hand-off preserves child checkpoints).
     */
    public static function release(string $batchRunId, string $nodeId, string $message = 'Worker released batch'): bool
    {
        if (!self::tableReady() || $batchRunId === '' || $nodeId === '') {
            return false;
        }
        $now = time();
        $updated = Capsule::table('ms365_batch_claims')
            ->where('batch_run_id', $batchRunId)
            ->where('worker_node_id', $nodeId)
            ->where('status', 'running')
            ->update([
                'status' => 'queued',
                'worker_node_id' => null,
                'running_tenant_key' => null,
                'claimed_at' => null,
                'lease_expires_at' => null,
                'error_message' => mb_substr($message, 0, 500),
                'updated_at' => $now,
            ]);
        if ($updated === 0) {
            return false;
        }
        self::requeueBatchChildren($batchRunId, $message, false);

        return true;
    }

    /** @return int batches reaped */
    public static function reapStaleBatches(): int
    {
        if (!self::tableReady()) {
            return 0;
        }
        $now = time();
        $heartbeatGap = Ms365EngineConfig::batchHeartbeatGapSeconds();
        $heartbeatCutoff = $now - $heartbeatGap;

        $rows = Capsule::table('ms365_batch_claims')
            ->where('status', 'running')
            ->get([
                'batch_run_id',
                'tenant_record_id',
                'worker_node_id',
                'attempts',
                'max_attempts',
                'last_heartbeat_at',
                'lease_expires_at',
            ]);

        $reaped = 0;
        foreach ($rows as $row) {
            $batchRunId = (string) ($row->batch_run_id ?? '');
            if ($batchRunId === '') {
                continue;
            }
            $lastHeartbeat = (int) ($row->last_heartbeat_at ?? 0);
            $leaseExpires = (int) ($row->lease_expires_at ?? 0);
            $staleHeartbeat = $lastHeartbeat <= 0 || $lastHeartbeat < $heartbeatCutoff;
            $staleLease = $leaseExpires > 0 && $leaseExpires < $now;
            if (!$staleHeartbeat && !$staleLease) {
                continue;
            }

            $attempts = (int) ($row->attempts ?? 0);
            $maxAttempts = (int) ($row->max_attempts ?? 0) > 0
                ? (int) $row->max_attempts
                : Ms365EngineConfig::batchMaxAttempts();
            $terminal = $attempts >= $maxAttempts;
            $message = $staleLease
                ? 'Batch lease expired (max_run backstop)'
                : 'Batch heartbeat stale';

            if ($terminal) {
                Capsule::table('ms365_batch_claims')
                    ->where('batch_run_id', $batchRunId)
                    ->where('status', 'running')
                    ->update([
                        'status' => 'failed',
                        'worker_node_id' => null,
                        'running_tenant_key' => null,
                        'lease_expires_at' => null,
                        'error_message' => mb_substr($message, 0, 500),
                        'updated_at' => $now,
                    ]);
                self::failBatchChildren($batchRunId, $message);
            } else {
                Capsule::table('ms365_batch_claims')
                    ->where('batch_run_id', $batchRunId)
                    ->where('status', 'running')
                    ->update([
                        'status' => 'queued',
                        'worker_node_id' => null,
                        'running_tenant_key' => null,
                        'claimed_at' => null,
                        'lease_expires_at' => null,
                        'attempts' => $attempts + 1,
                        'error_message' => mb_substr($message, 0, 500),
                        'updated_at' => $now,
                    ]);
                self::requeueBatchChildren($batchRunId, $message, true);
            }
            ++$reaped;
        }

        return $reaped;
    }

    public static function hasLiveLease(string $batchRunId, ?string $nodeId = null): bool
    {
        if (!self::tableReady() || $batchRunId === '') {
            return false;
        }
        $now = time();
        $query = Capsule::table('ms365_batch_claims')
            ->where('batch_run_id', $batchRunId)
            ->where('status', 'running')
            ->where('lease_expires_at', '>', $now);
        if ($nodeId !== null && $nodeId !== '') {
            $query->where('worker_node_id', $nodeId);
        }

        return $query->exists();
    }

    /**
     * Resolve a live batch lease for any child run_id (token refresh authorization).
     *
     * @return array<string, mixed>|null
     */
    public static function liveBatchLeaseForChildRun(string $runId): ?array
    {
        if (!self::tableReady() || $runId === '') {
            return null;
        }
        $run = BackupRunRepository::get($runId);
        if ($run === null) {
            return null;
        }
        $batchRunId = trim((string) ($run['e3_batch_run_id'] ?? ''));
        if ($batchRunId === '') {
            return null;
        }
        $now = time();
        $row = Capsule::table('ms365_batch_claims')
            ->where('batch_run_id', $batchRunId)
            ->where('status', 'running')
            ->where('lease_expires_at', '>', $now)
            ->first();
        if ($row === null) {
            return null;
        }

        return (array) $row;
    }

    public static function leaseExpiresAt(string $batchRunId): int
    {
        if (!self::tableReady() || $batchRunId === '') {
            return 0;
        }

        return (int) Capsule::table('ms365_batch_claims')
            ->where('batch_run_id', $batchRunId)
            ->value('lease_expires_at');
    }

    /** @return list<object> */
    private static function fetchQueuedCandidates(): array
    {
        $rows = Capsule::table('ms365_batch_claims')
            ->where('status', 'queued')
            ->orderBy('priority')
            ->orderBy('batch_run_id')
            ->limit(500)
            ->get();

        if (!Ms365EngineConfig::fairSchedulingEnabled()) {
            return $rows->take(50)->all();
        }

        return self::fetchQueuedCandidatesFairPhp($rows);
    }

    /** @param \Illuminate\Support\Collection<int, object> $rows @return list<object> */
    private static function fetchQueuedCandidatesFairPhp($rows): array
    {
        $byTenant = [];
        foreach ($rows as $row) {
            $tenantId = (int) ($row->tenant_record_id ?? 0);
            $byTenant[$tenantId][] = $row;
        }
        $out = [];
        $maxDepth = 0;
        foreach ($byTenant as $tenantRows) {
            $maxDepth = max($maxDepth, count($tenantRows));
        }
        for ($i = 0; $i < $maxDepth; ++$i) {
            foreach ($byTenant as $tenantRows) {
                if (isset($tenantRows[$i])) {
                    $tenantRows[$i]->fair_rank = $i + 1;
                    $out[] = $tenantRows[$i];
                }
            }
        }
        usort($out, static function ($a, $b): int {
            $prio = (int) ($a->priority ?? 100) <=> (int) ($b->priority ?? 100);
            if ($prio !== 0) {
                return $prio;
            }
            $fair = (int) ($a->fair_rank ?? 1) <=> (int) ($b->fair_rank ?? 1);
            if ($fair !== 0) {
                return $fair;
            }

            return strcmp((string) ($a->batch_run_id ?? ''), (string) ($b->batch_run_id ?? ''));
        });

        return array_slice($out, 0, 50);
    }

    private static function tenantHasRunningBatch(int $tenantRecordId, string $excludeBatchRunId = ''): bool
    {
        $query = Capsule::table('ms365_batch_claims')
            ->where('tenant_record_id', $tenantRecordId)
            ->where('status', 'running');
        if ($excludeBatchRunId !== '') {
            $query->where('batch_run_id', '!=', $excludeBatchRunId);
        }

        return $query->exists();
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function tryClaimBatch(
        string $batchRunId,
        int $tenantRecordId,
        string $nodeId,
        int $priority,
    ): ?array {
        $now = time();
        $lease = $now + Ms365EngineConfig::leaseSeconds();
        $updated = Capsule::table('ms365_batch_claims')
            ->where('batch_run_id', $batchRunId)
            ->where('status', 'queued')
            ->update([
                'status' => 'running',
                'tenant_record_id' => $tenantRecordId,
                'worker_node_id' => $nodeId,
                'running_tenant_key' => $tenantRecordId,
                'claimed_at' => $now,
                'lease_expires_at' => $lease,
                'last_heartbeat_at' => $now,
                'attempts' => Capsule::raw('attempts + 1'),
                'priority' => $priority,
                'error_message' => '',
                'updated_at' => $now,
            ]);
        if ($updated === 0) {
            return null;
        }

        $row = Capsule::table('ms365_batch_claims')
            ->where('batch_run_id', $batchRunId)
            ->first();

        return $row !== null ? (array) $row : null;
    }

    public static function promoteBatchChildToRunning(string $runId, string $nodeId): void
    {
        if ($runId === '' || $nodeId === '') {
            return;
        }
        $now = time();
        $lease = $now + Ms365EngineConfig::leaseSeconds();
        $updated = Capsule::table('ms365_job_queue')
            ->where('run_id', $runId)
            ->where('status', 'queued')
            ->update([
                'status' => 'running',
                'worker_node_id' => $nodeId,
                'claimed_at' => $now,
                'lease_expires_at' => $lease,
                'started_at' => $now,
                'error_message' => '',
            ]);
        if ($updated === 0) {
            return;
        }
        BackupRunRepository::update($runId, [
            'status' => 'running',
            'started_at' => $now,
            'engine_mode' => 'kopia',
            'updated_at' => $now,
        ]);
        Ms365WorkerLogRepository::recordAssignment($runId, $nodeId);
    }

    private static function requeueBatchChildren(string $batchRunId, string $message, bool $incrementAttempts): void
    {
        $children = Ms365BatchRunRepository::getChildrenForBatch($batchRunId);
        $now = time();
        foreach ($children as $child) {
            $runId = (string) ($child['id'] ?? '');
            $status = (string) ($child['status'] ?? '');
            if ($runId === '' || $status === 'success' || $status === 'cancelled') {
                continue;
            }
            $queueUpdate = [
                'status' => 'queued',
                'worker_node_id' => null,
                'claimed_at' => null,
                'lease_expires_at' => null,
                'scheduled_at' => $now,
                'error_message' => mb_substr($message, 0, 500),
            ];
            if ($incrementAttempts) {
                $queueUpdate['attempts'] = Capsule::raw('attempts + 1');
            }
            Capsule::table('ms365_job_queue')
                ->where('run_id', $runId)
                ->whereIn('status', ['running', 'queued'])
                ->update($queueUpdate);
            if (in_array($status, ['running', 'queued'], true)) {
                BackupRunRepository::update($runId, [
                    'status' => 'queued',
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private static function failBatchChildren(string $batchRunId, string $message): void
    {
        $children = Ms365BatchRunRepository::getChildrenForBatch($batchRunId);
        foreach ($children as $child) {
            $runId = (string) ($child['id'] ?? '');
            $status = (string) ($child['status'] ?? '');
            if ($runId === '' || $status === 'success' || $status === 'cancelled') {
                continue;
            }
            JobQueueRepository::markTerminalFailed($runId, $message);
            BackupRunRepository::update($runId, [
                'status' => 'error',
                'error_message' => Ms365CustomerError::message(new \RuntimeException($message)),
                'finished_at' => time(),
                'updated_at' => time(),
            ]);
        }
        Ms365BatchRunRepository::syncFromChildren($batchRunId);
    }

    private static function tenantClaimLockName(int $tenantRecordId): string
    {
        return 'ms365_tenant_batch_claim_' . $tenantRecordId;
    }

    private static function acquireTenantClaimLock(int $tenantRecordId, int $timeoutSeconds = 3): bool
    {
        if ($tenantRecordId <= 0) {
            return true;
        }
        $row = Capsule::connection()->selectOne(
            'SELECT GET_LOCK(?, ?) AS acquired',
            [self::tenantClaimLockName($tenantRecordId), max(1, $timeoutSeconds)]
        );

        return (int) ($row->acquired ?? 0) === 1;
    }

    private static function releaseTenantClaimLock(int $tenantRecordId): void
    {
        if ($tenantRecordId <= 0) {
            return;
        }
        Capsule::connection()->select(
            'SELECT RELEASE_LOCK(?)',
            [self::tenantClaimLockName($tenantRecordId)]
        );
    }
}
