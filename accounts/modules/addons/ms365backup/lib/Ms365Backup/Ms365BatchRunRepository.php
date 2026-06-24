<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;

/**
 * Parent run rows in s3_cloudbackup_runs for MS365 job executions.
 */
final class Ms365BatchRunRepository
{
    /**
     * Per-request memo for the ms365_backup_runs.stats_json column probe.
     * The schema is immutable within a request, so this avoids issuing one
     * information_schema query per child workload on hot progress polls.
     */
    private static ?bool $hasStatsJsonColumn = null;

    /** Stale child workload threshold aligned with WorkerClaimService::reconcileZombieRuns(). */
    private const STALE_CHILD_SECONDS = 120;

    /** Liveness window: worker is alive if progress/lease refreshed within this many seconds (≈4×45s heartbeat). */
    private const HEARTBEAT_GAP_SECONDS = 180;

    /** Reap running children with no progress posts for this long, even if the queue lease was renewed. */
    public const STALE_SILENCE_SECONDS = 1800;

    /** Running workloads silent longer than this do not count against the per-tenant claim cap. */
    public const STALLED_FOR_CAP_SECONDS = 180;

    /** Upload phases: cap/throttle shield requires material progress within this window. */
    public const UPLOAD_THROTTLE_PROGRESS_SECONDS = 600;

    /** Fail graph_sync (and similar) workloads stuck at zero items/bytes after this long. */
    private const STALE_WEDGE_SECONDS = 1800;

    /** Fail upload/snapshot phases with no progress posts for this long. */
    public const STALE_UPLOAD_SECONDS = 2700;

    /**
     * Minimum seconds between persisted parent-row live snapshots for a batch.
     * Every running child workload posts heartbeats; without this throttle they
     * all UPDATE the single parent s3_cloudbackup_runs row at once, forming a
     * row-lock convoy that pins mysqld. The live UI recomputes its own aggregate
     * per poll, so a slightly stale persisted snapshot is invisible to the user.
     */
    private const LIVE_SNAPSHOT_THROTTLE_SECONDS = 3;

    /** Window for showing the Graph throttle badge after recent 429 activity. */
    private const GRAPH_THROTTLE_WINDOW_SECONDS = 120;

      /** Recent Graph 429 activity + fresh lease = worker alive while waiting out throttling. */
    public const RECENT_THROTTLE_SECONDS = 1200;

    /** Cached result of the parent updated_at column probe (per request). */
    private static ?bool $parentHasUpdatedAt = null;

    /** @var array<int, string> tenant_record_id → azure_tenant_id (per reconcile pass) */
    private static array $azureTenantIdCache = [];

    /**
     * @param array<string, mixed> $parent
     */
    public static function isParentStatusLocked(array $parent): bool
    {
        $status = strtolower((string) ($parent['status'] ?? ''));
        if (in_array($status, ['success', 'failed', 'cancelled', 'warning', 'partial_success'], true)) {
            return true;
        }
        if (!empty($parent['cancel_requested']) && !empty($parent['finished_at'])) {
            return true;
        }

        return false;
    }

    /**
     * @return string batch run UUID
     */
    public static function create(int $clientId, string $e3JobId, string $triggerType = 'manual'): string
    {
        if (!Capsule::schema()->hasTable('s3_cloudbackup_runs')) {
            throw new \RuntimeException('Run history is not available.');
        }

        $runId = self::uuid();
        $now = date('Y-m-d H:i:s');
        $insert = [
            'run_id' => self::uuidToBinary($runId),
            'job_id' => self::uuidToBinary($e3JobId),
            'trigger_type' => in_array($triggerType, ['manual', 'schedule', 'validation'], true) ? $triggerType : 'manual',
            'status' => 'running',
            'created_at' => $now,
            'started_at' => $now,
        ];

        if (Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'engine')) {
            $insert['engine'] = 'ms365';
        }

        Capsule::table('s3_cloudbackup_runs')->insert($insert);

        return $runId;
    }

    /**
     * Record a scheduled slot that was skipped because a backup batch is still active.
     *
     * @return string skip batch run UUID
     */
    public static function recordScheduledSkip(string $e3JobId, string $existingBatchRunId): string
    {
        if (!self::isUuid($e3JobId) || !self::isUuid($existingBatchRunId)) {
            throw new \RuntimeException('Invalid job or run id for schedule skip.');
        }
        if (!Capsule::schema()->hasTable('s3_cloudbackup_runs')) {
            throw new \RuntimeException('Run history is not available.');
        }

        $runId = self::uuid();
        $now = date('Y-m-d H:i:s');
        $insert = [
            'run_id' => self::uuidToBinary($runId),
            'job_id' => self::uuidToBinary($e3JobId),
            'trigger_type' => 'schedule',
            'status' => 'warning',
            'created_at' => $now,
            'started_at' => $now,
            'finished_at' => $now,
            'error_summary' => 'Scheduled backup skipped: a previous run is still in progress.',
        ];

        if (Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'engine')) {
            $insert['engine'] = 'ms365';
        }
        if (Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'run_type')) {
            $insert['run_type'] = 'backup';
        }
        if (Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'stats_json')) {
            $insert['stats_json'] = json_encode([
                'ms365_schedule_skip' => true,
                'skipped_reason' => 'overlap',
                'existing_batch_run_id' => $existingBatchRunId,
            ], JSON_UNESCAPED_SLASHES);
        }

        Capsule::table('s3_cloudbackup_runs')->insert($insert);

        return $runId;
    }

    public static function isScheduleSkipStats(?string $statsJson): bool
    {
        if ($statsJson === null || trim($statsJson) === '') {
            return false;
        }
        $decoded = json_decode($statsJson, true);

        return is_array($decoded) && !empty($decoded['ms365_schedule_skip']);
    }

    /**
     * Reconcile parent batch row in s3_cloudbackup_runs from ms365_backup_runs children.
     */
    public static function syncFromChildren(string $batchRunId): void
    {
        if (!self::isUuid($batchRunId)) {
            return;
        }
        if (!Capsule::schema()->hasTable('ms365_backup_runs')
            || !Capsule::schema()->hasColumn('ms365_backup_runs', 'e3_batch_run_id')) {
            return;
        }

        if (!self::isRestoreBatch($batchRunId)) {
            self::reconcileBatchChildren($batchRunId);
        }

        $children = Capsule::table('ms365_backup_runs')
            ->where('e3_batch_run_id', $batchRunId)
            ->get(['status'])
            ->map(static fn ($row) => (array) $row)
            ->all();

        if ($children === []) {
            return;
        }

        $aggregate = self::aggregateStatus($children);
        if (in_array($aggregate, ['running', 'queued'], true)) {
            return;
        }

        if (Ms365BatchRetryService::maybeRequeueFailedShards($batchRunId) > 0) {
            return;
        }

        self::finalize($batchRunId, $aggregate);
    }

    /**
     * Reconcile stale/orphan child workloads so parent batches can finalize.
     */
    public static function reconcileBatchChildren(string $batchRunId): void
    {
        if (!self::isUuid($batchRunId)
            || self::isRestoreBatch($batchRunId)
            || !Capsule::schema()->hasTable('ms365_backup_runs')) {
            return;
        }

        $parent = Capsule::table('s3_cloudbackup_runs')
            ->whereRaw('run_id = ' . self::uuidToDbExpr($batchRunId))
            ->first();
        if (!$parent) {
            return;
        }

        $parentArr = (array) $parent;
        $children = self::getChildrenForBatch($batchRunId);
        if ($children === []) {
            return;
        }

        $now = time();
        $cancelRequested = !empty($parentArr['cancel_requested']);

        if ($cancelRequested) {
            $cancelledBy = !empty($parentArr['finished_at']) ? 'administrator' : 'user';
            BackupRunRepository::bulkCancelBatchChildren($batchRunId, $cancelledBy);

            return;
        }

        $queueByRun = self::queueRowsByChildId(array_column($children, 'id'));

        foreach ($children as $child) {
            $childId = (string) ($child['id'] ?? '');
            if ($childId === '' || !self::shouldReapRunningChild($child, $queueByRun[$childId] ?? null, $now)) {
                continue;
            }
            $message = 'Stale workload reconciled during batch sync';
            (new ProgressLogger($childId))->warning($message);
            WorkerClaimService::requeueBackupRuns([$childId], $message);
        }

        $children = self::getChildrenForBatch($batchRunId);
        $hasRunning = false;
        $hasError = false;
        $hasQueued = false;
        foreach ($children as $child) {
            $status = (string) ($child['status'] ?? '');
            if ($status === 'running') {
                $hasRunning = true;
            }
            if (in_array($status, ['error', 'failed'], true)) {
                $hasError = true;
            }
            if ($status === 'queued') {
                $hasQueued = true;
            }
        }

        if (!$hasRunning && $hasError && $hasQueued && !Ms365BatchRetryService::shouldRetainQueuedChildren($batchRunId)) {
            foreach ($children as $child) {
                $childId = (string) ($child['id'] ?? '');
                if ($childId === '' || (string) ($child['status'] ?? '') !== 'queued') {
                    continue;
                }
                self::cancelChildWithoutStart($childId);
            }
        }
    }

    /**
     * Reconcile every active MS365 backup batch parent (running rows in s3_cloudbackup_runs).
     * Intended for fleet cron so stuck children are reaped without worker completion hooks.
     *
     * @return array{batches: int}
     */
    public static function reconcileActiveBatches(int $limit = 100): array
    {
        if (!Capsule::schema()->hasTable('s3_cloudbackup_runs')
            || !Capsule::schema()->hasTable('ms365_backup_runs')) {
            return ['batches' => 0];
        }

        $query = Capsule::table('s3_cloudbackup_runs')
            ->where('status', 'running')
            ->whereNull('finished_at')
            ->orderByDesc('started_at')
            ->limit(max(1, $limit));

        if (Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'engine')) {
            $query->where('engine', 'ms365');
        }
        if (Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'run_type')) {
            $query->where(function ($q) {
                $q->whereNull('run_type')
                    ->orWhere('run_type', '!=', 'restore');
            });
        }

        $batches = 0;
        foreach ($query->get() as $row) {
            $arr = (array) $row;
            if (self::isParentStatusLocked($arr)) {
                continue;
            }
            $batchRunId = self::normalizeRunUuid($arr['run_id'] ?? '');
            if ($batchRunId === '' || self::isRestoreBatch($batchRunId)) {
                continue;
            }
            self::syncFromChildren($batchRunId);
            ++$batches;
        }

        return ['batches' => $batches];
    }

    /**
     * @param array<string, mixed> $child
     * @param array<string, mixed>|null $queue
     */
    private static function shouldReapRunningChild(array $child, ?array $queue, int $now): bool
    {
        if ((string) ($child['status'] ?? '') !== 'running') {
            return false;
        }
        if (self::isUploadStalled($child, $now, $queue)) {
            return true;
        }
        if (self::isThrottledWaitingAlive($child, $queue, $now)) {
            return false;
        }
        if (self::isWedgeStuck($child, $now, $queue)) {
            return true;
        }
        $progressAt = self::progressFreshnessAt($child);
        if ($progressAt > 0 && ($now - $progressAt) >= self::STALE_SILENCE_SECONDS) {
            return true;
        }
        if (self::isWorkerAlive($child, $queue, $now)) {
            return false;
        }
        $updatedAt = (int) ($child['updated_at'] ?? 0);
        if ($updatedAt >= ($now - self::STALE_CHILD_SECONDS)) {
            return false;
        }

        return true;
    }

    /** True when the workload phase depends on Microsoft Graph (not object-storage upload). */
    public static function isGraphBoundPhase(string $phase): bool
    {
        $phase = strtolower(trim($phase));

        return $phase === '' || $phase === 'graph_sync' || $phase === 'prior_snapshot';
    }

    /** Upload / snapshot phases use a shorter material-progress window than graph_sync. */
    public static function isUploadLikePhase(string $phase): bool
    {
        $phase = strtolower(trim($phase));

        return str_contains($phase, 'upload')
            || str_contains($phase, 'kopia')
            || str_contains($phase, 'snapshot');
    }

    public static function maxThrottleShieldProgressAgeSeconds(string $phase): int
    {
        return self::isUploadLikePhase($phase)
            ? self::UPLOAD_THROTTLE_PROGRESS_SECONDS
            : self::STALE_SILENCE_SECONDS;
    }

    /**
     * Run is actively waiting out Graph throttling (recent 429 + fresh worker lease).
     * Tenant-wide throttle signals only shield this workload while it still shows
     * recent progress; siblings' 429s must not pin cap slots or block reapers on
     * individually wedged runs (heartbeat-only throttle loops included).
     *
     * @param array<string, mixed> $child
     * @param array<string, mixed>|null $queue
     */
    public static function isThrottledWaitingAlive(array $child, ?array $queue, int $now): bool
    {
        if (!is_array($queue)
            || (string) ($queue['status'] ?? '') !== 'running'
            || (int) ($queue['lease_expires_at'] ?? 0) <= $now) {
            return false;
        }

        $progressAt = self::progressFreshnessAt($child);
        $progressAgeSeconds = $progressAt > 0 ? ($now - $progressAt) : PHP_INT_MAX;
        $phase = strtolower(trim((string) ($child['phase'] ?? '')));
        if ($progressAgeSeconds >= self::maxThrottleShieldProgressAgeSeconds($phase)) {
            return false;
        }

        if (Capsule::schema()->hasColumn('ms365_backup_runs', 'last_429_at')) {
            $last429At = (int) ($child['last_429_at'] ?? 0);
            if ($last429At > 0
                && ($now - $last429At) <= self::RECENT_THROTTLE_SECONDS
                && self::isGraphBoundPhase($phase)) {
                return true;
            }
        }

        if (!self::isGraphBoundPhase($phase)) {
            return false;
        }

        $tenantRecordId = (int) ($child['tenant_record_id'] ?? 0);
        if ($tenantRecordId > 0) {
            $azureTenantId = self::azureTenantIdForTenantRecord($tenantRecordId);
            if ($azureTenantId !== ''
                && GraphTenantBudgetService::recentlyThrottled($azureTenantId, $now, self::RECENT_THROTTLE_SECONDS)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param object|array<string, mixed> $row Queue/run join row with optional last_429_at, tenant_record_id, lease_expires_at.
     */
    public static function isThrottledWaitingAliveFromRow(object|array $row, int $now): bool
    {
        $leaseExpires = (int) (is_array($row) ? ($row['lease_expires_at'] ?? 0) : ($row->lease_expires_at ?? 0));
        if ($leaseExpires <= $now) {
            return false;
        }

        $progressAt = self::progressFreshnessAt(is_array($row) ? $row : (array) $row);
        $progressAgeSeconds = $progressAt > 0 ? ($now - $progressAt) : PHP_INT_MAX;
        $phase = strtolower(trim((string) (is_array($row) ? ($row['phase'] ?? '') : ($row->phase ?? ''))));
        if ($progressAgeSeconds >= self::maxThrottleShieldProgressAgeSeconds($phase)) {
            return false;
        }

        if (Capsule::schema()->hasColumn('ms365_backup_runs', 'last_429_at')) {
            $last429At = (int) (is_array($row) ? ($row['last_429_at'] ?? 0) : ($row->last_429_at ?? 0));
            if ($last429At > 0
                && ($now - $last429At) <= self::RECENT_THROTTLE_SECONDS
                && self::isGraphBoundPhase($phase)) {
                return true;
            }
        }

        if (!self::isGraphBoundPhase($phase)) {
            return false;
        }

        $tenantRecordId = (int) (is_array($row) ? ($row['tenant_record_id'] ?? 0) : ($row->tenant_record_id ?? 0));
        if ($tenantRecordId > 0) {
            $azureTenantId = self::azureTenantIdForTenantRecord($tenantRecordId);
            if ($azureTenantId !== ''
                && GraphTenantBudgetService::recentlyThrottled($azureTenantId, $now, self::RECENT_THROTTLE_SECONDS)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a running child should occupy a per-tenant workload claim slot.
     * Stalled workloads (no progress while not throttle-waiting) and exhausted
     * queue attempts are excluded so wedged whales do not pin the cap at max.
     *
     * @param array<string, mixed> $child
     * @param array<string, mixed>|null $queue
     */
    public static function countsAgainstTenantWorkloadCap(array $child, ?array $queue, int $now): bool
    {
        if ((string) ($child['status'] ?? '') !== 'running') {
            return false;
        }
        if (is_array($queue) && (string) ($queue['status'] ?? '') === 'running') {
            $maxAttempts = (int) ($queue['max_attempts'] ?? 0) > 0 ? (int) $queue['max_attempts'] : 3;
            if ((int) ($queue['attempts'] ?? 0) >= $maxAttempts) {
                return false;
            }
        }
        $progressAt = self::progressFreshnessAt($child);
        if ($progressAt <= 0) {
            return true;
        }
        $ageSeconds = $now - $progressAt;
        $phase = strtolower(trim((string) ($child['phase'] ?? '')));
        if ($ageSeconds >= self::maxThrottleShieldProgressAgeSeconds($phase)) {
            return false;
        }
        if ($ageSeconds < self::STALLED_FOR_CAP_SECONDS) {
            return true;
        }
        if (self::isThrottledWaitingAlive($child, $queue, $now)) {
            return true;
        }

        // #region agent log
        if ($ageSeconds >= self::STALLED_FOR_CAP_SECONDS) {
            @file_put_contents('/var/www/eazybackup.ca/.cursor/debug-f6115a.log', json_encode([
                'sessionId' => 'f6115a',
                'hypothesisId' => 'H1',
                'location' => 'Ms365BatchRunRepository.php:countsAgainstTenantWorkloadCap',
                'message' => 'running slot excluded from tenant cap',
                'data' => [
                    'run_id' => (string) ($child['id'] ?? ''),
                    'phase' => $phase,
                    'progress_age_s' => $ageSeconds,
                    'max_progress_age_s' => self::maxThrottleShieldProgressAgeSeconds($phase),
                ],
                'timestamp' => (int) round(microtime(true) * 1000),
            ]) . "\n", FILE_APPEND);
        }
        // #endregion

        return false;
    }

    /**
     * Skip infrastructure reapers when the tenant is throttled and the worker node is still alive.
     *
     * @param object|array<string, mixed> $row Queue/run join row with optional last_429_at, tenant_record_id, worker_node_id, lease_expires_at.
     */
    public static function shouldSkipThrottleReaper(object|array $row, int $now): bool
    {
        if (self::isThrottledWaitingAliveFromRow($row, $now)) {
            return true;
        }
        if (!self::isWorkerNodeAliveFromRow($row, $now)) {
            return false;
        }
        if (Capsule::schema()->hasColumn('ms365_backup_runs', 'last_429_at')) {
            $last429At = (int) (is_array($row) ? ($row['last_429_at'] ?? 0) : ($row->last_429_at ?? 0));
            if ($last429At > 0 && ($now - $last429At) <= self::RECENT_THROTTLE_SECONDS) {
                return true;
            }
        }
        $phase = strtolower(trim((string) (is_array($row) ? ($row['phase'] ?? '') : ($row->phase ?? ''))));
        if (!self::isGraphBoundPhase($phase)) {
            return false;
        }
        $tenantRecordId = (int) (is_array($row) ? ($row['tenant_record_id'] ?? 0) : ($row->tenant_record_id ?? 0));
        if ($tenantRecordId > 0) {
            $azureTenantId = self::azureTenantIdForTenantRecord($tenantRecordId);
            if ($azureTenantId !== ''
                && GraphTenantBudgetService::recentlyThrottled($azureTenantId, $now, self::RECENT_THROTTLE_SECONDS)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param object|array<string, mixed> $row
     */
    public static function isWorkerNodeAliveFromRow(object|array $row, int $now): bool
    {
        $nodeId = trim((string) (is_array($row) ? ($row['worker_node_id'] ?? '') : ($row->worker_node_id ?? '')));
        if ($nodeId === '' || !Capsule::schema()->hasTable('ms365_worker_nodes')) {
            return false;
        }
        $heartbeat = Capsule::table('ms365_worker_nodes')->where('node_id', $nodeId)->value('last_heartbeat_at');
        if ($heartbeat === null) {
            return false;
        }

        return (int) $heartbeat >= ($now - self::HEARTBEAT_GAP_SECONDS);
    }

    private static function azureTenantIdForTenantRecord(int $tenantRecordId): string
    {
        if ($tenantRecordId <= 0) {
            return '';
        }
        if (!array_key_exists($tenantRecordId, self::$azureTenantIdCache)) {
            $record = TenantRecordRepository::getById($tenantRecordId);
            if ($record === null) {
                self::$azureTenantIdCache[$tenantRecordId] = '';
            } else {
                $creds = TenantRecordRepository::resolvedCredentialsForRecord($record);
                self::$azureTenantIdCache[$tenantRecordId] = trim((string) ($creds['tenant_id'] ?? ''));
            }
        }

        return self::$azureTenantIdCache[$tenantRecordId];
    }

    /**
     * Timestamp of last real throughput progress (items/bytes), not lease-only heartbeats.
     *
     * @param array<string, mixed> $child
     */
    public static function progressFreshnessAt(array $child): int
    {
        if (Capsule::schema()->hasColumn('ms365_backup_runs', 'last_progress_at')) {
            $lastProgress = (int) ($child['last_progress_at'] ?? 0);
            if ($lastProgress > 0) {
                return $lastProgress;
            }
        }

        return (int) ($child['updated_at'] ?? 0);
    }

    /**
     * Worker liveness: fresh lease or recent real-progress signal (not throughput).
     *
     * @param array<string, mixed> $child
     * @param array<string, mixed>|null $queue
     */
    public static function isWorkerAlive(array $child, ?array $queue, int $now): bool
    {
        $progressAt = self::progressFreshnessAt($child);
        if ($progressAt >= ($now - self::HEARTBEAT_GAP_SECONDS)) {
            return true;
        }
        if (is_array($queue)
            && (string) ($queue['status'] ?? '') === 'running'
            && (int) ($queue['lease_expires_at'] ?? 0) > $now) {
            return true;
        }

        return false;
    }

    /** @param array<string, mixed> $child */
    private static function isWedgeStuck(array $child, int $now, ?array $queue): bool
    {
        if ((string) ($child['status'] ?? '') !== 'running') {
            return false;
        }
        if (self::isThrottledWaitingAlive($child, $queue, $now)) {
            return false;
        }
        $startedAt = (int) ($child['started_at'] ?? 0);
        if ($startedAt <= 0 || ($now - $startedAt) < self::STALE_WEDGE_SECONDS) {
            return false;
        }

        return (int) ($child['items_done'] ?? 0) === 0
            && (int) ($child['bytes_hashed'] ?? 0) === 0;
    }

    /** @param array<string, mixed> $child */
    private static function isUploadStalled(array $child, int $now, ?array $queue): bool
    {
        if ((string) ($child['status'] ?? '') !== 'running') {
            return false;
        }
        $phase = strtolower((string) ($child['phase'] ?? ''));
        if ($phase === ''
            || (!str_contains($phase, 'upload')
                && !str_contains($phase, 'kopia')
                && !str_contains($phase, 'snapshot'))) {
            return false;
        }
        $progressAt = self::progressFreshnessAt($child);

        return $progressAt > 0 && ($now - $progressAt) >= self::STALE_UPLOAD_SECONDS;
    }

    public static function syncForChildRun(string $childRunId): void
    {
        if ($childRunId === '' || !Capsule::schema()->hasTable('ms365_backup_runs')) {
            return;
        }
        $batchRunId = Capsule::table('ms365_backup_runs')
            ->where('id', $childRunId)
            ->value('e3_batch_run_id');
        if (!is_string($batchRunId) || $batchRunId === '') {
            return;
        }

        self::syncFromChildren($batchRunId);
    }

    /** @return list<array<string, mixed>> */
    public static function listForJobReconciled(string $e3JobId, int $limit = 50): array
    {
        $rows = self::listForJob($e3JobId, $limit);
        foreach ($rows as $row) {
            if (($row['status'] ?? '') === 'running' && !empty($row['run_id'])) {
                self::syncFromChildren((string) $row['run_id']);
            }
        }

        return self::listForJob($e3JobId, $limit);
    }

    public static function finalize(string $batchRunId, string $aggregateStatus): void
    {
        if (!self::isUuid($batchRunId)) {
            return;
        }
        $status = match ($aggregateStatus) {
            'error', 'failed' => 'failed',
            'partial_success' => 'partial_success',
            'success' => 'success',
            'cancelled' => 'cancelled',
            'running', 'queued' => 'running',
            default => 'warning',
        };

        $update = [
            'status' => $status,
            'finished_at' => date('Y-m-d H:i:s'),
        ];

        if (Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'error_summary')) {
            $summary = self::collectChildErrorSummary($batchRunId);
            if ($summary !== '') {
                $update['error_summary'] = $summary;
            }
        }

        Capsule::table('s3_cloudbackup_runs')
            ->whereRaw('run_id = ' . self::uuidToDbExpr($batchRunId))
            ->update($update);

        if ($status === 'success' && Capsule::schema()->hasTable('ms365_backup_runs')) {
            $child = Capsule::table('ms365_backup_runs')
                ->where('e3_batch_run_id', $batchRunId)
                ->first(['tenant_record_id', 'e3_job_id']);
            $tenantRecordId = (int) ($child->tenant_record_id ?? 0);
            $e3JobId = trim((string) ($child->e3_job_id ?? ''));
            if ($tenantRecordId > 0) {
                $record = TenantRecordRepository::getById($tenantRecordId);
                if ($record !== null) {
                    try {
                        Ms365KopiaRepoOperationService::scheduleForTenantBatchSuccess($record, $e3JobId !== '' ? $e3JobId : null);
                    } catch (\Throwable $e) {
                        logActivity('MS365 batch repo ops enqueue failed: ' . $e->getMessage());
                    }
                }
            }
        }
    }

    /**
     * Resolve a human-friendly description of where a restore is being written —
     * i.e. the Microsoft 365 account(s) the data is restored back into. Never
     * exposes storage bucket ids/names, which are irrelevant for a restore.
     *
     * Returns e.g. "Adele Vance (AdeleV@contoso.com)" or "" when unknown.
     */
    public static function restoreTargetSummary(string $batchRunId): string
    {
        if (!self::isUuid($batchRunId) || !Capsule::schema()->hasTable('ms365_restore_runs')) {
            return '';
        }

        $children = self::getChildrenForRestoreBatch($batchRunId);
        if ($children === []) {
            return '';
        }

        $hasRestoreModeCol = Capsule::schema()->hasColumn('ms365_restore_runs', 'restore_mode');
        if ($hasRestoreModeCol) {
            foreach ($children as $child) {
                if (strtolower((string) ($child['restore_mode'] ?? '')) === 'archive') {
                    return 'Download archive';
                }
            }
        }

        $labels = [];
        foreach ($children as $child) {
            $graphId = trim((string) ($child['target_graph_id'] ?? ''));
            $resourceId = trim((string) ($child['target_resource_id'] ?? ''));
            $label = self::resolveTargetIdentityLabel($graphId, $resourceId);
            if ($label !== '') {
                $labels[$label] = true;
            }
        }

        if ($labels === []) {
            return '';
        }

        $list = array_keys($labels);
        if (count($list) > 3) {
            $shown = array_slice($list, 0, 3);

            return implode(', ', $shown) . ' +' . (count($list) - 3) . ' more';
        }

        return implode(', ', $list);
    }

    private static function resolveTargetIdentityLabel(string $graphId, string $resourceId): string
    {
        [$name, $upn] = self::lookupBackupUserIdentity($graphId, $resourceId);
        if ($name !== '' && $upn !== '') {
            return $name . ' (' . $upn . ')';
        }
        if ($upn !== '') {
            return $upn;
        }
        if ($name !== '') {
            return $name;
        }

        $bareId = $resourceId;
        if ($bareId !== '' && str_contains($bareId, ':')) {
            $bareId = substr($bareId, strrpos($bareId, ':') + 1);
        }
        $lookupId = $graphId !== '' ? $graphId : $bareId;

        return $lookupId;
    }

    /**
     * @return array{0: string, 1: string} display name, UPN
     */
    private static function lookupBackupUserIdentity(string $graphId, string $resourceId): array
    {
        $bareId = $resourceId;
        if ($bareId !== '' && str_contains($bareId, ':')) {
            $bareId = substr($bareId, strrpos($bareId, ':') + 1);
        }
        $lookupId = $graphId !== '' ? $graphId : $bareId;
        if ($lookupId === '' || !Capsule::schema()->hasTable('ms365_backup_runs')) {
            return ['', ''];
        }

        $row = Capsule::table('ms365_backup_runs')
            ->where('graph_id', $lookupId)
            ->orderByDesc('created_at')
            ->first(['user_display_name', 'user_upn']);
        if ($row === null) {
            $row = Capsule::table('ms365_backup_runs')
                ->where('resource_id', 'like', '%' . $lookupId . '%')
                ->orderByDesc('created_at')
                ->first(['user_display_name', 'user_upn']);
        }
        if ($row === null) {
            return ['', ''];
        }

        return [
            trim((string) ($row->user_display_name ?? '')),
            trim((string) ($row->user_upn ?? '')),
        ];
    }

    /**
     * Build a concise, de-duplicated error summary from a batch's child runs
     * (works for both backup and restore batches).
     */
    private static function collectChildErrorSummary(string $batchRunId): string
    {
        $children = self::getBatchChildren($batchRunId);
        if ($children === []) {
            return '';
        }

        $messages = [];
        foreach ($children as $child) {
            $status = (string) ($child['status'] ?? '');
            if (!in_array($status, ['error', 'failed', 'warning'], true)) {
                continue;
            }
            $msg = trim((string) ($child['error_message'] ?? ''));
            if ($msg === '') {
                continue;
            }
            $messages[$msg] = true;
        }

        if ($messages === []) {
            return '';
        }

        $summary = implode('; ', array_keys($messages));

        return mb_substr($summary, 0, 1000);
    }

    /** @return list<array<string, mixed>> */
    public static function listForJob(string $e3JobId, int $limit = 50): array
    {
        if (!self::isUuid($e3JobId)) {
            return [];
        }

        $hasEngine = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'engine');
        $q = Capsule::table('s3_cloudbackup_runs')
            ->whereRaw('job_id = ' . self::uuidToDbExpr($e3JobId))
            ->orderByDesc('started_at')
            ->limit($limit);

        if ($hasEngine) {
            $q->where('engine', 'ms365');
        }

        return $q->get()
            ->map(static function ($row) {
                $arr = (array) $row;
                if (isset($arr['run_id']) && is_string($arr['run_id']) && strlen($arr['run_id']) === 16) {
                    $arr['run_id'] = self::binaryToUuid($arr['run_id']);
                }

                return $arr;
            })
            ->all();
    }

    /** @param list<array<string, mixed>> $childRuns */
    public static function aggregateStatus(array $childRuns): string
    {
        if ($childRuns === []) {
            return 'queued';
        }
        $hasRunning = false;
        $hasError = false;
        $allSuccess = true;
        $allCancelled = true;
        foreach ($childRuns as $run) {
            $st = (string) ($run['status'] ?? '');
            if (in_array($st, ['queued', 'running'], true)) {
                $hasRunning = true;
                $allSuccess = false;
                $allCancelled = false;
            }
            if (in_array($st, ['error', 'failed'], true)) {
                $hasError = true;
                $allSuccess = false;
                $allCancelled = false;
            }
            if ($st === 'cancelled') {
                $allSuccess = false;
            } else {
                $allCancelled = false;
            }
            if (!in_array($st, ['success', 'skipped'], true)) {
                $allSuccess = false;
            }
        }
        if ($hasRunning) {
            return 'running';
        }
        if ($allCancelled) {
            return 'cancelled';
        }
        if ($hasError) {
            $hasSuccess = false;
            foreach ($childRuns as $run) {
                $st = (string) ($run['status'] ?? '');
                if (in_array($st, ['success', 'skipped'], true)) {
                    $hasSuccess = true;
                    break;
                }
            }
            if ($hasSuccess) {
                return 'partial_success';
            }

            return 'failed';
        }
        if ($allSuccess) {
            return 'success';
        }

        return 'warning';
    }

    /** @return list<array<string, mixed>> */
    public static function getChildrenForBatch(string $batchRunId): array
    {
        if (!self::isUuid($batchRunId)
            || !Capsule::schema()->hasTable('ms365_backup_runs')
            || !Capsule::schema()->hasColumn('ms365_backup_runs', 'e3_batch_run_id')) {
            return [];
        }

        return Capsule::table('ms365_backup_runs')
            ->where('e3_batch_run_id', $batchRunId)
            ->orderBy('created_at')
            ->get()
            ->map(static fn ($row) => (array) $row)
            ->all();
    }

    /** @return array<string, mixed>|null */
    public static function getParentForBatch(string $batchRunId): ?array
    {
        if (!self::isUuid($batchRunId) || !Capsule::schema()->hasTable('s3_cloudbackup_runs')) {
            return null;
        }
        $row = Capsule::table('s3_cloudbackup_runs')
            ->whereRaw('run_id = ' . self::uuidToDbExpr($batchRunId))
            ->first();
        if ($row === null) {
            return null;
        }
        $arr = (array) $row;
        if (isset($arr['run_id']) && is_string($arr['run_id']) && strlen($arr['run_id']) === 16) {
            $arr['run_id'] = self::binaryToUuid($arr['run_id']);
        }

        return $arr;
    }

    public static function markBatchRetryInProgress(string $batchRunId, int $round, int $requeueCount): void
    {
        if (!self::isUuid($batchRunId) || !Capsule::schema()->hasTable('s3_cloudbackup_runs')) {
            return;
        }

        $parent = self::getParentForBatch($batchRunId);
        $statsJson = [];
        if (is_array($parent) && !empty($parent['stats_json'])) {
            $decoded = is_string($parent['stats_json'])
                ? json_decode($parent['stats_json'], true)
                : $parent['stats_json'];
            if (is_array($decoded)) {
                $statsJson = $decoded;
            }
        }

        $statsJson['ms365_batch_auto_retry_round'] = $round;
        $statsJson['ms365_batch_last_retry_at'] = time();
        $statsJson['ms365_batch_last_retry_count'] = $requeueCount;

        $update = [
            'status' => 'running',
            'finished_at' => null,
        ];
        if (Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'stats_json')) {
            $update['stats_json'] = json_encode($statsJson, JSON_UNESCAPED_SLASHES);
        }

        Capsule::table('s3_cloudbackup_runs')
            ->whereRaw('run_id = ' . self::uuidToDbExpr($batchRunId))
            ->update($update);
    }

    /**
     * @param list<array<string, mixed>> $children
     * @return array<string, mixed>
     */
    public static function computeAggregates(array $children, bool $isRestore = false): array
    {
        $bytesProcessed = 0;
        $bytesTransferred = 0;
        $itemsDone = 0;
        $itemsSkipped = 0;
        $itemsTotal = 0;
        $progressWeighted = 0.0;
        $progressWeight = 0;
        $completedWorkloads = 0;
        $terminalWorkloads = 0;
        $queuedWorkloads = 0;
        $activeRunning = 0;
        $totalWorkloads = count($children);
        $graph429Total = 0;
        $graphRequestsTotal = 0;
        $byteStatsComparable = true;
        $activeChild = null;
        $activeIndex = 0;
        /** @var list<array<string, mixed>> $runningChildren */
        $runningChildren = [];

        foreach ($children as $index => $child) {
            $status = (string) ($child['status'] ?? '');
            $childItemsTotal = max(0, (int) ($child['items_total'] ?? 0));
            $childItemsDone = max(0, (int) ($child['items_done'] ?? 0));
            $childItemsSkipped = max(0, (int) ($child['items_skipped'] ?? 0));
            $childPercent = (float) ($child['percent'] ?? 0);

            $bytesProcessed += (int) ($child['bytes_hashed'] ?? 0);
            $bytesTransferred += (int) ($child['bytes_uploaded'] ?? 0);
            $itemsDone += $childItemsDone;
            $itemsSkipped += $childItemsSkipped;
            $itemsTotal += $childItemsTotal;

            if (in_array($status, ['success', 'skipped', 'cancelled'], true)) {
                ++$completedWorkloads;
            }
            if (in_array($status, ['success', 'skipped', 'cancelled', 'error', 'failed'], true)) {
                ++$terminalWorkloads;
            }
            if ($status === 'queued') {
                ++$queuedWorkloads;
            }
            if ($status === 'running') {
                ++$activeRunning;
            }

            $childStats = self::decodeChildStatsJson($child);
            $hits429 = (int) ($childStats['graph_429_hits'] ?? 0);
            if ($hits429 > 0) {
                $graph429Total += $hits429;
            }
            $requests = (int) ($childStats['graph_requests'] ?? 0);
            if ($requests > 0) {
                $graphRequestsTotal += $requests;
            }

            if ($status === 'running') {
                $childPhase = strtolower(trim((string) ($child['phase'] ?? '')));
                if ($childPhase === '' || $childPhase === 'graph_sync' || $childPhase === 'prior_snapshot') {
                    $byteStatsComparable = false;
                }
            }

            if ($childItemsTotal > 0) {
                $progressWeighted += $childPercent * $childItemsTotal;
                $progressWeight += $childItemsTotal;
            } elseif ($childPercent > 0) {
                $progressWeighted += $childPercent;
                ++$progressWeight;
            }

            if ($status === 'running') {
                $runningChildren[] = $child;
                if ($activeChild === null) {
                    $activeChild = $child;
                    $activeIndex = $index + 1;
                }
            }
        }

        $progressPct = 0.0;
        if ($totalWorkloads > 1) {
            $workloadContributionSum = 0.0;
            foreach ($children as $child) {
                $workloadContributionSum += self::workloadProgressUnit($child);
            }
            $progressPct = round(($workloadContributionSum / $totalWorkloads) * 100, 2);
        } elseif ($progressWeight > 0) {
            $progressPct = min(100.0, round($progressWeighted / $progressWeight, 2));
        } elseif ($totalWorkloads > 0) {
            $progressPct = min(100.0, round(($completedWorkloads / $totalWorkloads) * 100, 2));
        }

        $currentItem = self::buildCurrentItemLabel($runningChildren, $activeChild);
        $dominantPhase = self::dominantPhaseForChildren($runningChildren);

        $stage = null;
        $workloadVerb = $isRestore ? 'Restoring' : 'Backing up';
        if ($totalWorkloads > 0) {
            if ($activeRunning > 1) {
                if (in_array($dominantPhase, ['graph_sync', 'prior_snapshot'], true)) {
                    $stage = sprintf(
                        'Syncing from Microsoft Graph (%d of %d workloads active)',
                        $activeRunning,
                        $totalWorkloads
                    );
                } elseif ($dominantPhase === 'kopia_upload') {
                    $stage = $isRestore
                        ? sprintf('Uploading restore data (%d of %d workloads active)', $activeRunning, $totalWorkloads)
                        : sprintf('Uploading to cloud storage (%d of %d workloads active)', $activeRunning, $totalWorkloads);
                } else {
                    $stage = sprintf('%s %d of %d workloads', $workloadVerb, $activeRunning, $totalWorkloads);
                }
            } elseif ($activeChild !== null) {
                $activeStatus = (string) ($activeChild['status'] ?? '');
                $activePhase = strtolower(trim((string) ($activeChild['phase'] ?? '')));
                if ($activeStatus === 'queued') {
                    $stage = $isRestore ? 'Waiting for restore worker' : 'Waiting for worker';
                } elseif (
                    $activeStatus === 'running'
                    && $activePhase === ''
                    && (int) ($activeChild['items_done'] ?? 0) === 0
                    && (float) ($activeChild['percent'] ?? 0) === 0.0
                ) {
                    $stage = $isRestore ? 'Waiting for restore worker' : 'Waiting for worker';
                } elseif ($activePhase === 'graph_sync' || $activePhase === 'prior_snapshot') {
                    $stage = 'Syncing from Microsoft Graph';
                } elseif ($activePhase === 'kopia_upload') {
                    $stage = $isRestore ? 'Uploading restore data' : 'Uploading to cloud storage';
                } else {
                    $stage = $workloadVerb . ' workload ' . $activeIndex . ' of ' . $totalWorkloads;
                }
            } elseif ($completedWorkloads >= $totalWorkloads) {
                $stage = 'Finishing';
            } else {
                $stage = 'Waiting for worker';
            }
            if ($queuedWorkloads > 0 && $activeRunning === 0) {
                $stage = sprintf(
                    'Waiting for worker (%d of %d workloads queued)',
                    $queuedWorkloads,
                    $totalWorkloads
                );
            }
        }

        return [
            'status' => self::aggregateStatus($children),
            'progress_pct' => $progressPct,
            'bytes_processed' => $bytesProcessed,
            'bytes_transferred' => $bytesTransferred,
            'bytes_total' => max($bytesProcessed, $bytesTransferred),
            'items_done' => $itemsDone,
            'items_skipped' => $itemsSkipped,
            'items_restored' => max(0, $itemsDone - $itemsSkipped),
            'items_total' => $itemsTotal,
            'objects_transferred' => $itemsDone,
            'objects_total' => $itemsTotal,
            'current_item' => $currentItem,
            'stage' => $stage,
            'completed_workloads' => $completedWorkloads,
            'total_workloads' => $totalWorkloads,
            'queued_workloads' => $queuedWorkloads,
            'active_running_workloads' => $activeRunning,
            'graph_429_hits_total' => $graph429Total,
            'graph_requests_total' => $graphRequestsTotal,
            'byte_stats_comparable' => $byteStatsComparable,
        ];
    }

    /**
     * @param array<string, mixed> $child
     * @return array<string, mixed>
     */
    private static function decodeChildStatsJson(array $child): array
    {
        if (self::$hasStatsJsonColumn === null) {
            self::$hasStatsJsonColumn = Capsule::schema()->hasColumn('ms365_backup_runs', 'stats_json');
        }
        if (!self::$hasStatsJsonColumn) {
            return [];
        }
        $raw = $child['stats_json'] ?? null;
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $child
     */
    private static function workloadProgressUnit(array $child): float
    {
        $status = (string) ($child['status'] ?? '');
        if (in_array($status, ['success', 'skipped', 'cancelled', 'error', 'failed'], true)) {
            return 1.0;
        }
        if ($status !== 'running') {
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
        if ($phase === 'kopia_upload' || $phase === 'upload') {
            return 0.5;
        }
        if ($phase === 'graph_sync' || $phase === 'prior_snapshot') {
            return 0.15;
        }

        return 0.05;
    }

    /**
     * @return array{speed: ?int, eta_seconds: ?int}
     */
    public static function computeSpeedAndEta(
        int $lastBytes,
        int $lastTs,
        int $currentBytes,
        int $bytesTotal,
        int $now,
    ): array {
        $speed = null;
        $etaSeconds = null;
        if ($lastTs > 0 && $now > $lastTs && $currentBytes > $lastBytes) {
            $elapsed = $now - $lastTs;
            $speed = (int) round(($currentBytes - $lastBytes) / max(1, $elapsed));
            if ($speed > 0 && $bytesTotal > $currentBytes) {
                $etaSeconds = (int) ceil(($bytesTotal - $currentBytes) / $speed);
            }
        }

        return ['speed' => $speed, 'eta_seconds' => $etaSeconds];
    }

    public static function computeItemsSpeed(
        int $lastItems,
        int $lastTs,
        int $currentItems,
        int $now,
    ): ?int {
        if ($lastTs > 0 && $now > $lastTs && $currentItems > $lastItems) {
            $elapsed = $now - $lastTs;

            return (int) round(($currentItems - $lastItems) / max(1, $elapsed));
        }

        return null;
    }

    private static function computeWindowedGraphThrottled(
        array $statsJson,
        int $current429Total,
        int $now,
    ): array {
        $prev429Total = (int) ($statsJson['ms365_graph_429_hits_total'] ?? 0);
        $throttleAt = (int) ($statsJson['ms365_graph_throttle_at'] ?? 0);
        $throttled = false;
        if ($current429Total > $prev429Total) {
            $throttleAt = $now;
            $throttled = true;
        } elseif ($throttleAt > 0 && ($now - $throttleAt) < self::GRAPH_THROTTLE_WINDOW_SECONDS) {
            $throttled = true;
        }

        return [
            'throttled' => $throttled,
            'throttle_at' => $throttleAt,
        ];
    }

    /**
     * Atomically claim the right to persist this batch's parent snapshot for the
     * current throttle window. Returns false when another heartbeat already
     * refreshed the parent within LIVE_SNAPSHOT_THROTTLE_SECONDS.
     *
     * A lock-free point read filters out the vast majority of concurrent
     * heartbeats without touching the row lock; only the few that pass race on a
     * conditional UPDATE, where exactly one wins (the rest match zero rows).
     */
    private static function claimLiveSnapshotWindow(string $batchRunId): bool
    {
        if (self::$parentHasUpdatedAt === null) {
            self::$parentHasUpdatedAt = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'updated_at');
        }
        if (self::$parentHasUpdatedAt !== true) {
            // No timestamp column to coordinate on; fall back to always updating.
            return true;
        }

        $now = time();
        $cutoff = date('Y-m-d H:i:s', $now - self::LIVE_SNAPSHOT_THROTTLE_SECONDS);

        $last = Capsule::table('s3_cloudbackup_runs')
            ->whereRaw('run_id = ' . self::uuidToDbExpr($batchRunId))
            ->value('updated_at');
        if ($last !== null && (string) $last >= $cutoff) {
            return false;
        }

        $claimed = Capsule::table('s3_cloudbackup_runs')
            ->whereRaw('run_id = ' . self::uuidToDbExpr($batchRunId))
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('updated_at')
                    ->orWhere('updated_at', '<', $cutoff);
            })
            ->update(['updated_at' => date('Y-m-d H:i:s', $now)]);

        return $claimed > 0;
    }

    public static function updateLiveSnapshot(string $batchRunId): void
    {
        if (!self::isUuid($batchRunId) || !Capsule::schema()->hasTable('s3_cloudbackup_runs')) {
            return;
        }

        // Coalesce concurrent worker heartbeats so only one persists the parent
        // aggregate per throttle window (prevents the parent-row lock convoy).
        if (!self::claimLiveSnapshotWindow($batchRunId)) {
            return;
        }

        $children = self::getChildrenForBatch($batchRunId);
        if ($children === []) {
            return;
        }

        $agg = self::computeAggregates($children);
        $now = time();

        $parent = Capsule::table('s3_cloudbackup_runs')
            ->whereRaw('run_id = ' . self::uuidToDbExpr($batchRunId))
            ->first();

        if (!$parent) {
            return;
        }

        $parentArr = (array) $parent;
        $statsJson = [];
        if (!empty($parentArr['stats_json'])) {
            $decoded = is_string($parentArr['stats_json'])
                ? json_decode($parentArr['stats_json'], true)
                : $parentArr['stats_json'];
            if (is_array($decoded)) {
                $statsJson = $decoded;
            }
        }

        $lastTs = (int) ($statsJson['ms365_last_ts'] ?? 0);
        $bytesTransferred = (int) $agg['bytes_transferred'];
        $bytesProcessed = (int) $agg['bytes_processed'];
        $bytesTotal = (int) $agg['bytes_total'];
        $lastProcessed = (int) ($statsJson['ms365_last_bytes_processed'] ?? $statsJson['ms365_last_bytes'] ?? 0);
        $speedEta = self::computeSpeedAndEta($lastProcessed, $lastTs, $bytesProcessed, $bytesTotal, $now);
        $speed = $speedEta['speed'];
        $etaSeconds = $speedEta['eta_seconds'];

        $objectsTransferred = (int) ($agg['objects_transferred'] ?? 0);
        $lastItems = (int) ($statsJson['ms365_last_items'] ?? 0);
        $itemsPerSec = self::computeItemsSpeed($lastItems, $lastTs, $objectsTransferred, $now);

        $throttleWindow = self::computeWindowedGraphThrottled(
            $statsJson,
            (int) ($agg['graph_429_hits_total'] ?? 0),
            $now
        );

        $statsJson['ms365_last_bytes'] = $bytesTransferred;
        $statsJson['ms365_last_bytes_processed'] = $bytesProcessed;
        $statsJson['ms365_last_items'] = $objectsTransferred;
        $statsJson['ms365_last_ts'] = $now;
        $statsJson['ms365_total_workloads'] = (int) $agg['total_workloads'];
        $statsJson['ms365_active_running_workloads'] = (int) ($agg['active_running_workloads'] ?? 0);
        $statsJson['ms365_queued_workloads'] = (int) ($agg['queued_workloads'] ?? 0);
        $statsJson['ms365_graph_429_hits_total'] = (int) ($agg['graph_429_hits_total'] ?? 0);
        $graphRequestsTotal = (int) ($agg['graph_requests_total'] ?? 0);
        $statsJson['ms365_graph_requests_total'] = $graphRequestsTotal;
        if ($graphRequestsTotal > 0) {
            $statsJson['ms365_graph_429_ratio'] = round(
                (int) ($agg['graph_429_hits_total'] ?? 0) / $graphRequestsTotal,
                4
            );
        }
        $statsJson['ms365_graph_throttled'] = $throttleWindow['throttled'];
        $statsJson['ms365_graph_throttle_at'] = $throttleWindow['throttle_at'];
        $statsJson['ms365_items_per_sec'] = $itemsPerSec;
        $statsJson['ms365_byte_stats_comparable'] = !empty($agg['byte_stats_comparable']);

        $progressPct = (float) ($agg['progress_pct'] ?? 0);
        $totalWorkloads = (int) ($agg['total_workloads'] ?? 0);
        $displayStatus = (string) $agg['status'];
        if ($totalWorkloads <= 1 && in_array($displayStatus, ['running', 'queued'], true)) {
            if ($progressPct > 0 && $progressPct < 1.0) {
                $progressPct = 1.0;
            }
        }

        $statusLocked = self::isParentStatusLocked($parentArr);

        $update = [
            'progress_pct' => $progressPct,
            'bytes_processed' => $agg['bytes_processed'],
            'bytes_transferred' => $bytesTransferred,
            'bytes_total' => $agg['bytes_total'],
            'objects_transferred' => $agg['objects_transferred'],
            'objects_total' => $agg['objects_total'],
            'stats_json' => json_encode($statsJson, JSON_UNESCAPED_SLASHES),
        ];

        if (Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'current_item')) {
            $update['current_item'] = $agg['current_item'];
        }
        if (Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'stage')) {
            $update['stage'] = $agg['stage'];
        }
        if (Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'speed_bytes_per_sec') && $speed !== null) {
            $update['speed_bytes_per_sec'] = $speed;
        }
        if (Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'eta_seconds') && $etaSeconds !== null) {
            $update['eta_seconds'] = $etaSeconds;
        }
        if (Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'updated_at')) {
            $update['updated_at'] = date('Y-m-d H:i:s');
        }

        if (!$statusLocked && in_array($displayStatus, ['running', 'queued'], true)) {
            $update['status'] = 'running';
        }

        Capsule::table('s3_cloudbackup_runs')
            ->whereRaw('run_id = ' . self::uuidToDbExpr($batchRunId))
            ->update($update);

        if (!$statusLocked && !in_array($displayStatus, ['running', 'queued'], true)) {
            self::syncFromChildren($batchRunId);
        }
    }

    /**
     * @param list<string> $childIds
     * @return array<string, array<string, mixed>>
     */
    private static function queueRowsByChildId(array $childIds): array
    {
        $queueByRun = [];
        if ($childIds === [] || !Capsule::schema()->hasTable('ms365_job_queue')) {
            return $queueByRun;
        }
        foreach (Capsule::table('ms365_job_queue')->whereIn('run_id', $childIds)->get() as $queueRow) {
            $queueByRun[(string) $queueRow->run_id] = (array) $queueRow;
        }

        return $queueByRun;
    }

    private static function cancelChildWithoutStart(string $childId): void
    {
        $message = Ms365BatchRetryService::CANCEL_NEVER_STARTED_MSG;
        $now = time();
        BackupRunRepository::update($childId, [
            'status' => 'cancelled',
            'phase' => 'cancelled',
            'error_message' => $message,
            'finished_at' => $now,
            'updated_at' => $now,
        ]);
        JobQueueRepository::markCancelled($childId, $message);
    }

    private static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    private static function isUuid(string $id): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id);
    }

    private static function uuidToBinary(string $uuid): string
    {
        return hex2bin(str_replace('-', '', strtolower($uuid)));
    }

    private static function uuidToDbExpr(string $uuid): string
    {
        return "UUID_TO_BIN('" . addslashes(strtolower($uuid)) . "')";
    }

    private static function binaryToUuid(string $binary): string
    {
        $hex = bin2hex($binary);
        if (strlen($hex) !== 32) {
            return $hex;
        }

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }

    public static function normalizeRunUuid(mixed $runId): string
    {
        if (!is_string($runId) || $runId === '') {
            return '';
        }
        if (strlen($runId) === 16) {
            return self::binaryToUuid($runId);
        }

        return $runId;
    }

    /** @return string batch restore run UUID */
    public static function createRestoreBatch(int $clientId, string $e3JobId): string
    {
        if (!Capsule::schema()->hasTable('s3_cloudbackup_runs')) {
            throw new \RuntimeException('Run history is not available.');
        }

        $runId = self::uuid();
        $now = date('Y-m-d H:i:s');
        $insert = [
            'run_id' => self::uuidToBinary($runId),
            'job_id' => self::uuidToBinary($e3JobId),
            'trigger_type' => 'manual',
            'status' => 'running',
            'created_at' => $now,
            'started_at' => $now,
        ];
        if (Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'engine')) {
            $insert['engine'] = 'ms365';
        }
        if (Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'run_type')) {
            $insert['run_type'] = 'restore';
        }
        if (Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'stats_json')) {
            $insert['stats_json'] = json_encode(['type' => 'ms365_restore']);
        }

        Capsule::table('s3_cloudbackup_runs')->insert($insert);

        return $runId;
    }

    /** @return list<array<string, mixed>> */
    public static function getChildrenForRestoreBatch(string $batchRunId): array
    {
        if (!self::isUuid($batchRunId) || !Capsule::schema()->hasTable('ms365_restore_runs')) {
            return [];
        }
        if (!Capsule::schema()->hasColumn('ms365_restore_runs', 'e3_batch_run_id')) {
            return [];
        }

        return Capsule::table('ms365_restore_runs')
            ->where('e3_batch_run_id', $batchRunId)
            ->orderBy('created_at')
            ->get()
            ->map(static fn ($row) => (array) $row)
            ->all();
    }

    public static function syncFromRestoreChildren(string $batchRunId): void
    {
        if (!self::isUuid($batchRunId)) {
            return;
        }
        $children = self::getChildrenForRestoreBatch($batchRunId);
        if ($children === []) {
            return;
        }
        $aggregate = self::aggregateStatus($children);
        if (in_array($aggregate, ['running', 'queued'], true)) {
            return;
        }
        self::finalize($batchRunId, $aggregate);
    }

    public static function syncForRestoreChildRun(string $restoreRunId): void
    {
        if ($restoreRunId === '' || !Capsule::schema()->hasTable('ms365_restore_runs')) {
            return;
        }
        $batchRunId = Capsule::table('ms365_restore_runs')
            ->where('id', $restoreRunId)
            ->value('e3_batch_run_id');
        if (!is_string($batchRunId) || $batchRunId === '') {
            return;
        }
        self::syncFromRestoreChildren($batchRunId);
    }

    public static function isRestoreBatch(string $batchRunId): bool
    {
        if (!self::isUuid($batchRunId) || !Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'run_type')) {
            return false;
        }
        $runType = Capsule::table('s3_cloudbackup_runs')
            ->whereRaw('run_id = ' . self::uuidToDbExpr($batchRunId))
            ->value('run_type');

        return strtolower((string) $runType) === 'restore';
    }

    /** @return list<array<string, mixed>> */
    public static function getBatchChildren(string $batchRunId): array
    {
        if (self::isRestoreBatch($batchRunId)) {
            return self::normalizeRestoreChildrenForAggregate(self::getChildrenForRestoreBatch($batchRunId));
        }

        return self::getChildrenForBatch($batchRunId);
    }

    /**
     * @param list<array<string, mixed>> $children
     * @return list<array<string, mixed>>
     */
    private static function normalizeRestoreChildrenForAggregate(array $children): array
    {
        $out = [];
        foreach ($children as $child) {
            $itemsTotal = max(1, (int) ($child['items_total'] ?? 0));
            $itemsDone = (int) ($child['items_done'] ?? 0);
            $percent = $itemsTotal > 0 ? min(100, ($itemsDone / $itemsTotal) * 100) : 0;
            if (($child['status'] ?? '') === 'success') {
                $percent = 100;
            }
            [$displayName, $upn] = self::lookupBackupUserIdentity(
                (string) ($child['target_graph_id'] ?? ''),
                (string) ($child['target_resource_id'] ?? '')
            );
            if ($displayName === '') {
                $displayName = self::resolveTargetIdentityLabel(
                    (string) ($child['target_graph_id'] ?? ''),
                    (string) ($child['target_resource_id'] ?? '')
                );
            }
            $out[] = array_merge($child, [
                'physical_key' => (string) ($child['target_resource_id'] ?? $child['target_graph_id'] ?? ''),
                'user_display_name' => $displayName,
                'user_upn' => $upn,
                'percent' => $percent,
            ]);
        }

        return $out;
    }

    public static function updateLiveSnapshotForRestore(string $batchRunId): void
    {
        if (!self::isUuid($batchRunId) || !Capsule::schema()->hasTable('s3_cloudbackup_runs')) {
            return;
        }
        $children = self::normalizeRestoreChildrenForAggregate(self::getChildrenForRestoreBatch($batchRunId));
        if ($children === []) {
            return;
        }
        $agg = self::computeAggregates($children, self::isRestoreBatch($batchRunId));
        $stage = (string) ($agg['stage'] ?? '');

        $parent = Capsule::table('s3_cloudbackup_runs')
            ->whereRaw('run_id = ' . self::uuidToDbExpr($batchRunId))
            ->first();
        if (!$parent) {
            return;
        }
        $parentArr = (array) $parent;
        $statusLocked = self::isParentStatusLocked($parentArr);

        $update = [
            'progress_pct' => $agg['progress_pct'],
            'bytes_transferred' => 0,
            'bytes_processed' => 0,
            'objects_transferred' => $agg['items_done'],
            'objects_total' => $agg['items_total'],
        ];

        if (Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'current_item')) {
            $update['current_item'] = $agg['current_item'];
        }
        if (Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'stage')) {
            $update['stage'] = $stage;
        }
        if (Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'updated_at')) {
            $update['updated_at'] = date('Y-m-d H:i:s');
        }
        if (Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'progress_json')) {
            $progressJson = [];
            if (!empty($parentArr['progress_json'])) {
                $decoded = is_string($parentArr['progress_json'])
                    ? json_decode($parentArr['progress_json'], true)
                    : $parentArr['progress_json'];
                if (is_array($decoded)) {
                    $progressJson = $decoded;
                }
            }
            if ($stage !== '') {
                $progressJson['stage'] = $stage;
            }
            if (!empty($agg['current_item'])) {
                $progressJson['current_item'] = $agg['current_item'];
            }
            $update['progress_json'] = json_encode($progressJson, JSON_UNESCAPED_SLASHES);
        }

        $displayStatus = (string) $agg['status'];
        if (!$statusLocked) {
            if (!in_array($displayStatus, ['running', 'queued'], true)) {
                $update['status'] = match ($displayStatus) {
                    'failed', 'error' => 'failed',
                    'success' => 'success',
                    'cancelled' => 'cancelled',
                    default => 'warning',
                };
                $update['finished_at'] = date('Y-m-d H:i:s');
                if (Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'error_summary')) {
                    $summary = self::collectChildErrorSummary($batchRunId);
                    if ($summary !== '') {
                        $update['error_summary'] = $summary;
                    }
                }
            } else {
                $update['status'] = 'running';
            }
        }
        Capsule::table('s3_cloudbackup_runs')
            ->whereRaw('run_id = ' . self::uuidToDbExpr($batchRunId))
            ->update($update);
    }

    /**
     * @param list<array<string, mixed>> $runningChildren
     * @param array<string, mixed>|null $primaryChild
     */
    private static function buildCurrentItemLabel(array $runningChildren, ?array $primaryChild): ?string
    {
        if ($runningChildren === []) {
            return null;
        }
        if (count($runningChildren) === 1 && $primaryChild !== null) {
            $type = (string) ($primaryChild['resource_type'] ?? 'workload');
            $name = self::childDisplayName($primaryChild);
            $phase = trim((string) ($primaryChild['phase'] ?? ''));
            $label = $name !== '' ? $type . ': ' . $name : $type;

            return $phase !== '' ? $label . ' — ' . $phase : $label;
        }

        $names = [];
        foreach ($runningChildren as $child) {
            $name = self::childDisplayName($child);
            if ($name !== '') {
                $names[] = $name;
            }
        }
        $names = array_values(array_unique($names));
        if ($names === []) {
            return sprintf('%d workloads active', count($runningChildren));
        }

        $listed = implode(', ', $names);
        $maxLen = 900;
        if (strlen($listed) > $maxLen) {
            $listed = substr($listed, 0, $maxLen - 4) . '…';
        }

        return sprintf('%d workloads active: %s', count($runningChildren), $listed);
    }

    /**
     * @param list<array<string, mixed>> $runningChildren
     */
    private static function dominantPhaseForChildren(array $runningChildren): string
    {
        if ($runningChildren === []) {
            return '';
        }
        $counts = [];
        foreach ($runningChildren as $child) {
            $phase = strtolower(trim((string) ($child['phase'] ?? '')));
            if ($phase === '') {
                continue;
            }
            $counts[$phase] = ($counts[$phase] ?? 0) + 1;
        }
        if ($counts === []) {
            return '';
        }
        arsort($counts);

        return (string) array_key_first($counts);
    }

    /** @param array<string, mixed> $child */
    private static function childDisplayName(array $child): string
    {
        $name = trim((string) ($child['user_display_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        return trim((string) ($child['physical_key'] ?? ''));
    }
}
