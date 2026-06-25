<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Module\Addon\CloudStorage\Client\CustomerFacingTextSanitizer;

/**
 * Shared worker API hooks for backup and restore runs.
 */
final class Ms365RestoreWorkerHooks
{
    public static function isRunCancelled(string $runId): bool
    {
        if (RestoreRunRepository::isRestoreRun($runId)) {
            $run = RestoreRunRepository::get($runId);

            return $run !== null && ($run['status'] ?? '') === 'cancelled';
        }

        return BackupRunRepository::isCancelled($runId);
    }

    /** @param array<string, mixed> $body */
    public static function onProgress(string $runId, array $body, bool $renewLease = true): int
    {
        if (RestoreRunRepository::isRestoreRun($runId)) {
            return self::restoreProgress($runId, $body);
        }

        return self::backupProgress($runId, $body, $renewLease);
    }

    /**
     * Batched backup progress: one batch lease renewal, per-child progress without per-child leases.
     *
     * @param list<array<string, mixed>> $children
     */
    public static function onBatchProgress(string $batchRunId, string $nodeId, array $children): int
    {
        if ($batchRunId === '' || $nodeId === '') {
            return 0;
        }
        WorkerLeaseService::renewForBatch($batchRunId, $nodeId);
        Ms365BatchClaimRepository::recordProgress($batchRunId, $nodeId);

        $graphTenantBudget = 0;
        $tenantRecordId = 0;
        $azureTenantId = '';
        $sharedCumulative429 = 0;
        foreach ($children as $childBody) {
            if (!is_array($childBody)) {
                continue;
            }
            $runId = trim((string) ($childBody['run_id'] ?? ''));
            if ($runId === '') {
                continue;
            }
            if (Ms365RestoreWorkerHooks::isRunCancelled($runId)) {
                continue;
            }
            Ms365BatchClaimRepository::promoteBatchChildToRunning($runId, $nodeId);
            // Batch children share one graph.Client, so each reports the SAME
            // cumulative 429 count. Suppress per-child budget recording here and
            // record the shared client's throttle exactly once below (high-water
            // mark) to avoid multiplying every 429 by the child count.
            $graphTenantBudget = max($graphTenantBudget, self::backupProgress($runId, $childBody, false, false));
            $sharedCumulative429 = max($sharedCumulative429, (int) ($childBody['graph_429_hits'] ?? 0));
            if ($tenantRecordId === 0) {
                $existing = BackupRunRepository::get($runId) ?? [];
                $tenantRecordId = (int) ($existing['tenant_record_id'] ?? 0);
                $azureTenantId = self::azureTenantIdForTenantRecord($tenantRecordId);
            }
        }

        if ($azureTenantId !== '') {
            GraphTenantBudgetService::recordSharedThrottle($tenantRecordId, $azureTenantId, $sharedCumulative429);
            $graphTenantBudget = max($graphTenantBudget, GraphTenantBudgetService::workerShare($tenantRecordId, $azureTenantId));
        }

        return $graphTenantBudget;
    }

    /**
     * @param list<array<string, mixed>> $children
     */
    public static function onBatchComplete(string $batchRunId, string $nodeId, array $children): void
    {
        foreach ($children as $childBody) {
            if (!is_array($childBody)) {
                continue;
            }
            $runId = trim((string) ($childBody['run_id'] ?? ''));
            if ($runId === '') {
                continue;
            }
            self::backupComplete($runId, $childBody);
        }
        if ($batchRunId === '' || $nodeId === '') {
            return;
        }

        Ms365BatchRunRepository::syncFromChildren($batchRunId);

        $allChildren = Ms365BatchRunRepository::getChildrenForBatch($batchRunId);
        $aggregate = Ms365BatchRunRepository::aggregateStatus($allChildren);
        if (!in_array($aggregate, ['running', 'queued'], true)) {
            Ms365BatchClaimRepository::complete($batchRunId, $nodeId);
        }
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
    private static function backupProgress(string $runId, array $body, bool $renewLease = true, bool $recordTenantThrottle = true): int
    {
        $rawPhase = (string) ($body['phase'] ?? '');
        $message = (string) ($body['message'] ?? $rawPhase);
        $existing = BackupRunRepository::get($runId) ?? [];

        $incomingPercent = (float) ($body['percent'] ?? 0);
        $incomingItemsDone = (int) ($body['items_done'] ?? 0);
        $incomingItemsTotal = (int) ($body['items_total'] ?? 0);
        $incomingBytesHashed = (int) ($body['bytes_hashed'] ?? 0);
        $incomingBytesUploaded = (int) ($body['bytes_uploaded'] ?? 0);
        $incoming429 = (int) ($body['graph_429_hits'] ?? 0);
        $incomingAdaptive = (int) ($body['graph_adaptive_limit'] ?? 0);
        $incomingRequests = (int) ($body['graph_requests'] ?? 0);
        $existingChildStats = self::decodeChildStatsJson($existing);
        $existingChild429 = (int) ($existingChildStats['graph_429_hits'] ?? 0);
        $existingChildRequests = (int) ($existingChildStats['graph_requests'] ?? 0);
        $delta429 = max(0, $incoming429 - $existingChild429);
        $deltaRequests = max(0, $incomingRequests - $existingChildRequests);
        $effectivePhase = strtolower(trim($rawPhase !== '' ? $rawPhase : (string) ($existing['phase'] ?? '')));
        $graphSyncRequestLiveness = $effectivePhase === 'graph_sync' && $deltaRequests > 0;

        $noProgress = !empty($body['no_progress']);
        $throttleWaiting = !empty($body['throttle_waiting']);
        $tenantRecordId = (int) ($existing['tenant_record_id'] ?? 0);
        $azureTenantId = self::azureTenantIdForTenantRecord($tenantRecordId);
        if ($noProgress) {
            if ($throttleWaiting || $delta429 > 0 || $graphSyncRequestLiveness) {
                $fields = ['updated_at' => time()];
                if ($graphSyncRequestLiveness
                    && \WHMCS\Database\Capsule::schema()->hasColumn('ms365_backup_runs', 'last_progress_at')) {
                    $fields['last_progress_at'] = time();
                }
                if (\WHMCS\Database\Capsule::schema()->hasColumn('ms365_backup_runs', 'last_429_at')
                    && ($delta429 > 0
                        || ($throttleWaiting && Ms365BatchRunRepository::isGraphBoundPhase($effectivePhase)))) {
                    $fields['last_429_at'] = time();
                }
                if ($incoming429 > 0 || $incomingAdaptive > 0 || $incomingRequests > 0) {
                    $statsPatch = [];
                    if ($incoming429 > 0) {
                        $statsPatch['graph_429_hits'] = max(
                            $incoming429,
                            (int) self::decodeChildStatsJson($existing)['graph_429_hits'] ?? 0
                        );
                    }
                    if ($incomingAdaptive > 0) {
                        $statsPatch['graph_adaptive_limit'] = $incomingAdaptive;
                    }
                    if ($incomingRequests > 0) {
                        $statsPatch['graph_requests'] = max($incomingRequests, $existingChildRequests);
                    }
                    $encoded = self::encodeMergedChildStatsJson($existing, $statsPatch);
                    if ($encoded !== null) {
                        $fields['stats_json'] = $encoded;
                    }
                }
                BackupRunRepository::update($runId, $fields);
                if ($recordTenantThrottle && $azureTenantId !== '') {
                    GraphTenantBudgetService::recordTenant429($tenantRecordId, $azureTenantId, $delta429);
                }
            } elseif (Ms365BatchRunRepository::isUploadLikePhase($effectivePhase)) {
                // Kopia upload/hash can run for long stretches without byte deltas; keep
                // liveness fresh so reapers and the live UI do not treat the run as stalled.
                $uploadFields = ['updated_at' => time()];
                if (\WHMCS\Database\Capsule::schema()->hasColumn('ms365_backup_runs', 'last_progress_at')) {
                    $uploadFields['last_progress_at'] = time();
                }
                BackupRunRepository::update($runId, $uploadFields);
                if ($renewLease) {
                    WorkerLeaseService::renewForRun($runId);
                }
                WorkerClaimService::clearQueueOperationalMessage($runId);
            }

            return $azureTenantId !== ''
                ? GraphTenantBudgetService::workerShare($tenantRecordId, $azureTenantId)
                : 0;
        }

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

            $effectiveItemsDone = (int) ($fields['items_done'] ?? $storedItemsDone);
            $effectiveItemsTotal = (int) ($fields['items_total'] ?? $storedItemsTotal);
            if ($effectiveItemsTotal > 0 && $effectiveItemsDone > $effectiveItemsTotal) {
                $fields['items_done'] = $effectiveItemsTotal;
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

        if (!$isHeartbeat && \WHMCS\Database\Capsule::schema()->hasColumn('ms365_backup_runs', 'last_progress_at')) {
            $storedItemsDone = (int) ($existing['items_done'] ?? 0);
            $storedBytesHashed = (int) ($existing['bytes_hashed'] ?? 0);
            $storedBytesUploaded = (int) ($existing['bytes_uploaded'] ?? 0);
            $effectiveItemsDone = (int) ($fields['items_done'] ?? $storedItemsDone);
            $effectiveBytesHashed = (int) ($fields['bytes_hashed'] ?? $storedBytesHashed);
            $effectiveBytesUploaded = (int) ($fields['bytes_uploaded'] ?? $storedBytesUploaded);
            if ($effectiveItemsDone > $storedItemsDone
                || $effectiveBytesHashed > $storedBytesHashed
                || $effectiveBytesUploaded > $storedBytesUploaded) {
                $fields['last_progress_at'] = time();
            }
        }

        if ($graphSyncRequestLiveness
            && \WHMCS\Database\Capsule::schema()->hasColumn('ms365_backup_runs', 'last_progress_at')) {
            $fields['last_progress_at'] = time();
        }

        if (!empty($body['manifest_id'])) {
            $fields['manifest_id'] = (string) $body['manifest_id'];
        }

        $statsPatch = [];
        if ($incoming429 > 0 || $incomingAdaptive > 0 || $incomingRequests > 0) {
            if ($incoming429 > 0 || $incomingAdaptive > 0) {
                $statsPatch['graph_429_hits'] = max($incoming429, (int) self::decodeChildStatsJson($existing)['graph_429_hits'] ?? 0);
                if ($incomingAdaptive > 0) {
                    $statsPatch['graph_adaptive_limit'] = $incomingAdaptive;
                }
            }
            if ($incomingRequests > 0) {
                $statsPatch['graph_requests'] = max($incomingRequests, $existingChildRequests);
            }
        }
        $phasePatch = self::buildPhaseTimingStatsPatch($existing, $rawPhase, time(), $isHeartbeat);
        if ($phasePatch !== null) {
            $statsPatch = array_merge($statsPatch, $phasePatch);
        }
        if ($statsPatch !== []) {
            $encoded = self::encodeMergedChildStatsJson($existing, $statsPatch);
            if ($encoded !== null) {
                $fields['stats_json'] = $encoded;
            }
        }

        if (($delta429 > 0 || $throttleWaiting) && \WHMCS\Database\Capsule::schema()->hasColumn('ms365_backup_runs', 'last_429_at')) {
            $fields['last_429_at'] = time();
        }

        $checkpointStates = $body['checkpoint_delta_states'] ?? null;
        if (is_array($checkpointStates) && $checkpointStates !== []) {
            $tenantRecordId = (int) ($existing['tenant_record_id'] ?? 0);
            $physicalKey = (string) ($existing['physical_key'] ?? '');
            $e3JobId = trim((string) ($existing['e3_job_id'] ?? ''));
            if ($tenantRecordId > 0 && $physicalKey !== '') {
                DeltaStateRepository::saveStates(
                    $tenantRecordId,
                    $physicalKey,
                    $checkpointStates,
                    $e3JobId !== '' ? $e3JobId : null
                );
            }
            $encoded = self::encodeMergedChildStatsJson(
                array_merge($existing, isset($fields['stats_json']) ? ['stats_json' => $fields['stats_json']] : []),
                ['checkpoint_delta_states_saved_at' => time()]
            );
            if ($encoded !== null) {
                $fields['stats_json'] = $encoded;
            }
        }

        BackupRunRepository::update($runId, $fields);

        if (!$isHeartbeat) {
            if ($renewLease) {
                WorkerLeaseService::renewForRun($runId);
            }
            WorkerClaimService::clearQueueOperationalMessage($runId);
        } elseif (Ms365BatchRunRepository::isUploadLikePhase($effectivePhase)) {
            if ($renewLease) {
                WorkerLeaseService::renewForRun($runId);
            }
            if (\WHMCS\Database\Capsule::schema()->hasColumn('ms365_backup_runs', 'last_progress_at')) {
                BackupRunRepository::update($runId, [
                    'updated_at' => time(),
                    'last_progress_at' => time(),
                ]);
            }
            WorkerClaimService::clearQueueOperationalMessage($runId);
        }

        if ($recordTenantThrottle && $azureTenantId !== '') {
            GraphTenantBudgetService::recordTenant429($tenantRecordId, $azureTenantId, $delta429);
        }

        $batchRunId = $existing['e3_batch_run_id'] ?? BackupRunRepository::get($runId)['e3_batch_run_id'] ?? null;
        if (is_string($batchRunId) && $batchRunId !== '') {
            Ms365BatchRunRepository::updateLiveSnapshot($batchRunId);
        }

        if ($isHeartbeat) {
            return $azureTenantId !== ''
                ? GraphTenantBudgetService::workerShare($tenantRecordId, $azureTenantId)
                : 0;
        }

        $logMessage = CustomerFacingTextSanitizer::scrubLogMessage($message);
        if (!self::shouldPersistProgressLog($runId, $logMessage, $rawPhase)) {
            return $azureTenantId !== ''
                ? GraphTenantBudgetService::workerShare($tenantRecordId, $azureTenantId)
                : 0;
        }

        $logger = new ProgressLogger($runId);
        $logger->info($logMessage, [
            'percent' => $fields['percent'] ?? ($existing['percent'] ?? null),
            'bytes_hashed' => $body['bytes_hashed'] ?? null,
            'bytes_uploaded' => $body['bytes_uploaded'] ?? null,
            'graph_429_hits' => $incoming429 > 0 ? $incoming429 : null,
        ]);

        if ($incoming429 >= 5 && !$isHeartbeat) {
            $logger->warning('Microsoft Graph throttling detected', [
                'graph_429_hits' => $incoming429,
                'graph_adaptive_limit' => $incomingAdaptive > 0 ? $incomingAdaptive : null,
            ]);
        }

        return $azureTenantId !== ''
            ? GraphTenantBudgetService::workerShare($tenantRecordId, $azureTenantId)
            : 0;
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
    private static function restoreProgress(string $runId, array $body): int
    {
        $rawPhase = (string) ($body['phase'] ?? '');
        if ($rawPhase === 'graph_sync') {
            self::restoreFail(
                $runId,
                'Restore worker ran backup instead of restore (upgrade worker to 0.1.6+).'
            );

            return 0;
        }
        $existing = RestoreRunRepository::get($runId) ?? [];
        $incoming429 = (int) ($body['graph_429_hits'] ?? 0);
        $existing429 = (int) ($existing['graph_429_hits'] ?? 0);
        $delta429 = max(0, $incoming429 - $existing429);
        $throttleWaiting = !empty($body['throttle_waiting']);
        $tenantRecordId = (int) ($existing['tenant_record_id'] ?? 0);
        $azureTenantId = self::azureTenantIdForTenantRecord($tenantRecordId);

        $fields = [
            'status' => 'running',
            'phase' => CustomerFacingTextSanitizer::scrub($rawPhase),
            'items_done' => (int) ($body['items_done'] ?? 0),
            'items_total' => (int) ($body['items_total'] ?? 0),
        ];
        if (\WHMCS\Database\Capsule::schema()->hasColumn('ms365_restore_runs', 'items_skipped')) {
            $fields['items_skipped'] = (int) ($body['items_skipped'] ?? 0);
        }
        if ($incoming429 > 0 && \WHMCS\Database\Capsule::schema()->hasColumn('ms365_restore_runs', 'graph_429_hits')) {
            $fields['graph_429_hits'] = max($incoming429, $existing429);
        }
        if (($delta429 > 0 || $throttleWaiting)
            && \WHMCS\Database\Capsule::schema()->hasColumn('ms365_restore_runs', 'last_429_at')) {
            $fields['last_429_at'] = time();
        }
        RestoreRunRepository::update($runId, $fields);

        WorkerLeaseService::renewForRun($runId);

        if ($azureTenantId !== '') {
            GraphTenantBudgetService::recordTenant429($tenantRecordId, $azureTenantId, $delta429);
        }

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
            return $azureTenantId !== ''
                ? GraphTenantBudgetService::workerShare($tenantRecordId, $azureTenantId)
                : 0;
        }
        if (self::isRestoreFailureProgressMessage($logMessage)) {
            $logger->error($logMessage, $context);
        } else {
            $logger->info($logMessage, $context);
        }

        return $azureTenantId !== ''
            ? GraphTenantBudgetService::workerShare($tenantRecordId, $azureTenantId)
            : 0;
    }

    private static function azureTenantIdForTenantRecord(int $tenantRecordId): string
    {
        if ($tenantRecordId <= 0) {
            return '';
        }
        $record = TenantRecordRepository::getById($tenantRecordId);
        if ($record === null) {
            return '';
        }
        $creds = TenantRecordRepository::resolvedCredentialsForRecord($record);

        return trim((string) ($creds['tenant_id'] ?? ''));
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
        $existing = BackupRunRepository::get($runId) ?? [];
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
        $stats = [];
        if ($statsRaw !== '') {
            $decoded = json_decode($statsRaw, true);
            if (is_array($decoded)) {
                $stats = $decoded;
            }
        }
        $filesFromStats = (int) ($stats['files'] ?? 0);
        $finalItemCount = max(
            (int) ($existing['items_done'] ?? 0),
            (int) ($existing['items_total'] ?? 0),
            (int) ($body['items_done'] ?? 0),
            (int) ($body['items_total'] ?? 0),
            $filesFromStats,
        );
        if ($finalItemCount > 0) {
            $update['items_done'] = $finalItemCount;
            $update['items_total'] = $finalItemCount;
        }
        if ($stats !== []) {
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
            if (\WHMCS\Database\Capsule::schema()->hasColumn('ms365_backup_runs', 'stats_json')) {
                $existingForStats = BackupRunRepository::get($runId) ?? [];
                $merged = self::decodeChildStatsJson($existingForStats);
                $merged = array_merge($merged, $stats);
                unset($merged['kopia_upload_started_at']);
                $encoded = json_encode($merged, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
                if (is_string($encoded)) {
                    $update['stats_json'] = $encoded;
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
        $restoreMode = 'tenant';
        if ($run !== null) {
            if (\WHMCS\Database\Capsule::schema()->hasColumn('ms365_restore_runs', 'restore_mode')) {
                $restoreMode = (string) ($run['restore_mode'] ?? 'tenant');
            }
            $selection = json_decode((string) ($run['selection_json'] ?? ''), true);
            if (is_array($selection) && is_array($selection['items'] ?? null)) {
                $expectedItems = count($selection['items']);
            }
            if ($restoreMode === '' && is_array($selection)) {
                $restoreMode = (string) ($selection['restore_mode'] ?? 'tenant');
            }
        }

        if ($restoreMode === 'archive') {
            $objectKey = trim((string) ($stats['object_key'] ?? ''));
            $bytes = (int) ($stats['bytes'] ?? 0);
            if ($objectKey === '' || $bytes <= 0) {
                self::restoreFail($runId, 'Archive export finished without a downloadable archive.');

                return;
            }

            $ttlDays = Ms365ArchiveExportService::archiveExportTtlDays();
            $update = [
                'status' => 'success',
                'phase' => 'complete',
                'items_done' => max(1, (int) ($stats['files'] ?? $expectedItems)),
                'items_skipped' => 0,
                'finished_at' => $now,
            ];
            if (\WHMCS\Database\Capsule::schema()->hasColumn('ms365_restore_runs', 'archive_object_key')) {
                $update['archive_object_key'] = $objectKey;
            }
            if (\WHMCS\Database\Capsule::schema()->hasColumn('ms365_restore_runs', 'archive_bucket')) {
                $archiveBucket = trim((string) ($stats['bucket'] ?? ''));
                if ($archiveBucket === '' && $run !== null) {
                    $archiveBucket = trim((string) ($run['archive_bucket'] ?? ''));
                }
                $update['archive_bucket'] = $archiveBucket !== '' ? $archiveBucket : null;
            }
            if (\WHMCS\Database\Capsule::schema()->hasColumn('ms365_restore_runs', 'archive_size_bytes')) {
                $update['archive_size_bytes'] = $bytes;
            }
            if (\WHMCS\Database\Capsule::schema()->hasColumn('ms365_restore_runs', 'archive_expires_at')) {
                $update['archive_expires_at'] = $now + ($ttlDays * 86400);
            }

            RestoreRunRepository::update($runId, $update);
            JobQueueRepository::markDone($runId);
            Ms365BatchRunRepository::syncForRestoreChildRun($runId);

            $logger = new RestoreProgressLogger($runId);
            $files = (int) ($stats['files'] ?? 0);
            $summary = 'Archive export completed (' . $bytes . ' bytes)';
            if ($files > 0) {
                $summary .= ', ' . $files . ' file(s)';
            }
            $logger->info($summary, [
                'object_key' => $objectKey,
                'bytes' => $bytes,
                'files' => $files,
            ]);

            return;
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

    /** @return array<string, mixed> */
    private static function decodeChildStatsJson(array $run): array
    {
        if (!\WHMCS\Database\Capsule::schema()->hasColumn('ms365_backup_runs', 'stats_json')) {
            return [];
        }
        $raw = $run['stats_json'] ?? null;
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $patch
     */
    private static function encodeMergedChildStatsJson(array $existing, array $patch): ?string
    {
        if (!\WHMCS\Database\Capsule::schema()->hasColumn('ms365_backup_runs', 'stats_json')) {
            return null;
        }
        $merged = array_merge(self::decodeChildStatsJson($existing), $patch);
        $encoded = json_encode($merged, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

        return is_string($encoded) ? $encoded : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function buildPhaseTimingStatsPatch(
        array $existing,
        string $newPhase,
        int $now,
        bool $isHeartbeat,
    ): ?array {
        if (!\WHMCS\Database\Capsule::schema()->hasColumn('ms365_backup_runs', 'stats_json')) {
            return null;
        }
        $phase = strtolower(trim($newPhase));
        if ($phase === '') {
            return null;
        }
        $prevPhase = strtolower(trim((string) ($existing['phase'] ?? '')));
        $stats = self::decodeChildStatsJson($existing);
        $patch = [];
        $graphPhases = ['graph_sync', 'prior_snapshot'];

        if ($phase === 'kopia_upload' && in_array($prevPhase, $graphPhases, true) && !isset($stats['graph_sync_ms'])) {
            $startedAt = (int) ($existing['started_at'] ?? 0);
            if ($startedAt > 0) {
                $patch['graph_sync_ms'] = ($now - $startedAt) * 1000;
            }
            $patch['kopia_upload_started_at'] = $now;
        }

        if ($phase === 'kopia_upload' && !$isHeartbeat) {
            $kopiaStart = (int) ($patch['kopia_upload_started_at'] ?? $stats['kopia_upload_started_at'] ?? 0);
            if ($kopiaStart <= 0 && $phase !== $prevPhase) {
                $kopiaStart = $now;
                $patch['kopia_upload_started_at'] = $now;
            }
            if ($kopiaStart > 0) {
                $patch['kopia_snapshot_ms'] = ($now - $kopiaStart) * 1000;
            }
        }

        return $patch === [] ? null : $patch;
    }

    private static function backupFail(string $runId, string $message): void
    {
        $now = time();
        $customerMessage = Ms365CustomerError::message(new \RuntimeException($message));
        $requeued = JobQueueRepository::markFailed($runId, $message);
        if (!$requeued) {
            BackupRunRepository::update($runId, [
                'status' => 'error',
                'error_message' => $customerMessage,
                'finished_at' => $now,
                'updated_at' => $now,
            ]);
        }
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
