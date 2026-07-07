<?php

declare(strict_types=1);

namespace WHMCS\Module\Addon\CloudStorage\Client;

use Ms365Backup\BackupRunRepository;
use Ms365Backup\Ms365BatchRunRepository;
use Ms365Backup\Ms365LiveSpeedMetrics;
use Ms365Backup\PhysicalKeyHelper;
use Ms365Backup\ProgressLogger;
use Ms365Backup\TenantResource;
use Ms365Backup\WorkerProcess;
use WHMCS\Database\Capsule;

require_once dirname(__DIR__) . '/Ms365BackupBootstrap.php';

/**
 * Bridges MS365 batch runs into the e3 Cloud Backup live progress / log APIs.
 */
final class Ms365BatchLiveService
{
    /** Aligns with Ms365BatchRunRepository heartbeat gap — fresher progress reads as "Active". */
    private const WORKLOAD_ACTIVE_PROGRESS_SECONDS = 180;

    public static function isMs365BatchRun(array $run): bool
    {
        return strtolower((string) ($run['engine'] ?? '')) === 'ms365';
    }

    /**
     * @return array<string, mixed>
     */
    public static function aggregateProgress(string $batchRunId, int $clientId, ?array $parentRun = null): array
    {
        cloudstorage_load_ms365backup();
        self::assertBatchOwnership($batchRunId, $clientId);

        $isRestore = Ms365BatchRunRepository::isRestoreBatch($batchRunId);

        // Read-only live snapshot for UI polling. Do not call syncFromChildren here:
        // it runs reconcileBatchChildren (mutating, expensive) and is intended for
        // worker hooks/cron — not 2s progress polls on 200+ workload batches.
        if ($isRestore) {
            Ms365BatchRunRepository::updateLiveSnapshotForRestore($batchRunId);
        } else {
            Ms365BatchRunRepository::updateLiveSnapshot($batchRunId);
        }

        $parentRun = self::loadParentRun($batchRunId, $clientId);
        if ($parentRun === null) {
            throw new \RuntimeException('Run not found or access denied.');
        }

        $children = Ms365BatchRunRepository::getBatchChildren($batchRunId);
        $agg = Ms365BatchRunRepository::computeAggregates($children, $isRestore);
        $workloads = self::listWorkloadsForCustomer($batchRunId, $clientId, $children);
        $parentStats = self::decodeParentStatsJson($parentRun);
        $displayStatus = (string) ($parentRun['status'] ?? $agg['status']);
        if (in_array($displayStatus, ['running', 'starting', 'queued'], true)) {
            $displayStatus = (string) $agg['status'];
            if ($displayStatus === 'queued') {
                $displayStatus = 'running';
            }
        }

        $serverTimezone = date_default_timezone_get() ?: 'UTC';
        $startedAtEpochMs = self::epochMs($parentRun['started_at'] ?? null, $serverTimezone);
        $finishedAtEpochMs = self::epochMs($parentRun['finished_at'] ?? null, $serverTimezone);

        return [
            'status' => $displayStatus,
            'error_summary' => (string) ($parentRun['error_summary'] ?? ''),
            'worker_host' => '',
            'progress_pct' => self::resolveProgressPct($parentRun, $agg),
            'bytes_total' => $parentRun['bytes_total'] ?? $agg['bytes_total'],
            'bytes_transferred' => (int) ($parentRun['bytes_transferred'] ?? $agg['bytes_transferred']),
            'bytes_processed' => (int) ($parentRun['bytes_processed'] ?? $agg['bytes_processed']),
            'objects_total' => (int) ($parentRun['objects_total'] ?? $agg['objects_total']),
            'objects_transferred' => (int) ($parentRun['objects_transferred'] ?? $agg['objects_transferred']),
            'files_done' => $isRestore ? (int) ($agg['items_restored'] ?? $agg['items_done']) : (int) $agg['items_done'],
            'files_skipped' => $isRestore ? (int) ($agg['items_skipped'] ?? 0) : 0,
            'files_total' => (int) $agg['items_total'],
            'folders_done' => null,
            'speed_bytes_per_sec' => $parentRun['speed_bytes_per_sec'] !== null
                ? (int) $parentRun['speed_bytes_per_sec']
                : null,
            'speed_metric_kind' => (string) ($parentStats['ms365_speed_metric_kind'] ?? Ms365LiveSpeedMetrics::KIND_NONE),
            'speed_metric_label' => Ms365LiveSpeedMetrics::labelForKind(
                (string) ($parentStats['ms365_speed_metric_kind'] ?? Ms365LiveSpeedMetrics::KIND_NONE)
            ),
            'speed_updated_at' => isset($parentStats['ms365_speed_updated_at'])
                ? (int) $parentStats['ms365_speed_updated_at']
                : null,
            'items_per_sec' => isset($parentStats['ms365_items_per_sec'])
                ? (int) $parentStats['ms365_items_per_sec']
                : null,
            'graph_requests_per_sec' => isset($parentStats['ms365_graph_requests_per_sec'])
                ? (int) $parentStats['ms365_graph_requests_per_sec']
                : null,
            'dominant_phase' => (string) ($agg['dominant_phase'] ?? ''),
            'eta_seconds' => $parentRun['eta_seconds'] ?? null,
            'current_item' => CustomerFacingTextSanitizer::scrubLogMessage(
                (string) ($parentRun['current_item'] ?? $agg['current_item'] ?? '')
            ) ?: null,
            'stage' => self::resolveStageLabel($parentRun, $agg, $isRestore),
            'active_running_workloads' => (int) ($agg['active_running_workloads'] ?? 0),
            'total_workloads' => (int) ($agg['total_workloads'] ?? 0),
            'queued_workloads' => (int) ($agg['queued_workloads'] ?? 0),
            'completed_workloads' => (int) ($agg['completed_workloads'] ?? 0),
            'workloads' => $workloads,
            'graph_429_hits_total' => (int) ($parentStats['ms365_graph_429_hits_total'] ?? $agg['graph_429_hits_total'] ?? 0),
            'graph_429_ratio' => (float) ($parentStats['ms365_graph_429_ratio'] ?? 0),
            'graph_throttled' => self::isMaterialGraphThrottle(
                $parentStats,
                $agg,
                !empty($parentStats['ms365_graph_throttled'])
            ),
            'graph_requests_total' => (int) ($parentStats['ms365_graph_requests_total'] ?? $agg['graph_requests_total'] ?? 0),
            'byte_stats_comparable' => !empty($parentStats['ms365_byte_stats_comparable']),
            'started_at' => $parentRun['started_at'] ?? null,
            'finished_at' => $parentRun['finished_at'] ?? null,
            'started_at_epoch_ms' => $startedAtEpochMs,
            'finished_at_epoch_ms' => $finishedAtEpochMs,
            'log_excerpt' => '',
            'log_lines' => [],
            'formatted_log_excerpt' => null,
            'entries' => [],
            'log_excerpt_hash' => null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function aggregateEvents(string $batchRunId, int $clientId, int $sinceId, int $limit, ?\DateTimeZone $userTz = null): array
    {
        cloudstorage_load_ms365backup();
        self::assertBatchOwnership($batchRunId, $clientId);

        if (!Capsule::schema()->hasTable('ms365_backup_log_lines')) {
            return [];
        }

        $children = Ms365BatchRunRepository::getBatchChildren($batchRunId);
        $childIds = array_column($children, 'id');
        if ($childIds === []) {
            return [];
        }

        $childrenById = [];
        foreach ($children as $child) {
            $childrenById[(string) $child['id']] = $child;
        }

        $query = Capsule::table('ms365_backup_log_lines')
            ->whereIn('run_id', $childIds)
            ->orderBy('id', 'asc');
        if ($sinceId > 0) {
            $query->where('id', '>', $sinceId);
        }

        $out = [];
        foreach ($query->limit($limit)->get() as $row) {
            $line = (array) $row;
            $child = $childrenById[(string) ($line['run_id'] ?? '')] ?? [];
            $out[] = Ms365LogFormatter::toEvent($line, $child, $userTz);
        }

        return $out;
    }

    /**
     * @return array{
     *   backup_log: string,
     *   validation_log: null,
     *   has_validation: bool,
     *   structured_logs: list<array<string, mixed>>,
     *   run_summary: array<string, mixed>
     * }
     */
    public static function aggregateStructuredLogs(string $batchRunId, int $clientId, ?\DateTimeZone $userTz = null): array
    {
        cloudstorage_load_ms365backup();
        self::assertBatchOwnership($batchRunId, $clientId);

        Ms365BatchRunRepository::syncFromChildren($batchRunId);
        $parentRun = self::loadParentRun($batchRunId, $clientId);
        $children = Ms365BatchRunRepository::getBatchChildren($batchRunId);
        $agg = Ms365BatchRunRepository::computeAggregates($children);

        $structuredLogs = [];
        if (Capsule::schema()->hasTable('ms365_backup_log_lines')) {
            $childIds = array_column($children, 'id');
            if ($childIds !== []) {
                $childrenById = [];
                foreach ($children as $child) {
                    $childrenById[(string) $child['id']] = $child;
                }
                $lines = Capsule::table('ms365_backup_log_lines')
                    ->whereIn('run_id', $childIds)
                    ->orderBy('id', 'asc')
                    ->limit(5000)
                    ->get();
                foreach ($lines as $row) {
                    $line = (array) $row;
                    $child = $childrenById[(string) ($line['run_id'] ?? '')] ?? [];
                    $structuredLogs[] = Ms365LogFormatter::toStructuredLog($line, $child, $userTz);
                }
            }
        }

        $formattedBackupLog = implode("\n", array_map(static function (array $row): string {
            $ts = !empty($row['ts']) ? '[' . $row['ts'] . '] ' : '';
            $level = !empty($row['level']) ? '(' . strtoupper((string) $row['level']) . ') ' : '';

            return $ts . $level . (string) ($row['message'] ?? '');
        }, $structuredLogs));

        $bytesTransferred = (int) ($parentRun['bytes_transferred'] ?? $agg['bytes_transferred']);

        return [
            'backup_log' => $formattedBackupLog,
            'validation_log' => null,
            'has_validation' => false,
            'structured_logs' => $structuredLogs,
            'run_summary' => [
                'bytes_transferred' => $bytesTransferred,
                'bytes_processed' => (int) ($parentRun['bytes_processed'] ?? $agg['bytes_processed']),
                'is_restore' => false,
                'uploaded_formatted' => self::formatBytesHuman($bytesTransferred),
                'downloaded_formatted' => '—',
            ],
        ];
    }

    /**
     * @return array{status: string, message?: string, run_id?: string}
     */
    public static function cancelBatch(
        string $batchRunId,
        int $clientId,
        bool $forceCancel = false,
        string $cancelledBy = 'user'
    ): array
    {
        try {
            cloudstorage_load_ms365backup();
            self::assertBatchOwnership($batchRunId, $clientId);

            $parentRun = self::loadParentRun($batchRunId, $clientId);
            if ($parentRun === null) {
                return ['status' => 'fail', 'message' => 'Run not found or access denied'];
            }

            $children = Ms365BatchRunRepository::getBatchChildren($batchRunId);
            $activeCount = self::countActiveChildren($children);
            $currentStatus = (string) ($parentRun['status'] ?? '');
            $cancelableStatuses = ['queued', 'starting', 'running'];
            $terminalStatuses = ['success', 'warning', 'failed', 'cancelled', 'partial_success'];
            $cancelAlreadyRequested = !empty($parentRun['cancel_requested']);

            if ($forceCancel) {
                if ($activeCount === 0 && in_array($currentStatus, $terminalStatuses, true)) {
                    return ['status' => 'fail', 'message' => 'This backup has already finished. Refresh the page to see the final status.'];
                }
            } elseif ($activeCount === 0) {
                if ($cancelAlreadyRequested || in_array($currentStatus, $terminalStatuses, true)) {
                    return [
                        'status' => 'success',
                        'message' => 'This backup is no longer running.',
                        'run_id' => $batchRunId,
                    ];
                }
            } elseif (!in_array($currentStatus, $cancelableStatuses, true) && !$cancelAlreadyRequested) {
                $aggStatus = Ms365BatchRunRepository::aggregateStatus($children);
                if (!in_array($aggStatus, ['running', 'queued'], true)) {
                    return [
                        'status' => 'fail',
                        'message' => 'Run cannot be cancelled in current status: ' . $currentStatus,
                    ];
                }
            }

            // Flag parent first so workers see cancel_requested on their next heartbeat.
            $update = ['cancel_requested' => 1];
            if ($forceCancel) {
                $update['status'] = 'cancelled';
                $update['finished_at'] = date('Y-m-d H:i:s');
                $update['error_summary'] = 'Cancellation forced by user';
            } elseif ($activeCount > 0) {
                $update['status'] = 'running';
            }

            Capsule::table('s3_cloudbackup_runs')
                ->whereRaw('run_id = UUID_TO_BIN(?)', [strtolower($batchRunId)])
                ->update($update);

            $terminatePaths = [];
            foreach ($children as $child) {
                if ((string) ($child['status'] ?? '') !== 'running') {
                    continue;
                }
                if ((string) ($child['engine_mode'] ?? '') === 'kopia') {
                    continue;
                }
                $backupPath = (string) ($child['backup_path'] ?? '');
                if ($backupPath !== '' && is_dir($backupPath)) {
                    $terminatePaths[] = $backupPath;
                }
            }

            $cancelledCount = 0;
            if (Ms365BatchRunRepository::isRestoreBatch($batchRunId)) {
                foreach ($children as $child) {
                    $childId = (string) ($child['id'] ?? '');
                    if ($childId === '' || !BackupRunRepository::isCancellable($childId)) {
                        continue;
                    }
                    if (BackupRunRepository::requestCancel($childId, $cancelledBy)) {
                        ++$cancelledCount;
                    }
                }
                // Restore batches are small; sync inline so the parent finalizes promptly.
                Ms365BatchRunRepository::syncFromRestoreChildren($batchRunId);
            } else {
                $cancelledCount = BackupRunRepository::bulkCancelBatchChildren($batchRunId, $cancelledBy);
                // Parent finalize runs via ms365_worker_fleet cron (reconcileActiveBatches).
            }

            foreach ($terminatePaths as $backupPath) {
                try {
                    WorkerProcess::terminate($backupPath);
                } catch (\Throwable $e) {
                    // Cancellation is recorded even if the worker cannot be signalled.
                }
            }

            if ($cancelledCount > 0) {
                $firstChildId = '';
                foreach ($children as $child) {
                    $childId = (string) ($child['id'] ?? '');
                    if ($childId !== '') {
                        $firstChildId = $childId;
                        break;
                    }
                }
                if ($firstChildId !== '') {
                    $logger = new ProgressLogger($firstChildId);
                    $cancelByLabel = $cancelledBy === 'administrator' ? 'administrator' : 'user';
                    $logger->info('Cancellation requested by ' . $cancelByLabel, [
                        'batch_run_id' => $batchRunId,
                        'workloads_cancelled' => $cancelledCount,
                    ]);
                }
            }

            return [
                'status' => 'success',
                'message' => $cancelledCount > 0
                    ? 'Cancellation requested for ' . $cancelledCount . ' workload(s).'
                    : ($activeCount > 0
                        ? 'Cancellation requested.'
                        : 'This backup is no longer running.'),
                'run_id' => $batchRunId,
                'workloads_cancelled' => $cancelledCount,
            ];
        } catch (\Throwable $e) {
            logModuleCall('cloudstorage', 'ms365_cancel_batch_error', [
                'batch_run_id' => $batchRunId,
                'client_id' => $clientId,
            ], $e->getMessage());

            return ['status' => 'fail', 'message' => 'Failed to cancel run. Please try again later.'];
        }
    }

    /** @param list<array<string, mixed>> $children */
    private static function countActiveChildren(array $children): int
    {
        $count = 0;
        foreach ($children as $child) {
            if (in_array((string) ($child['status'] ?? ''), ['queued', 'running'], true)) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * Customer-safe workload rows for the e3 live progress panel.
     *
     * @param list<array<string, mixed>>|null $children
     * @return list<array<string, mixed>>
     */
    public static function listWorkloadsForCustomer(string $batchRunId, int $clientId, ?array $children = null): array
    {
        cloudstorage_load_ms365backup();
        self::assertBatchOwnership($batchRunId, $clientId);

        if ($children === null) {
            $children = Ms365BatchRunRepository::getBatchChildren($batchRunId);
        }

        $queueByRun = [];
        if ($children !== [] && Capsule::schema()->hasTable('ms365_job_queue')) {
            $childIds = array_column($children, 'id');
            if ($childIds !== []) {
                foreach (Capsule::table('ms365_job_queue')->whereIn('run_id', $childIds)->get() as $q) {
                    $queueByRun[(string) $q->run_id] = (array) $q;
                }
            }
        }

        $groups = [];
        foreach ($children as $child) {
            $groupKey = self::workloadGroupKey($child);
            $groups[$groupKey][] = $child;
        }

        $rows = [];
        foreach ($groups as $groupChildren) {
            $rows[] = self::formatCustomerWorkloadGroupRow($groupChildren, $queueByRun);
        }

        usort($rows, [self::class, 'sortCustomerWorkloadRows']);

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $groupChildren
     * @param array<string, array<string, mixed>> $queueByRun
     * @return array<string, mixed>
     */
    private static function formatCustomerWorkloadGroupRow(array $groupChildren, array $queueByRun): array
    {
        $primary = self::pickPrimaryChild($groupChildren);
        $row = self::formatCustomerWorkloadRow(
            $primary,
            $queueByRun[(string) ($primary['id'] ?? '')] ?? []
        );

        $mergedStatus = self::mergeGroupStatus($groupChildren);
        $row['status'] = $mergedStatus;

        [$itemsDone, $itemsTotal, $percent] = self::mergeGroupProgress($groupChildren, $mergedStatus);
        $row['items_done'] = $itemsDone;
        $row['items_total'] = $itemsTotal;
        $row['percent'] = round($percent, 2);
        $row['progress_label'] = self::formatProgressLabel($itemsDone, $itemsTotal, $percent, $mergedStatus);

        $phaseChild = self::pickPhaseChild($groupChildren);
        $phase = (string) ($phaseChild['phase'] ?? '');
        $row['phase'] = $phase;
        $row['phase_label'] = self::formatPhaseLabel($phase);

        $events = self::collectWorkloadEvents($groupChildren, $queueByRun);
        $row['events'] = $events;
        $row['error'] = $events !== [] ? (string) ($events[0]['message'] ?? '') : '';
        $row['notes'] = self::mergeGroupSkippedNotes($groupChildren);
        foreach (self::mergeGroupFreshness($groupChildren, $queueByRun) as $key => $value) {
            $row[$key] = $value;
        }

        return $row;
    }

    /** @param array<string, mixed> $child */
    private static function workloadGroupKey(array $child): string
    {
        $resourceType = strtolower((string) ($child['resource_type'] ?? 'workload'));
        $logicalKey = self::workloadLogicalKey($child);

        return $resourceType . "\0" . $logicalKey;
    }

    /** @param array<string, mixed> $child */
    private static function workloadLogicalKey(array $child): string
    {
        $scope = self::decodeChildScopeJson($child);
        $siteId = trim((string) ($scope['_site_id'] ?? ''));
        if ($siteId !== '') {
            return 'site:' . strtolower($siteId);
        }

        $physicalKey = (string) ($child['physical_key'] ?? '');
        $parentKey = PhysicalKeyHelper::aggregateParentKey($physicalKey, $child);
        if ($parentKey !== '') {
            return strtolower($parentKey);
        }

        $graphId = trim((string) ($child['target_graph_id'] ?? $child['graph_id'] ?? ''));
        if ($graphId !== '') {
            return strtolower($graphId);
        }

        return strtolower(trim((string) ($child['user_display_name'] ?? '')));
    }

    /** @param array<string, mixed> $child */
    private static function decodeChildScopeJson(array $child): array
    {
        $raw = $child['scope_json'] ?? null;
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
     * @param list<array<string, mixed>> $children
     * @return array<string, mixed>
     */
    private static function pickPrimaryChild(array $children): array
    {
        $sorted = $children;
        usort($sorted, static function (array $a, array $b): int {
            $rankCmp = self::childStatusRank((string) ($a['status'] ?? ''))
                <=> self::childStatusRank((string) ($b['status'] ?? ''));
            if ($rankCmp !== 0) {
                return $rankCmp;
            }

            return self::childActivityEpoch($b) <=> self::childActivityEpoch($a);
        });

        return $sorted[0] ?? $children[0];
    }

    /**
     * @param list<array<string, mixed>> $children
     * @return array<string, mixed>
     */
    private static function pickPhaseChild(array $children): array
    {
        foreach (['running', 'starting', 'queued'] as $activeStatus) {
            foreach ($children as $child) {
                if (strtolower((string) ($child['status'] ?? '')) === $activeStatus) {
                    return $child;
                }
            }
        }

        return self::pickPrimaryChild($children);
    }

    /**
     * @param list<array<string, mixed>> $children
     */
    private static function mergeGroupStatus(array $children): string
    {
        $bestRank = PHP_INT_MAX;
        $bestStatus = '';
        foreach ($children as $child) {
            $status = strtolower((string) ($child['status'] ?? ''));
            $rank = self::childStatusRank($status);
            if ($rank < $bestRank) {
                $bestRank = $rank;
                $bestStatus = $status;
            }
        }

        return $bestStatus !== '' ? $bestStatus : 'unknown';
    }

    /**
     * @param list<array<string, mixed>> $children
     * @return array{0: int, 1: int, 2: float}
     */
    private static function mergeGroupProgress(array $children, string $mergedStatus): array
    {
        $itemsDone = 0;
        $itemsTotal = 0;
        foreach ($children as $child) {
            $itemsDone += max(0, (int) ($child['items_done'] ?? 0));
            $itemsTotal += max(0, (int) ($child['items_total'] ?? 0));
        }

        $percent = 0.0;
        if ($itemsTotal > 0) {
            $percent = min(100.0, ($itemsDone / $itemsTotal) * 100);
        } elseif (count($children) === 1) {
            $only = $children[0];
            $percent = isset($only['percent']) ? (float) $only['percent'] : 0.0;
        } else {
            $parts = [];
            foreach ($children as $child) {
                if (isset($child['percent'])) {
                    $parts[] = (float) $child['percent'];
                }
            }
            if ($parts !== []) {
                $percent = array_sum($parts) / count($parts);
            }
        }

        if ($mergedStatus === 'success') {
            $percent = 100.0;
        }

        return [$itemsDone, $itemsTotal, $percent];
    }

    /**
     * @param list<array<string, mixed>> $children
     * @param array<string, array<string, mixed>> $queueByRun
     * @return list<array{ts: string, level: string, message: string, status: string}>
     */
    private static function collectWorkloadEvents(array $children, array $queueByRun): array
    {
        $events = [];
        $seen = [];
        $sorted = $children;
        usort($sorted, static fn (array $a, array $b): int => self::childActivityEpoch($b) <=> self::childActivityEpoch($a));

        foreach ($sorted as $child) {
            $runId = (string) ($child['id'] ?? '');
            $message = self::formatCustomerWorkloadError($child, $queueByRun[$runId] ?? []);
            if ($message === '') {
                continue;
            }

            $dedupeKey = strtolower($message);
            if (isset($seen[$dedupeKey])) {
                continue;
            }
            $seen[$dedupeKey] = true;

            $status = strtolower((string) ($child['status'] ?? ''));
            $events[] = [
                'ts' => self::formatChildTimestamp($child),
                'level' => in_array($status, ['failed', 'error'], true) ? 'error' : 'warning',
                'message' => $message,
                'status' => $status,
            ];
        }

        return $events;
    }

    private static function childStatusRank(string $status): int
    {
        return match (strtolower($status)) {
            'running' => 0,
            'starting' => 1,
            'queued' => 2,
            'warning', 'partial_success' => 3,
            'failed', 'error' => 4,
            'success' => 5,
            'cancelled' => 6,
            default => 7,
        };
    }

    /** @param array<string, mixed> $child */
    private static function childActivityEpoch(array $child): int
    {
        foreach (['updated_at', 'finished_at', 'started_at', 'created_at'] as $field) {
            $value = $child[$field] ?? null;
            if (is_numeric($value) && (int) $value > 0) {
                return (int) $value;
            }
        }

        return 0;
    }

    /** @param array<string, mixed> $child */
    private static function formatChildTimestamp(array $child): string
    {
        $epoch = self::childActivityEpoch($child);
        if ($epoch <= 0) {
            return '';
        }

        $timezone = date_default_timezone_get() ?: 'UTC';
        try {
            $dt = new \DateTime('@' . $epoch);
            $dt->setTimezone(new \DateTimeZone($timezone));

            return $dt->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * @param array<string, mixed> $child
     * @param array<string, mixed> $queue
     * @return array<string, mixed>
     */
    private static function formatCustomerWorkloadRow(array $child, array $queue): array
    {
        $resourceType = (string) ($child['resource_type'] ?? 'workload');
        $typeLabel = TenantResource::badgeLabel($resourceType);
        $name = trim((string) ($child['user_display_name'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($child['physical_key'] ?? $child['target_graph_id'] ?? ''));
        }

        $status = strtolower((string) ($child['status'] ?? ''));
        $phase = (string) ($child['phase'] ?? '');
        $itemsTotal = max(0, (int) ($child['items_total'] ?? 0));
        $itemsDone = max(0, (int) ($child['items_done'] ?? 0));
        $percent = isset($child['percent']) ? (float) $child['percent'] : null;
        if ($percent === null) {
            $percent = $itemsTotal > 0 ? min(100.0, ($itemsDone / $itemsTotal) * 100) : 0.0;
        }
        if ($status === 'success') {
            $percent = 100.0;
        }

        $row = [
            'workload_type' => $typeLabel,
            'workload_name' => $name,
            'resource_type' => $resourceType,
            'status' => $status,
            'phase' => $phase,
            'phase_label' => self::formatPhaseLabel($phase),
            'error' => self::formatCustomerWorkloadError($child, $queue),
            'notes' => self::formatCustomerWorkloadSkippedNotes($child),
            'items_done' => $itemsDone,
            'items_total' => $itemsTotal,
            'percent' => round($percent, 2),
            'progress_label' => self::formatProgressLabel($itemsDone, $itemsTotal, $percent, $status),
        ];
        foreach (self::computeWorkloadFreshness($child, $queue) as $key => $value) {
            $row[$key] = $value;
        }

        return $row;
    }

    /** @param array<string, mixed> $a @param array<string, mixed> $b */
    private static function sortCustomerWorkloadRows(array $a, array $b): int
    {
        $rank = static function (string $status): int {
            return match ($status) {
                'running' => 0,
                'starting' => 1,
                'queued' => 2,
                'warning', 'partial_success' => 3,
                'failed', 'error' => 4,
                'success' => 5,
                'cancelled' => 6,
                default => 7,
            };
        };

        $statusCmp = $rank((string) ($a['status'] ?? '')) <=> $rank((string) ($b['status'] ?? ''));
        if ($statusCmp !== 0) {
            return $statusCmp;
        }

        $nameA = strtolower((string) (($a['workload_type'] ?? '') . ' ' . ($a['workload_name'] ?? '')));
        $nameB = strtolower((string) (($b['workload_type'] ?? '') . ' ' . ($b['workload_name'] ?? '')));

        return $nameA <=> $nameB;
    }

    /** @param array<string, mixed> $child @param array<string, mixed> $queue */
    private static function formatCustomerWorkloadError(array $child, array $queue): string
    {
        $status = strtolower((string) ($child['status'] ?? ''));
        if (in_array($status, ['success', 'complete', 'skipped', 'cancelled'], true)) {
            return '';
        }

        $parts = [];
        $runError = trim((string) ($child['error_message'] ?? ''));
        $queueError = trim((string) ($queue['error_message'] ?? $queue['last_error'] ?? ''));
        if ($runError !== '') {
            $parts[] = self::softenInfrastructureQueueMessage($runError);
        }
        if ($queueError !== '' && $queueError !== $runError) {
            $softened = self::softenInfrastructureQueueMessage($queueError);
            if ($softened !== '') {
                $parts[] = $softened === 'Recovering this workload'
                    ? $softened
                    : 'Queue: ' . $softened;
            }
        }

        return implode(' · ', array_values(array_filter($parts, static fn (string $part): bool => $part !== '')));
    }

    private static function softenInfrastructureQueueMessage(string $message): string
    {
        $normalized = trim($message);
        if ($normalized === '') {
            return '';
        }

        $recoveringNeedles = [
            'stale progress (worker busy)',
            'stale progress reconciled',
            'stale workload reconciled during batch sync',
            'orphaned claim released',
            'worker idle',
            'worker drain hand-off',
            'worker released claim',
            'near-complete recovery',
            'lease expired',
            'run re-queued',
        ];
        $lower = strtolower($normalized);
        foreach ($recoveringNeedles as $needle) {
            if ($lower === $needle || str_contains($lower, $needle)) {
                return 'Recovering this workload';
            }
        }

        return CustomerFacingTextSanitizer::scrubLogMessage($normalized);
    }

    /**
     * @param array<string, mixed> $child
     * @param array<string, mixed> $queue
     * @return array{last_progress_age_seconds: ?int, stalled: bool}
     */
    private static function computeWorkloadFreshness(array $child, array $queue): array
    {
        $status = strtolower((string) ($child['status'] ?? ''));
        if (!in_array($status, ['running', 'starting'], true)) {
            return ['last_progress_age_seconds' => null, 'stalled' => false];
        }

        $now = time();
        $progressAt = Ms365BatchRunRepository::progressFreshnessAt($child);
        if ($progressAt <= 0) {
            return ['last_progress_age_seconds' => null, 'stalled' => false];
        }

        $ageSeconds = max(0, $now - $progressAt);
        $queuePayload = $queue !== [] ? $queue : null;
        $stalled = $ageSeconds >= self::WORKLOAD_ACTIVE_PROGRESS_SECONDS;

        return [
            'last_progress_age_seconds' => $ageSeconds,
            'stalled' => $stalled,
        ];
    }

    /**
     * @param list<array<string, mixed>> $groupChildren
     * @param array<string, array<string, mixed>> $queueByRun
     * @return array{last_progress_age_seconds: ?int, stalled: bool}
     */
    private static function mergeGroupFreshness(array $groupChildren, array $queueByRun): array
    {
        $worstAge = null;
        $stalled = false;
        foreach ($groupChildren as $child) {
            $status = strtolower((string) ($child['status'] ?? ''));
            if (!in_array($status, ['running', 'starting'], true)) {
                continue;
            }
            $runId = (string) ($child['id'] ?? '');
            $freshness = self::computeWorkloadFreshness($child, $queueByRun[$runId] ?? []);
            $age = $freshness['last_progress_age_seconds'];
            if ($age !== null && ($worstAge === null || $age > $worstAge)) {
                $worstAge = $age;
            }
            if ($freshness['stalled']) {
                $stalled = true;
            }
        }

        return [
            'last_progress_age_seconds' => $worstAge,
            'stalled' => $stalled,
        ];
    }

    /**
     * Friendly notes for sub-workloads skipped without failing the parent run.
     *
     * @param array<string, mixed> $child
     * @return list<string>
     */
    private static function formatCustomerWorkloadSkippedNotes(array $child): array
    {
        $notes = [];
        $childStats = self::decodeChildStatsJson($child);
        $workloads = is_array($childStats['workloads'] ?? null) ? $childStats['workloads'] : [];
        foreach ($workloads as $workloadName => $data) {
            if (!is_array($data)) {
                continue;
            }
            $reason = trim((string) ($data['skipped'] ?? ''));
            if ($reason === '') {
                continue;
            }
            $note = self::formatSkippedWorkloadNote((string) $workloadName, $reason);
            if ($note !== '') {
                $notes[] = $note;
            }
        }

        return $notes;
    }

    /**
     * @param list<array<string, mixed>> $groupChildren
     * @return list<string>
     */
    private static function mergeGroupSkippedNotes(array $groupChildren): array
    {
        $notes = [];
        $seen = [];
        foreach ($groupChildren as $child) {
            foreach (self::formatCustomerWorkloadSkippedNotes($child) as $note) {
                $key = strtolower($note);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $notes[] = $note;
            }
        }

        return $notes;
    }

    private static function formatSkippedWorkloadNote(string $workloadName, string $reason): string
    {
        $workloadLabel = match (strtolower($workloadName)) {
            'mail' => 'Mail',
            'contacts' => 'Contacts',
            'tasks' => 'Tasks',
            'calendar' => 'Calendar',
            'sharepoint' => 'SharePoint',
            default => ucwords(str_replace('_', ' ', $workloadName)),
        };

        return match ($reason) {
            'mailbox_not_enabled' => $workloadLabel . ' not available for this mailbox',
            'access_denied' => 'No access to this site',
            default => $workloadLabel . ' skipped',
        };
    }

    /** @param array<string, mixed> $child */
    private static function decodeChildStatsJson(array $child): array
    {
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

    private static function formatPhaseLabel(string $phase): string
    {
        $normalized = strtolower(trim($phase));
        if ($normalized === '') {
            return '—';
        }

        $labels = [
            'graph_sync' => 'Graph sync',
            'prior_snapshot' => 'Prior snapshot',
            'kopia_upload' => 'Upload',
            'upload' => 'Upload',
            'snapshot' => 'Snapshot',
            'complete' => 'Complete',
            'cancelled' => 'Cancelled',
        ];
        if (isset($labels[$normalized])) {
            return $labels[$normalized];
        }

        return ucwords(str_replace('_', ' ', $normalized));
    }

    private static function formatProgressLabel(int $itemsDone, int $itemsTotal, float $percent, string $status): string
    {
        if ($status === 'success') {
            return 'Complete';
        }
        if ($itemsTotal > 0) {
            return $itemsDone . '/' . $itemsTotal . ' items';
        }
        if ($percent > 0) {
            return number_format($percent, 1) . '%';
        }

        return '—';
    }

    /**
     * Apply aggregated progress fields to a parent run array for Smarty initial render.
     *
     * @param array<string, mixed> $run
     * @return array<string, mixed>
     */
    public static function enrichRunForDisplay(array $run, int $clientId): array
    {
        if (!self::isMs365BatchRun($run)) {
            return $run;
        }
        $batchRunId = (string) ($run['run_id'] ?? '');
        if ($batchRunId === '') {
            return $run;
        }

        try {
            $progress = self::aggregateProgress($batchRunId, $clientId, $run);
            foreach (['progress_pct', 'bytes_processed', 'bytes_transferred', 'bytes_total', 'objects_total', 'objects_transferred', 'speed_bytes_per_sec', 'speed_metric_kind', 'speed_metric_label', 'speed_updated_at', 'items_per_sec', 'graph_requests_per_sec', 'dominant_phase', 'eta_seconds', 'current_item', 'stage', 'status', 'total_workloads', 'completed_workloads', 'active_running_workloads', 'queued_workloads', 'graph_requests_total', 'byte_stats_comparable'] as $key) {
                if (array_key_exists($key, $progress) && $progress[$key] !== null) {
                    $run[$key] = $progress[$key];
                }
            }
        } catch (\Throwable $e) {
            // Best-effort initial render.
        }

        return $run;
    }

    private static function assertBatchOwnership(string $batchRunId, int $clientId): void
    {
        if (self::loadParentRun($batchRunId, $clientId) === null) {
            throw new \RuntimeException('Run not found or access denied.');
        }
    }

    /** @return array<string, mixed>|null */
    private static function loadParentRun(string $batchRunId, int $clientId): ?array
    {
        $hasJobIdPk = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'job_id');
        $query = Capsule::table('s3_cloudbackup_runs');
        if ($hasJobIdPk) {
            $query->join('s3_cloudbackup_jobs', 's3_cloudbackup_runs.job_id', '=', 's3_cloudbackup_jobs.job_id');
        } else {
            $query->join('s3_cloudbackup_jobs', 's3_cloudbackup_runs.job_id', '=', 's3_cloudbackup_jobs.id');
        }

        $row = $query
            ->where('s3_cloudbackup_jobs.client_id', $clientId)
            ->whereRaw('s3_cloudbackup_runs.run_id = UUID_TO_BIN(?)', [strtolower($batchRunId)])
            ->first(['s3_cloudbackup_runs.*']);

        if (!$row) {
            return null;
        }

        $arr = (array) $row;
        if (isset($arr['run_id']) && is_string($arr['run_id']) && strlen($arr['run_id']) === 16) {
            $arr['run_id'] = self::binaryToUuid($arr['run_id']);
        }

        return $arr;
    }

    private static function epochMs(mixed $datetime, string $timezone): ?int
    {
        if (empty($datetime)) {
            return null;
        }
        try {
            $dt = new \DateTime((string) $datetime, new \DateTimeZone($timezone));

            return (int) ($dt->getTimestamp() * 1000);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function formatBytesHuman(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        if ($bytes === 0) {
            return '0 B';
        }
        $pow = (int) floor(log($bytes) / log(1024));
        $pow = min($pow, count($units) - 1);
        $value = $bytes / pow(1024, $pow);

        return round($value, $precision) . ' ' . $units[$pow];
    }

    /** @param array<string, mixed> $parentRun
     * @return array<string, mixed>
     */
    private static function decodeParentStatsJson(array $parentRun): array
    {
        if (empty($parentRun['stats_json'])) {
            return [];
        }
        $decoded = is_string($parentRun['stats_json'])
            ? json_decode($parentRun['stats_json'], true)
            : $parentRun['stats_json'];

        return is_array($decoded) ? $decoded : [];
    }

    private static function resolveProgressPct(array $parentRun, array $agg): mixed
    {
        $parentStatus = strtolower((string) ($parentRun['status'] ?? ''));
        if (in_array($parentStatus, ['success', 'failed', 'cancelled', 'warning', 'partial_success'], true)) {
            if ($parentStatus === 'success') {
                return 100;
            }

            return $parentRun['progress_pct'] ?? ($agg['progress_pct'] ?? null);
        }

        $aggPct = $agg['progress_pct'] ?? null;
        if ($aggPct !== null && (float) $aggPct > 0) {
            return $aggPct;
        }

        return $parentRun['progress_pct'] ?? $aggPct;
    }

    /** @param array<string, mixed> $parentRun */
    /** @param array<string, mixed> $agg */
    private static function resolveStageLabel(array $parentRun, array $agg, bool $isRestore): ?string
    {
        $stage = trim((string) ($parentRun['stage'] ?? ''));
        if ($stage === '' && !empty($parentRun['progress_json'])) {
            $decoded = is_string($parentRun['progress_json'])
                ? json_decode($parentRun['progress_json'], true)
                : $parentRun['progress_json'];
            if (is_array($decoded)) {
                $stage = trim((string) ($decoded['stage'] ?? ''));
            }
        }
        if ($stage === '') {
            $stage = trim((string) ($agg['stage'] ?? ''));
        }
        if ($isRestore) {
            $stage = str_replace('Backing up', 'Restoring', $stage);
        }
        $stage = CustomerFacingTextSanitizer::scrubLogMessage($stage);

        return $stage !== '' ? $stage : null;
    }

    /**
     * Show throttle UI only when pacing is material (active window or high 429 ratio).
     *
     * @param array<string, mixed> $parentStats
     * @param array<string, mixed> $agg
     */
    private static function isMaterialGraphThrottle(array $parentStats, array $agg, bool $windowThrottled): bool
    {
        if ($windowThrottled) {
            return true;
        }
        $ratio = (float) ($parentStats['ms365_graph_429_ratio'] ?? 0);
        if ($ratio <= 0) {
            $requests = (int) ($parentStats['ms365_graph_requests_total'] ?? $agg['graph_requests_total'] ?? 0);
            $hits = (int) ($parentStats['ms365_graph_429_hits_total'] ?? $agg['graph_429_hits_total'] ?? 0);
            if ($requests > 0) {
                $ratio = $hits / $requests;
            }
        }

        return $ratio >= 0.05;
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
}
