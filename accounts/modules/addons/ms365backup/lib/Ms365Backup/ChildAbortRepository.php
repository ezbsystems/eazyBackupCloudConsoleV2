<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;

/** Control-plane soft abort for wedged batch children on live tenant owners. */
final class ChildAbortRepository
{
    public const REQUEUE_AFTER_SECONDS = 90;

    public static function columnReady(): bool
    {
        return Capsule::schema()->hasTable('ms365_backup_runs')
            && Capsule::schema()->hasColumn('ms365_backup_runs', 'abort_requested_at');
    }

    /** @return list<string> */
    public static function listAbortRequestedRunIds(string $batchRunId): array
    {
        if ($batchRunId === '' || !self::columnReady()) {
            return [];
        }

        return Capsule::table('ms365_backup_runs')
            ->where('e3_batch_run_id', $batchRunId)
            ->where('status', 'running')
            ->whereNotNull('abort_requested_at')
            ->where('abort_requested_at', '>', 0)
            ->pluck('id')
            ->all();
    }

    public static function markAbortRequested(array $runIds, int $now): int
    {
        $runIds = array_values(array_filter(array_map('strval', $runIds)));
        if ($runIds === [] || !self::columnReady()) {
            return 0;
        }

        return Capsule::table('ms365_backup_runs')
            ->whereIn('id', $runIds)
            ->where('status', 'running')
            ->where(function ($query): void {
                $query->whereNull('abort_requested_at')->orWhere('abort_requested_at', '<=', 0);
            })
            ->update([
                'abort_requested_at' => $now,
                'updated_at' => $now,
            ]);
    }

    public static function clearAbortRequested(array $runIds): void
    {
        $runIds = array_values(array_filter(array_map('strval', $runIds)));
        if ($runIds === [] || !self::columnReady()) {
            return;
        }

        Capsule::table('ms365_backup_runs')
            ->whereIn('id', $runIds)
            ->update([
                'abort_requested_at' => null,
                'updated_at' => time(),
            ]);
    }
}
