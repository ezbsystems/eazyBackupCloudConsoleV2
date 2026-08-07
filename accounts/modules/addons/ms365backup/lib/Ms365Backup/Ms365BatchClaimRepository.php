<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;

require_once dirname(__DIR__, 3) . '/cloudstorage/lib/Ms365BackupBootstrap.php';

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

    /**
     * Complete any claim (queued or running, with or without an owner) whose
     * children are all terminal.
     *
     * A claimable batch whose children are all success/cancelled/error can never
     * produce a valid payload — buildBatchPayload() derives the tenant from the
     * first non-terminal child and otherwise throws "Tenant record not found for
     * batch", which made workers claim such a batch, fail payload build, requeue,
     * and churn attempts indefinitely (observed attempts 10 and 39 >> max 5).
     * Marking these done removes them from the claimable pool so they stop
     * wasting claim cycles and never block the per-tenant single-owner guard.
     *
     * @return int number of finished claims completed
     */
    public static function completeFinishedClaims(): int
    {
        if (!self::tableReady()) {
            return 0;
        }
        $rows = Capsule::table('ms365_batch_claims')
            ->whereIn('status', ['queued', 'running'])
            ->get(['batch_run_id']);

        $now = time();
        $completed = 0;
        foreach ($rows as $row) {
            $batchRunId = (string) ($row->batch_run_id ?? '');
            if ($batchRunId === '' || !self::batchChildrenAllTerminal($batchRunId)) {
                continue;
            }
            $updated = Capsule::table('ms365_batch_claims')
                ->where('batch_run_id', $batchRunId)
                ->whereIn('status', ['queued', 'running'])
                ->update([
                    'status' => 'done',
                    'worker_node_id' => null,
                    'running_tenant_key' => null,
                    'claimed_at' => null,
                    'lease_expires_at' => null,
                    'error_message' => 'All child workloads terminal',
                    'updated_at' => $now,
                ]);
            if ($updated > 0) {
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

    /**
     * True only when the batch has at least one child row and none are active
     * (queued/running). A batch with zero child rows is treated as NOT finished
     * (it may be mid-enqueue), so it is never spuriously completed.
     */
    private static function batchChildrenAllTerminal(string $batchRunId): bool
    {
        if ($batchRunId === '' || !Capsule::schema()->hasTable('ms365_backup_runs')) {
            return false;
        }
        $total = Capsule::table('ms365_backup_runs')
            ->where('e3_batch_run_id', $batchRunId)
            ->count();
        if ($total === 0) {
            return false;
        }

        return !self::batchHasActiveChildren($batchRunId);
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

        $updated = Capsule::table('ms365_batch_claims')
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
        if ($updated && $terminal) {
            self::failBatchChildren($batchRunId, $message);
        }

        return $updated;
    }

    /**
     * Release a running batch claim so the worker drains and re-claims with a fresh
     * payload, without resetting every child row to queued (preserves upload progress).
     */
    public static function handOffRunningBatchClaim(string $batchRunId, string $nodeId, string $message): bool
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
                'status' => 'queued',
                'worker_node_id' => null,
                'running_tenant_key' => null,
                'claimed_at' => null,
                'lease_expires_at' => null,
                'error_message' => mb_substr($message, 0, 500),
                'updated_at' => $now,
            ]) > 0;
    }

    /**
     * Running batch workers only launch children present at claim time. Queued children
     * left behind after reaper/release need a claim hand-off to be picked up again.
     *
     * @param list<string> $batchRunIds
     */
    public static function handOffRunningBatchClaims(array $batchRunIds, string $message): int
    {
        $handed = 0;
        foreach (array_values(array_unique(array_filter(array_map('strval', $batchRunIds)))) as $batchRunId) {
            if ($batchRunId === '') {
                continue;
            }
            $claim = Capsule::table('ms365_batch_claims')
                ->where('batch_run_id', $batchRunId)
                ->where('status', 'running')
                ->first();
            if ($claim === null) {
                continue;
            }
            $nodeId = (string) ($claim->worker_node_id ?? '');
            if ($nodeId === '' || !self::handOffRunningBatchClaim($batchRunId, $nodeId, $message)) {
                continue;
            }
            ++$handed;
        }

        return $handed;
    }

    /**
     * Reconcile run/queue rows that diverged during batch drain or child reaper events.
     *
     * @return int rows healed
     */
    public static function reconcileRunQueueStatusMismatch(): int
    {
        if (!Capsule::schema()->hasTable('ms365_backup_runs') || !Capsule::schema()->hasTable('ms365_job_queue')) {
            return 0;
        }
        $now = time();
        $healed = 0;
        $runQueuedQueueRunning = Capsule::table('ms365_backup_runs as r')
            ->join('ms365_job_queue as q', 'q.run_id', '=', 'r.id')
            ->where('r.status', 'queued')
            ->where('q.status', 'running')
            ->pluck('r.id');
        foreach ($runQueuedQueueRunning as $runId) {
            BackupRunRepository::update((string) $runId, [
                'status' => 'running',
                'updated_at' => $now,
            ]);
            ++$healed;
        }

        // Prod Sylvia: worker completed no_changes (queue=done, stats status set) but the
        // run row stayed running/graph_sync, pinning "1 workload with no progress".
        $query = Capsule::table('ms365_backup_runs as r')
            ->join('ms365_job_queue as q', 'q.run_id', '=', 'r.id')
            ->whereIn('r.status', ['running', 'starting'])
            ->where('q.status', 'done')
            ->select(['r.id', 'r.phase', 'r.stats_json', 'r.finished_at', 'r.percent']);
        foreach ($query->get() as $row) {
            $runId = (string) ($row->id ?? '');
            if ($runId === '') {
                continue;
            }
            $stats = [];
            $raw = $row->stats_json ?? null;
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $stats = $decoded;
                }
            }
            $isNoChanges = ($stats['status'] ?? '') === 'no_changes';
            $phase = strtolower(trim((string) ($row->phase ?? '')));
            $finishedAt = (int) ($row->finished_at ?? 0);
            if (!$isNoChanges && $finishedAt <= 0 && (float) ($row->percent ?? 0) < 100.0) {
                continue;
            }
            BackupRunRepository::update($runId, [
                'status' => 'success',
                'phase' => 'complete',
                'percent' => 100,
                'finished_at' => $finishedAt > 0 ? $finishedAt : $now,
                'updated_at' => $now,
                'error_message' => null,
            ]);
            if ($isNoChanges && Capsule::schema()->hasColumn('ms365_backup_runs', 'stats_json')) {
                $merged = $stats;
                $merged['status'] = 'no_changes';
                $encoded = json_encode($merged, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
                if (is_string($encoded)) {
                    BackupRunRepository::update($runId, ['stats_json' => $encoded]);
                }
            }
            Ms365BatchRunRepository::syncForChildRun($runId);
            ++$healed;
        }

        return $healed;
    }

    /**
     * Hand off active batch claims that still own queued children waiting for a worker slot.
     *
     * Only children requeued *after* the current claim (`q.scheduled_at > b.claimed_at`)
     * can strand work: the batch owner only launches the claim-time child set. Claim-time
     * children that remain `queued` while waiting on the in-process concurrency semaphore
     * are expected and must NOT trigger a hand-off (that caused 15s claim thrash with
     * attempts climbing into the thousands on production whale batches).
     * Requeued children also wait while any sibling is still running; cancelling healthy
     * siblings to refresh one retry payload turns transient Graph failures into batch churn.
     *
     * @return int batches handed off
     */
    public static function reconcileStrandedBatchQueuedChildren(): int
    {
        if (!self::tableReady() || !Capsule::schema()->hasTable('ms365_backup_runs')) {
            return 0;
        }
        $now = time();
        // Reaped children land queued with a fresh scheduled_at after claimed_at; give the
        // owner a few minutes before tearing down in-flight siblings for a hand-off.
        $cutoff = $now - 300;
        $batchRunIds = Capsule::table('ms365_backup_runs as r')
            ->join('ms365_job_queue as q', 'q.run_id', '=', 'r.id')
            ->join('ms365_batch_claims as b', 'b.batch_run_id', '=', 'r.e3_batch_run_id')
            ->where('b.status', 'running')
            ->where('r.status', 'queued')
            ->where('q.status', 'queued')
            ->whereNotNull('b.claimed_at')
            ->whereColumn('q.scheduled_at', '>', 'b.claimed_at')
            ->where('q.scheduled_at', '<', $cutoff)
            ->whereNotNull('r.e3_batch_run_id')
            ->where('r.e3_batch_run_id', '!=', '')
            ->whereNotExists(static function ($query): void {
                $query->select(Capsule::raw(1))
                    ->from('ms365_backup_runs as active')
                    ->whereColumn('active.e3_batch_run_id', 'b.batch_run_id')
                    ->where('active.status', 'running');
            })
            ->distinct()
            ->pluck('r.e3_batch_run_id')
            ->all();

        return self::handOffRunningBatchClaims($batchRunIds, 'Stranded queued batch children');
    }

    /**
     * Hand off batches that have been claimed for a while yet still have zero
     * running children (only queued remain). Unlike stranded-requeue hand-off,
     * this covers claim-time children the owner never started — e.g. worker
     * crash-loop during batch start — so another idle node can pick them up.
     *
     * @return int batches handed off
     */
    public static function reconcileIdleOwnedQueuedBatches(): int
    {
        if (!self::tableReady() || !Capsule::schema()->hasTable('ms365_backup_runs')) {
            return 0;
        }
        $now = time();
        // Long enough that a healthy owner would have promoted at least one child
        // past tryReserve; short enough that a crash-looping owner frees quickly.
        $cutoff = $now - 180;
        $batchRunIds = Capsule::table('ms365_batch_claims as b')
            ->where('b.status', 'running')
            ->whereNotNull('b.claimed_at')
            ->where('b.claimed_at', '<', $cutoff)
            ->whereExists(static function ($query): void {
                $query->select(Capsule::raw(1))
                    ->from('ms365_backup_runs as queued')
                    ->whereColumn('queued.e3_batch_run_id', 'b.batch_run_id')
                    ->where('queued.status', 'queued');
            })
            ->whereNotExists(static function ($query): void {
                $query->select(Capsule::raw(1))
                    ->from('ms365_backup_runs as active')
                    ->whereColumn('active.e3_batch_run_id', 'b.batch_run_id')
                    ->where('active.status', 'running');
            })
            ->pluck('b.batch_run_id')
            ->all();

        if ($batchRunIds !== []) {
            // #region agent log
            @file_put_contents(
                '/var/www/eazybackup.ca/.cursor/debug-9e9596.log',
                json_encode([
                    'sessionId' => '9e9596',
                    'hypothesisId' => 'H4',
                    'location' => 'Ms365BatchClaimRepository.php:reconcileIdleOwnedQueuedBatches',
                    'message' => 'idle owned batch handoff candidates',
                    'data' => ['batch_run_ids' => array_values($batchRunIds), 'count' => count($batchRunIds)],
                    'timestamp' => (int) round(microtime(true) * 1000),
                ], JSON_UNESCAPED_SLASHES) . "\n",
                FILE_APPEND
            );
            // #endregion
        }

        return self::handOffRunningBatchClaims($batchRunIds, 'Idle owned batch with queued children');
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
        // Retire claims whose children are all terminal before reaping stale
        // running claims, so finished-but-not-completed batches stop churning
        // through the claim pool ("Tenant record not found" requeue loop).
        self::completeFinishedClaims();
        $reaped = self::reapStalledBatchChildren();
        $reaped += self::reconcileRunQueueStatusMismatch();
        $reaped += self::reconcileStrandedBatchQueuedChildren();
        $reaped += self::reconcileIdleOwnedQueuedBatches();
        $now = time();
        $heartbeatGap = Ms365EngineConfig::batchHeartbeatGapSeconds();
        $heartbeatCutoff = $now - $heartbeatGap;
        // A wedged owner can keep heartbeating (load>0 from stale "running" child
        // rows) yet post zero progress for a very long time. The heartbeat-gap
        // rule alone never reclaims such a batch, so it sits "running" while idle
        // workers starve. Treat a running batch as stale once it has made no
        // progress (floored by claim time, so a freshly claimed batch is never
        // reaped before its first progress post) for STALE_SILENCE_SECONDS.
        $progressCutoff = $now - Ms365BatchRunRepository::STALE_SILENCE_SECONDS;

        $rows = Capsule::table('ms365_batch_claims')
            ->where('status', 'running')
            ->get([
                'batch_run_id',
                'tenant_record_id',
                'worker_node_id',
                'attempts',
                'max_attempts',
                'last_heartbeat_at',
                'last_progress_at',
                'claimed_at',
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
            $progressRef = max((int) ($row->last_progress_at ?? 0), (int) ($row->claimed_at ?? 0));
            $staleHeartbeat = $lastHeartbeat <= 0 || $lastHeartbeat < $heartbeatCutoff;
            $staleLease = $leaseExpires > 0 && $leaseExpires < $now;
            $staleProgress = $progressRef > 0 && $progressRef < $progressCutoff;
            if (!$staleHeartbeat && !$staleLease && !$staleProgress) {
                continue;
            }

            $attempts = (int) ($row->attempts ?? 0);
            $maxAttempts = (int) ($row->max_attempts ?? 0) > 0
                ? (int) $row->max_attempts
                : Ms365EngineConfig::batchMaxAttempts();
            $terminal = $attempts >= $maxAttempts;
            $message = $staleLease
                ? 'Batch lease expired (max_run backstop)'
                : ($staleProgress && !$staleHeartbeat
                    ? 'Batch progress stale (owner heartbeating without progress)'
                    : 'Batch heartbeat stale');

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

        $reaped += self::recoverStrandedFailedBatches();

        return $reaped;
    }

    /**
     * Re-queue individual batch children with stale throughput or expired leases even when
     * sibling children keep the batch claim's last_progress_at fresh.
     *
     * @return int children re-queued
     */
    public static function reapStalledBatchChildren(): int
    {
        if (!Capsule::schema()->hasTable('ms365_backup_runs')) {
            return 0;
        }
        $now = time();
        $liveBatchSet = [];
        if (self::tableReady()) {
            $heartbeatCutoff = $now - Ms365EngineConfig::batchHeartbeatGapSeconds();
            $liveBatchIds = Capsule::table('ms365_batch_claims')
                ->where('status', 'running')
                ->where('last_heartbeat_at', '>=', $heartbeatCutoff)
                ->where('lease_expires_at', '>', $now)
                ->pluck('batch_run_id')
                ->all();
            foreach ($liveBatchIds as $batchRunId) {
                $liveBatchSet[(string) $batchRunId] = true;
            }
        }

        $select = [
            'id',
            'e3_batch_run_id',
            'phase',
            'started_at',
            'last_progress_at',
            'updated_at',
            'items_done',
            'items_total',
        ];
        if (Capsule::schema()->hasColumn('ms365_backup_runs', 'bytes_hashed')) {
            $select[] = 'bytes_hashed';
            $select[] = 'bytes_uploaded';
        }
        if (Capsule::schema()->hasColumn('ms365_backup_runs', 'stats_json')) {
            $select[] = 'stats_json';
        }
        if (ChildAbortRepository::columnReady()) {
            $select[] = 'abort_requested_at';
        }
        $rows = Capsule::table('ms365_backup_runs')
            ->where('status', 'running')
            ->whereNotNull('e3_batch_run_id')
            ->where('e3_batch_run_id', '!=', '')
            ->get($select);

        $toRequeue = [];
        $toRequeueLive = [];
        $toAbort = [];
        $toClearAbort = [];
        foreach ($rows as $row) {
            $runId = (string) ($row->id ?? '');
            if ($runId === '') {
                continue;
            }
            $child = (array) $row;
            $phase = strtolower(trim((string) ($child['phase'] ?? '')));
            $itemsDone = (int) ($child['items_done'] ?? 0);
            $itemsTotal = (int) ($child['items_total'] ?? 0);
            $bytesHashed = (int) ($child['bytes_hashed'] ?? 0);
            $bytesUploaded = (int) ($child['bytes_uploaded'] ?? 0);
            $freshness = Ms365BatchRunRepository::progressFreshnessAt($child);
            $silenceSeconds = Ms365BatchRunRepository::STALE_SILENCE_SECONDS;
            // Shortened silence is upload-phase only. Graph-bound children often reach
            // items_done==items_total with bytes_hashed>0 (content sizes) while mail/
            // calendar sync is still active; applying the upload-tail 600s rule there
            // false-aborts live Graph work (prod: Child progress stale live owner abort).
            if ($phase === 'prior_snapshot') {
                $silenceSeconds = min($silenceSeconds, 600);
            }
            if (Ms365BatchRunRepository::isUploadLikePhase($phase)
                && $itemsDone > 0
                && ($itemsTotal <= 0 || $itemsDone < $itemsTotal)) {
                $silenceSeconds = min($silenceSeconds, 600);
            }
            if (Ms365BatchRunRepository::isUploadLikePhase($phase)
                && $itemsTotal > 0
                && $itemsDone >= $itemsTotal) {
                $silenceSeconds = min(
                    $silenceSeconds,
                    Ms365BatchRunRepository::UPLOAD_TAIL_STALE_SECONDS
                );
            }
            // Zero-byte upload open/hash wedge (prod: Emily Corbett). items_done stays 0
            // while Kopia never hashes; without this the child waited full 1800s.
            if (Ms365BatchRunRepository::isUploadLikePhase($phase)
                && $itemsDone === 0
                && $bytesHashed === 0
                && $bytesUploaded === 0) {
                $silenceSeconds = min(
                    $silenceSeconds,
                    Ms365BatchRunRepository::UPLOAD_TAIL_STALE_SECONDS
                );
            }
            $staleProgress = $freshness > 0 && $freshness < ($now - $silenceSeconds);

            $queue = Capsule::table('ms365_job_queue')->where('run_id', $runId)->first();
            $staleLease = $queue !== null
                && (string) ($queue->status ?? '') === 'running'
                && (int) ($queue->lease_expires_at ?? 0) > 0
                && (int) $queue->lease_expires_at < $now;

            $abortAt = ChildAbortRepository::columnReady()
                ? (int) ($child['abort_requested_at'] ?? 0)
                : 0;

            $batchRunId = (string) ($child['e3_batch_run_id'] ?? '');

            if (!$staleProgress && !$staleLease) {
                // Progress resumed after a soft-abort: clear orphaned abort so a later
                // brief silence does not immediately requeue past the grace window.
                if ($abortAt > 0) {
                    $toClearAbort[] = $runId;
                }
                continue;
            }

            if ($batchRunId !== '' && isset($liveBatchSet[$batchRunId])) {
                if ($abortAt <= 0) {
                    $toAbort[] = $runId;
                } elseif (($now - $abortAt) >= ChildAbortRepository::REQUEUE_AFTER_SECONDS) {
                    $toRequeueLive[] = $runId;
                }
                continue;
            }

            $toRequeue[] = $runId;
        }

        $actions = 0;
        $toClearAbort = array_values(array_unique($toClearAbort));
        if ($toClearAbort !== []) {
            ChildAbortRepository::clearAbortRequested($toClearAbort);
            $actions += count($toClearAbort);
        }
        if ($toAbort !== []) {
            $actions += ChildAbortRepository::markAbortRequested($toAbort, $now);
        }

        $toRequeue = array_values(array_unique($toRequeue));
        if ($toRequeue !== []) {
            WorkerClaimService::requeueBackupRuns(
                $toRequeue,
                'Child progress stale (batch sibling heartbeats)'
            );
            ChildAbortRepository::clearAbortRequested($toRequeue);
            $actions += count($toRequeue);
        }

        $toRequeueLive = array_values(array_unique($toRequeueLive));
        if ($toRequeueLive !== []) {
            WorkerClaimService::requeueBackupRuns(
                $toRequeueLive,
                'Child progress stale (live owner abort)'
            );
            ChildAbortRepository::clearAbortRequested($toRequeueLive);
            $actions += count($toRequeueLive);
        }

        return $actions;
    }

    /**
     * Re-queue batches that terminal-failed for a transient infra reason
     * (heartbeat/lease stale — e.g. the owning worker restarted during a deploy)
     * but still have pending children.
     *
     * Terminal-failing such a batch permanently strands customer work even
     * though an available worker could finish it (observed: a tenant batch
     * exhausted its 5 attempts purely from worker restarts, leaving 15 children
     * stuck 'queued' forever while other nodes sat idle). Pending work with
     * available capacity must remain runnable, so we give the batch a fresh
     * attempt budget. Genuinely-stuck batches re-fail after max_attempts
     * heartbeat gaps and are revived again on a bounded ~max_attempts*gap cycle
     * (visible and far better than permanent stranding); failures that are not
     * transient infra (different error_message) are left failed.
     *
     * @return int number of batches re-queued
     */
    public static function recoverStrandedFailedBatches(): int
    {
        if (!self::tableReady()) {
            return 0;
        }
        $rows = Capsule::table('ms365_batch_claims')
            ->where('status', 'failed')
            ->get(['batch_run_id', 'error_message', 'updated_at']);

        $now = time();
        // Cool-down so a batch just terminal-failed in this reaper cycle is not
        // immediately revived (would thrash attempts forever).
        $reviveAfter = $now - 300;
        $recovered = 0;
        foreach ($rows as $row) {
            $batchRunId = (string) ($row->batch_run_id ?? '');
            if ($batchRunId === '') {
                continue;
            }
            if ((int) ($row->updated_at ?? 0) > $reviveAfter) {
                continue;
            }
            if (!self::isTransientFailureReason((string) ($row->error_message ?? ''))) {
                continue;
            }
            // Revive claim and any children terminal-failed by the same infra reaper so
            // progress-stale / heartbeat-stale batches do not strand permanently after
            // attempts are exhausted (observed: 1073 children all "Batch progress stale").
            self::requeueInfraFailedChildren($batchRunId, (string) ($row->error_message ?? ''));
            if (!self::batchHasActiveChildren($batchRunId)) {
                continue;
            }
            $updated = Capsule::table('ms365_batch_claims')
                ->where('batch_run_id', $batchRunId)
                ->where('status', 'failed')
                ->update([
                    'status' => 'queued',
                    'worker_node_id' => null,
                    'running_tenant_key' => null,
                    'claimed_at' => null,
                    'lease_expires_at' => null,
                    'attempts' => 0,
                    'error_message' => 'Re-queued after transient failure (pending children remain)',
                    'updated_at' => $now,
                ]);
            if ($updated > 0) {
                Ms365BatchRunRepository::reopenAfterTransientInfraFailure($batchRunId);
                ++$recovered;
            }
        }

        return $recovered;
    }

    /**
     * Reset children that were terminal-failed for a transient infra reason so the
     * revived batch claim has runnable work again.
     */
    private static function requeueInfraFailedChildren(string $batchRunId, string $message): void
    {
        if ($batchRunId === '' || !Capsule::schema()->hasTable('ms365_backup_runs')) {
            return;
        }
        $now = time();
        $children = Capsule::table('ms365_backup_runs')
            ->where('e3_batch_run_id', $batchRunId)
            ->whereIn('status', ['error', 'failed', 'queued', 'running'])
            ->get(['id', 'status', 'error_message']);

        foreach ($children as $child) {
            $runId = (string) ($child->id ?? '');
            if ($runId === '') {
                continue;
            }
            $status = (string) ($child->status ?? '');
            $childErr = (string) ($child->error_message ?? '');
            $infraChild = self::isTransientFailureReason($childErr)
                || self::isTransientFailureReason($message)
                || in_array($status, ['queued', 'running'], true);
            if (!$infraChild) {
                continue;
            }
            if (in_array($status, ['error', 'failed', 'queued', 'running'], true)) {
                Capsule::table('ms365_backup_runs')->where('id', $runId)->update([
                    'status' => 'queued',
                    'error_message' => null,
                    'finished_at' => null,
                    'phase' => '',
                    'percent' => 0,
                    'updated_at' => $now,
                ]);
            }
            Capsule::table('ms365_job_queue')->where('run_id', $runId)->update([
                'status' => 'queued',
                'worker_node_id' => null,
                'claimed_at' => null,
                'lease_expires_at' => null,
                'finished_at' => null,
                'scheduled_at' => $now,
                'attempts' => 0,
                'error_message' => mb_substr(
                    $message !== '' ? $message : 'Re-queued after transient batch failure',
                    0,
                    500
                ),
            ]);
        }
    }

    private static function isTransientFailureReason(string $message): bool
    {
        $m = strtolower(trim($message));
        if ($m === '') {
            return false;
        }

        return strpos($m, 'heartbeat stale') !== false
            || strpos($m, 'lease expired') !== false
            || strpos($m, 'progress stale') !== false;
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
        $prior = Capsule::table('ms365_batch_claims')
            ->where('batch_run_id', $batchRunId)
            ->where('status', 'queued')
            ->first(['error_message', 'attempts']);
        if ($prior === null) {
            return null;
        }
        // Drain / hard-disk hand-offs must not burn attempt budget; otherwise a
        // healthy whale batch dies after a few soft→hard pressure cycles on 61GiB
        // workers (prod: 6b19cbee attempts 4/5 while children were still viable).
        $priorError = strtolower(trim((string) ($prior->error_message ?? '')));
        $infraHandOff = $priorError !== ''
            && (str_contains($priorError, 'drain')
                || str_contains($priorError, 'disk hard')
                || str_contains($priorError, 'disk pressure'));
        $attemptsExpr = $infraHandOff
            ? 'attempts'
            : 'attempts + 1';
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
                'attempts' => Capsule::raw($attemptsExpr),
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
        if ($runId === '' || $nodeId === '' || !self::shouldPromoteFromBatchProgress($runId)) {
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
                'attempts' => Capsule::raw('LEAST(attempts + 1, max_attempts)'),
                'error_message' => '',
            ]);
        if ($updated === 0) {
            return;
        }
        $runUpdate = [
            'status' => 'running',
            'started_at' => $now,
            // Clear sticky phase from a prior attempt. Soft-abort infra requeues preserve
            // upload phase + counters; without this reset the worker restarts at
            // graph_sync/prior_snapshot while the reaper still applies the 180s upload-tail.
            'phase' => '',
            'engine_mode' => 'kopia',
            'updated_at' => $now,
        ];
        // Reset liveness for this attempt. Counters are max'd with prior values, so
        // flat early progress posts would not bump last_progress_at otherwise.
        if (Capsule::schema()->hasColumn('ms365_backup_runs', 'last_progress_at')) {
            $runUpdate['last_progress_at'] = $now;
        }
        BackupRunRepository::update($runId, $runUpdate);
        ChildAbortRepository::clearAbortRequested([$runId]);
        Ms365WorkerLogRepository::recordAssignment($runId, $nodeId);
    }

    /**
     * Hub progress must not undo a live-owner soft-abort requeue while the wedged
     * in-memory run is still posting snapshots.
     */
    public static function shouldPromoteFromBatchProgress(string $runId): bool
    {
        if ($runId === '' || !Capsule::schema()->hasTable('ms365_job_queue')) {
            return true;
        }
        $queue = Capsule::table('ms365_job_queue')->where('run_id', $runId)->first(['status', 'error_message']);
        if ($queue === null) {
            return true;
        }
        if ((string) ($queue->status ?? '') !== 'queued') {
            return true;
        }
        $message = strtolower(trim((string) ($queue->error_message ?? '')));
        if ($message === '') {
            return true;
        }

        // Any queued child with an operational error was deliberately released for a
        // future claim. A stale in-flight hub snapshot is not proof that it restarted.
        return false;
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
