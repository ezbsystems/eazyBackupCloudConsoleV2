<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;

/**
 * Parent run rows in s3_cloudbackup_runs for MS365 job executions.
 */
final class Ms365BatchRunRepository
{
    /** Stale child workload threshold aligned with WorkerClaimService::reconcileZombieRuns(). */
    private const STALE_CHILD_SECONDS = 120;

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
        $cutoff = $now - self::STALE_CHILD_SECONDS;
        $cancelRequested = !empty($parentArr['cancel_requested']);

        if ($cancelRequested) {
            $cancelledBy = !empty($parentArr['finished_at']) ? 'administrator' : 'user';
            foreach ($children as $child) {
                $childId = (string) ($child['id'] ?? '');
                $status = (string) ($child['status'] ?? '');
                if ($childId === '' || !in_array($status, ['queued', 'running'], true)) {
                    continue;
                }
                BackupRunRepository::requestCancel($childId, $cancelledBy);
            }

            return;
        }

        $queueByRun = self::queueRowsByChildId(array_column($children, 'id'));

        foreach ($children as $child) {
            $childId = (string) ($child['id'] ?? '');
            if ($childId === '' || (string) ($child['status'] ?? '') !== 'running') {
                continue;
            }
            if ((int) ($child['updated_at'] ?? 0) >= $cutoff) {
                continue;
            }
            $queue = $queueByRun[$childId] ?? null;
            if (is_array($queue)
                && (string) ($queue['status'] ?? '') === 'running'
                && (int) ($queue['lease_expires_at'] ?? 0) > $now) {
                continue;
            }
            Ms365RestoreWorkerHooks::onFail($childId, 'Stale workload reconciled during batch sync');
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
        ];
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

    public static function updateLiveSnapshot(string $batchRunId): void
    {
        if (!self::isUuid($batchRunId) || !Capsule::schema()->hasTable('s3_cloudbackup_runs')) {
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

        $lastBytes = (int) ($statsJson['ms365_last_bytes'] ?? 0);
        $lastTs = (int) ($statsJson['ms365_last_ts'] ?? 0);
        $speed = null;
        $etaSeconds = null;
        $bytesTransferred = (int) $agg['bytes_transferred'];
        if ($lastTs > 0 && $now > $lastTs && $bytesTransferred >= $lastBytes) {
            $elapsed = $now - $lastTs;
            $speed = (int) round(($bytesTransferred - $lastBytes) / max(1, $elapsed));
            $bytesTotal = (int) $agg['bytes_total'];
            if ($speed > 0 && $bytesTotal > $bytesTransferred) {
                $etaSeconds = (int) ceil(($bytesTotal - $bytesTransferred) / $speed);
            }
        }

        $statsJson['ms365_last_bytes'] = $bytesTransferred;
        $statsJson['ms365_last_ts'] = $now;
        $statsJson['ms365_total_workloads'] = (int) $agg['total_workloads'];
        $statsJson['ms365_active_running_workloads'] = (int) ($agg['active_running_workloads'] ?? 0);
        $statsJson['ms365_queued_workloads'] = (int) ($agg['queued_workloads'] ?? 0);

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
