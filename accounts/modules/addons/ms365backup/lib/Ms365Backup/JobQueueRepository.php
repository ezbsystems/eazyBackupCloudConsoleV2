<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;

final class JobQueueRepository
{
    /** Progress older than this is treated as a stale/zombie running claim. */
    public const ZOMBIE_STALE_SECONDS = 120;

    public static function zombieStaleSeconds(): int
    {
        return self::ZOMBIE_STALE_SECONDS;
    }

    public static function isNonRetryableError(string $message): bool
    {
        $message = strtolower($message);
        if (str_contains($message, 'looking for beginning of value')) {
            return true;
        }
        if (str_contains($message, 'graph 401 after token refresh')) {
            return true;
        }
        $patterns = [
            'graph 403',
            'unauthorized',
            'invalid_grant',
            'token expired',
            'access denied',
            'authentication failed',
        ];
        foreach ($patterns as $pattern) {
            if (str_contains($message, $pattern)) {
                return true;
            }
        }

        return false;
    }

    public static function countStaleRunning(): int
    {
        if (!class_exists(Capsule::class)) {
            return 0;
        }
        $now = time();
        $cutoff = $now - self::zombieStaleSeconds();

        return (int) Capsule::table('ms365_job_queue as q')
            ->join('ms365_backup_runs as r', 'r.id', '=', 'q.run_id')
            ->where('q.status', 'running')
            ->where('r.updated_at', '<', $cutoff)
            ->where(function ($query) use ($now) {
                $query->whereNull('q.lease_expires_at')
                    ->orWhere('q.lease_expires_at', '<=', $now);
            })
            ->count();
    }

    public static function countExhaustedJobs(): int
    {
        if (!class_exists(Capsule::class)) {
            return 0;
        }

        return (int) Capsule::table('ms365_job_queue')
            ->whereIn('status', ['queued', 'running'])
            ->whereColumn('attempts', '>=', 'max_attempts')
            ->count();
    }

    public static function enqueue(string $runId, int $priority = 100): void
    {
        self::insertQueueRow($runId, 'backup', $priority);
    }

    /**
     * @param list<string> $runIds
     */
    public static function enqueueMany(array $runIds, int $priority = 100): void
    {
        if ($runIds === [] || !class_exists(Capsule::class)) {
            return;
        }
        $runIds = array_values(array_unique(array_filter(array_map('strval', $runIds))));
        if ($runIds === []) {
            return;
        }

        $now = time();
        $hasJobType = self::hasJobTypeColumn();
        $rows = [];
        foreach ($runIds as $runId) {
            $row = [
                'run_id' => $runId,
                'status' => 'queued',
                'priority' => $priority,
                'attempts' => 0,
                'max_attempts' => 5,
                'scheduled_at' => $now,
                'created_at' => $now,
            ];
            if ($hasJobType) {
                $row['job_type'] = 'backup';
            }
            $rows[] = $row;
        }
        foreach (array_chunk($rows, 200) as $chunk) {
            Capsule::table('ms365_job_queue')->insertOrIgnore($chunk);
        }
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
            'max_attempts' => 5,
            'scheduled_at' => $now,
            'created_at' => $now,
        ];
        if (self::hasJobTypeColumn()) {
            $row['job_type'] = $jobType;
        }
        Capsule::table('ms365_job_queue')->insert($row);
    }

    private static ?bool $hasJobTypeColumn = null;

    private static function hasJobTypeColumn(): bool
    {
        if (self::$hasJobTypeColumn === null) {
            self::$hasJobTypeColumn = Capsule::schema()->hasColumn('ms365_job_queue', 'job_type');
        }

        return self::$hasJobTypeColumn;
    }

    /** Re-queue an existing row for retry or priority bump. */
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

    // Absolute backstop ceiling for a single run. Kept comfortably above the worker's
    // own max_run_seconds (default 12h) so a healthy long run self-fails (reportably)
    // before the server backstop ever fires. This is only a safety net for a run whose
    // worker is alive (lease kept fresh by node heartbeat) yet wedged.
    private const STALE_RUNNING_SECONDS = 50400; // 14h
    // A running row is considered abandoned when it has not reported progress for this
    // long AND its lease has lapsed (worker process gone). A healthy run updates
    // ms365_backup_runs.updated_at every ~60s via progress/heartbeat, so legitimately
    // long single-resource runs are never reaped mid-flight.
    private const STALE_PROGRESS_SECONDS = 900; // 15m
    private const MAX_RUNNING_PER_CLIENT = 3;

    public static function recoverStaleRunning(): int
    {
        if (!class_exists(Capsule::class)) {
            return 0;
        }
        $now = time();
        $backstopCutoff = $now - self::STALE_RUNNING_SECONDS;
        $progressCutoff = $now - self::STALE_PROGRESS_SECONDS;

        // Only recover runs that are genuinely dead/abandoned:
        //  (a) the lease has lapsed AND no recent progress (worker process gone), or
        //  (b) the run has blown past the absolute backstop ceiling (wedged-but-alive).
        // This intentionally does NOT reap slow-but-alive long runs, which keep their
        // lease fresh and keep updating progress.
        $rows = Capsule::table('ms365_job_queue as q')
            ->leftJoin('ms365_backup_runs as r', 'r.id', '=', 'q.run_id')
            ->where('q.status', 'running')
            ->where(function ($query) use ($now, $progressCutoff, $backstopCutoff) {
                $query->where(function ($abandoned) use ($now, $progressCutoff) {
                    $abandoned->where(function ($lease) use ($now) {
                        $lease->whereNull('q.lease_expires_at')
                            ->orWhere('q.lease_expires_at', '<', $now);
                    })->where(function ($progress) use ($progressCutoff) {
                        $progress->whereNull('r.updated_at')
                            ->orWhere('r.updated_at', '<', $progressCutoff);
                    });
                })->orWhere('q.started_at', '<', $backstopCutoff);
            })
            ->get(['q.run_id', 'q.attempts', 'q.max_attempts']);
        if ($rows->isEmpty()) {
            return 0;
        }
        $requeue = [];
        $exhausted = [];
        foreach ($rows as $row) {
            $runId = (string) $row->run_id;
            $max = (int) $row->max_attempts > 0 ? (int) $row->max_attempts : 3;
            if ((int) $row->attempts >= $max) {
                $exhausted[] = $runId;
            } else {
                $requeue[] = $runId;
            }
        }

        if ($requeue !== []) {
            Capsule::table('ms365_job_queue')
                ->whereIn('run_id', $requeue)
                ->update([
                    'status' => 'queued',
                    'worker_node_id' => null,
                    'claimed_at' => null,
                    'lease_expires_at' => null,
                    'scheduled_at' => $now,
                    'error_message' => 'Recovered stale running job',
                ]);
            foreach ($requeue as $runId) {
                BackupRunRepository::update($runId, [
                    'status' => 'queued',
                    'updated_at' => $now,
                ]);
            }
        }

        foreach ($exhausted as $runId) {
            self::markTerminalFailed($runId, 'Stale running job gave up after max attempts');
            BackupRunRepository::update($runId, [
                'status' => 'error',
                'error_message' => 'Worker stopped responding and the run exceeded its retry limit.',
                'finished_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $rows->count();
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
        Ms365WorkerLogRepository::releaseAssignment($runId, 'complete');
    }

    /** Mark queue entry terminal so cancelled runs release concurrency slots. */
    public static function markCancelled(string $runId, string $message = 'Cancelled'): void
    {
        if (!class_exists(Capsule::class)) {
            return;
        }
        $now = time();
        Capsule::table('ms365_job_queue')->where('run_id', $runId)->update([
            'status' => 'failed',
            'finished_at' => $now,
            'worker_node_id' => null,
            'claimed_at' => null,
            'lease_expires_at' => null,
            'error_message' => mb_substr($message, 0, 500),
        ]);
        Ms365WorkerLogRepository::releaseAssignment($runId, 'cancelled');
    }

    public static function markFailed(string $runId, string $message): void
    {
        if (!class_exists(Capsule::class)) {
            return;
        }
        if (self::isNonRetryableError($message)) {
            self::markTerminalFailed($runId, $message);

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
                'worker_node_id' => null,
                'claimed_at' => null,
                'lease_expires_at' => null,
            ]);
            Ms365WorkerLogRepository::releaseAssignment($runId, 'fail_requeue');

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
        Ms365WorkerLogRepository::releaseAssignment($runId, 'fail');
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
        $now = time();
        $cutoff = $now - self::zombieStaleSeconds();

        return (int) Capsule::table('ms365_job_queue as q')
            ->leftJoin('ms365_backup_runs as br', 'br.id', '=', 'q.run_id')
            ->leftJoin('ms365_restore_runs as rr', 'rr.id', '=', 'q.run_id')
            ->where('q.status', 'running')
            ->where(function ($query) use ($clientId) {
                $query->where('br.whmcs_client_id', $clientId)
                    ->orWhere('rr.whmcs_client_id', $clientId);
            })
            ->where(function ($query) use ($now, $cutoff) {
                $query->where('q.lease_expires_at', '>', $now)
                    ->orWhere('br.updated_at', '>=', $cutoff);
            })
            ->count();
    }
}
