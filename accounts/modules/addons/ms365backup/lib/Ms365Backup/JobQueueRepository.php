<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;

final class JobQueueRepository
{
    public static function enqueue(string $runId, int $priority = 100): void
    {
        self::insertQueueRow($runId, 'backup', $priority);
    }

    public static function enqueueRestore(string $restoreRunId, int $priority = 90): void
    {
        self::insertQueueRow($restoreRunId, 'restore', $priority);
    }

    private static function insertQueueRow(string $runId, string $jobType, int $priority): void
    {
        if (!class_exists(Capsule::class)) {
            return;
        }
        $now = time();
        $exists = Capsule::table('ms365_job_queue')->where('run_id', $runId)->exists();
        if ($exists) {
            return;
        }
        $row = [
            'run_id' => $runId,
            'status' => 'queued',
            'priority' => $priority,
            'attempts' => 0,
            'max_attempts' => 3,
            'scheduled_at' => $now,
            'created_at' => $now,
        ];
        if (Capsule::schema()->hasColumn('ms365_job_queue', 'job_type')) {
            $row['job_type'] = $jobType;
        }
        Capsule::table('ms365_job_queue')->insert($row);
    }

    /** Re-queue an existing row (e.g. kopia_shadow after PHP completes). */
    public static function requeue(string $runId, int $priority = 50): void
    {
        if (!class_exists(Capsule::class)) {
            return;
        }
        $now = time();
        $updated = Capsule::table('ms365_job_queue')->where('run_id', $runId)->update([
            'status' => 'queued',
            'priority' => $priority,
            'worker_node_id' => null,
            'claimed_at' => null,
            'lease_expires_at' => null,
            'scheduled_at' => $now,
            'error_message' => null,
        ]);
        if ($updated === 0) {
            self::enqueue($runId, $priority);
        }
    }

    private const STALE_RUNNING_SECONDS = 7200;
    private const MAX_RUNNING_PER_CLIENT = 3;

    public static function recoverStaleRunning(): int
    {
        if (!class_exists(Capsule::class)) {
            return 0;
        }
        $cutoff = time() - self::STALE_RUNNING_SECONDS;

        $runIds = Capsule::table('ms365_job_queue')
            ->where('status', 'running')
            ->where('started_at', '<', $cutoff)
            ->pluck('run_id')
            ->all();
        if ($runIds === []) {
            return 0;
        }
        $now = time();
        Capsule::table('ms365_job_queue')
            ->whereIn('run_id', $runIds)
            ->update([
                'status' => 'queued',
                'worker_node_id' => null,
                'claimed_at' => null,
                'lease_expires_at' => null,
                'scheduled_at' => $now,
                'error_message' => 'Recovered stale running job',
            ]);
        foreach ($runIds as $runId) {
            BackupRunRepository::update($runId, [
                'status' => 'queued',
                'updated_at' => $now,
            ]);
        }

        return count($runIds);
    }

    public static function claimNext(): ?array
    {
        if (!class_exists(Capsule::class)) {
            return null;
        }
        self::recoverStaleRunning();
        $now = time();
        $candidate = Capsule::table('ms365_job_queue')
            ->where('status', 'queued')
            ->where('scheduled_at', '<=', $now)
            ->orderBy('priority')
            ->orderBy('id')
            ->first();
        if ($candidate === null) {
            return null;
        }

        $run = BackupRunRepository::get((string) $candidate->run_id);
        $clientId = (int) ($run['whmcs_client_id'] ?? 0);
        if ($clientId > 0 && self::countRunningForClient($clientId) >= self::MAX_RUNNING_PER_CLIENT) {
            return null;
        }

        $updated = Capsule::table('ms365_job_queue')
            ->where('id', $candidate->id)
            ->where('status', 'queued')
            ->update([
                'status' => 'running',
                'started_at' => $now,
                'attempts' => (int) $candidate->attempts + 1,
            ]);
        if ($updated === 0) {
            return self::claimNext();
        }

        return (array) $candidate;
    }

    public static function markRunning(string $runId): void
    {
        if (!class_exists(Capsule::class)) {
            return;
        }
        Capsule::table('ms365_job_queue')->where('run_id', $runId)->update([
            'status' => 'running',
            'started_at' => time(),
        ]);
    }

    public static function markDone(string $runId): void
    {
        if (!class_exists(Capsule::class)) {
            return;
        }
        Capsule::table('ms365_job_queue')->where('run_id', $runId)->update([
            'status' => 'done',
            'finished_at' => time(),
        ]);
    }

    public static function markFailed(string $runId, string $message): void
    {
        if (!class_exists(Capsule::class)) {
            return;
        }
        $job = Capsule::table('ms365_job_queue')->where('run_id', $runId)->first();
        if ($job === null) {
            return;
        }
        $attempts = (int) $job->attempts;
        $max = (int) $job->max_attempts;
        if ($attempts < $max) {
            Capsule::table('ms365_job_queue')->where('run_id', $runId)->update([
                'status' => 'queued',
                'scheduled_at' => time() + 60,
                'error_message' => $message,
            ]);

            return;
        }
        self::markTerminalFailed($runId, $message);
    }

    /** Mark a queue entry failed without scheduling another attempt. */
    public static function markTerminalFailed(string $runId, string $message): void
    {
        if (!class_exists(Capsule::class)) {
            return;
        }
        Capsule::table('ms365_job_queue')->where('run_id', $runId)->update([
            'status' => 'failed',
            'finished_at' => time(),
            'error_message' => mb_substr($message, 0, 500),
            'worker_node_id' => null,
            'lease_expires_at' => null,
        ]);
    }

    public static function countQueued(): int
    {
        if (!class_exists(Capsule::class)) {
            return 0;
        }

        return (int) Capsule::table('ms365_job_queue')->where('status', 'queued')->count();
    }

    public static function countRunningForClient(int $clientId): int
    {
        if (!class_exists(Capsule::class) || $clientId <= 0) {
            return 0;
        }

        return (int) Capsule::table('ms365_job_queue as q')
            ->leftJoin('ms365_backup_runs as br', 'br.id', '=', 'q.run_id')
            ->leftJoin('ms365_restore_runs as rr', 'rr.id', '=', 'q.run_id')
            ->where('q.status', 'running')
            ->where(function ($query) use ($clientId) {
                $query->where('br.whmcs_client_id', $clientId)
                    ->orWhere('rr.whmcs_client_id', $clientId);
            })
            ->count();
    }
}
