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

        self::finalize($batchRunId, $aggregate);
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
            'success' => 'success',
            'cancelled' => 'cancelled',
            'running', 'queued' => 'running',
            default => 'warning',
        };

        $update = [
            'status' => $status,
            'finished_at' => date('Y-m-d H:i:s'),
        ];

        // Propagate child error messages up to the parent so the live UI and run
        // history surface a real reason instead of an empty error_summary.
        if (in_array($status, ['failed', 'warning'], true)
            && Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'error_summary')) {
            $summary = self::collectChildErrorSummary($batchRunId);
            if ($summary !== '') {
                $update['error_summary'] = $summary;
            }
        }

        Capsule::table('s3_cloudbackup_runs')
            ->whereRaw('run_id = ' . self::uuidToDbExpr($batchRunId))
            ->update($update);

        if ($status === 'success' && Capsule::schema()->hasTable('ms365_backup_runs')) {
            $tenantRecordId = (int) Capsule::table('ms365_backup_runs')
                ->where('e3_batch_run_id', $batchRunId)
                ->value('tenant_record_id');
            if ($tenantRecordId > 0) {
                $record = TenantRecordRepository::getById($tenantRecordId);
                if ($record !== null) {
                    try {
                        Ms365KopiaMaintenanceService::scheduleForTenantIfDue($record);
                    } catch (\Throwable $e) {
                        logActivity('MS365 batch maintenance enqueue failed: ' . $e->getMessage());
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
        // Strip a "user:" / "group:" style prefix to recover the bare graph id.
        $bareId = $resourceId;
        if ($bareId !== '' && str_contains($bareId, ':')) {
            $bareId = substr($bareId, strrpos($bareId, ':') + 1);
        }
        $lookupId = $graphId !== '' ? $graphId : $bareId;
        if ($lookupId === '') {
            return '';
        }

        $name = '';
        $upn = '';
        if (Capsule::schema()->hasTable('ms365_backup_runs')) {
            $row = Capsule::table('ms365_backup_runs')
                ->where('graph_id', $lookupId)
                ->orderByDesc('created_at')
                ->first(['user_display_name', 'user_upn']);
            if ($row === null && $lookupId !== '') {
                $row = Capsule::table('ms365_backup_runs')
                    ->where('resource_id', 'like', '%' . $lookupId . '%')
                    ->orderByDesc('created_at')
                    ->first(['user_display_name', 'user_upn']);
            }
            if ($row !== null) {
                $name = trim((string) ($row->user_display_name ?? ''));
                $upn = trim((string) ($row->user_upn ?? ''));
            }
        }

        if ($name !== '' && $upn !== '') {
            return $name . ' (' . $upn . ')';
        }
        if ($upn !== '') {
            return $upn;
        }
        if ($name !== '') {
            return $name;
        }

        // Last resort: a trimmed id so we surface *something* without leaking a
        // bucket number. Keep it short — graph ids are GUID-length.
        return $lookupId;
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

    /**
     * @param list<array<string, mixed>> $children
     * @return array<string, mixed>
     */
    public static function computeAggregates(array $children, bool $isRestore = false): array
    {
        $bytesProcessed = 0;
        $bytesTransferred = 0;
        $itemsDone = 0;
        $itemsTotal = 0;
        $progressWeighted = 0.0;
        $progressWeight = 0;
        $completedWorkloads = 0;
        $totalWorkloads = count($children);
        $activeChild = null;
        $activeIndex = 0;

        foreach ($children as $index => $child) {
            $status = (string) ($child['status'] ?? '');
            $childItemsTotal = max(0, (int) ($child['items_total'] ?? 0));
            $childItemsDone = max(0, (int) ($child['items_done'] ?? 0));
            $childPercent = (float) ($child['percent'] ?? 0);

            $bytesProcessed += (int) ($child['bytes_hashed'] ?? 0);
            $bytesTransferred += (int) ($child['bytes_uploaded'] ?? 0);
            $itemsDone += $childItemsDone;
            $itemsTotal += $childItemsTotal;

            if (in_array($status, ['success', 'skipped', 'cancelled'], true)) {
                ++$completedWorkloads;
            }

            if ($childItemsTotal > 0) {
                $progressWeighted += $childPercent * $childItemsTotal;
                $progressWeight += $childItemsTotal;
            } elseif ($childPercent > 0) {
                $progressWeighted += $childPercent;
                ++$progressWeight;
            }

            if (in_array($status, ['queued', 'running'], true) && $activeChild === null) {
                $activeChild = $child;
                $activeIndex = $index + 1;
            }
        }

        $progressPct = 0.0;
        if ($progressWeight > 0) {
            $progressPct = min(100.0, round($progressWeighted / $progressWeight, 2));
        } elseif ($totalWorkloads > 0) {
            $progressPct = min(100.0, round(($completedWorkloads / $totalWorkloads) * 100, 2));
        }

        $currentItem = null;
        if ($activeChild !== null) {
            $type = (string) ($activeChild['resource_type'] ?? 'workload');
            $name = trim((string) ($activeChild['user_display_name'] ?? ''));
            if ($name === '') {
                $name = trim((string) ($activeChild['physical_key'] ?? ''));
            }
            $phase = trim((string) ($activeChild['phase'] ?? ''));
            $label = $name !== '' ? $type . ': ' . $name : $type;
            $currentItem = $phase !== '' ? $label . ' — ' . $phase : $label;
        }

        $stage = null;
        $workloadVerb = $isRestore ? 'Restoring' : 'Backing up';
        if ($totalWorkloads > 0) {
            if ($activeChild !== null) {
                $activeStatus = (string) ($activeChild['status'] ?? '');
                if ($activeStatus === 'queued') {
                    $stage = $isRestore ? 'Waiting for restore worker' : 'Queued';
                } else {
                    $stage = $workloadVerb . ' workload ' . $activeIndex . ' of ' . $totalWorkloads;
                }
            } elseif ($completedWorkloads >= $totalWorkloads) {
                $stage = 'Finishing';
            } else {
                $stage = 'Queued';
            }
        }

        return [
            'status' => self::aggregateStatus($children),
            'progress_pct' => $progressPct,
            'bytes_processed' => $bytesProcessed,
            'bytes_transferred' => $bytesTransferred,
            'bytes_total' => max($bytesProcessed, $bytesTransferred),
            'items_done' => $itemsDone,
            'items_total' => $itemsTotal,
            'objects_transferred' => $itemsDone,
            'objects_total' => $itemsTotal,
            'current_item' => $currentItem,
            'stage' => $stage,
            'completed_workloads' => $completedWorkloads,
            'total_workloads' => $totalWorkloads,
        ];
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

        $update = [
            'progress_pct' => $agg['progress_pct'],
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

        $displayStatus = (string) $agg['status'];
        if (in_array($displayStatus, ['running', 'queued'], true)) {
            $update['status'] = 'running';
        }

        Capsule::table('s3_cloudbackup_runs')
            ->whereRaw('run_id = ' . self::uuidToDbExpr($batchRunId))
            ->update($update);

        if (!in_array($displayStatus, ['running', 'queued'], true)) {
            self::syncFromChildren($batchRunId);
        }
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
            $out[] = array_merge($child, [
                'physical_key' => (string) ($child['target_resource_id'] ?? $child['target_graph_id'] ?? ''),
                'user_display_name' => (string) ($child['target_graph_id'] ?? ''),
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
        if (Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'progress_json')) {
            $parent = Capsule::table('s3_cloudbackup_runs')
                ->whereRaw('run_id = ' . self::uuidToDbExpr($batchRunId))
                ->first();
            $progressJson = [];
            if ($parent && !empty($parent->progress_json)) {
                $decoded = is_string($parent->progress_json)
                    ? json_decode($parent->progress_json, true)
                    : $parent->progress_json;
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
        if (!in_array($displayStatus, ['running', 'queued'], true)) {
            $update['status'] = match ($displayStatus) {
                'failed', 'error' => 'failed',
                'success' => 'success',
                'cancelled' => 'cancelled',
                default => 'warning',
            };
            $update['finished_at'] = date('Y-m-d H:i:s');
            if (in_array($update['status'], ['failed', 'warning'], true)
                && Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'error_summary')) {
                $summary = self::collectChildErrorSummary($batchRunId);
                if ($summary !== '') {
                    $update['error_summary'] = $summary;
                }
            }
        } else {
            $update['status'] = 'running';
        }
        Capsule::table('s3_cloudbackup_runs')
            ->whereRaw('run_id = ' . self::uuidToDbExpr($batchRunId))
            ->update($update);
    }
}
