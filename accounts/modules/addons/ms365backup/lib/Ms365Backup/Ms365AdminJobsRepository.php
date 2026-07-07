<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;

require_once dirname(__DIR__, 3) . '/cloudstorage/lib/Ms365BackupBootstrap.php';

/**
 * Admin listing of MS365 batch runs (s3_cloudbackup_runs where engine=ms365).
 */
final class Ms365AdminJobsRepository
{
    /** @return array{rows: list<array<string, mixed>>, total: int, page: int, per_page: int} */
    public static function listJobs(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $page = max(1, $page);
        $perPage = min(200, max(1, $perPage));

        if (!Capsule::schema()->hasTable('s3_cloudbackup_runs')
            || !Capsule::schema()->hasTable('s3_cloudbackup_jobs')) {
            return ['rows' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage];
        }

        $hasRunIdBinary = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'run_id');
        $hasEngine = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'engine');
        $hasRunType = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'run_type');
        $hasCancelRequested = Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'cancel_requested');
        $hasJobIdPk = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'job_id');
        $hasJobBackupUserId = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'backup_user_id');

        $query = Capsule::table('s3_cloudbackup_runs as r')
            ->join('s3_cloudbackup_jobs as j', static function ($join) use ($hasJobIdPk) {
                if ($hasJobIdPk) {
                    $join->on('r.job_id', '=', 'j.job_id');
                } else {
                    $join->on('r.job_id', '=', 'j.id');
                }
            })
            ->join('tblclients as c', 'j.client_id', '=', 'c.id');

        if ($hasEngine) {
            $query->where('r.engine', 'ms365');
        } elseif (Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'source_type')) {
            $query->where('j.source_type', 'ms365');
        }

        if (!empty($filters['client_id'])) {
            $query->where('j.client_id', (int) $filters['client_id']);
        }
        if (!empty($filters['client_name'])) {
            $term = '%' . trim((string) $filters['client_name']) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('c.firstname', 'LIKE', $term)
                    ->orWhere('c.lastname', 'LIKE', $term)
                    ->orWhere('c.companyname', 'LIKE', $term)
                    ->orWhere('c.email', 'LIKE', $term);
            });
        }
        if (!empty($filters['job_name'])) {
            $query->where('j.name', 'LIKE', '%' . trim((string) $filters['job_name']) . '%');
        }
        if (!empty($filters['status'])) {
            $query->where('r.status', (string) $filters['status']);
        }
        if (!empty($filters['type']) && $hasRunType) {
            $type = strtolower((string) $filters['type']);
            if ($type === 'restore') {
                $query->where('r.run_type', 'restore');
            } elseif ($type === 'backup') {
                $query->where(function ($q) {
                    $q->whereNull('r.run_type')->orWhere('r.run_type', '!=', 'restore');
                });
            }
        }
        if (!empty($filters['date_from'])) {
            $query->where('r.started_at', '>=', (string) $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('r.started_at', '<=', (string) $filters['date_to'] . ' 23:59:59');
        }
        if (!empty($filters['run_id'])) {
            $needle = strtolower(trim((string) $filters['run_id']));
            if ($hasRunIdBinary && self::isUuid($needle)) {
                $query->whereRaw('r.run_id = UUID_TO_BIN(?)', [$needle]);
            } else {
                $query->where('r.run_id', 'LIKE', '%' . $needle . '%');
            }
        }

        $total = (int) $query->count();

        $select = [
            'r.status',
            'r.started_at',
            'r.finished_at',
            'r.progress_pct',
            'r.error_summary',
            'r.created_at',
            'j.name as job_name',
            'j.client_id',
            'c.firstname',
            'c.lastname',
            'c.companyname',
            'c.email',
        ];
        if ($hasJobBackupUserId) {
            $select[] = 'j.backup_user_id';
        }
        if ($hasRunIdBinary) {
            $select[] = Capsule::raw('BIN_TO_UUID(r.run_id) as run_id');
        } else {
            $select[] = 'r.run_id';
        }
        if ($hasRunType) {
            $select[] = 'r.run_type';
        }
        if ($hasCancelRequested) {
            $select[] = 'r.cancel_requested';
        }

        $rows = $query
            ->select($select)
            ->orderByDesc('r.started_at')
            ->orderByDesc('r.created_at')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $parsedRows = [];
        $backupRunIds = [];
        $restoreRunIds = [];
        $billingPairs = [];
        foreach ($rows as $row) {
            $arr = (array) $row;
            $runId = (string) ($arr['run_id'] ?? '');
            $clientId = (int) ($arr['client_id'] ?? 0);
            $backupUserId = (int) ($arr['backup_user_id'] ?? 0);
            $runType = strtolower((string) ($arr['run_type'] ?? ''));
            $type = $runType === 'restore' ? 'restore' : 'backup';
            if ($runId !== '') {
                if ($type === 'restore') {
                    $restoreRunIds[] = $runId;
                } else {
                    $backupRunIds[] = $runId;
                }
            }
            if ($clientId > 0) {
                $billingPairs[$clientId . ':' . $backupUserId] = [$clientId, $backupUserId];
            }
            $parsedRows[] = [
                'arr' => $arr,
                'run_id' => $runId,
                'client_id' => $clientId,
                'backup_user_id' => $backupUserId,
                'type' => $type,
                'client_name' => self::formatClientName($arr),
            ];
        }

        $billingCache = self::billingSummariesForKeys(array_values($billingPairs));
        $childCountCache = self::childCountsForRuns($backupRunIds, $restoreRunIds);

        $out = [];
        foreach ($parsedRows as $parsed) {
            $arr = $parsed['arr'];
            $runId = $parsed['run_id'];
            $clientId = $parsed['client_id'];
            $backupUserId = $parsed['backup_user_id'];
            $type = $parsed['type'];
            $billingKey = $clientId . ':' . $backupUserId;
            $counts = $childCountCache[$runId] ?? ['total' => 0, 'failed' => 0];
            $out[] = [
                'run_id' => $runId,
                'job_name' => (string) ($arr['job_name'] ?? ''),
                'client_id' => $clientId,
                'client_name' => $parsed['client_name'],
                'backup_user_id' => $backupUserId,
                'billing' => $billingCache[$billingKey] ?? ['protected_users' => 0, 'onedrive_overage_gib' => 0, 'trial_status' => null],
                'status' => (string) ($arr['status'] ?? ''),
                'started_at' => $arr['started_at'] ?? null,
                'finished_at' => $arr['finished_at'] ?? null,
                'progress_pct' => $arr['progress_pct'] ?? null,
                'error_summary' => (string) ($arr['error_summary'] ?? ''),
                'type' => $type,
                'child_count' => $counts['total'],
                'failed_child_count' => $counts['failed'],
                'duration_seconds' => self::durationSeconds($arr['started_at'] ?? null, $arr['finished_at'] ?? null),
                'cancel_requested' => !empty($arr['cancel_requested']),
            ];
        }

        return ['rows' => $out, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    /** @return list<array<string, mixed>> */
    public static function getBatchChildrenDetail(string $batchRunId): array
    {
        if (!self::isUuid($batchRunId)) {
            return [];
        }

        $children = Ms365BatchRunRepository::getBatchChildren($batchRunId);
        if ($children === []) {
            return [];
        }

        $childIds = array_column($children, 'id');
        $queueByRun = [];
        if (Capsule::schema()->hasTable('ms365_job_queue')) {
            foreach (Capsule::table('ms365_job_queue')->whereIn('run_id', $childIds)->get() as $q) {
                $queueByRun[(string) $q->run_id] = (array) $q;
            }
        }

        $out = [];
        foreach ($children as $child) {
            $runId = (string) ($child['id'] ?? '');
            $queue = $queueByRun[$runId] ?? [];
            $childStats = self::decodeChildStatsJson($child);
            $queueError = (string) ($queue['error_message'] ?? '');
            $out[] = [
                'run_id' => $runId,
                'workload_label' => self::workloadLabel($child),
                'status' => (string) ($child['status'] ?? ''),
                'phase' => (string) ($child['phase'] ?? ''),
                'error_message' => (string) ($child['error_message'] ?? ''),
                'queue_error' => $queueError,
                'percent' => $child['percent'] ?? null,
                'bytes_hashed' => (int) ($child['bytes_hashed'] ?? 0),
                'bytes_uploaded' => (int) ($child['bytes_uploaded'] ?? 0),
                'graph_sync_ms' => isset($childStats['graph_sync_ms']) ? (int) $childStats['graph_sync_ms'] : null,
                'kopia_snapshot_ms' => isset($childStats['kopia_snapshot_ms']) ? (int) $childStats['kopia_snapshot_ms'] : null,
                'workload_skipped' => self::decodeWorkloadSkipped($childStats),
                'attempts' => (int) ($queue['attempts'] ?? 0),
                'max_attempts' => (int) ($queue['max_attempts'] ?? 3),
                'queue_status' => (string) ($queue['status'] ?? ''),
                'last_error' => $queueError,
            ];
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private static function decodeChildStatsJson(array $child): array
    {
        if (!Capsule::schema()->hasColumn('ms365_backup_runs', 'stats_json')) {
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

    /** @return array<string, string> */
    private static function decodeWorkloadSkipped(array $stats): array
    {
        $workloads = is_array($stats['workloads'] ?? null) ? $stats['workloads'] : [];
        $skipped = [];
        foreach ($workloads as $name => $data) {
            if (!is_array($data)) {
                continue;
            }
            $reason = trim((string) ($data['skipped'] ?? ''));
            if ($reason !== '') {
                $skipped[(string) $name] = $reason;
            }
        }

        return $skipped;
    }

    /** @param array<string, mixed> $child */
    private static function workloadLabel(array $child): string
    {
        $name = trim((string) ($child['user_display_name'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($child['physical_key'] ?? $child['target_graph_id'] ?? ''));
        }
        $type = (string) ($child['resource_type'] ?? 'workload');

        return $name !== '' ? $type . ': ' . $name : $type;
    }

    /** @return array{total: int, failed: int} */
    private static function childCounts(string $batchRunId, string $type): array
    {
        if (!self::isUuid($batchRunId)) {
            return ['total' => 0, 'failed' => 0];
        }
        if ($type === 'restore' && Capsule::schema()->hasTable('ms365_restore_runs')) {
            $total = (int) Capsule::table('ms365_restore_runs')->where('e3_batch_run_id', $batchRunId)->count();
            $failed = (int) Capsule::table('ms365_restore_runs')
                ->where('e3_batch_run_id', $batchRunId)
                ->whereIn('status', ['error', 'failed'])
                ->count();

            return ['total' => $total, 'failed' => $failed];
        }
        if (!Capsule::schema()->hasTable('ms365_backup_runs')) {
            return ['total' => 0, 'failed' => 0];
        }
        $total = (int) Capsule::table('ms365_backup_runs')->where('e3_batch_run_id', $batchRunId)->count();
        $failed = (int) Capsule::table('ms365_backup_runs')
            ->where('e3_batch_run_id', $batchRunId)
            ->whereIn('status', ['error', 'failed'])
            ->count();

        return ['total' => $total, 'failed' => $failed];
    }

    /**
     * @param list<array{0: int, 1: int}> $pairs
     * @return array<string, array{protected_users: int, onedrive_overage_gib: int, trial_status: string|null}>
     */
    private static function billingSummariesForKeys(array $pairs): array
    {
        $empty = ['protected_users' => 0, 'onedrive_overage_gib' => 0, 'trial_status' => null];
        $out = [];
        foreach ($pairs as $pair) {
            $out[(int) $pair[0] . ':' . (int) $pair[1]] = $empty;
        }
        if ($pairs === [] || !Capsule::schema()->hasTable('ms365_billing_usage_snapshots')) {
            return $out;
        }

        $clientIds = array_values(array_unique(array_map(static fn (array $pair): int => (int) $pair[0], $pairs)));
        $rows = Capsule::table('ms365_billing_usage_snapshots')
            ->whereIn('client_id', $clientIds)
            ->where('taken_at', '>=', date('Y-m-d', strtotime('-14 days')))
            ->orderByDesc('taken_at')
            ->get(['client_id', 'backup_user_id', 'metric', 'qty', 'service_id']);

        $seen = [];
        $serviceIds = [];
        foreach ($rows as $row) {
            $key = (int) ($row->client_id ?? 0) . ':' . (int) ($row->backup_user_id ?? 0);
            if (!isset($out[$key])) {
                continue;
            }
            $metric = (string) ($row->metric ?? '');
            $metricKey = $key . ':' . $metric;
            if (isset($seen[$metricKey])) {
                continue;
            }
            $seen[$metricKey] = true;
            if ($metric === Ms365BillingConfig::METRIC_PROTECTED_USERS) {
                $out[$key]['protected_users'] = (int) ($row->qty ?? 0);
            } elseif ($metric === Ms365BillingConfig::METRIC_ONEDRIVE_OVERAGE_GIB) {
                $out[$key]['onedrive_overage_gib'] = (int) ($row->qty ?? 0);
            }
            $serviceId = (int) ($row->service_id ?? 0);
            if ($serviceId > 0) {
                $serviceIds[$key] = $serviceId;
            }
        }

        if ($serviceIds !== [] && Capsule::schema()->hasTable('ms365_billing_trial_state')) {
            $trialRows = Capsule::table('ms365_billing_trial_state')
                ->whereIn('service_id', array_values(array_unique($serviceIds)))
                ->get(['service_id', 'status']);
            $trialByService = [];
            foreach ($trialRows as $trialRow) {
                $trialByService[(int) ($trialRow->service_id ?? 0)] = (string) ($trialRow->status ?? '');
            }
            foreach ($serviceIds as $key => $serviceId) {
                if (isset($trialByService[$serviceId]) && $trialByService[$serviceId] !== '') {
                    $out[$key]['trial_status'] = $trialByService[$serviceId];
                }
            }
        }

        return $out;
    }

    /**
     * @param list<string> $backupRunIds
     * @param list<string> $restoreRunIds
     * @return array<string, array{total: int, failed: int}>
     */
    private static function childCountsForRuns(array $backupRunIds, array $restoreRunIds): array
    {
        $out = [];
        foreach (array_merge($backupRunIds, $restoreRunIds) as $runId) {
            $out[$runId] = ['total' => 0, 'failed' => 0];
        }

        $backupRunIds = array_values(array_unique(array_filter($backupRunIds, static fn (string $id): bool => self::isUuid($id))));
        $restoreRunIds = array_values(array_unique(array_filter($restoreRunIds, static fn (string $id): bool => self::isUuid($id))));

        if ($backupRunIds !== [] && Capsule::schema()->hasTable('ms365_backup_runs')) {
            $rows = Capsule::table('ms365_backup_runs')
                ->select([
                    'e3_batch_run_id',
                    Capsule::raw('COUNT(*) as total'),
                    Capsule::raw("SUM(CASE WHEN status IN ('error', 'failed') THEN 1 ELSE 0 END) as failed"),
                ])
                ->whereIn('e3_batch_run_id', $backupRunIds)
                ->groupBy('e3_batch_run_id')
                ->get();
            foreach ($rows as $row) {
                $runId = (string) ($row->e3_batch_run_id ?? '');
                if ($runId === '') {
                    continue;
                }
                $out[$runId] = [
                    'total' => (int) ($row->total ?? 0),
                    'failed' => (int) ($row->failed ?? 0),
                ];
            }
        }

        if ($restoreRunIds !== [] && Capsule::schema()->hasTable('ms365_restore_runs')) {
            $rows = Capsule::table('ms365_restore_runs')
                ->select([
                    'e3_batch_run_id',
                    Capsule::raw('COUNT(*) as total'),
                    Capsule::raw("SUM(CASE WHEN status IN ('error', 'failed') THEN 1 ELSE 0 END) as failed"),
                ])
                ->whereIn('e3_batch_run_id', $restoreRunIds)
                ->groupBy('e3_batch_run_id')
                ->get();
            foreach ($rows as $row) {
                $runId = (string) ($row->e3_batch_run_id ?? '');
                if ($runId === '') {
                    continue;
                }
                $out[$runId] = [
                    'total' => (int) ($row->total ?? 0),
                    'failed' => (int) ($row->failed ?? 0),
                ];
            }
        }

        return $out;
    }

    /** @return array{protected_users: int, onedrive_overage_gib: int, trial_status: string|null} */
    private static function billingSummaryForRow(int $clientId, int $backupUserId): array
    {
        $key = $clientId . ':' . $backupUserId;
        $summaries = self::billingSummariesForKeys([[$clientId, $backupUserId]]);

        return $summaries[$key] ?? ['protected_users' => 0, 'onedrive_overage_gib' => 0, 'trial_status' => null];
    }

    /** @param array<string, mixed> $row */
    private static function formatClientName(array $row): string
    {
        $company = trim((string) ($row['companyname'] ?? ''));
        if ($company !== '') {
            return $company;
        }
        $name = trim(((string) ($row['firstname'] ?? '')) . ' ' . ((string) ($row['lastname'] ?? '')));
        if ($name !== '') {
            return $name;
        }

        return (string) ($row['email'] ?? '');
    }

    private static function durationSeconds(mixed $started, mixed $finished): ?int
    {
        if (empty($started)) {
            return null;
        }
        try {
            $start = new \DateTime((string) $started);
            $end = !empty($finished) ? new \DateTime((string) $finished) : new \DateTime();
            $diff = $end->getTimestamp() - $start->getTimestamp();

            return max(0, $diff);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function isUuid(string $id): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id) === 1;
    }
}
