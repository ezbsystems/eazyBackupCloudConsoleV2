<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;

/**
 * Lists MS365 backup batch runs as restore points for the e3 Restore tab.
 */
final class Ms365RestoreSnapshotService
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function listForJob(int $clientId, int $backupUserId, string $jobId, int $limit = 50, int $offset = 0): array
    {
        if (!self::isUuid($jobId) || !Capsule::schema()->hasTable('s3_cloudbackup_runs')) {
            return [];
        }

        $job = Capsule::table('s3_cloudbackup_jobs')
            ->whereRaw('job_id = ' . self::uuidToDbExpr($jobId))
            ->first();
        if ($job === null) {
            return [];
        }
        if ((int) ($job->client_id ?? 0) !== $clientId) {
            return [];
        }
        if ($backupUserId > 0 && Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'backup_user_id')) {
            if ((int) ($job->backup_user_id ?? 0) !== $backupUserId) {
                return [];
            }
        }

        $q = Capsule::table('s3_cloudbackup_runs')
            ->whereRaw('job_id = ' . self::uuidToDbExpr($jobId))
            ->whereIn('status', ['success', 'warning'])
            ->orderByDesc('finished_at')
            ->orderByDesc('started_at')
            ->offset(max(0, $offset))
            ->limit(max(1, min(100, $limit)));

        if (Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'engine')) {
            $q->where('engine', 'ms365');
        }
        if (Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'run_type')) {
            $q->where(function ($inner) {
                $inner->whereNull('run_type')
                    ->orWhere('run_type', '')
                    ->orWhere('run_type', 'backup');
            });
        }

        $rows = $q->get()->map(static fn ($row) => (array) $row)->all();
        $out = [];
        foreach ($rows as $row) {
            $batchRunId = self::normalizeRunId($row['run_id'] ?? '');
            if ($batchRunId === '') {
                continue;
            }
            $children = Ms365BatchRunRepository::getChildrenForBatch($batchRunId);
            $restorableChildren = array_values(array_filter(
                $children,
                static fn (array $c) => ($c['status'] ?? '') === 'success'
                    && trim((string) ($c['manifest_id'] ?? '')) !== ''
                    && (($c['engine_mode'] ?? '') === 'kopia' || trim((string) ($c['manifest_id'] ?? '')) !== ''),
            ));
            if ($restorableChildren === []) {
                continue;
            }

            $finishedAt = (string) ($row['finished_at'] ?? $row['started_at'] ?? '');
            $childCount = count(ShardRunAggregateService::aggregateForRestore($restorableChildren));
            $label = self::formatSnapshotLabel($finishedAt, $childCount);

            $out[] = [
                'id' => $batchRunId,
                'batch_run_id' => $batchRunId,
                'job_id' => $jobId,
                'job_name' => (string) ($job->name ?? $job->job_name ?? 'Microsoft 365'),
                'finished_at' => $finishedAt,
                'created_at' => (string) ($row['created_at'] ?? ''),
                'engine' => 'ms365',
                'status' => (string) ($row['status'] ?? 'success'),
                'is_restorable' => true,
                'non_restorable_reason' => '',
                'snapshot_label' => $label,
                'child_runs' => ShardRunAggregateService::aggregateForRestore($restorableChildren),
            ];
        }

        return $out;
    }

    private static function formatSnapshotLabel(string $finishedAt, int $workloadCount): string
    {
        $ts = strtotime($finishedAt);
        $dateLabel = $ts > 0 ? gmdate('M j, Y H:i', $ts) . ' UTC' : $finishedAt;

        return $dateLabel . ' — ' . $workloadCount . ' workload' . ($workloadCount === 1 ? '' : 's');
    }

    private static function normalizeRunId(mixed $runId): string
    {
        if (!is_string($runId) || $runId === '') {
            return '';
        }
        if (strlen($runId) === 16) {
            return Ms365BatchRunRepository::normalizeRunUuid($runId);
        }

        return $runId;
    }

    private static function isUuid(string $id): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $id) === 1;
    }

    private static function uuidToDbExpr(string $uuid): string
    {
        $hex = str_replace('-', '', strtolower($uuid));
        if (strlen($hex) !== 32) {
            return "''";
        }
        $bin = '';
        for ($i = 0; $i < 32; $i += 2) {
            $bin .= chr(hexdec(substr($hex, $i, 2)));
        }

        return '0x' . bin2hex($bin);
    }
}
