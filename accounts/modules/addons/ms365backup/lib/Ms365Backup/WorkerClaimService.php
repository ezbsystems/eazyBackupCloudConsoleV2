<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\HelperController;

final class WorkerClaimService
{
    /** Infrastructure requeues without progress improvement before marking graph_sync workloads error. */
    private const INFRA_REQUEUE_STALL_LIMIT = 4;

    private const INFRA_REQUEUE_STALL_MESSAGE = 'Workload stalled during Graph sync';

    /** Minimum seconds between fleet-wide maintenance sweeps (run from the batch claim path). */
    private const REAPER_MIN_INTERVAL_SECONDS = 15;

    /**
     * Fleet-wide interval lock for the maintenance sweeps. Only the poll that wins the
     * atomic update for the current window actually runs the reapers; all other polls
     * skip them. Keeps the hot claim path free of full queue/run reconciler scans.
     */
    private static function tryAcquireReaperSlot(): bool
    {
        if (!class_exists(Capsule::class)) {
            return true;
        }
        $now = time();
        $cutoff = $now - self::REAPER_MIN_INTERVAL_SECONDS;
        $key = 'ms365_reapers_last_run';
        try {
            $affected = Capsule::table('tbladdonmodules')
                ->where('module', 'ms365backup')
                ->where('setting', $key)
                ->whereRaw('CAST(`value` AS UNSIGNED) <= ?', [$cutoff])
                ->update(['value' => (string) $now]);
            if ($affected > 0) {
                return true;
            }
            $exists = Capsule::table('tbladdonmodules')
                ->where('module', 'ms365backup')
                ->where('setting', $key)
                ->exists();
            if (!$exists) {
                Capsule::table('tbladdonmodules')->insert([
                    'module' => 'ms365backup',
                    'setting' => $key,
                    'value' => (string) $now,
                ]);

                return true;
            }
        } catch (\Throwable $e) {
            // On any contention/error, default to running the sweeps (safe behaviour).
            return true;
        }

        return false;
    }

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

    /** Per-run worker APIs are restore-only; backup workloads use batch endpoints. */
    public static function requireRestoreRunId(string $runId): void
    {
        if ($runId === '' || !RestoreRunRepository::isRestoreRun($runId)) {
            throw new \RuntimeException('Per-run worker API is restore-only; backup uses batch endpoints.');
        }
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

        $resumed = self::resumeOwnedRunningClaim($nodeId);
        if ($resumed !== null) {
            return $resumed;
        }

        if (self::countPlatformRunning() >= Ms365EngineConfig::platformMaxConcurrent()) {
            return null;
        }

        $restoreCandidates = collect();
        if (self::restoreClaimQueryReady()) {
            $restoreQ = self::buildRestoreClaimQuery();
            $fairScheduling = Ms365EngineConfig::fairSchedulingEnabled();
            $restoreCandidates = $fairScheduling
                ? self::fetchRestoreClaimCandidatesFair($restoreQ)
                : self::fetchRestoreClaimCandidatesFifo($restoreQ);
        }

        $merged = $restoreCandidates;
        $fairScheduling = Ms365EngineConfig::fairSchedulingEnabled();
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
            if ($clientId > 0 && self::countRunningForClient($clientId) >= Ms365EngineConfig::perClientMaxConcurrent()) {
                continue;
            }
            $jobType = (string) ($candidate->job_type ?? 'backup');
            if ($jobType !== 'restore') {
                continue;
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
                    'error_message' => '',
                ]);
            if ($updated === 0) {
                continue;
            }

            Ms365WorkerLogRepository::recordAssignment($runId, $nodeId);

            RestoreRunRepository::update($runId, [
                'status' => 'running',
                'phase' => 'claimed',
                'started_at' => $now,
            ]);

            try {
                return self::buildRestoreRunPayload($runId);
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

    /**
     * Claim the next tenant backup batch for a worker node (backup path; restore uses claimNext).
     *
     * @return array<string, mixed>|null batch payload with children[]
     */
    public static function claimNextBatch(string $nodeId, ?array $claimHint = null): ?array
    {
        if (!Ms365BatchClaimRepository::tableReady()) {
            return null;
        }

        if (self::tryAcquireReaperSlot()) {
            Ms365BatchClaimRepository::reapStaleBatches();
        }

        $node = WorkerNodeRepository::get($nodeId);
        if ($node === null || ($node['status'] ?? '') !== 'active') {
            return null;
        }

        if (self::isBlockedByBaselineVersion($node)) {
            return null;
        }

        $ownedBatches = Ms365BatchClaimRepository::countRunningForNode($nodeId);
        if ($ownedBatches >= Ms365EngineConfig::maxBatchesPerNode()) {
            $resumed = self::resumeOwnedRunningBatch($nodeId);
            if ($resumed !== null) {
                return $resumed;
            }

            return null;
        }

        if (Ms365BatchClaimRepository::countPlatformRunning() >= Ms365EngineConfig::platformMaxConcurrent()) {
            return null;
        }

        $claimed = Ms365BatchClaimRepository::claimForNode($nodeId);
        if ($claimed === null) {
            return null;
        }

        $batchRunId = (string) ($claimed['batch_run_id'] ?? '');
        if ($batchRunId === '') {
            return null;
        }

        try {
            return self::buildBatchPayload($batchRunId, $nodeId);
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            Ms365BatchClaimRepository::release($batchRunId, $nodeId, $message);
            if (self::isPermanentAuthFailure($message)) {
                Ms365BatchClaimRepository::fail($batchRunId, $nodeId, $message);
            }

            return null;
        }
    }

    /** @return array<string, mixed>|null */
    public static function resumeOwnedRunningBatch(string $nodeId): ?array
    {
        if ($nodeId === '' || !Ms365BatchClaimRepository::tableReady()) {
            return null;
        }
        $row = Ms365BatchClaimRepository::getRunningForNode($nodeId);
        if ($row === null) {
            return null;
        }
        $batchRunId = (string) ($row['batch_run_id'] ?? '');
        if ($batchRunId === '') {
            return null;
        }
        Ms365BatchClaimRepository::renew($batchRunId, $nodeId);

        try {
            return self::buildBatchPayload($batchRunId, $nodeId);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** @return array<string, mixed> */
    public static function buildBatchPayload(string $batchRunId, string $nodeId): array
    {
        $childrenRows = Ms365BatchRunRepository::getChildrenForBatch($batchRunId);
        if ($childrenRows === []) {
            throw new \RuntimeException('Batch has no child workloads.');
        }

        $tenantRecordId = 0;
        foreach ($childrenRows as $child) {
            $status = (string) ($child['status'] ?? '');
            if ($status === 'success' || $status === 'cancelled') {
                continue;
            }
            $tenantRecordId = (int) ($child['tenant_record_id'] ?? 0);
            if ($tenantRecordId > 0) {
                break;
            }
        }
        if ($tenantRecordId <= 0) {
            throw new \RuntimeException('Tenant record not found for batch.');
        }

        $batchContext = self::batchPayloadContextForTenant($tenantRecordId);
        if ($batchContext === null) {
            throw new \RuntimeException('Tenant record not found for batch.');
        }
        $batchContext = self::enrichBatchPayloadContext($batchContext, $childrenRows, $tenantRecordId);

        $children = [];
        foreach ($childrenRows as $child) {
            $status = (string) ($child['status'] ?? '');
            if ($status === 'success' || $status === 'cancelled') {
                continue;
            }
            $runId = (string) ($child['id'] ?? '');
            if ($runId === '') {
                continue;
            }
            $payload = self::buildRunPayload($runId, $batchContext, $child);
            if ($payload !== null) {
                $children[] = $payload;
            }
        }

        if ($children === []) {
            Ms365BatchClaimRepository::complete($batchRunId, $nodeId);
            throw new \RuntimeException('Batch has no pending child workloads.');
        }

        return [
            'batch_run_id' => $batchRunId,
            'job_type' => 'backup_batch',
            'tenant_record_id' => $tenantRecordId,
            'azure_tenant_id' => $batchContext['azure_tenant_id'],
            'graph_token' => $batchContext['graph_token'],
            'graph_region' => $batchContext['graph_region'],
            'graph_tenant_budget' => $batchContext['graph_tenant_budget'],
            'lease_expires_at' => Ms365BatchClaimRepository::leaseExpiresAt($batchRunId),
            'engine_mode' => Ms365EngineConfig::engineMode(),
            'children' => $children,
        ];
    }

    /**
     * Shared tenant credentials for batch claim payloads (one Graph token per batch).
     *
     * @return array{record: array<string, mixed>, creds: array<string, mixed>, azure_tenant_id: string, graph_token: string, graph_region: string, graph_tenant_budget: int}|null
     */
    private static function batchPayloadContextForTenant(int $tenantRecordId): ?array
    {
        if ($tenantRecordId <= 0) {
            return null;
        }
        $record = TenantRecordRepository::getById($tenantRecordId);
        if ($record === null) {
            return null;
        }
        $creds = TenantRecordRepository::resolvedCredentialsForRecord($record);
        $azureTenantId = trim((string) ($creds['tenant_id'] ?? ''));

        return [
            'record' => $record,
            'creds' => $creds,
            'azure_tenant_id' => $azureTenantId,
            'graph_token' => self::graphTokenForTenantRecord($record),
            'graph_region' => (string) ($creds['region'] ?? 'GlobalPublicCloud'),
            'graph_tenant_budget' => GraphTenantBudgetService::workerShare($tenantRecordId, $azureTenantId),
        ];
    }

    /**
     * @param array<string, mixed> $batchContext
     * @param list<array<string, mixed>> $childrenRows
     *
     * @return array<string, mixed>
     */
    private static function enrichBatchPayloadContext(array $batchContext, array $childrenRows, int $tenantRecordId): array
    {
        $record = $batchContext['record'] ?? null;
        if (!is_array($record)) {
            throw new \RuntimeException('Invalid batch payload context.');
        }

        $pendingChildren = [];
        $e3JobIds = [];
        foreach ($childrenRows as $child) {
            $status = (string) ($child['status'] ?? '');
            if ($status === 'success' || $status === 'cancelled') {
                continue;
            }
            $pendingChildren[] = $child;
            $e3JobIds[trim((string) ($child['e3_job_id'] ?? ''))] = true;
        }

        $destinationsByJob = [];
        foreach (array_keys($e3JobIds) as $e3JobId) {
            foreach ($pendingChildren as $child) {
                if (trim((string) ($child['e3_job_id'] ?? '')) !== $e3JobId) {
                    continue;
                }
                $destinationsByJob[$e3JobId] = Ms365JobDestinationService::resolveForRun($child, $record);
                break;
            }
        }

        $firstE3JobId = '';
        foreach ($pendingChildren as $child) {
            $firstE3JobId = trim((string) ($child['e3_job_id'] ?? ''));
            if ($firstE3JobId !== '') {
                break;
            }
        }

        $jobScope = DeltaStateRepository::computeJobScope($firstE3JobId, $tenantRecordId);
        $physicalKeys = self::prefetchPhysicalKeysForBatch($childrenRows);

        $batchContext['destinations_by_job'] = $destinationsByJob;
        $batchContext['job_scope'] = $jobScope;
        $batchContext['platform_workload_flags'] = Ms365EngineConfig::workloadFlags();
        $batchContext['pagination_limits'] = Ms365EngineConfig::paginationLimits();
        $batchContext['manifests'] = KopiaRepoBootstrapService::latestManifestForSources(
            $tenantRecordId,
            $physicalKeys,
            $jobScope,
        );
        $batchContext['delta_states'] = DeltaStateRepository::getStatesForSources(
            $tenantRecordId,
            $physicalKeys,
            $jobScope['scoped_job_id'] ?? null,
        );
        $batchContext['legacy_delta_states'] = self::latestDeltaStatesForSources(
            $tenantRecordId,
            $physicalKeys,
            $jobScope,
        );

        return $batchContext;
    }

    /**
     * @param list<array<string, mixed>> $childrenRows
     *
     * @return list<string>
     */
    private static function prefetchPhysicalKeysForBatch(array $childrenRows): array
    {
        $keys = [];
        foreach ($childrenRows as $child) {
            $status = (string) ($child['status'] ?? '');
            if ($status === 'success' || $status === 'cancelled') {
                continue;
            }
            $physicalKey = (string) ($child['physical_key'] ?? '');
            if ($physicalKey === '') {
                continue;
            }
            $keys[$physicalKey] = true;
            $parentPhysicalKey = PhysicalKeyHelper::aggregateParentKey($physicalKey, $child);
            if ($parentPhysicalKey !== '') {
                $keys[$parentPhysicalKey] = true;
            }

            $scope = [];
            $scopeRaw = (string) ($child['scope_json'] ?? '');
            if ($scopeRaw !== '') {
                $decoded = json_decode($scopeRaw, true);
                if (is_array($decoded)) {
                    $scope = $decoded;
                }
            }
            $logical = [];
            $logicalRaw = (string) ($child['logical_sources_json'] ?? '');
            if ($logicalRaw !== '') {
                $decoded = json_decode($logicalRaw, true);
                if (is_array($decoded)) {
                    $logical = $decoded;
                }
            }
            $driveId = self::driveIdForRun($child, $scope, $logical);
            if ($driveId !== '' && str_starts_with(PhysicalKeyHelper::baseKey($physicalKey), 'user:')) {
                $keys['drive:' . $driveId] = true;
            }
        }

        return array_keys($keys);
    }

    public static function releaseBatchClaim(
        string $nodeId,
        string $batchRunId,
        string $message = 'Worker released batch',
        string $reason = '',
    ): bool {
        if ($nodeId === '' || $batchRunId === '') {
            return false;
        }
        $isDrain = $reason === 'drain';
        if ($isDrain) {
            $message = 'Worker drain hand-off';
        }

        return Ms365BatchClaimRepository::release($batchRunId, $nodeId, $message);
    }

    public static function completeBatchClaim(string $nodeId, string $batchRunId): bool
    {
        if ($nodeId === '' || $batchRunId === '') {
            return false;
        }

        return Ms365BatchClaimRepository::complete($batchRunId, $nodeId);
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

    /**
     * Re-queue a terminal-failed backup child without wiping graph/kopia progress.
     */
    public static function requeueFailedBackupPreservingProgress(string $runId, string $message = 'Run re-queued'): bool
    {
        if ($runId === '' || RestoreRunRepository::isRestoreRun($runId)) {
            return false;
        }
        $run = BackupRunRepository::get($runId);
        if ($run === null) {
            return false;
        }
        $status = (string) ($run['status'] ?? '');
        if (!in_array($status, ['error', 'failed'], true)) {
            return false;
        }
        $now = time();
        Ms365WorkerLogRepository::releaseAssignment($runId, 'manual_requeue');

        $stats = self::decodeRunStatsJson($run);
        unset($stats['infra_requeue']);
        $encoded = self::encodeRunStatsJson($stats);

        $queueUpdate = [
            'status' => 'queued',
            'worker_node_id' => null,
            'claimed_at' => null,
            'lease_expires_at' => null,
            'scheduled_at' => $now,
            'attempts' => 0,
            'error_message' => mb_substr($message, 0, 500),
        ];
        if (Capsule::schema()->hasColumn('ms365_job_queue', 'finished_at')) {
            $queueUpdate['finished_at'] = null;
        }
        Capsule::table('ms365_job_queue')->where('run_id', $runId)->update($queueUpdate);

        $runUpdate = [
            'status' => 'queued',
            'error_message' => null,
            'finished_at' => null,
            'updated_at' => $now,
        ];
        if ($encoded !== null) {
            $runUpdate['stats_json'] = $encoded;
        }
        BackupRunRepository::update($runId, $runUpdate);
        Ms365BatchRunRepository::syncForChildRun($runId);

        return true;
    }

    /**
     * Return payload for a run already claimed running on this node (ghost-claim resume).
     */
    public static function resumeOwnedRunningClaim(string $nodeId): ?array
    {
        if ($nodeId === '') {
            return null;
        }
        $row = Capsule::table('ms365_job_queue as q')
            ->join('ms365_restore_runs as r', 'r.id', '=', 'q.run_id')
            ->where('q.worker_node_id', $nodeId)
            ->where('q.status', 'running')
            ->whereIn('r.status', ['queued', 'running'])
            ->orderBy('q.claimed_at')
            ->first(['q.run_id']);
        if ($row === null) {
            return null;
        }
        $runId = (string) ($row->run_id ?? '');
        if ($runId === '') {
            return null;
        }
        try {
            return self::buildRestoreRunPayload($runId);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function buildRunPayload(string $runId, ?array $batchContext = null, ?array $preloadedRun = null): ?array
    {
        $run = $preloadedRun ?? BackupRunRepository::get($runId);
        if ($run === null) {
            return null;
        }
        $tenantRecordId = (int) ($run['tenant_record_id'] ?? 0);
        if ($batchContext !== null) {
            $record = $batchContext['record'] ?? null;
            $creds = $batchContext['creds'] ?? null;
            if (!is_array($record) || !is_array($creds)) {
                throw new \RuntimeException('Invalid batch payload context.');
            }
        } else {
            $record = TenantRecordRepository::getById($tenantRecordId);
            if ($record === null) {
                throw new \RuntimeException('Tenant record not found for run.');
            }
            $creds = TenantRecordRepository::resolvedCredentialsForRecord($record);
        }

        $e3JobId = trim((string) ($run['e3_job_id'] ?? ''));
        if ($batchContext !== null) {
            $destinationsByJob = $batchContext['destinations_by_job'] ?? [];
            $dest = $destinationsByJob[$e3JobId] ?? Ms365JobDestinationService::resolveForRun($run, $record);
        } else {
            $dest = Ms365JobDestinationService::resolveForRun($run, $record);
        }

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
        if ($batchContext !== null) {
            $manifestMap = $batchContext['manifests'] ?? [];
            $previousManifest = $manifestMap[$physicalKey] ?? '';
            $deltaStates = self::resolveDeltaStatesFromBatchContext(
                $physicalKey,
                $parentPhysicalKey,
                $batchContext,
            );
        } else {
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
        }
        $scopeFlags = self::scopeFlagsForWorker($scope);

        $driveId = self::driveIdForRun($run, $scope, $logical);
        if ($driveId !== '' && str_starts_with(PhysicalKeyHelper::baseKey($physicalKey), 'user:')) {
            $driveKey = 'drive:' . $driveId;
            if ($batchContext !== null) {
                $driveDeltas = self::resolveDriveDeltaStatesFromBatchContext($driveKey, $batchContext);
            } else {
                $driveDeltas = DeltaStateRepository::getStatesForSource($tenantRecordId, $driveKey, $e3JobId !== '' ? $e3JobId : null);
                if ($driveDeltas === []) {
                    $driveDeltas = self::latestDeltaStates($tenantRecordId, $driveKey, $e3JobId);
                    if (!is_array($driveDeltas)) {
                        $driveDeltas = [];
                    }
                }
            }
            if (is_array($driveDeltas['onedrive'] ?? null)) {
                $deltaStates['onedrive'] = array_merge($deltaStates['onedrive'] ?? [], $driveDeltas['onedrive']);
            }
        }

        $platformFlags = null;
        if ($batchContext !== null) {
            $platformFlags = $batchContext['platform_workload_flags'] ?? null;
        }
        $workloads = self::workloadsForRun($run, $scope, is_array($platformFlags) ? $platformFlags : null);
        $siteId = self::siteIdForRun($run, $scope);
        $paginationAll = $batchContext !== null
            ? ($batchContext['pagination_limits'] ?? Ms365EngineConfig::paginationLimits())
            : null;
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
            'graph_pagination' => $paginationAll !== null
                ? self::paginationLimitsForWorkloadsFromCache($workloads, $paginationAll)
                : Ms365EngineConfig::paginationLimitsForWorkloads($workloads),
            'lease_expires_at' => WorkerLeaseService::leaseExpiresAt($runId),
            'kopia_source_path' => PhysicalKeyHelper::kopiaSourcePath((string) ($creds['tenant_id'] ?? ''), $physicalKey, $scope),
        ];
        if ($batchContext === null) {
            $payload['graph_token'] = self::graphTokenForTenantRecord($record);
            $payload['graph_tenant_budget'] = GraphTenantBudgetService::workerShare(
                $tenantRecordId,
                (string) ($creds['tenant_id'] ?? '')
            );
        }
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
     * @return array{graph_token?: string, expires_in?: int, retry_after?: int}
     */
    public static function refreshGraphTokenForRun(string $runId): array
    {
        $runId = trim($runId);
        if ($runId === '') {
            throw new \RuntimeException('run_id required.');
        }

        $batchLease = Ms365BatchClaimRepository::liveBatchLeaseForChildRun($runId);
        if ($batchLease !== null) {
            $tenantRecordId = (int) ($batchLease['tenant_record_id'] ?? 0);
            $record = TenantRecordRepository::getById($tenantRecordId);
            if ($record === null) {
                throw new \RuntimeException('Tenant record not found for batch.');
            }

            return [
                'graph_token' => self::graphTokenForTenantRecord($record),
                'expires_in' => 3600,
            ];
        }

        if (RestoreRunRepository::isRestoreRun($runId)) {
            $run = RestoreRunRepository::get($runId);
        } else {
            $run = BackupRunRepository::get($runId);
        }
        if ($run === null) {
            return ['retry_after' => 30];
        }
        $runStatus = (string) ($run['status'] ?? '');
        $queueRow = Capsule::table('ms365_job_queue')->where('run_id', $runId)->first();
        $queueStatus = $queueRow ? (string) ($queueRow->status ?? '') : '';
        $terminalQueue = in_array($queueStatus, ['failed', 'done'], true);
        $activeRun = in_array($runStatus, ['running', 'queued'], true);
        $activeQueue = in_array($queueStatus, ['running', 'queued'], true);
        if ($terminalQueue || (!$activeRun && !$activeQueue)) {
            return ['retry_after' => 30];
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

    /** @return array<string, array<string, string>> */
    private static function resolveDeltaStatesFromBatchContext(
        string $physicalKey,
        string $parentPhysicalKey,
        array $batchContext,
    ): array {
        $deltaMap = $batchContext['delta_states'] ?? [];
        $legacyMap = $batchContext['legacy_delta_states'] ?? [];

        $deltaStates = $deltaMap[$physicalKey] ?? [];
        if ($deltaStates === [] && $parentPhysicalKey !== $physicalKey) {
            $deltaStates = $deltaMap[$parentPhysicalKey] ?? [];
        }
        if ($deltaStates === []) {
            $legacy = $legacyMap[$physicalKey] ?? null;
            if (is_array($legacy) && $legacy !== []) {
                $deltaStates = $legacy;
            } elseif ($parentPhysicalKey !== $physicalKey) {
                $legacyParent = $legacyMap[$parentPhysicalKey] ?? null;
                if (is_array($legacyParent) && $legacyParent !== []) {
                    $deltaStates = $legacyParent;
                }
            }
        }

        return $deltaStates;
    }

    /** @return array<string, array<string, string>> */
    private static function resolveDriveDeltaStatesFromBatchContext(string $driveKey, array $batchContext): array
    {
        $deltaMap = $batchContext['delta_states'] ?? [];
        $driveDeltas = $deltaMap[$driveKey] ?? [];
        if ($driveDeltas !== []) {
            return $driveDeltas;
        }
        $legacyMap = $batchContext['legacy_delta_states'] ?? [];
        $legacyDrive = $legacyMap[$driveKey] ?? null;

        return is_array($legacyDrive) ? $legacyDrive : [];
    }

    /**
     * @param list<string> $physicalKeys
     * @param array{e3_job_id: string, legacy_shared_bucket: bool} $jobScope
     *
     * @return array<string, array<string, mixed>> physical_key => decoded delta_states_json
     */
    private static function latestDeltaStatesForSources(int $tenantRecordId, array $physicalKeys, array $jobScope): array
    {
        if ($tenantRecordId <= 0 || $physicalKeys === [] || !Capsule::schema()->hasColumn('ms365_backup_runs', 'delta_states_json')) {
            return [];
        }

        $physicalKeys = array_values(array_unique(array_filter(
            $physicalKeys,
            static fn ($key) => is_string($key) && $key !== '',
        )));
        if ($physicalKeys === []) {
            return [];
        }

        $e3JobId = trim((string) ($jobScope['e3_job_id'] ?? ''));
        $legacy = (bool) ($jobScope['legacy_shared_bucket'] ?? true);

        $q = Capsule::table('ms365_backup_runs')
            ->where('tenant_record_id', $tenantRecordId)
            ->whereIn('physical_key', $physicalKeys)
            ->where('status', 'success')
            ->whereNotNull('delta_states_json')
            ->where('delta_states_json', '!=', '');

        if ($e3JobId !== '' && Capsule::schema()->hasColumn('ms365_backup_runs', 'e3_job_id')) {
            if ($legacy) {
                $q->where(function ($sub) use ($e3JobId): void {
                    $sub->where('e3_job_id', $e3JobId)->orWhereNull('e3_job_id')->orWhere('e3_job_id', '');
                });
            } else {
                $q->where('e3_job_id', $e3JobId);
            }
        }

        $rows = $q->orderByDesc('finished_at')->get(['physical_key', 'delta_states_json']);
        $out = [];
        foreach ($rows as $row) {
            $physicalKey = (string) ($row->physical_key ?? '');
            if ($physicalKey === '' || isset($out[$physicalKey])) {
                continue;
            }
            $raw = (string) ($row->delta_states_json ?? '');
            if ($raw === '') {
                continue;
            }
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && $decoded !== []) {
                $out[$physicalKey] = $decoded;
            }
        }

        return $out;
    }

    /** @param array<string, mixed> $workloads */
    private static function paginationLimitsForWorkloadsFromCache(array $workloads, array $allLimits): array
    {
        $out = [];
        if (isset($allLimits['default'])) {
            $out['default'] = $allLimits['default'];
        }
        foreach ($workloads as $name => $enabled) {
            if (!$enabled || !is_string($name)) {
                continue;
            }
            if (isset($allLimits[$name])) {
                $out[$name] = $allLimits[$name];
            }
        }

        return $out;
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
            $jobScope = DeltaStateRepository::computeJobScope($e3JobId, $tenantRecordId);
            if ($jobScope['legacy_shared_bucket']) {
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
            'graph_tenant_budget' => GraphTenantBudgetService::workerShare(
                $tenantRecordId,
                (string) ($creds['tenant_id'] ?? '')
            ),
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
    private static function workloadsForRun(array $run, array $scope, ?array $platformFlags = null): array
    {
        $flags = $platformFlags ?? Ms365EngineConfig::workloadFlags();
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

        $platform = $platformFlags ?? Ms365EngineConfig::workloadFlags();
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

    /** Fail restore runs still marked running on a worker that reports zero load
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

    /** Clear internal queue ops text once a worker is actively running the job again. */
    public static function clearQueueOperationalMessage(string $runId): void
    {
        if ($runId === '' || !Capsule::schema()->hasTable('ms365_job_queue')) {
            return;
        }
        Capsule::table('ms365_job_queue')
            ->where('run_id', $runId)
            ->whereIn('status', ['running', 'queued'])
            ->where('error_message', '!=', '')
            ->update([
                'error_message' => '',
            ]);
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
        } elseif (strcasecmp($message, 'Worker released claim') === 0) {
            // Admit-reject / tryStart-fail releases never ran the workload; do not burn attempts.
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

        $permanentlyStalled = [];
        if ($infrastructureRequeue) {
            $stillEligible = [];
            foreach ($runIds as $runId) {
                if (self::shouldFailInfrastructureStalledRun($runId)) {
                    $permanentlyStalled[] = $runId;
                } else {
                    self::recordInfrastructureRequeueAttempt($runId);
                    $stillEligible[] = $runId;
                }
            }
            $runIds = $stillEligible;
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

        foreach ($permanentlyStalled as $runId) {
            if (BackupRunRepository::isCancelled($runId)) {
                continue;
            }
            $run = BackupRunRepository::get($runId);
            if ($run !== null && in_array($run['status'] ?? '', ['success', 'error', 'cancelled'], true)) {
                continue;
            }
            self::terminalFailBackupRun($runId, self::INFRA_REQUEUE_STALL_MESSAGE);
        }
    }

    /**
     * Terminal-fail a backup run without requeueing (stall / infra-gave-up paths).
     */
    public static function terminalFailBackupRun(string $runId, string $message): void
    {
        if ($runId === '' || RestoreRunRepository::isRestoreRun($runId)) {
            return;
        }
        $now = time();
        $customerMessage = Ms365CustomerError::message(new \RuntimeException($message));
        JobQueueRepository::markTerminalFailed($runId, $message);
        BackupRunRepository::update($runId, [
            'status' => 'error',
            'error_message' => $customerMessage,
            'finished_at' => $now,
            'updated_at' => $now,
        ]);
        Ms365BatchRunRepository::syncForChildRun($runId);
        (new ProgressLogger($runId))->error($customerMessage !== '' ? $customerMessage : 'Backup failed');
    }

    public static function countPlatformRunning(): int
    {
        return (int) Capsule::table('ms365_job_queue')->where('status', 'running')->count();
    }

    public static function countRunningForClient(int $clientId): int
    {
        return JobQueueRepository::countRunningForClient($clientId);
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
    private static function headRowsPerBatch($rows, int $perBatchLimit)
    {
        return $rows->groupBy(static function ($row) {
            $batch = trim((string) ($row->e3_batch_run_id ?? ''));
            if ($batch !== '') {
                return $batch;
            }

            return (string) ($row->run_id ?? '');
        })->flatMap(static function ($group) use ($perBatchLimit) {
            return $group->take($perBatchLimit);
        })->values();
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
                Capsule::select('SELECT ROW_NUMBER() OVER (ORDER BY (SELECT 1)) AS rn FROM (SELECT 1 AS x) AS t LIMIT 1');
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

    /** @param array<string, mixed> $run */
    private static function isNearCompleteRun(array $run): bool
    {
        $itemsDone = (int) ($run['items_done'] ?? 0);
        $itemsTotal = (int) ($run['items_total'] ?? 0);

        return $itemsTotal > 0 && $itemsDone >= $itemsTotal - 1;
    }

    private static function shouldFailInfrastructureStalledRun(string $runId): bool
    {
        if ($runId === '' || RestoreRunRepository::isRestoreRun($runId)) {
            return false;
        }
        $run = BackupRunRepository::get($runId);
        if ($run === null) {
            return false;
        }
        if (self::isNearCompleteRun($run)) {
            return false;
        }
        $phase = strtolower(trim((string) ($run['phase'] ?? '')));
        if (!in_array($phase, ['graph_sync', 'prior_snapshot'], true)) {
            return false;
        }
        $stats = self::decodeRunStatsJson($run);
        $tracker = is_array($stats['infra_requeue'] ?? null) ? $stats['infra_requeue'] : [];
        $count = (int) ($tracker['count'] ?? 0);
        if ($count < self::INFRA_REQUEUE_STALL_LIMIT - 1) {
            return false;
        }
        $current = self::progressSnapshotForRun($run, $stats);
        $previous = is_array($tracker['snapshot'] ?? null) ? $tracker['snapshot'] : [];
        if ($previous === []) {
            return false;
        }

        return !self::progressImprovedSince($previous, $current, $phase);
    }

    private static function recordInfrastructureRequeueAttempt(string $runId): void
    {
        if ($runId === '' || RestoreRunRepository::isRestoreRun($runId)) {
            return;
        }
        $run = BackupRunRepository::get($runId);
        if ($run === null) {
            return;
        }
        $phase = strtolower(trim((string) ($run['phase'] ?? '')));
        if (!in_array($phase, ['graph_sync', 'prior_snapshot'], true)) {
            return;
        }
        $stats = self::decodeRunStatsJson($run);
        $current = self::progressSnapshotForRun($run, $stats);
        if (self::isNearCompleteRun($run)) {
            $stats['infra_requeue'] = [
                'count' => 0,
                'snapshot' => $current,
            ];
            $encoded = self::encodeRunStatsJson($stats);
            if ($encoded !== null) {
                BackupRunRepository::update($runId, [
                    'stats_json' => $encoded,
                    'updated_at' => time(),
                ]);
            }

            return;
        }
        $tracker = is_array($stats['infra_requeue'] ?? null) ? $stats['infra_requeue'] : [];
        $previous = is_array($tracker['snapshot'] ?? null) ? $tracker['snapshot'] : [];
        $count = (int) ($tracker['count'] ?? 0);
        if ($previous !== [] && self::progressImprovedSince($previous, $current, $phase)) {
            $count = 0;
        } else {
            ++$count;
        }
        $stats['infra_requeue'] = [
            'count' => $count,
            'snapshot' => $current,
        ];
        $encoded = self::encodeRunStatsJson($stats);
        if ($encoded === null) {
            return;
        }
        BackupRunRepository::update($runId, [
            'stats_json' => $encoded,
            'updated_at' => time(),
        ]);
    }

    /**
     * @param array<string, mixed> $run
     * @param array<string, mixed> $stats
     *
     * @return array{items_done: int, percent: float, bytes_hashed: int, bytes_uploaded: int, graph_requests: int}
     */
    private static function progressSnapshotForRun(array $run, array $stats): array
    {
        return [
            'items_done' => (int) ($run['items_done'] ?? 0),
            'percent' => (float) ($run['percent'] ?? 0),
            'bytes_hashed' => (int) ($run['bytes_hashed'] ?? 0),
            'bytes_uploaded' => (int) ($run['bytes_uploaded'] ?? 0),
            'graph_requests' => (int) ($stats['graph_requests'] ?? 0),
        ];
    }

    /**
     * @param array{items_done: int, percent: float, bytes_hashed: int, bytes_uploaded: int, graph_requests: int} $previous
     * @param array{items_done: int, percent: float, bytes_hashed: int, bytes_uploaded: int, graph_requests: int} $current
     */
    private static function progressImprovedSince(array $previous, array $current, string $phase): bool
    {
        if ($current['items_done'] > $previous['items_done']) {
            return true;
        }
        if ($current['percent'] > $previous['percent']) {
            return true;
        }
        if ($current['bytes_hashed'] > $previous['bytes_hashed']) {
            return true;
        }
        if ($current['bytes_uploaded'] > $previous['bytes_uploaded']) {
            return true;
        }
        if (in_array($phase, ['graph_sync', 'prior_snapshot'], true)
            && $current['graph_requests'] > $previous['graph_requests']) {
            return true;
        }

        return false;
    }

    /** @return array<string, mixed> */
    private static function decodeRunStatsJson(array $run): array
    {
        $raw = $run['stats_json'] ?? '';
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string, mixed> $stats */
    private static function encodeRunStatsJson(array $stats): ?string
    {
        if (!Capsule::schema()->hasColumn('ms365_backup_runs', 'stats_json')) {
            return null;
        }

        return json_encode($stats, JSON_UNESCAPED_SLASHES);
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
