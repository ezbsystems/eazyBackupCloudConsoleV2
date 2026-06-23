<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\HelperController;

final class WorkerClaimService
{
    /** Must exceed worker progress_heartbeat_seconds (default 60) to avoid false orphans. */
    private const ORPHAN_STALE_SECONDS = 120;

    /** Fast reclaim for never-started claims (no progress posted). */
    private const ORPHAN_UNACKED_SECONDS = 60;

    /** Requeue running claims with no real items/bytes progress for this long (even with a fresh lease). */
    private const STALE_PROGRESS_SILENCE_SECONDS = 1800;

    /** Run IDs the control plane considers actively claimed by this worker. */
    public static function activeClaimRunIds(string $nodeId): array
    {
        if ($nodeId === '' || !Capsule::schema()->hasTable('ms365_job_queue')) {
            return [];
        }

        return Capsule::table('ms365_job_queue')
            ->where('worker_node_id', $nodeId)
            ->where('status', 'running')
            ->orderBy('claimed_at')
            ->pluck('run_id')
            ->map(static fn ($id) => (string) $id)
            ->all();
    }

    public static function runningClaimCountForNode(string $nodeId): int
    {
        return count(self::activeClaimRunIds($nodeId));
    }

    /**
     * When the worker reports in-memory load but owns no queue claims, treat the node as idle
     * for fleet gates (deploy, orphan release, claim capacity) until the worker reconciles.
     */
    public static function effectiveReportedLoad(string $nodeId, int $reportedLoad): int
    {
        if ($reportedLoad <= 0) {
            return 0;
        }
        $queueLoad = self::runningClaimCountForNode($nodeId);

        return $queueLoad === 0 ? 0 : max($reportedLoad, $queueLoad);
    }

    /** @return int Nodes whose stored load was corrected to match queue truth */
    public static function reconcileGhostNodeLoads(): int
    {
        $fixed = 0;
        foreach (WorkerNodeRepository::listNodes(['active', 'draining', 'offline', 'registering']) as $node) {
            $nodeId = (string) ($node['node_id'] ?? '');
            if ($nodeId === '') {
                continue;
            }
            $reported = (int) ($node['current_load'] ?? 0);
            $effective = self::effectiveReportedLoad($nodeId, $reported);
            if ($effective !== $reported) {
                WorkerNodeRepository::heartbeat($nodeId, $effective, '', null, 0);
                $fixed++;
            }
        }

        return $fixed;
    }

    public static function claimNext(string $nodeId, ?array $claimHint = null): ?array
    {
        self::releaseOrphanedClaimsForAllNodes(self::ORPHAN_STALE_SECONDS);
        self::releaseExpiredLeases();
        self::recoverStaleRunning();
        self::reconcileZombieRuns();

        $node = WorkerNodeRepository::get($nodeId);
        if ($node === null || ($node['status'] ?? '') !== 'active') {
            return null;
        }

        if (self::isBlockedByBaselineVersion($node)) {
            return null;
        }

        $effectiveLoad = self::effectiveReportedLoad(
            $nodeId,
            (int) ($node['current_load'] ?? 0)
        );
        if ($effectiveLoad >= (int) ($node['max_concurrent_runs'] ?? 1)) {
            return null;
        }

        if (self::countPlatformRunning() >= Ms365EngineConfig::platformMaxConcurrent()) {
            return null;
        }

        $backupQuery = self::buildBackupClaimQuery();
        $fairScheduling = Ms365EngineConfig::fairSchedulingEnabled();
        $candidates = $fairScheduling
            ? self::fetchBackupClaimCandidatesFair($backupQuery)
            : self::fetchBackupClaimCandidatesFifo($backupQuery);

        $restoreCandidates = collect();
        if (self::restoreClaimQueryReady()) {
            $restoreQ = self::buildRestoreClaimQuery();
            $restoreCandidates = $fairScheduling
                ? self::fetchRestoreClaimCandidatesFair($restoreQ)
                : self::fetchRestoreClaimCandidatesFifo($restoreQ);
        }

        $merged = $candidates->merge($restoreCandidates);
        if ($fairScheduling) {
            $merged = $merged->sortBy([
                ['priority', 'asc'],
                ['fair_rank', 'asc'],
                ['id', 'asc'],
            ])->values();
        } else {
            $merged = $merged->sortBy([
                ['priority', 'asc'],
                ['id', 'asc'],
            ])->values();
        }

        $acceptHeavy = $claimHint === null || (bool) ($claimHint['accept_heavy'] ?? true);

        foreach ($merged as $candidate) {
            $physicalKey = (string) ($candidate->physical_key ?? '');
            if (!$acceptHeavy && self::isHeavyPhysicalKey($physicalKey)) {
                continue;
            }
            $clientId = (int) ($candidate->whmcs_client_id ?? 0);
            $tenantRecordId = (int) ($candidate->tenant_record_id ?? 0);
            if ($clientId > 0 && self::countRunningForClient($clientId) >= Ms365EngineConfig::perClientMaxConcurrent()) {
                continue;
            }
            if ($tenantRecordId > 0 && self::countRunningForTenant($tenantRecordId) >= Ms365EngineConfig::perTenantMaxConcurrent()) {
                continue;
            }
            $jobType = (string) ($candidate->job_type ?? 'backup');
            if ($jobType !== 'restore') {
                if (self::isSupersededRun((string) ($candidate->run_id ?? ''), $tenantRecordId, (string) ($candidate->physical_key ?? ''))) {
                    self::failSupersededRun((string) $candidate->run_id);
                    continue;
                }
            }

            $runId = (string) $candidate->run_id;
            $now = time();
            $lease = $now + Ms365EngineConfig::leaseSeconds();
            $updated = Capsule::table('ms365_job_queue')
                ->where('id', $candidate->id)
                ->where('status', 'queued')
                ->update([
                    'status' => 'running',
                    'worker_node_id' => $nodeId,
                    'claimed_at' => $now,
                    'lease_expires_at' => $lease,
                    'started_at' => $now,
                    'attempts' => (int) $candidate->attempts + 1,
                ]);
            if ($updated === 0) {
                continue;
            }

            Ms365WorkerLogRepository::recordAssignment($runId, $nodeId);

            if ($jobType === 'restore') {
                RestoreRunRepository::update($runId, [
                    'status' => 'running',
                    'phase' => 'claimed',
                    'started_at' => $now,
                ]);
            } else {
                if (BackupRunRepository::isCancelled($runId)) {
                    self::rollbackClaim($runId, 'Run cancelled');

                    continue;
                }
                $runUpdate = [
                    'status' => 'running',
                    'started_at' => $now,
                    'engine_mode' => 'kopia',
                ];
                if (Capsule::schema()->hasColumn('ms365_backup_runs', 'last_progress_at')) {
                    $runUpdate['last_progress_at'] = $now;
                }
                BackupRunRepository::update($runId, $runUpdate);
            }

            try {
                if ($jobType === 'restore') {
                    return self::buildRestoreRunPayload($runId);
                }

                return self::buildRunPayload($runId);
            } catch (\Throwable $e) {
                $message = $e->getMessage();
                if (self::isPermanentAuthFailure($message)) {
                    self::failClaimedRun($runId, $message);
                } else {
                    self::rollbackClaim($runId, $message);
                }
            }
        }

        return null;
    }

    private static function isSupersededRun(string $runId, int $tenantRecordId, string $physicalKey): bool
    {
        if ($runId === '' || $tenantRecordId <= 0 || $physicalKey === '') {
            return false;
        }
        $run = BackupRunRepository::get($runId);
        if ($run === null) {
            return false;
        }
        $createdAt = (int) ($run['created_at'] ?? 0);

        return Capsule::table('ms365_backup_runs')
            ->where('tenant_record_id', $tenantRecordId)
            ->where('physical_key', $physicalKey)
            ->where('status', 'success')
            ->where('created_at', '>', $createdAt)
            ->exists();
    }

    private static function failSupersededRun(string $runId): void
    {
        $message = 'Superseded by a newer successful backup run';
        $now = time();
        BackupRunRepository::update($runId, [
            'status' => 'error',
            'error_message' => $message,
            'finished_at' => $now,
            'updated_at' => $now,
        ]);
        // A newer run already succeeded; retrying this one is pointless.
        JobQueueRepository::markTerminalFailed($runId, $message);
        Ms365BatchRunRepository::syncForChildRun($runId);
    }

    private static function isPermanentAuthFailure(string $message): bool
    {
        return preg_match('/AADSTS\d+/i', $message) === 1
            || stripos($message, 'unauthorized_client') !== false
            || stripos($message, 'invalid_client') !== false
            || stripos($message, 'Tenant is not connected') !== false
            || stripos($message, 'Tenant record not found') !== false;
    }

    private static function rollbackClaim(string $runId, string $message): void
    {
        $now = time();
        Capsule::table('ms365_job_queue')
            ->where('run_id', $runId)
            ->where('status', 'running')
            ->update([
                'status' => 'queued',
                'worker_node_id' => null,
                'claimed_at' => null,
                'lease_expires_at' => null,
                'scheduled_at' => $now,
                'error_message' => mb_substr($message, 0, 500),
            ]);
        self::rollbackAttemptIfUnacked($runId);
        Ms365WorkerLogRepository::releaseAssignment($runId, 'rollback');
        if (RestoreRunRepository::isRestoreRun($runId)) {
            RestoreRunRepository::update($runId, [
                'status' => 'queued',
            ]);
        } else {
            BackupRunRepository::update($runId, [
                'status' => 'queued',
                'updated_at' => $now,
            ]);
        }
    }

    private static function failClaimedRun(string $runId, string $message): void
    {
        if (RestoreRunRepository::isRestoreRun($runId)) {
            Ms365RestoreWorkerHooks::onFail($runId, $message);

            return;
        }
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
    }

    public static function buildRunPayload(string $runId): ?array
    {
        $run = BackupRunRepository::get($runId);
        if ($run === null) {
            return null;
        }
        $tenantRecordId = (int) ($run['tenant_record_id'] ?? 0);
        $record = TenantRecordRepository::getById($tenantRecordId);
        if ($record === null) {
            throw new \RuntimeException('Tenant record not found for run.');
        }

        $graphToken = self::graphTokenForTenantRecord($record);
        $creds = TenantRecordRepository::resolvedCredentialsForRecord($record);

        $storage = BackupStorageFactory::createForTenantRecord($record);
        $e3JobId = trim((string) ($run['e3_job_id'] ?? ''));
        $dest = Ms365JobDestinationService::resolveForRun($run, $record);

        $scope = [];
        $scopeRaw = (string) ($run['scope_json'] ?? '');
        if ($scopeRaw !== '') {
            $decoded = json_decode($scopeRaw, true);
            if (is_array($decoded)) {
                $scope = $decoded;
            }
        }

        $logical = [];
        $logicalRaw = (string) ($run['logical_sources_json'] ?? '');
        if ($logicalRaw !== '') {
            $decoded = json_decode($logicalRaw, true);
            if (is_array($decoded)) {
                $logical = $decoded;
            }
        }

        $physicalKey = (string) ($run['physical_key'] ?? '');
        $parentPhysicalKey = PhysicalKeyHelper::aggregateParentKey($physicalKey, $run);
        $shard = PhysicalKeyHelper::parseShard($physicalKey);
        $previousManifest = KopiaRepoBootstrapService::latestManifestForSource($tenantRecordId, $physicalKey, $e3JobId !== '' ? $e3JobId : null);
        $deltaStates = DeltaStateRepository::getStatesForSource($tenantRecordId, $physicalKey, $e3JobId !== '' ? $e3JobId : null);
        if ($deltaStates === [] && $parentPhysicalKey !== $physicalKey) {
            $deltaStates = DeltaStateRepository::getStatesForSource($tenantRecordId, $parentPhysicalKey, $e3JobId !== '' ? $e3JobId : null);
        }
        if ($deltaStates === []) {
            $legacy = self::latestDeltaStates($tenantRecordId, $physicalKey, $e3JobId);
            if (is_array($legacy) && $legacy !== []) {
                $deltaStates = $legacy;
            } elseif ($parentPhysicalKey !== $physicalKey) {
                $legacyParent = self::latestDeltaStates($tenantRecordId, $parentPhysicalKey, $e3JobId);
                if (is_array($legacyParent)) {
                    $deltaStates = $legacyParent;
                }
            }
        }
        $scopeFlags = self::scopeFlagsForWorker($scope);

        $driveId = self::driveIdForRun($run, $scope, $logical);
        if ($driveId !== '' && str_starts_with(PhysicalKeyHelper::baseKey($physicalKey), 'user:')) {
            $driveKey = 'drive:' . $driveId;
            $driveDeltas = DeltaStateRepository::getStatesForSource($tenantRecordId, $driveKey, $e3JobId !== '' ? $e3JobId : null);
            if ($driveDeltas === []) {
                $driveDeltas = self::latestDeltaStates($tenantRecordId, $driveKey, $e3JobId);
                if (!is_array($driveDeltas)) {
                    $driveDeltas = [];
                }
            }
            if (is_array($driveDeltas['onedrive'] ?? null)) {
                $deltaStates['onedrive'] = array_merge($deltaStates['onedrive'] ?? [], $driveDeltas['onedrive']);
            }
        }

        $workloads = self::workloadsForRun($run, $scope);
        $siteId = self::siteIdForRun($run, $scope);
        $payload = [
            'run_id' => $runId,
            'job_type' => 'backup',
            'tenant_record_id' => $tenantRecordId,
            'whmcs_client_id' => (int) ($run['whmcs_client_id'] ?? 0),
            'azure_tenant_id' => (string) ($creds['tenant_id'] ?? ''),
            'resource_id' => (string) ($run['resource_id'] ?? ''),
            'resource_type' => (string) ($run['resource_type'] ?? ''),
            'physical_key' => $physicalKey,
            'parent_physical_key' => $parentPhysicalKey,
            'graph_id' => (string) ($run['graph_id'] ?? $run['user_id'] ?? ''),
            'drive_id' => $driveId,
            'site_id' => $siteId,
            'list_id' => self::listIdForRun($run, $scope),
            'excluded_list_ids' => self::excludedListIdsForRun($scope),
            'scope' => (object) $scopeFlags,
            'logical_sources' => $logical,
            'graph_token' => $graphToken,
            'graph_region' => (string) ($creds['region'] ?? 'GlobalPublicCloud'),
            'dest_endpoint' => $dest['endpoint'],
            'dest_region' => $dest['region'],
            'dest_bucket' => $dest['bucket'],
            'dest_prefix' => $dest['prefix'],
            'dest_access_key' => $dest['access_key'],
            'dest_secret_key' => $dest['secret_key'],
            'repo_password' => $dest['repo_password'],
            'kopia_repo_id' => $dest['kopia_repo_id'],
            'previous_manifest_id' => $previousManifest,
            'incremental_enabled' => $previousManifest !== '' && $deltaStates !== [],
            'delta_states' => $deltaStates !== [] ? $deltaStates : new \stdClass(),
            'engine_mode' => Ms365EngineConfig::engineMode(),
            'workloads' => $workloads,
            'graph_pagination' => Ms365EngineConfig::paginationLimitsForWorkloads($workloads),
            'lease_expires_at' => WorkerLeaseService::leaseExpiresAt($runId),
            'kopia_source_path' => PhysicalKeyHelper::kopiaSourcePath((string) ($creds['tenant_id'] ?? ''), $physicalKey, $scope),
            'graph_tenant_budget' => GraphTenantBudgetService::workerShare(
                $tenantRecordId,
                (string) ($creds['tenant_id'] ?? '')
            ),
        ];
        if ($shard !== null) {
            $shardMeta = [];
            if (is_array($scope['_shard'] ?? null)) {
                $shardMeta = $scope['_shard'];
            }
            $payload['shard'] = [
                'index' => (int) ($shardMeta['index'] ?? $shard['index'] ?? 0),
                'total' => (int) ($shardMeta['total'] ?? $shard['total'] ?? 0),
                'kind' => (string) ($shardMeta['kind'] ?? $shard['kind'] ?? 'range'),
                'segment' => (string) ($shardMeta['segment'] ?? $shard['segment'] ?? ''),
                'parent_physical_key' => (string) ($shardMeta['parent_physical_key'] ?? $parentPhysicalKey),
            ];
        }

        return $payload;
    }

    /**
     * Issue a fresh Graph access token for an active backup or restore run (mid-run refresh).
     *
     * @return array{graph_token: string, expires_in: int}
     */
    public static function refreshGraphTokenForRun(string $runId): array
    {
        $runId = trim($runId);
        if ($runId === '') {
            throw new \RuntimeException('run_id required.');
        }

        if (RestoreRunRepository::isRestoreRun($runId)) {
            $run = RestoreRunRepository::get($runId);
        } else {
            $run = BackupRunRepository::get($runId);
        }
        if ($run === null) {
            throw new \RuntimeException('Run not found.');
        }
        if ((string) ($run['status'] ?? '') !== 'running') {
            throw new \RuntimeException('Run is not active.');
        }

        $tenantRecordId = (int) ($run['tenant_record_id'] ?? 0);
        $record = TenantRecordRepository::getById($tenantRecordId);
        if ($record === null) {
            throw new \RuntimeException('Tenant record not found for run.');
        }

        return [
            'graph_token' => self::graphTokenForTenantRecord($record),
            'expires_in' => 3600,
        ];
    }

    /**
     * @param array<string, mixed> $record
     */
    private static function graphTokenForTenantRecord(array $record): string
    {
        $creds = TenantRecordRepository::resolvedCredentialsForRecord($record);
        $tokenProvider = new TokenProvider(
            $creds['region'],
            $creds['tenant_id'],
            $creds['client_id'],
            $creds['client_secret'],
        );

        return $tokenProvider->getAccessToken();
    }

    /**
     * Most recent successful delta_states for a source, emitted verbatim into the claim payload.
     * Returns an object (stdClass) when none exist so JSON encodes to {} for the Go map decoder.
     *
     * @return \stdClass|array<string, array<string, string>>
     */
    private static function latestDeltaStates(int $tenantRecordId, string $physicalKey, ?string $e3JobId = null)
    {
        $empty = new \stdClass();
        if ($tenantRecordId <= 0 || $physicalKey === '') {
            return $empty;
        }
        if (!Capsule::schema()->hasColumn('ms365_backup_runs', 'delta_states_json')) {
            return $empty;
        }
        $q = Capsule::table('ms365_backup_runs')
            ->where('tenant_record_id', $tenantRecordId)
            ->where('physical_key', $physicalKey)
            ->where('status', 'success')
            ->whereNotNull('delta_states_json')
            ->where('delta_states_json', '!=', '');
        if ($e3JobId !== null && $e3JobId !== '' && Capsule::schema()->hasColumn('ms365_backup_runs', 'e3_job_id')) {
            $job = Ms365JobDestinationService::loadJobRow($e3JobId);
            $tenant = TenantRecordRepository::getById($tenantRecordId);
            if ($job !== null && $tenant !== null && Ms365JobDestinationService::isLegacySharedBucket($job, $tenant)) {
                $q->where(function ($sub) use ($e3JobId): void {
                    $sub->where('e3_job_id', $e3JobId)->orWhereNull('e3_job_id')->orWhere('e3_job_id', '');
                });
            } else {
                $q->where('e3_job_id', $e3JobId);
            }
        }
        $raw = $q->orderByDesc('finished_at')->value('delta_states_json');
        if (!is_string($raw) || $raw === '') {
            return $empty;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || $decoded === []) {
            return $empty;
        }

        return $decoded;
    }

    public static function buildRestoreRunPayload(string $restoreRunId): ?array
    {
        $run = RestoreRunRepository::get($restoreRunId);
        if ($run === null) {
            return null;
        }
        $tenantRecordId = (int) ($run['tenant_record_id'] ?? 0);
        $record = TenantRecordRepository::getById($tenantRecordId);
        if ($record === null) {
            throw new \RuntimeException('Tenant record not found for restore.');
        }

        $graphToken = self::graphTokenForTenantRecord($record);
        $creds = TenantRecordRepository::resolvedCredentialsForRecord($record);

        $dest = self::destinationForTenantRecord($record);
        $backupRunId = trim((string) ($run['backup_run_id'] ?? ''));
        if ($backupRunId !== '') {
            $backupRun = BackupRunRepository::get($backupRunId);
            if (is_array($backupRun)) {
                $dest = Ms365JobDestinationService::resolveForRun($backupRun, $record);
            }
        }

        $selection = [];
        $selectionRaw = (string) ($run['selection_json'] ?? '');
        if ($selectionRaw !== '') {
            $decoded = json_decode($selectionRaw, true);
            if (is_array($decoded)) {
                $selection = $decoded;
            }
        }

        $items = $selection['items'] ?? [];
        $targets = $selection['targets'] ?? [];
        if (!is_array($items)) {
            $items = [];
        }
        if (!is_array($targets)) {
            $targets = [];
        }

        $restoreItems = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $restoreItems[] = [
                'child_run_id' => (string) ($item['child_run_id'] ?? ''),
                'manifest_id' => (string) ($item['manifest_id'] ?? $run['source_manifest_id'] ?? ''),
                'path' => (string) ($item['path'] ?? ''),
                'path_prefix' => (string) ($item['path_prefix'] ?? ''),
                'type' => (string) ($item['type'] ?? ''),
            ];
        }

        $restoreTargets = [];
        foreach ($targets as $target) {
            if (!is_array($target)) {
                continue;
            }
            $restoreTargets[] = [
                'resource_id' => (string) ($target['resource_id'] ?? $run['target_resource_id'] ?? ''),
                'graph_id' => (string) ($target['graph_id'] ?? $run['target_graph_id'] ?? ''),
                'resource_type' => (string) ($target['resource_type'] ?? $run['resource_type'] ?? 'user'),
            ];
        }
        if ($restoreTargets === [] && trim((string) ($run['target_graph_id'] ?? '')) !== '') {
            $restoreTargets[] = [
                'resource_id' => (string) ($run['target_resource_id'] ?? ''),
                'graph_id' => (string) ($run['target_graph_id'] ?? ''),
                'resource_type' => (string) ($run['resource_type'] ?? 'user'),
            ];
        }

        $driveId = self::driveIdForRestoreRun($run, $restoreItems);

        $restoreMode = 'tenant';
        if (Capsule::schema()->hasColumn('ms365_restore_runs', 'restore_mode')) {
            $restoreMode = trim((string) ($run['restore_mode'] ?? ''));
        }
        if ($restoreMode === '') {
            $restoreMode = trim((string) ($selection['restore_mode'] ?? 'tenant'));
        }
        if ($restoreMode !== 'archive') {
            $restoreMode = 'tenant';
        }

        $payload = [
            'run_id' => $restoreRunId,
            'job_type' => 'restore',
            'restore_mode' => $restoreMode,
            'tenant_record_id' => $tenantRecordId,
            'whmcs_client_id' => (int) ($run['whmcs_client_id'] ?? 0),
            'azure_tenant_id' => (string) ($creds['tenant_id'] ?? ''),
            'graph_id' => (string) ($run['target_graph_id'] ?? ''),
            'resource_type' => (string) ($run['resource_type'] ?? ''),
            'drive_id' => $driveId,
            'graph_token' => $graphToken,
            'graph_region' => (string) ($creds['region'] ?? 'GlobalPublicCloud'),
            'dest_endpoint' => $dest['endpoint'],
            'dest_region' => $dest['region'],
            'dest_bucket' => $dest['bucket'],
            'dest_prefix' => $dest['prefix'],
            'dest_access_key' => $dest['access_key'],
            'dest_secret_key' => $dest['secret_key'],
            'repo_password' => $dest['repo_password'],
            'kopia_repo_id' => $dest['kopia_repo_id'],
            'source_manifest_id' => (string) ($run['source_manifest_id'] ?? ''),
            'engine_mode' => Ms365EngineConfig::engineMode(),
            'restore_selection' => [
                'items' => $restoreItems,
                'targets' => $restoreTargets,
                'conflict_policy' => (string) ($run['conflict_policy'] ?? 'skip_duplicates'),
                'restore_mode' => $restoreMode,
            ],
            'lease_expires_at' => WorkerLeaseService::leaseExpiresAt($restoreRunId),
        ];

        if ($restoreMode === 'archive') {
            $archiveExport = [
                'object_key' => Ms365ArchiveExportService::precomputedObjectKey($restoreRunId),
                'bucket' => (string) ($dest['bucket'] ?? ''),
                'prefix' => 'exports/',
                'compression' => 'store',
            ];
            $payload['archive_export'] = $archiveExport;
            $payload['restore_selection']['archive_export'] = $archiveExport;
        }

        return $payload;
    }

    /** @return array<string, bool> */
    private static function workloadsForRun(array $run, array $scope): array
    {
        $flags = Ms365EngineConfig::workloadFlags();
        $physical = PhysicalKeyHelper::baseKey((string) ($run['physical_key'] ?? ''));
        if ($physical === '') {
            return $flags;
        }

        $only = null;
        if (str_starts_with($physical, 'user:') || str_starts_with($physical, 'mailbox:')) {
            $only = ['mail', 'calendar', 'contacts', 'tasks'];
            if ((bool) ($scope['onedrive'] ?? false) || (bool) ($scope['files'] ?? false)) {
                $only[] = 'onedrive';
            }
        } elseif (str_starts_with($physical, 'drive:') || str_starts_with($physical, 'onedrive:')) {
            $siteId = trim((string) ($scope['_site_id'] ?? ''));
            if ($siteId !== '') {
                $only = ['sharepoint'];
            } else {
                $only = ['onedrive'];
            }
        } elseif (str_starts_with($physical, 'site:')) {
            $only = [];
            $filesEnabled = !array_key_exists('files', $scope) || (bool) $scope['files'];
            $listsEnabled = !array_key_exists('lists', $scope) || (bool) $scope['lists'];
            if ($filesEnabled) {
                $only[] = 'sharepoint';
            }
            if ($listsEnabled) {
                $only[] = 'sharepoint_lists';
            }
        } elseif (str_starts_with($physical, 'list:')) {
            $only = ['sharepoint_lists'];
        } elseif (str_starts_with($physical, 'team:') || str_starts_with($physical, 'channel:')) {
            $only = ['teams'];
        } elseif (str_starts_with($physical, 'planner:')) {
            $only = ['planner'];
        } elseif (str_starts_with($physical, 'onenote:')) {
            $only = ['onenote'];
        } elseif (str_starts_with($physical, 'directory:')) {
            $only = ['directory'];
        }

        if ($only === null) {
            return $flags;
        }

        if ($only === []) {
            return array_fill_keys(array_keys($flags), false);
        }

        $platform = Ms365EngineConfig::workloadFlags();
        $narrowed = array_fill_keys(array_keys($flags), false);
        foreach ($only as $w) {
            $narrowed[$w] = (bool) ($platform[$w] ?? false);
            if (in_array($w, ['mail', 'calendar', 'contacts', 'tasks'], true) && array_key_exists($w, $scope)) {
                $narrowed[$w] = $narrowed[$w] && (bool) $scope[$w];
            }
            if ($w === 'onedrive' && (array_key_exists('onedrive', $scope) || array_key_exists('files', $scope))) {
                $narrowed[$w] = $narrowed[$w] && ((bool) ($scope['onedrive'] ?? false) || (bool) ($scope['files'] ?? false));
            }
        }

        return $narrowed;
    }

    /**
     * @param array<string, mixed> $run
     * @param array<string, mixed> $scope
     * @param list<array<string, mixed>> $logical
     */
    private static function driveIdForRun(array $run, array $scope, array $logical): string
    {
        $fromScope = trim((string) ($scope['_drive_id'] ?? ''));
        if ($fromScope !== '') {
            return $fromScope;
        }
        foreach ($logical as $source) {
            if (!is_array($source)) {
                continue;
            }
            if ((string) ($source['resource_type'] ?? '') !== TenantResource::TYPE_USER_ONEDRIVE) {
                continue;
            }
            $id = (string) ($source['id'] ?? '');
            if ($id !== '') {
                $graphId = TenantResource::graphIdFromResourceId($id);
                if ($graphId !== '') {
                    return $graphId;
                }
            }
        }
        $physical = PhysicalKeyHelper::baseKey((string) ($run['physical_key'] ?? ''));
        if (str_starts_with($physical, 'drive:')) {
            $fromScope = trim((string) ($scope['_drive_id'] ?? ''));
            if ($fromScope !== '') {
                return $fromScope;
            }

            return substr($physical, 6);
        }

        return '';
    }

    /**
     * @param array<string, mixed> $run
     * @param array<string, mixed> $scope
     */
    private static function siteIdForRun(array $run, array $scope): string
    {
        $fromScope = trim((string) ($scope['_site_id'] ?? ''));
        if ($fromScope !== '') {
            return $fromScope;
        }
        $physical = PhysicalKeyHelper::baseKey((string) ($run['physical_key'] ?? ''));
        if (str_starts_with($physical, 'site:')) {
            return substr($physical, 5);
        }

        return '';
    }

    /**
     * @param array<string, mixed> $run
     * @param array<string, mixed> $scope
     */
    private static function listIdForRun(array $run, array $scope): string
    {
        $fromScope = trim((string) ($scope['_list_id'] ?? ''));
        if ($fromScope !== '') {
            return $fromScope;
        }
        $physical = PhysicalKeyHelper::baseKey((string) ($run['physical_key'] ?? ''));
        if (str_starts_with($physical, 'list:')) {
            return substr($physical, 5);
        }

        return '';
    }

    /**
     * @param array<string, mixed> $scope
     * @return list<string>
     */
    private static function excludedListIdsForRun(array $scope): array
    {
        $raw = $scope['_excluded_list_ids'] ?? [];
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $id) {
            $id = trim((string) $id);
            if ($id !== '') {
                $out[] = $id;
            }
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $restoreItems
     */
    private static function driveIdForRestoreRun(array $run, array $restoreItems): string
    {
        $backupRunId = trim((string) ($run['backup_run_id'] ?? ''));
        if ($backupRunId !== '') {
            $backupRun = BackupRunRepository::get($backupRunId);
            if (is_array($backupRun)) {
                $scopeRaw = (string) ($backupRun['scope_json'] ?? '');
                $scope = $scopeRaw !== '' ? (json_decode($scopeRaw, true) ?: []) : [];
                $logicalRaw = (string) ($backupRun['logical_sources_json'] ?? '');
                $logical = $logicalRaw !== '' ? (json_decode($logicalRaw, true) ?: []) : [];
                $driveId = self::driveIdForRun($backupRun, is_array($scope) ? $scope : [], is_array($logical) ? $logical : []);
                if ($driveId !== '') {
                    return $driveId;
                }
            }
        }

        foreach ($restoreItems as $item) {
            $path = (string) ($item['path'] ?? $item['path_prefix'] ?? '');
            if ($path !== '' && preg_match('#/drives/([^/]+)/#', $path, $m) === 1) {
                return (string) $m[1];
            }
        }

        return '';
    }

    /** @return array{endpoint: string, region: string, bucket: string, prefix: string, access_key: string, secret_key: string, repo_password: string, kopia_repo_id: string} */
    public static function destinationForTenantRecord(array $record): array
    {
        $dest = Ms365JobDestinationService::resolveLegacyTenantBucket($record);

        return [
            'endpoint' => $dest['endpoint'],
            'region' => $dest['region'],
            'bucket' => $dest['bucket'],
            'prefix' => $dest['prefix'],
            'access_key' => $dest['access_key'],
            'secret_key' => $dest['secret_key'],
            'repo_password' => $dest['repo_password'],
            'kopia_repo_id' => $dest['kopia_repo_id'],
        ];
    }

    /** @return array{endpoint: string, region: string, bucket: string, prefix: string, access_key: string, secret_key: string, repo_password: string, kopia_repo_id: string} */
    private static function resolveDestination(array $record, BackupStorageInterface $storage): array
    {
        return self::destinationForTenantRecord($record);
    }

    public static function releaseExpiredLeases(): int
    {
        $now = time();
        $rows = Capsule::table('ms365_job_queue')
            ->where('status', 'running')
            ->whereNotNull('lease_expires_at')
            ->where('lease_expires_at', '<', $now)
            ->pluck('run_id')
            ->all();
        if ($rows === []) {
            return 0;
        }
        self::requeueRuns($rows, 'Lease expired; re-queued');

        return count($rows);
    }

    /**
     * Fail restore runs still marked running on a worker that reports zero load
     * (worker exited without sending complete/fail).
     */
    public static function failOrphanedRestoreRunsForNode(string $nodeId, int $reportedLoad, int $staleSeconds = 180): int
    {
        if ($nodeId === '' || $reportedLoad > 0 || !Capsule::schema()->hasTable('ms365_restore_runs')) {
            return 0;
        }

        $cutoff = time() - max(60, $staleSeconds);
        $rows = Capsule::table('ms365_job_queue as q')
            ->join('ms365_restore_runs as r', 'r.id', '=', 'q.run_id')
            ->where('q.status', 'running')
            ->where('q.worker_node_id', $nodeId)
            ->where('q.claimed_at', '<', $cutoff)
            ->select(['q.run_id', 'r.e3_batch_run_id'])
            ->get();

        $count = 0;
        foreach ($rows as $row) {
            $runId = (string) ($row->run_id ?? '');
            if ($runId === '') {
                continue;
            }
            Ms365RestoreWorkerHooks::onFail(
                $runId,
                'Restore worker stopped responding before reporting completion. Check Microsoft 365 — items may already be restored.'
            );
            ++$count;
        }

        return $count;
    }

    /** Fail restore child runs for a batch when the worker is idle and progress is stale. */
    public static function reconcileStaleRestoreBatch(string $batchRunId, int $staleSeconds = 90): void
    {
        if (!Capsule::schema()->hasTable('ms365_restore_runs') || $batchRunId === '') {
            return;
        }

        $cutoff = time() - max(60, $staleSeconds);
        $children = Capsule::table('ms365_restore_runs')
            ->where('e3_batch_run_id', $batchRunId)
            ->where('status', 'running')
            ->where('updated_at', '<', $cutoff)
            ->get(['id']);

        foreach ($children as $child) {
            $runId = (string) ($child->id ?? '');
            if ($runId === '') {
                continue;
            }
            $queue = Capsule::table('ms365_job_queue')
                ->where('run_id', $runId)
                ->where('status', 'running')
                ->first(['worker_node_id']);
            if ($queue === null) {
                continue;
            }
            $nodeId = (string) ($queue->worker_node_id ?? '');
            if ($nodeId === '') {
                continue;
            }
            $load = (int) Capsule::table('ms365_worker_nodes')->where('node_id', $nodeId)->value('current_load');
            if ($load > 0) {
                continue;
            }
            Ms365RestoreWorkerHooks::onFail(
                $runId,
                'Restore worker stopped responding before reporting completion. Check Microsoft 365 — items may already be restored.'
            );
        }
    }

    /**
     * Re-queue runs claimed by a worker that reports zero load but still owns active leases
     * (e.g. worker restart after self-update without completing/failing the run).
     */
    public static function releaseOrphanedClaimsForNode(string $nodeId, int $reportedLoad, int $staleProgressSeconds = 300): int
    {
        if ($nodeId === '') {
            return 0;
        }
        $reportedLoad = self::effectiveReportedLoad($nodeId, $reportedLoad);
        if ($reportedLoad > 0) {
            return self::releaseStalledClaimsForBusyNode($nodeId, $staleProgressSeconds);
        }

        return self::releaseOrphanedClaimsForIdleNode($nodeId, $staleProgressSeconds);
    }

    private static function releaseOrphanedClaimsForIdleNode(string $nodeId, int $staleProgressSeconds): int
    {
        $now = time();
        $staleProgressSeconds = max(self::ORPHAN_STALE_SECONDS, $staleProgressSeconds);
        $staleCutoff = $now - $staleProgressSeconds;
        $unackedCutoff = $now - self::ORPHAN_UNACKED_SECONDS;

        $select = [
            'q.run_id',
            'q.lease_expires_at',
            'r.updated_at',
            'r.percent',
            'r.items_done',
            'r.phase',
        ];
        if (Capsule::schema()->hasColumn('ms365_backup_runs', 'last_progress_at')) {
            $select[] = 'r.last_progress_at';
        }
        if (Capsule::schema()->hasColumn('ms365_backup_runs', 'last_429_at')) {
            $select[] = 'r.last_429_at';
        }

        $candidates = Capsule::table('ms365_job_queue as q')
            ->join('ms365_backup_runs as r', 'r.id', '=', 'q.run_id')
            ->where('q.status', 'running')
            ->where('q.worker_node_id', $nodeId)
            ->where('q.claimed_at', '<', $unackedCutoff)
            ->select($select)
            ->get();

        $toRequeue = [];
        foreach ($candidates as $row) {
            if (Ms365BatchRunRepository::isThrottledWaitingAliveFromRow($row, $now)) {
                continue;
            }
            $leaseExpires = (int) ($row->lease_expires_at ?? 0);
            if ($leaseExpires > $now) {
                continue;
            }
            $progressAt = self::claimProgressFreshnessAt($row);
            if (self::isUnacknowledgedClaimRow($row)) {
                if ($progressAt >= $unackedCutoff) {
                    continue;
                }
            } elseif ($progressAt >= $staleCutoff) {
                continue;
            }
            $runId = (string) ($row->run_id ?? '');
            if ($runId !== '') {
                $toRequeue[] = $runId;
            }
        }

        if ($toRequeue === []) {
            return 0;
        }
        self::requeueRuns($toRequeue, 'Orphaned claim released (worker idle)');

        return count($toRequeue);
    }

    private static function releaseStalledClaimsForBusyNode(string $nodeId, int $staleProgressSeconds): int
    {
        $now = time();
        $silenceSeconds = max(self::STALE_PROGRESS_SILENCE_SECONDS, $staleProgressSeconds);
        $silenceCutoff = $now - $silenceSeconds;

        $select = [
            'q.run_id',
            'r.updated_at',
            'r.percent',
            'r.items_done',
            'r.phase',
        ];
        if (Capsule::schema()->hasColumn('ms365_backup_runs', 'last_progress_at')) {
            $select[] = 'r.last_progress_at';
        }
        if (Capsule::schema()->hasColumn('ms365_backup_runs', 'last_429_at')) {
            $select[] = 'r.last_429_at';
        }
        $select[] = 'q.lease_expires_at';

        $candidates = Capsule::table('ms365_job_queue as q')
            ->join('ms365_backup_runs as r', 'r.id', '=', 'q.run_id')
            ->where('q.status', 'running')
            ->where('q.worker_node_id', $nodeId)
            ->whereIn('r.status', ['queued', 'running'])
            ->select($select)
            ->get();

        $toRequeue = [];
        foreach ($candidates as $row) {
            if (Ms365BatchRunRepository::isThrottledWaitingAliveFromRow($row, $now)) {
                continue;
            }
            $progressAt = self::claimProgressFreshnessAt($row);
            if ($progressAt <= 0 || $progressAt >= $silenceCutoff) {
                continue;
            }
            $runId = (string) ($row->run_id ?? '');
            if ($runId !== '') {
                $toRequeue[] = $runId;
            }
        }

        if ($toRequeue === []) {
            return 0;
        }
        self::requeueRuns($toRequeue, 'Stale progress (worker busy)');

        return count($toRequeue);
    }

    /** @return int orphans re-queued across all active nodes reporting zero load */
    public static function releaseOrphanedClaimsForAllNodes(int $staleProgressSeconds = 120): int
    {
        $total = 0;
        foreach (WorkerNodeRepository::activeNodes() as $node) {
            $total += self::releaseOrphanedClaimsForNode(
                (string) ($node['node_id'] ?? ''),
                (int) ($node['current_load'] ?? 0),
                $staleProgressSeconds
            );
        }

        return $total;
    }

    /**
     * Detect and clean zombie/stale queue rows that block concurrency slots.
     *
     * @return array{requeued: int, failed: int, synced: int}
     */
    public static function reconcileZombieRuns(int $staleSeconds = 120): array
    {
        $now = time();
        $cutoff = $now - max(60, $staleSeconds);
        $requeued = 0;
        $failed = 0;
        $synced = 0;

        $exhaustedSelect = [
            'q.run_id',
            'q.status as queue_status',
            'q.lease_expires_at',
            'q.worker_node_id',
            'br.updated_at as backup_updated_at',
        ];
        if (Capsule::schema()->hasColumn('ms365_backup_runs', 'last_progress_at')) {
            $exhaustedSelect[] = 'br.last_progress_at';
        }
        if (Capsule::schema()->hasColumn('ms365_backup_runs', 'last_429_at')) {
            $exhaustedSelect[] = 'br.last_429_at';
        }
        $exhausted = Capsule::table('ms365_job_queue as q')
            ->leftJoin('ms365_backup_runs as br', 'br.id', '=', 'q.run_id')
            ->whereIn('q.status', ['queued', 'running'])
            ->whereColumn('q.attempts', '>=', 'q.max_attempts')
            ->select($exhaustedSelect)
            ->get();
        foreach ($exhausted as $row) {
            $runId = (string) ($row->run_id ?? '');
            if ($runId === '') {
                continue;
            }
            if (self::isActivelyRunningClaim($row, $now, $cutoff)) {
                continue;
            }
            $run = BackupRunRepository::get($runId);
            if ($run !== null && in_array($run['status'] ?? '', ['success', 'error', 'cancelled'], true)) {
                JobQueueRepository::markTerminalFailed($runId, 'Run exceeded max attempts');
                continue;
            }
            if ($run !== null && self::shouldRequeueExhaustedBackupRun($run)) {
                self::requeueRuns([$runId], 'Stale partial backup re-queued after worker loss');
                ++$requeued;
                continue;
            }
            Ms365RestoreWorkerHooks::onFail($runId, 'Run exceeded max attempts');
            ++$failed;
        }

        $staleRows = Capsule::table('ms365_job_queue as q')
            ->join('ms365_backup_runs as r', 'r.id', '=', 'q.run_id')
            ->where('q.status', 'running')
            ->whereIn('r.status', ['queued', 'running'])
            ->where('r.updated_at', '<', $cutoff)
            ->where(function ($query) use ($now) {
                $query->whereNull('q.lease_expires_at')
                    ->orWhere('q.lease_expires_at', '<', $now);
            })
            ->get(['q.run_id', 'q.attempts', 'q.max_attempts', 'q.worker_node_id']);

        foreach ($staleRows as $row) {
            $runId = (string) ($row->run_id ?? '');
            if ($runId === '') {
                continue;
            }
            $max = (int) ($row->max_attempts ?? 0) > 0 ? (int) $row->max_attempts : 3;
            if ((int) ($row->attempts ?? 0) >= $max) {
                Ms365RestoreWorkerHooks::onFail($runId, 'Stale run gave up after max attempts');
                ++$failed;
            } else {
                self::requeueRuns([$runId], 'Stale run reconciled');
                ++$requeued;
            }
        }

        $silenceCutoff = $now - self::STALE_PROGRESS_SILENCE_SECONDS;
        $stalledLeasedQuery = Capsule::table('ms365_job_queue as q')
            ->join('ms365_backup_runs as r', 'r.id', '=', 'q.run_id')
            ->where('q.status', 'running')
            ->whereIn('r.status', ['queued', 'running'])
            ->where('q.lease_expires_at', '>', $now);

        if (Capsule::schema()->hasColumn('ms365_backup_runs', 'last_progress_at')) {
            $stalledLeasedQuery->where(function ($query) use ($silenceCutoff) {
                $query->where(function ($q) use ($silenceCutoff) {
                    $q->whereNotNull('r.last_progress_at')
                        ->where('r.last_progress_at', '<', $silenceCutoff);
                })->orWhere(function ($q) use ($silenceCutoff) {
                    $q->whereNull('r.last_progress_at')
                        ->where('r.updated_at', '<', $silenceCutoff);
                });
            });
        } else {
            $stalledLeasedQuery->where('r.updated_at', '<', $silenceCutoff);
        }

        $stalledSelect = ['q.run_id', 'q.attempts', 'q.max_attempts', 'q.lease_expires_at'];
        if (Capsule::schema()->hasColumn('ms365_backup_runs', 'last_429_at')) {
            $stalledSelect[] = 'r.last_429_at';
        }

        foreach ($stalledLeasedQuery->get($stalledSelect) as $row) {
            if (Ms365BatchRunRepository::isThrottledWaitingAliveFromRow($row, $now)) {
                continue;
            }
            $runId = (string) ($row->run_id ?? '');
            if ($runId === '') {
                continue;
            }
            $max = (int) ($row->max_attempts ?? 0) > 0 ? (int) $row->max_attempts : 3;
            if ((int) ($row->attempts ?? 0) >= $max) {
                Ms365RestoreWorkerHooks::onFail($runId, 'Stale progress gave up after max attempts');
                ++$failed;
            } else {
                self::requeueRuns([$runId], 'Stale progress reconciled');
                ++$requeued;
            }
        }

        $orphanChildren = Capsule::table('ms365_backup_runs as r')
            ->leftJoin('ms365_job_queue as q', 'q.run_id', '=', 'r.id')
            ->where('r.status', 'running')
            ->where(function ($query) {
                $query->whereNull('q.id')
                    ->orWhere('q.status', '!=', 'running');
            })
            ->where('r.updated_at', '<', $cutoff)
            ->pluck('r.id')
            ->all();

        foreach ($orphanChildren as $runId) {
            $runId = (string) $runId;
            if ($runId === '') {
                continue;
            }
            JobQueueRepository::requeue($runId);
            BackupRunRepository::update($runId, [
                'status' => 'queued',
                'updated_at' => $now,
            ]);
            Ms365BatchRunRepository::syncForChildRun($runId);
            ++$synced;
        }

        return ['requeued' => $requeued, 'failed' => $failed, 'synced' => $synced];
    }

    /** @param list<string> $runIds */
    public static function requeueBackupRuns(array $runIds, string $message = 'Run re-queued'): int
    {
        $runIds = array_values(array_filter(array_map('strval', $runIds)));
        if ($runIds === []) {
            return 0;
        }
        self::requeueRuns($runIds, $message);

        return count($runIds);
    }

    public static function releaseClaim(string $nodeId, string $runId, string $message = 'Worker released claim', string $reason = ''): bool
    {
        if ($nodeId === '' || $runId === '') {
            return false;
        }
        $isDrain = $reason === 'drain';
        if ($isDrain) {
            $message = 'Worker drain hand-off';
        }
        $now = time();
        $updated = Capsule::table('ms365_job_queue')
            ->where('run_id', $runId)
            ->where('worker_node_id', $nodeId)
            ->where('status', 'running')
            ->update([
                'status' => 'queued',
                'worker_node_id' => null,
                'claimed_at' => null,
                'lease_expires_at' => null,
                'scheduled_at' => $now,
                'error_message' => mb_substr($message, 0, 500),
            ]);
        if ($updated === 0) {
            return false;
        }
        if ($isDrain) {
            self::rollbackAttemptForInfrastructureRequeue($runId);
        } else {
            self::rollbackAttemptIfUnacked($runId);
        }
        Ms365WorkerLogRepository::releaseAssignment($runId, $isDrain ? 'drain' : 'release');
        if (RestoreRunRepository::isRestoreRun($runId)) {
            RestoreRunRepository::update($runId, [
                'status' => 'queued',
                'updated_at' => $now,
            ]);
        } else {
            BackupRunRepository::update($runId, [
                'status' => 'queued',
                'updated_at' => $now,
            ]);
        }

        return true;
    }

    /** @param list<string> $runIds */
    private static function requeueRuns(array $runIds, string $message): void
    {
        $runIds = array_values(array_unique(array_filter(array_map('strval', $runIds))));
        if ($runIds === []) {
            return;
        }
        $now = time();
        $infrastructureRequeue = self::isInfrastructureRequeueMessage($message);

        if ($infrastructureRequeue) {
            foreach ($runIds as $runId) {
                self::rollbackAttemptForInfrastructureRequeue($runId);
            }
        }

        // Partition runs by remaining attempts so a permanently-failing run cannot
        // thrash the queue forever (claim -> stall/fail -> requeue -> reclaim ...),
        // which previously overflowed the attempts column to 255.
        $exhausted = [];
        $retry = $runIds;
        $rows = Capsule::table('ms365_job_queue')
            ->whereIn('run_id', $runIds)
            ->get(['run_id', 'attempts', 'max_attempts']);
        foreach ($rows as $row) {
            $runId = (string) $row->run_id;
            $max = (int) $row->max_attempts > 0 ? (int) $row->max_attempts : 3;
            if ((int) $row->attempts >= $max) {
                $exhausted[$runId] = true;
            }
        }
        if ($exhausted !== []) {
            $retry = array_values(array_filter($runIds, static fn ($id) => !isset($exhausted[$id])));
        }

        if ($retry !== []) {
            foreach ($retry as $runId) {
                self::rollbackAttemptIfUnacked($runId);
                Ms365WorkerLogRepository::releaseAssignment($runId, self::releaseReasonFromRequeueMessage($message));
            }
            Capsule::table('ms365_job_queue')
                ->whereIn('run_id', $retry)
                ->update([
                    'status' => 'queued',
                    'worker_node_id' => null,
                    'claimed_at' => null,
                    'lease_expires_at' => null,
                    'scheduled_at' => $now,
                    'error_message' => mb_substr($message, 0, 500),
                ]);
            foreach ($retry as $runId) {
                if (BackupRunRepository::isCancelled($runId)) {
                    continue;
                }
                $run = BackupRunRepository::get($runId);
                if ($run !== null && in_array($run['status'] ?? '', ['success', 'error', 'cancelled'], true)) {
                    continue;
                }
                $runUpdate = [
                    'status' => 'queued',
                    'updated_at' => $now,
                ];
                if (!$infrastructureRequeue) {
                    $runUpdate['phase'] = '';
                    $runUpdate['percent'] = 0;
                    $runUpdate['items_done'] = 0;
                    $runUpdate['items_total'] = 0;
                }
                BackupRunRepository::update($runId, $runUpdate);
                Ms365BatchRunRepository::syncForChildRun($runId);
            }
        }

        foreach (array_keys($exhausted) as $runId) {
            if (BackupRunRepository::isCancelled($runId)) {
                continue;
            }
            $run = BackupRunRepository::get($runId);
            if ($run !== null && in_array($run['status'] ?? '', ['success', 'error', 'cancelled'], true)) {
                continue;
            }
            Ms365RestoreWorkerHooks::onFail(
                $runId,
                $message . ' (gave up after max attempts)'
            );
        }
    }

    public static function recoverStaleRunning(): int
    {
        return JobQueueRepository::recoverStaleRunning();
    }

    public static function countPlatformRunning(): int
    {
        return (int) Capsule::table('ms365_job_queue')->where('status', 'running')->count();
    }

    public static function countRunningForClient(int $clientId): int
    {
        return JobQueueRepository::countRunningForClient($clientId);
    }

    public static function countRunningForTenant(int $tenantRecordId): int
    {
        $now = time();
        $cutoff = $now - JobQueueRepository::zombieStaleSeconds();

        return (int) Capsule::table('ms365_job_queue as q')
            ->join('ms365_backup_runs as r', 'r.id', '=', 'q.run_id')
            ->where('q.status', 'running')
            ->where('r.status', 'running')
            ->where('r.tenant_record_id', $tenantRecordId)
            ->where(function ($query) use ($now, $cutoff) {
                $query->where('q.lease_expires_at', '>', $now)
                    ->orWhere('r.updated_at', '>=', $cutoff);
            })
            ->count();
    }

    private static function restoreClaimQueryReady(): bool
    {
        if (!Capsule::schema()->hasTable('ms365_restore_runs')) {
            return false;
        }
        foreach (['target_resource_id', 'selection_json', 'source_manifest_id', 'conflict_policy'] as $column) {
            if (!Capsule::schema()->hasColumn('ms365_restore_runs', $column)) {
                return false;
            }
        }

        return true;
    }

    /** @return \Illuminate\Database\Query\Builder */
    private static function buildBackupClaimQuery()
    {
        $backupQuery = Capsule::table('ms365_job_queue as q')
            ->join('ms365_backup_runs as r', 'r.id', '=', 'q.run_id')
            ->where('q.status', 'queued')
            ->where('q.scheduled_at', '<=', time())
            ->where(function ($q) {
                $q->whereNull('q.worker_node_id')->orWhere('q.lease_expires_at', '<', time());
            });
        if (Capsule::schema()->hasColumn('ms365_job_queue', 'job_type')) {
            $backupQuery->where(function ($inner) {
                $inner->where('q.job_type', 'backup')->orWhereNull('q.job_type');
            });
        }

        return $backupQuery;
    }

    /** @return \Illuminate\Database\Query\Builder */
    private static function buildRestoreClaimQuery()
    {
        $restoreQ = Capsule::table('ms365_job_queue as q')
            ->join('ms365_restore_runs as r', 'r.id', '=', 'q.run_id')
            ->where('q.status', 'queued')
            ->where('q.scheduled_at', '<=', time())
            ->where(function ($q) {
                $q->whereNull('q.worker_node_id')->orWhere('q.lease_expires_at', '<', time());
            });
        if (Capsule::schema()->hasColumn('ms365_job_queue', 'job_type')) {
            $restoreQ->where('q.job_type', 'restore');
        }

        return $restoreQ;
    }

    /** @return list<string> */
    private static function backupClaimSelectColumns(): array
    {
        return [
            'q.*',
            'r.tenant_record_id',
            'r.whmcs_client_id',
            'r.resource_id',
            'r.resource_type',
            'r.graph_id',
            'r.physical_key',
            'r.scope_json',
            'r.e3_batch_run_id',
        ];
    }

    /** @return \Illuminate\Support\Collection<int, object> */
    private static function fetchBackupClaimCandidatesFifo($backupQuery)
    {
        return $backupQuery
            ->where('r.status', 'queued')
            ->orderBy('q.priority')
            ->orderBy('q.id')
            ->select(self::backupClaimSelectColumns())
            ->limit(50)
            ->get();
    }

    /** @return \Illuminate\Support\Collection<int, object> */
    private static function fetchBackupClaimCandidatesFair($backupQuery)
    {
        if (self::windowFunctionsSupported()) {
            return self::fetchBackupClaimCandidatesFairSql($backupQuery);
        }

        return self::fetchBackupClaimCandidatesFairPhp($backupQuery);
    }

    /** @return \Illuminate\Support\Collection<int, object> */
    private static function fetchBackupClaimCandidatesFairSql($backupQuery)
    {
        return $backupQuery
            ->where('r.status', 'queued')
            ->selectRaw(
                'q.*, r.tenant_record_id, r.whmcs_client_id, r.resource_id, r.resource_type,'
                . ' r.graph_id, r.physical_key, r.scope_json, r.e3_batch_run_id,'
                . ' ROW_NUMBER() OVER (PARTITION BY COALESCE(r.e3_batch_run_id, q.run_id)'
                . ' ORDER BY q.priority ASC, q.id ASC) AS fair_rank'
            )
            ->orderByRaw('q.priority ASC, fair_rank ASC, q.id ASC')
            ->limit(50)
            ->get();
    }

    /** @return \Illuminate\Support\Collection<int, object> */
    private static function fetchBackupClaimCandidatesFairPhp($backupQuery)
    {
        $rows = $backupQuery
            ->where('r.status', 'queued')
            ->select(self::backupClaimSelectColumns())
            ->orderByRaw('COALESCE(r.e3_batch_run_id, q.run_id)')
            ->orderBy('q.priority')
            ->orderBy('q.id')
            ->limit(5000)
            ->get();

        return self::assignFairRankInPhp($rows);
    }

    /** @return \Illuminate\Support\Collection<int, object> */
    private static function fetchRestoreClaimCandidatesFifo($restoreQ)
    {
        return $restoreQ
            ->orderBy('q.priority')
            ->orderBy('q.id')
            ->select(self::restoreClaimSelectColumns())
            ->limit(50)
            ->get();
    }

    /** @return \Illuminate\Support\Collection<int, object> */
    private static function fetchRestoreClaimCandidatesFair($restoreQ)
    {
        if (!self::restoreHasBatchColumn()) {
            return self::fetchRestoreClaimCandidatesFifo($restoreQ)
                ->each(static function ($row): void {
                    $row->fair_rank = 1;
                });
        }
        if (self::windowFunctionsSupported()) {
            return self::fetchRestoreClaimCandidatesFairSql($restoreQ);
        }

        return self::fetchRestoreClaimCandidatesFairPhp($restoreQ);
    }

    /** @return \Illuminate\Support\Collection<int, object> */
    private static function fetchRestoreClaimCandidatesFairSql($restoreQ)
    {
        $columns = implode(', ', self::restoreClaimSelectColumns());

        return $restoreQ
            ->selectRaw(
                $columns . ', r.e3_batch_run_id,'
                . ' ROW_NUMBER() OVER (PARTITION BY COALESCE(r.e3_batch_run_id, q.run_id)'
                . ' ORDER BY q.priority ASC, q.id ASC) AS fair_rank'
            )
            ->orderByRaw('q.priority ASC, fair_rank ASC, q.id ASC')
            ->limit(50)
            ->get();
    }

    /** @return \Illuminate\Support\Collection<int, object> */
    private static function fetchRestoreClaimCandidatesFairPhp($restoreQ)
    {
        $columns = self::restoreClaimSelectColumns();
        if (!in_array('r.e3_batch_run_id', $columns, true)) {
            $columns[] = 'r.e3_batch_run_id';
        }
        $rows = $restoreQ
            ->select($columns)
            ->orderByRaw('COALESCE(r.e3_batch_run_id, q.run_id)')
            ->orderBy('q.priority')
            ->orderBy('q.id')
            ->limit(5000)
            ->get();

        return self::assignFairRankInPhp($rows);
    }

    /**
     * @param \Illuminate\Support\Collection<int, object> $rows
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    private static function assignFairRankInPhp($rows)
    {
        $grouped = $rows->groupBy(static function ($row) {
            $batch = trim((string) ($row->e3_batch_run_id ?? ''));
            if ($batch !== '') {
                return $batch;
            }

            return (string) ($row->run_id ?? '');
        });
        $ranked = collect();
        foreach ($grouped as $group) {
            $sorted = $group->sortBy([
                ['priority', 'asc'],
                ['id', 'asc'],
            ])->values();
            foreach ($sorted as $i => $row) {
                $row->fair_rank = $i + 1;
                $ranked->push($row);
            }
        }

        return $ranked->sortBy([
            ['priority', 'asc'],
            ['fair_rank', 'asc'],
            ['id', 'asc'],
        ])->take(50)->values();
    }

    private static ?bool $windowFunctionsSupported = null;

    private static function windowFunctionsSupported(): bool
    {
        if (self::$windowFunctionsSupported === null) {
            try {
                Capsule::select('SELECT ROW_NUMBER() OVER (ORDER BY 1) AS rn FROM (SELECT 1 AS x) AS t LIMIT 1');
                self::$windowFunctionsSupported = true;
            } catch (\Throwable $e) {
                self::$windowFunctionsSupported = false;
            }
        }

        return self::$windowFunctionsSupported;
    }

    private static ?bool $restoreHasBatchColumn = null;

    private static function restoreHasBatchColumn(): bool
    {
        if (self::$restoreHasBatchColumn === null) {
            self::$restoreHasBatchColumn = Capsule::schema()->hasColumn('ms365_restore_runs', 'e3_batch_run_id');
        }

        return self::$restoreHasBatchColumn;
    }

    /** @return list<string> */
    private static function restoreClaimSelectColumns(): array
    {
        return [
            'q.*',
            'r.tenant_record_id',
            'r.whmcs_client_id',
            'r.resource_type',
            'r.target_graph_id',
            'r.target_resource_id',
            'r.selection_json',
            'r.source_manifest_id',
            'r.conflict_policy',
            'r.backup_run_id',
        ];
    }

    /**
     * Strip internal scope metadata (_drive_id, _shard) so the worker receives bool flags only.
     *
     * @param array<string, mixed> $scope
     *
     * @return array<string, bool>
     */
    private static function scopeFlagsForWorker(array $scope): array
    {
        $flags = [];
        foreach ($scope as $key => $value) {
            if (!is_string($key) || str_starts_with($key, '_')) {
                continue;
            }
            if (is_bool($value)) {
                $flags[$key] = $value;
            } elseif (is_int($value)) {
                $flags[$key] = $value !== 0;
            }
        }

        return $flags;
    }

    private static function isUnacknowledgedClaim(string $runId): bool
    {
        if ($runId === '') {
            return false;
        }
        if (RestoreRunRepository::isRestoreRun($runId)) {
            $restore = RestoreRunRepository::get($runId);
            if ($restore === null) {
                return true;
            }
            $phase = strtolower(trim((string) ($restore['phase'] ?? '')));

            return in_array($phase, ['', 'claimed'], true);
        }
        $run = BackupRunRepository::get($runId);
        if ($run === null) {
            return true;
        }

        return self::isUnacknowledgedClaimRow($run);
    }

    /** @param object|array<string, mixed> $row */
    private static function isUnacknowledgedClaimRow(object|array $row): bool
    {
        $phase = strtolower(trim((string) (is_array($row) ? ($row['phase'] ?? '') : ($row->phase ?? ''))));
        $percent = (float) (is_array($row) ? ($row['percent'] ?? 0) : ($row->percent ?? 0));
        $itemsDone = (int) (is_array($row) ? ($row['items_done'] ?? 0) : ($row->items_done ?? 0));

        return $percent === 0.0 && $itemsDone === 0 && in_array($phase, ['', 'claimed'], true);
    }

    private static function isInfrastructureRequeueMessage(string $message): bool
    {
        $message = strtolower($message);

        return str_contains($message, 'orphaned claim')
            || str_contains($message, 'worker idle')
            || str_contains($message, 'lease expired')
            || str_contains($message, 'worker released claim')
            || str_contains($message, 'worker drain hand-off')
            || str_contains($message, 'stale run')
            || str_contains($message, 'stale workload')
            || str_contains($message, 'stale progress')
            || str_contains($message, 'stale partial backup');
    }

    /** @param object|array<string, mixed> $row */
    private static function claimProgressFreshnessAt(object|array $row): int
    {
        if (Capsule::schema()->hasColumn('ms365_backup_runs', 'last_progress_at')) {
            $lastProgress = (int) (is_array($row) ? ($row['last_progress_at'] ?? 0) : ($row->last_progress_at ?? 0));
            if ($lastProgress > 0) {
                return $lastProgress;
            }
        }

        return (int) (is_array($row) ? ($row['updated_at'] ?? 0) : ($row->updated_at ?? $row->backup_updated_at ?? 0));
    }

    /**
     * @param array<string, mixed> $run
     */
    private static function shouldRequeueExhaustedBackupRun(array $run): bool
    {
        $status = (string) ($run['status'] ?? '');
        if (!in_array($status, ['running', 'queued'], true)) {
            return false;
        }
        $itemsDone = (int) ($run['items_done'] ?? 0);
        $percent = (float) ($run['percent'] ?? 0);
        if ($itemsDone <= 0 && $percent <= 0) {
            return false;
        }
        $phase = strtolower(trim((string) ($run['phase'] ?? '')));
        if (!in_array($phase, ['upload', 'kopia_upload', 'graph_sync', 'prior_snapshot'], true)) {
            return false;
        }
        $error = trim((string) ($run['error_message'] ?? ''));

        return $error === '' || $error === 'Run exceeded max attempts';
    }

    private static function rollbackAttemptForInfrastructureRequeue(string $runId): void
    {
        if ($runId === '') {
            return;
        }
        $row = Capsule::table('ms365_job_queue')->where('run_id', $runId)->first(['attempts']);
        if ($row === null || (int) ($row->attempts ?? 0) <= 0) {
            return;
        }
        Capsule::table('ms365_job_queue')
            ->where('run_id', $runId)
            ->update(['attempts' => max(0, (int) $row->attempts - 1)]);
    }

    /** Roll back one queue attempt for batch-level shard auto-retry. */
    public static function rollbackAttemptForBatchRequeue(string $runId): void
    {
        self::rollbackAttemptForInfrastructureRequeue($runId);
    }

    private static function rollbackAttemptIfUnacked(string $runId): void
    {
        if (!self::isUnacknowledgedClaim($runId)) {
            return;
        }
        $row = Capsule::table('ms365_job_queue')->where('run_id', $runId)->first(['attempts']);
        if ($row === null || (int) ($row->attempts ?? 0) <= 0) {
            return;
        }
        Capsule::table('ms365_job_queue')
            ->where('run_id', $runId)
            ->update(['attempts' => max(0, (int) $row->attempts - 1)]);
    }

    private static function isActivelyRunningClaim(object $queueRow, int $now, int $staleCutoff): bool
    {
        if ((string) ($queueRow->queue_status ?? '') !== 'running') {
            return false;
        }
        if (Ms365BatchRunRepository::isThrottledWaitingAliveFromRow($queueRow, $now)) {
            return true;
        }
        $progressAt = self::claimProgressFreshnessAt($queueRow);
        $silenceCutoff = $now - self::STALE_PROGRESS_SILENCE_SECONDS;
        if ($progressAt > 0 && $progressAt < $silenceCutoff) {
            return false;
        }
        $leaseExpires = (int) ($queueRow->lease_expires_at ?? 0);
        if ($leaseExpires > $now) {
            return true;
        }
        $updatedAt = (int) ($queueRow->backup_updated_at ?? 0);
        $heartbeatCutoff = $now - 180;

        return $updatedAt >= max($staleCutoff, $heartbeatCutoff);
    }

    private static function releaseReasonFromRequeueMessage(string $message): string
    {
        $lower = strtolower($message);
        if (str_contains($lower, 'orphan')) {
            return 'orphan';
        }
        if (str_contains($lower, 'lease')) {
            return 'lease_expired';
        }
        if (str_contains($lower, 'stale')) {
            return 'stale';
        }
        if (str_contains($lower, 'drain')) {
            return 'drain';
        }

        return 'requeue';
    }

    private static function isBlockedByBaselineVersion(array $node): bool
    {
        $val = strtolower(trim(Ms365EngineConfig::moduleSettingPublic('ms365_worker_fleet_auto_baseline_update', 'on')));
        if ($val === 'off' || $val === '0') {
            return false;
        }
        $latest = \Ms365Backup\Fleet\ReleaseRepository::latest();
        if ($latest === null) {
            return false;
        }

        return \Ms365Backup\Fleet\ReleaseRepository::nodeNeedsUpdate(
            (string) ($node['version'] ?? ''),
            (string) ($latest['version'] ?? '')
        );
    }

    private static function isHeavyPhysicalKey(string $physicalKey): bool
    {
        $base = PhysicalKeyHelper::baseKey($physicalKey);
        if ($base === '') {
            return false;
        }

        return str_starts_with($base, 'site:')
            || str_starts_with($base, 'drive:')
            || str_starts_with($base, 'onedrive:');
    }

    /**
     * Fail queued rows whose backup run is already terminal (error/cancelled).
     *
     * @return int rows updated
     */
    public static function reconcileQueuedErroredRuns(): int
    {
        $now = time();
        $rows = Capsule::table('ms365_job_queue as q')
            ->join('ms365_backup_runs as r', 'r.id', '=', 'q.run_id')
            ->where('q.status', 'queued')
            ->whereIn('r.status', ['error', 'cancelled', 'failed'])
            ->select(['q.id', 'q.run_id', 'r.status', 'r.error_message'])
            ->limit(500)
            ->get();

        $count = 0;
        foreach ($rows as $row) {
            $message = trim((string) ($row->error_message ?? ''));
            if ($message === '') {
                $message = 'Run status ' . (string) ($row->status ?? 'error') . ' while queue was still queued';
            }
            Capsule::table('ms365_job_queue')->where('id', $row->id)->update([
                'status' => 'failed',
                'error_message' => mb_substr($message, 0, 500),
                'finished_at' => $now,
            ]);
            $count++;
        }

        return $count;
    }
}
