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

        $rows = $query
            ->select($select)
            ->orderByDesc('r.started_at')
            ->orderByDesc('r.created_at')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $out = [];
        $billingCache = [];
        foreach ($rows as $row) {
            $arr = (array) $row;
            $runId = (string) ($arr['run_id'] ?? '');
            $clientName = self::formatClientName($arr);
            $clientId = (int) ($arr['client_id'] ?? 0);
            $backupUserId = (int) ($arr['backup_user_id'] ?? 0);
            $runType = strtolower((string) ($arr['run_type'] ?? ''));
            $type = $runType === 'restore' ? 'restore' : 'backup';
            $status = strtolower((string) ($arr['status'] ?? ''));
            if (in_array($status, ['running', 'starting', 'queued'], true)) {
                if ($type === 'backup') {
                    Ms365BatchRunRepository::reconcileBatchChildren($runId);
                    Ms365BatchRunRepository::syncFromChildren($runId);
                } else {
                    Ms365BatchRunRepository::syncFromRestoreChildren($runId);
                }
                $fresh = Capsule::table('s3_cloudbackup_runs as r')
                    ->whereRaw('r.run_id = UUID_TO_BIN(?)', [strtolower($runId)])
                    ->first(['r.status', 'r.progress_pct', 'r.error_summary', 'r.finished_at']);
                if ($fresh) {
                    $freshArr = (array) $fresh;
                    $arr['status'] = $freshArr['status'] ?? $arr['status'];
                    $arr['progress_pct'] = $freshArr['progress_pct'] ?? $arr['progress_pct'];
                    $arr['error_summary'] = $freshArr['error_summary'] ?? $arr['error_summary'];
                    $arr['finished_at'] = $freshArr['finished_at'] ?? $arr['finished_at'];
                }
            }
            $counts = self::childCounts($runId, $type);
            $billingKey = $clientId . ':' . $backupUserId;
            if (!isset($billingCache[$billingKey])) {
                $billingCache[$billingKey] = self::billingSummaryForRow($clientId, $backupUserId);
            }
            $out[] = [
                'run_id' => $runId,
                'job_name' => (string) ($arr['job_name'] ?? ''),
                'client_id' => $clientId,
                'client_name' => $clientName,
                'backup_user_id' => $backupUserId,
                'billing' => $billingCache[$billingKey],
                'status' => (string) ($arr['status'] ?? ''),
                'started_at' => $arr['started_at'] ?? null,
                'finished_at' => $arr['finished_at'] ?? null,
                'progress_pct' => $arr['progress_pct'] ?? null,
                'error_summary' => (string) ($arr['error_summary'] ?? ''),
                'type' => $type,
                'child_count' => $counts['total'],
                'failed_child_count' => $counts['failed'],
                'duration_seconds' => self::durationSeconds($arr['started_at'] ?? null, $arr['finished_at'] ?? null),
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
            $out[] = [
                'run_id' => $runId,
                'workload_label' => self::workloadLabel($child),
                'status' => (string) ($child['status'] ?? ''),
                'error_message' => (string) ($child['error_message'] ?? ''),
                'percent' => $child['percent'] ?? null,
                'attempts' => (int) ($queue['attempts'] ?? 0),
                'max_attempts' => (int) ($queue['max_attempts'] ?? 3),
                'queue_status' => (string) ($queue['status'] ?? ''),
                'last_error' => (string) ($queue['error_message'] ?? ''),
            ];
        }

        return $out;
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

    /** @return array{protected_users: int, onedrive_overage_gib: int, trial_status: string|null} */
    private static function billingSummaryForRow(int $clientId, int $backupUserId): array
    {
        $empty = ['protected_users' => 0, 'onedrive_overage_gib' => 0, 'trial_status' => null];
        if ($clientId <= 0) {
            return $empty;
        }
        try {
            cloudstorage_load_ms365backup();
            if ($backupUserId > 0) {
                $summary = Ms365BillingService::usageSummaryForBackupUser($clientId, $backupUserId);

                return [
                    'protected_users' => (int) ($summary['protected_users']['current'] ?? 0),
                    'onedrive_overage_gib' => (int) ($summary['onedrive_overage_gib']['current'] ?? 0),
                    'trial_status' => isset($summary['trial_status']) ? (string) $summary['trial_status'] : null,
                ];
            }
            $live = Ms365UsageMeter::measureClient($clientId);

            return [
                'protected_users' => (int) ($live['protected_users'] ?? 0),
                'onedrive_overage_gib' => (int) ($live['onedrive_overage_gib'] ?? 0),
                'trial_status' => null,
            ];
        } catch (\Throwable $_) {
            return $empty;
        }
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
