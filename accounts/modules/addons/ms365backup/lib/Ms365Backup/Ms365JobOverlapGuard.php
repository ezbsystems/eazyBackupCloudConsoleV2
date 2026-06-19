<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;

/**
 * Detects in-flight MS365 backup batches for per-job overlap protection.
 */
final class Ms365JobOverlapGuard
{
    /** @var list<string> */
    public const ACTIVE_STATUSES = ['queued', 'starting', 'running'];

    /**
     * @return array{run_id: string, status: string}|null
     */
    public static function findActiveBackupBatch(string $e3JobId): ?array
    {
        if (!self::isUuid($e3JobId) || !Capsule::schema()->hasTable('s3_cloudbackup_runs')) {
            return null;
        }
        if (!Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'job_id')) {
            return null;
        }

        $q = Capsule::table('s3_cloudbackup_runs')
            ->whereRaw('job_id = ' . self::uuidToDbExpr($e3JobId))
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->orderByDesc('started_at');

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

        $row = $q->first(['run_id', 'status']);
        if ($row === null) {
            return null;
        }

        $runId = Ms365BatchRunRepository::normalizeRunUuid($row->run_id ?? '');
        if ($runId === '') {
            return null;
        }

        return [
            'run_id' => $runId,
            'status' => (string) ($row->status ?? 'running'),
        ];
    }

    private static function isUuid(string $id): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id);
    }

    private static function uuidToDbExpr(string $uuid): string
    {
        return "UUID_TO_BIN('" . addslashes(strtolower($uuid)) . "')";
    }
}
