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
        $fields = [
            'phase' => CustomerFacingTextSanitizer::scrub($rawPhase),
            'percent' => (float) ($body['percent'] ?? 0),
            'items_done' => (int) ($body['items_done'] ?? 0),
            'items_total' => (int) ($body['items_total'] ?? 0),
            'updated_at' => time(),
        ];
        if (\WHMCS\Database\Capsule::schema()->hasColumn('ms365_backup_runs', 'bytes_hashed')) {
            $fields['bytes_hashed'] = (int) ($body['bytes_hashed'] ?? 0);
            $fields['bytes_uploaded'] = (int) ($body['bytes_uploaded'] ?? 0);
        }
        if (!empty($body['manifest_id'])) {
            $fields['manifest_id'] = (string) $body['manifest_id'];
        }
        BackupRunRepository::update($runId, $fields);

        WorkerLeaseService::renewForRun($runId);

        $batchRunId = BackupRunRepository::get($runId)['e3_batch_run_id'] ?? null;
        if (is_string($batchRunId) && $batchRunId !== '') {
            Ms365BatchRunRepository::updateLiveSnapshot($batchRunId);
        }

        $logger = new ProgressLogger($runId);
        $logMessage = CustomerFacingTextSanitizer::scrubLogMessage(
            (string) ($body['message'] ?? $rawPhase)
        );
        $logger->info($logMessage, [
            'percent' => $fields['percent'],
            'bytes_hashed' => $body['bytes_hashed'] ?? null,
            'bytes_uploaded' => $body['bytes_uploaded'] ?? null,
        ]);
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
        RestoreRunRepository::update($runId, [
            'status' => 'running',
            'phase' => CustomerFacingTextSanitizer::scrub($rawPhase),
            'items_done' => (int) ($body['items_done'] ?? 0),
            'items_total' => (int) ($body['items_total'] ?? 0),
        ]);

        WorkerLeaseService::renewForRun($runId);

        $run = RestoreRunRepository::get($runId);
        $batchRunId = (string) ($run['e3_batch_run_id'] ?? '');
        if ($batchRunId !== '') {
            Ms365BatchRunRepository::updateLiveSnapshotForRestore($batchRunId);

            $percent = (float) ($body['percent'] ?? 0);
            $itemsTotal = (int) ($body['items_total'] ?? 0);
            $itemsDone = (int) ($body['items_done'] ?? 0);
            if ($itemsTotal > 0) {
                $percent = max($percent, min(99.0, ($itemsDone / $itemsTotal) * 100));
            }
            if ($percent > 0 && Capsule::schema()->hasColumn('s3_cloudbackup_runs', 'progress_pct')) {
                Capsule::table('s3_cloudbackup_runs')
                    ->whereRaw('run_id = UUID_TO_BIN(?)', [strtolower($batchRunId)])
                    ->update(['progress_pct' => round(min(99.0, $percent), 2)]);
            }
        }

        $logger = new RestoreProgressLogger($runId);
        $logger->info(CustomerFacingTextSanitizer::scrubLogMessage((string) ($body['message'] ?? $rawPhase)), [
            'items_done' => (int) ($body['items_done'] ?? 0),
            'items_total' => (int) ($body['items_total'] ?? 0),
        ]);
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
                                $deltaStates
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
        $logger->info('Restore completed', [
            'restored' => $restored,
            'skipped' => $skipped,
            'message' => $skipped > 0 && $restored === 0
                ? $skipped . ' item(s) already exist in the destination and were skipped.'
                : null,
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
        JobQueueRepository::markFailed($runId, $customerMessage);
        Ms365BatchRunRepository::syncForRestoreChildRun($runId);
    }
}
