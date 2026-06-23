<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;

/**
 * Re-queue failed or never-started child workloads on the same MS365 batch run.
 */
final class Ms365BatchRetryService
{
    public const CANCEL_NEVER_STARTED_MSG = 'Batch ended before workload started';

    public static function isEnabled(): bool
    {
        return Ms365EngineConfig::batchAutoRetryEnabled();
    }

    /**
     * When true, reconcileBatchChildren should not cancel queued children that never started.
     */
    public static function shouldRetainQueuedChildren(string $batchRunId): bool
    {
        if (!self::isEnabled() || !self::isUuid($batchRunId)) {
            return false;
        }
        if (Ms365BatchRunRepository::isRestoreBatch($batchRunId)) {
            return false;
        }
        $parent = Ms365BatchRunRepository::getParentForBatch($batchRunId);
        if ($parent === null || !empty($parent['cancel_requested'])) {
            return false;
        }

        return self::currentRetryRound($parent) < Ms365EngineConfig::batchAutoRetryMaxRounds();
    }

    /**
     * Re-queue eligible failed/cancelled-never-started children. Returns count requeued.
     */
    public static function maybeRequeueFailedShards(string $batchRunId): int
    {
        if (!self::isEnabled() || !self::isUuid($batchRunId)) {
            return 0;
        }
        if (Ms365BatchRunRepository::isRestoreBatch($batchRunId)) {
            return 0;
        }

        $parent = Ms365BatchRunRepository::getParentForBatch($batchRunId);
        if ($parent === null || !empty($parent['cancel_requested'])) {
            return 0;
        }

        $round = self::currentRetryRound($parent);
        if ($round >= Ms365EngineConfig::batchAutoRetryMaxRounds()) {
            return 0;
        }

        $children = Ms365BatchRunRepository::getChildrenForBatch($batchRunId);
        $queueByRun = self::queueRowsByChildId(array_column($children, 'id'));
        $toRequeue = [];
        foreach ($children as $child) {
            $childId = (string) ($child['id'] ?? '');
            if ($childId === '' || !self::isEligibleForRetry($child, $queueByRun[$childId] ?? null)) {
                continue;
            }
            $toRequeue[] = $childId;
        }
        if ($toRequeue === []) {
            return 0;
        }

        $newRound = $round + 1;
        $message = 'Batch auto-retry (round ' . $newRound . ')';
        foreach ($toRequeue as $runId) {
            self::requeueChildForBatchRetry($runId, $message);
        }
        Ms365BatchRunRepository::markBatchRetryInProgress($batchRunId, $newRound, count($toRequeue));

        return count($toRequeue);
    }

    /**
     * @param array<string, mixed> $child
     * @param array<string, mixed>|null $queue
     */
    public static function isEligibleForRetry(array $child, ?array $queue = null): bool
    {
        $status = (string) ($child['status'] ?? '');
        $errorMsg = trim((string) ($child['error_message'] ?? ''));
        if ($errorMsg === '' && is_array($queue)) {
            $errorMsg = trim((string) ($queue['error_message'] ?? ''));
        }

        if ($status === 'cancelled' && $errorMsg === self::CANCEL_NEVER_STARTED_MSG) {
            return true;
        }

        if (!in_array($status, ['error', 'failed'], true)) {
            return false;
        }

        if ($errorMsg === '') {
            return true;
        }

        // The child run's error_message is customer-sanitized and will not match
        // the technical signatures in isNonRetryableError(). Prefer the queue's
        // raw error (internal/ops only) so permanent failures aren't re-run.
        $technicalMsg = is_array($queue) ? trim((string) ($queue['error_message'] ?? '')) : '';
        if ($technicalMsg === '') {
            $technicalMsg = $errorMsg;
        }

        return !JobQueueRepository::isNonRetryableError($technicalMsg);
    }

    private static function requeueChildForBatchRetry(string $runId, string $message): void
    {
        if ($runId === '') {
            return;
        }
        $now = time();
        WorkerClaimService::rollbackAttemptForBatchRequeue($runId);
        Ms365WorkerLogRepository::releaseAssignment($runId, 'batch_auto_retry');

        $queueUpdate = [
            'status' => 'queued',
            'worker_node_id' => null,
            'claimed_at' => null,
            'lease_expires_at' => null,
            'scheduled_at' => $now,
            'error_message' => mb_substr($message, 0, 500),
        ];
        if (Capsule::schema()->hasColumn('ms365_job_queue', 'finished_at')) {
            $queueUpdate['finished_at'] = null;
        }
        Capsule::table('ms365_job_queue')->where('run_id', $runId)->update($queueUpdate);

        BackupRunRepository::resetForQueueRequeue($runId, $now);
    }

    /**
     * @param array<string, mixed> $parent
     */
    public static function currentRetryRound(array $parent): int
    {
        $raw = $parent['stats_json'] ?? '';
        if ($raw === '' || $raw === null) {
            return 0;
        }
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($decoded)) {
            return 0;
        }

        return max(0, (int) ($decoded['ms365_batch_auto_retry_round'] ?? 0));
    }

    /**
     * @param list<string> $childIds
     * @return array<string, array<string, mixed>>
     */
    private static function queueRowsByChildId(array $childIds): array
    {
        $queueByRun = [];
        if ($childIds === [] || !Capsule::schema()->hasTable('ms365_job_queue')) {
            return $queueByRun;
        }
        foreach (Capsule::table('ms365_job_queue')->whereIn('run_id', $childIds)->get() as $queueRow) {
            $queueByRun[(string) $queueRow->run_id] = (array) $queueRow;
        }

        return $queueByRun;
    }

    private static function isUuid(string $id): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id);
    }
}
