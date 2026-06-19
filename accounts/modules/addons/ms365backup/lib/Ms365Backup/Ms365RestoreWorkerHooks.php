<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Module\Addon\CloudStorage\Client\CustomerFacingTextSanitizer;

/**
 * Shared worker API hooks for backup and restore runs.
 */
final class Ms365RestoreWorkerHooks
{
    /** @param array<string, mixed> $body */
    public static function onProgress(string $runId, array $body): void
    {
        if (RestoreRunRepository::isRestoreRun($runId)) {
            self::restoreProgress($runId, $body);

            return;
        }
        self::backupProgress($runId, $body);
    }

    /** @param array<string, mixed> $body */
    public static function onComplete(string $runId, array $body): void
    {
        if (RestoreRunRepository::isRestoreRun($runId)) {
            self::restoreComplete($runId, $body);

            return;
        }
        self::backupComplete($runId, $body);
    }

    public static function onFail(string $runId, string $message): void
    {
        if (RestoreRunRepository::isRestoreRun($runId)) {
            self::restoreFail($runId, $message);

            return;
        }
        self::backupFail($runId, $message);
    }

    /** @param array<string, mixed> $body */
    private static function backupProgress(string $runId, array $body): void
    {
        $rawPhase = (string) ($body['phase'] ?? '');
        $message = (string) ($body['message'] ?? $rawPhase);
        $existing = BackupRunRepository::get($runId) ?? [];

        $incomingPercent = (float) ($body['percent'] ?? 0);
        $incomingItemsDone = (int) ($body['items_done'] ?? 0);
        $incomingItemsTotal = (int) ($body['items_total'] ?? 0);
        $incomingBytesHashed = (int) ($body['bytes_hashed'] ?? 0);
        $incomingBytesUploaded = (int) ($body['bytes_uploaded'] ?? 0);

        $isHeartbeat = strtolower(trim($message)) === 'heartbeat'
            || self::isLeaseOnlyProgressPayload(
                $incomingPercent,
                $incomingItemsDone,
                $incomingItemsTotal,
                $incomingBytesHashed,
                $incomingBytesUploaded
            );

        $fields = [
            'updated_at' => time(),
        ];
        if ($rawPhase !== '') {
            $fields['phase'] = CustomerFacingTextSanitizer::scrub($rawPhase);
        }

        if (!$isHeartbeat) {
            $storedPercent = (float) ($existing['percent'] ?? 0);
            if ($incomingPercent > 0 || $storedPercent <= 0) {
                $fields['percent'] = max($storedPercent, $incomingPercent);
            }

            $storedItemsDone = (int) ($existing['items_done'] ?? 0);
            if ($incomingItemsDone > 0 || $storedItemsDone <= 0) {
                $fields['items_done'] = max($storedItemsDone, $incomingItemsDone);
            }

            $storedItemsTotal = (int) ($existing['items_total'] ?? 0);
            if ($incomingItemsTotal > 0 || $storedItemsTotal <= 0) {
                $fields['items_total'] = max($storedItemsTotal, $incomingItemsTotal);
            }

            if (\WHMCS\Database\Capsule::schema()->hasColumn('ms365_backup_runs', 'bytes_hashed')) {
                $storedBytesHashed = (int) ($existing['bytes_hashed'] ?? 0);
                if ($incomingBytesHashed > 0 || $storedBytesHashed <= 0) {
                    $fields['bytes_hashed'] = max($storedBytesHashed, $incomingBytesHashed);
                }

                $storedBytesUploaded = (int) ($existing['bytes_uploaded'] ?? 0);
                if ($incomingBytesUploaded > 0 || $storedBytesUploaded <= 0) {
                    $fields['bytes_uploaded'] = max($storedBytesUploaded, $incomingBytesUploaded);
                }
            }
        }

        if (!empty($body['manifest_id'])) {
            $fields['manifest_id'] = (string) $body['manifest_id'];
        }

        BackupRunRepository::update($runId, $fields);

        WorkerLeaseService::renewForRun($runId);

        $batchRunId = $existing['e3_batch_run_id'] ?? BackupRunRepository::get($runId)['e3_batch_run_id'] ?? null;
        if (is_string($batchRunId) && $batchRunId !== '') {
            Ms365BatchRunRepository::updateLiveSnapshot($batchRunId);
        }

        if ($isHeartbeat) {
            return;
        }

        $logMessage = CustomerFacingTextSanitizer::scrubLogMessage($message);
        if (!self::shouldPersistProgressLog($runId, $logMessage, $rawPhase)) {
            return;
        }

        $logger = new ProgressLogger($runId);
        $logger->info($logMessage, [
            'percent' => $fields['percent'] ?? ($existing['percent'] ?? null),
            'bytes_hashed' => $body['bytes_hashed'] ?? null,
            'bytes_uploaded' => $body['bytes_uploaded'] ?? null,
        ]);
    }

    private static function isLeaseOnlyProgressPayload(
        float $percent,
        int $itemsDone,
        int $itemsTotal,
        int $bytesHashed,
        int $bytesUploaded
    ): bool {
        return $percent <= 0
            && $itemsDone <= 0
            && $itemsTotal <= 0
            && $bytesHashed <= 0
            && $bytesUploaded <= 0;
    }

    /** @param array<string, mixed> $body */
    private static function restoreProgress(string $runId, array $body): void
    {
        $rawPhase = (string) ($body['phase'] ?? '');
        if ($rawPhase === 'graph_sync') {
            self::restoreFail(
                $runId,
                'Restore worker ran backup instead of restore (upgrade worker to 0.1.6+).'
            );

            return;
        }
        $fields = [
            'status' => 'running',
            'phase' => CustomerFacingTextSanitizer::scrub($rawPhase),
            'items_done' => (int) ($body['items_done'] ?? 0),
            'items_total' => (int) ($body['items_total'] ?? 0),
        ];
        if (\WHMCS\Database\Capsule::schema()->hasColumn('ms365_restore_runs', 'items_skipped')) {
            $fields['items_skipped'] = (int) ($body['items_skipped'] ?? 0);
        }
        RestoreRunRepository::update($runId, $fields);

        WorkerLeaseService::renewForRun($runId);

        $run = RestoreRunRepository::get($runId);
        $batchRunId = (string) ($run['e3_batch_run_id'] ?? '');
        if ($batchRunId !== '') {
            // Parent progress_pct / objects_* are derived from child rows here.
            Ms365BatchRunRepository::updateLiveSnapshotForRestore($batchRunId);
        }

        $logger = new RestoreProgressLogger($runId);
        $message = (string) ($body['message'] ?? $rawPhase);
        $logMessage = CustomerFacingTextSanitizer::scrubLogMessage($message);
        $context = [
            'items_done' => (int) ($body['items_done'] ?? 0),
            'items_total' => (int) ($body['items_total'] ?? 0),
            'items_skipped' => (int) ($body['items_skipped'] ?? 0),
        ];
        if ($logMessage === '') {
            return;
        }
        if (self::isRestoreFailureProgressMessage($logMessage)) {
            $logger->error($logMessage, $context);
        } else {
            $logger->info($logMessage, $context);
        }
    }

    private static function isRestoreFailureProgressMessage(string $message): bool
    {
        return (bool) preg_match(
            '/failed to restore|no items were restored|restore failed|upload session|graph 4\d\d/i',
            $message
        );
    }

    /**
     * Avoid one DB log row per Kopia progress tick (whale uploads can emit thousands).
     */
    private static function shouldPersistProgressLog(string $runId, string $message, string $phase): bool
    {
        $message = strtolower(trim($message));
        $phase = strtolower(trim($phase));
        if ($message === 'upload in progress' || ($phase === 'kopia_upload' && $message === '')) {
            return false;
        }

        return true;
    }

    /** @param array<string, mixed> $body */
    private static function backupComplete(string $runId, array $body): void
    {
        $now = time();
        $update = [
            'status' => 'success',
            'phase' => 'complete',
            'percent' => 100,
            'finished_at' => $now,
            'updated_at' => $now,
            'engine_mode' => 'kopia',
        ];
        $manifestId = trim((string) ($body['manifest_id'] ?? ''));
        if ($manifestId !== '') {
            $update['manifest_id'] = $manifestId;
        }
        $statsRaw = (string) ($body['stats_json'] ?? '');
        if ($statsRaw !== '') {
            $stats = json_decode($statsRaw, true);
            if (is_array($stats)) {
                if (isset($stats['bytes_hashed'])) {
                    $update['bytes_hashed'] = (int) $stats['bytes_hashed'];
                }
                if (isset($stats['bytes_uploaded'])) {
                    $update['bytes_uploaded'] = (int) $stats['bytes_uploaded'];
                }
                if (array_key_exists('delta_states', $stats)) {
                    $deltaStates = is_array($stats['delta_states']) ? $stats['delta_states'] : [];
                    if ($deltaStates !== []) {
                        $run = BackupRunRepository::get($runId);
                        if ($run !== null) {
                            DeltaStateRepository::advanceOnShardSuccess(
                                (int) ($run['tenant_record_id'] ?? 0),
                                (string) ($run['physical_key'] ?? ''),
                                $deltaStates,
                                trim((string) ($run['e3_job_id'] ?? '')) !== '' ? (string) $run['e3_job_id'] : null
                            );
                        }
                    }
                    if (\WHMCS\Database\Capsule::schema()->hasColumn('ms365_backup_runs', 'delta_states_json')) {
                        $encoded = json_encode($deltaStates, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
                        $update['delta_states_json'] = is_string($encoded) ? $encoded : '{}';
                    }
                }
            }
        }
        BackupRunRepository::update($runId, $update);
        JobQueueRepository::markDone($runId);
        Ms365BatchRunRepository::syncForChildRun($runId);

        $logger = new ProgressLogger($runId);
        $logger->info('Backup completed', ['manifest_id' => $manifestId]);
    }

    /** @param array<string, mixed> $body */
    private static function restoreComplete(string $runId, array $body): void
    {
        $now = time();
        $statsRaw = (string) ($body['stats_json'] ?? '');
        $restored = 0;
        $skipped = 0;
        $stats = [];
        if ($statsRaw !== '') {
            $decoded = json_decode($statsRaw, true);
            if (is_array($decoded)) {
                $stats = $decoded;
                $restored = (int) ($stats['restored'] ?? 0);
                $skipped = (int) ($stats['skipped'] ?? 0);
            }
        }

        $run = RestoreRunRepository::get($runId);
        $expectedItems = 0;
        if ($run !== null) {
            $selection = json_decode((string) ($run['selection_json'] ?? ''), true);
            if (is_array($selection) && is_array($selection['items'] ?? null)) {
                $expectedItems = count($selection['items']);
            }
        }

        $noopBackupStats = ($stats['status'] ?? '') === 'no_changes';
        if ($noopBackupStats || ($expectedItems > 0 && $restored === 0 && $skipped === 0)) {
            $message = $noopBackupStats
                ? 'Restore worker ran backup instead of restore (upgrade worker to 0.1.6+).'
                : 'Restore finished without restoring any selected items.';
            self::restoreFail($runId, $message);

            return;
        }

        RestoreRunRepository::update($runId, [
            'status' => 'success',
            'phase' => 'complete',
            'items_done' => $restored + $skipped,
            'items_skipped' => $skipped,
            'finished_at' => $now,
        ]);
        JobQueueRepository::markDone($runId);
        Ms365BatchRunRepository::syncForRestoreChildRun($runId);

        $logger = new RestoreProgressLogger($runId);
        $summaryParts = [];
        if ($restored > 0) {
            $summaryParts[] = $restored . ' file(s) restored';
        }
        if ($skipped > 0) {
            $summaryParts[] = $skipped . ' file(s) skipped (already in destination)';
        }
        $summary = $summaryParts !== [] ? 'Restore completed: ' . implode(', ', $summaryParts) : 'Restore completed';
        $logger->info($summary, [
            'restored' => $restored,
            'skipped' => $skipped,
        ]);
    }

    private static function backupFail(string $runId, string $message): void
    {
        $now = time();
        $customerMessage = Ms365CustomerError::message(new \RuntimeException($message));
        BackupRunRepository::update($runId, [
            'status' => 'error',
            'error_message' => $customerMessage,
            'finished_at' => $now,
            'updated_at' => $now,
        ]);
        JobQueueRepository::markFailed($runId, $customerMessage);
        Ms365BatchRunRepository::syncForChildRun($runId);

        $logger = new ProgressLogger($runId);
        $logText = $customerMessage !== '' ? $customerMessage : 'Backup failed';
        $logger->error($logText);
    }

    private static function restoreFail(string $runId, string $message): void
    {
        $now = time();
        $customerMessage = Ms365CustomerError::message(new \RuntimeException($message));
        RestoreRunRepository::update($runId, [
            'status' => 'error',
            'error_message' => $customerMessage,
            'finished_at' => $now,
        ]);
        JobQueueRepository::markTerminalFailed($runId, $customerMessage);
        Ms365BatchRunRepository::syncForRestoreChildRun($runId);

        $logger = new RestoreProgressLogger($runId);
        $logText = $customerMessage !== '' ? $customerMessage : 'Restore failed';
        $logger->error($logText);
    }
}
