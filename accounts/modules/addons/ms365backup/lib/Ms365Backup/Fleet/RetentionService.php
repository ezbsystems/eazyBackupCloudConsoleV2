<?php
declare(strict_types=1);

namespace Ms365Backup\Fleet;

use WHMCS\Database\Capsule;

/**
 * Prunes high-churn operational tables that previously grew without bound.
 *
 * The MS365 worker fleet writes a row to ms365_run_worker_assignments on every
 * claim (and the claim loop rejects/releases ~85% of claims), plus terminal
 * rows accumulate in ms365_job_queue and ms365_worker_log_lines. Left unbounded
 * these tables bloat to hundreds of thousands of rows, inflating index size and
 * lock surface for the per-poll reconciliation queries. Retention keeps only a
 * recent debugging window and deletes in bounded batches so each transaction is
 * short (avoiding long row locks / large redo bursts on the hot path).
 */
final class RetentionService
{
    private const ASSIGNMENT_RETENTION_DAYS = 7;
    private const QUEUE_TERMINAL_RETENTION_DAYS = 7;
    private const LOG_LINE_RETENTION_DAYS = 30;

    private const BATCH_SIZE = 5000;
    private const MAX_BATCHES = 50;

    /**
     * @return array<string,int> rows deleted per table
     */
    public static function prune(): array
    {
        $now = time();
        // #region agent log
        $agentDebugStart = hrtime(true);
        // #endregion

        $deleted = [
            'assignments' => self::pruneBatched(
                static function () use ($now) {
                    return Capsule::table('ms365_run_worker_assignments')
                        ->whereNotNull('released_at')
                        ->where('released_at', '<', $now - self::ASSIGNMENT_RETENTION_DAYS * 86400)
                        ->limit(self::BATCH_SIZE)
                        ->delete();
                }
            ),
            'queue_terminal' => self::pruneBatched(
                static function () use ($now) {
                    return Capsule::table('ms365_job_queue')
                        ->whereIn('status', ['done', 'failed'])
                        ->where('finished_at', '>', 0)
                        ->where('finished_at', '<', $now - self::QUEUE_TERMINAL_RETENTION_DAYS * 86400)
                        ->limit(self::BATCH_SIZE)
                        ->delete();
                }
            ),
            'log_lines' => self::pruneBatched(
                static function () use ($now) {
                    return Capsule::table('ms365_worker_log_lines')
                        ->where('created_at', '>', 0)
                        ->where('created_at', '<', $now - self::LOG_LINE_RETENTION_DAYS * 86400)
                        ->limit(self::BATCH_SIZE)
                        ->delete();
                }
            ),
        ];

        // #region agent log
        file_put_contents('/var/www/eazybackup.ca/.cursor/debug-459149.log', json_encode(['sessionId' => '459149', 'runId' => 'pre-fix', 'hypothesisId' => 'H1', 'location' => 'Ms365Backup/Fleet/RetentionService.php:65', 'message' => 'Fleet retention pass completed', 'data' => ['durationMs' => round((hrtime(true) - $agentDebugStart) / 1e6, 3), 'deleted' => $deleted], 'timestamp' => (int) round(microtime(true) * 1000)], JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
        // #endregion

        return $deleted;
    }

    /**
     * @param callable():int $deleteBatch
     */
    private static function pruneBatched(callable $deleteBatch): int
    {
        $total = 0;
        for ($i = 0; $i < self::MAX_BATCHES; $i++) {
            $deleted = (int) $deleteBatch();
            $total += $deleted;
            if ($deleted < self::BATCH_SIZE) {
                break;
            }
        }

        return $total;
    }
}
