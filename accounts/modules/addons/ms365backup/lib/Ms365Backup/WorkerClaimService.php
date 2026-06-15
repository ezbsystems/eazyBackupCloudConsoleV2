<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\HelperController;

final class WorkerClaimService
{
    public static function claimNext(string $nodeId): ?array
    {
        WorkerNodeRepository::markOfflineStale();
        self::releaseExpiredLeases();
        self::recoverStaleRunning();

        $node = WorkerNodeRepository::get($nodeId);
        if ($node === null || ($node['status'] ?? '') !== 'active') {
            return null;
        }

        if ((int) ($node['current_load'] ?? 0) >= (int) ($node['max_concurrent_runs'] ?? 1)) {
            return null;
        }

        if (self::countPlatformRunning() >= Ms365EngineConfig::platformMaxConcurrent()) {
            return null;
        }

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
        $candidates = $backupQuery
            ->orderBy('q.priority')
            ->orderBy('q.id')
            ->select(['q.*', 'r.tenant_record_id', 'r.whmcs_client_id', 'r.resource_id', 'r.resource_type', 'r.graph_id', 'r.physical_key', 'r.scope_json'])
            ->limit(50)
            ->get();

        $restoreCandidates = collect();
        if (self::restoreClaimQueryReady()) {
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
            $restoreCandidates = $restoreQ
                ->orderBy('q.priority')
                ->orderBy('q.id')
                ->select(self::restoreClaimSelectColumns())
                ->limit(50)
                ->get();
        }

        $merged = $candidates->merge($restoreCandidates)->sortBy([
            ['priority', 'asc'],
            ['id', 'asc'],
        ])->values();

        foreach ($merged as $candidate) {
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

            if ($jobType === 'restore') {
                RestoreRunRepository::update($runId, [
                    'status' => 'running',
                    'phase' => 'claimed',
                    'started_at' => $now,
                ]);
            } else {
                BackupRunRepository::update($runId, [
                    'status' => 'running',
                    'started_at' => $now,
                    'engine_mode' => 'kopia',
                ]);
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
        JobQueueRepository::markFailed($runId, $message);
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
        BackupRunRepository::update($runId, [
            'status' => 'error',
            'error_message' => $customerMessage,
            'finished_at' => $now,
            'updated_at' => $now,
        ]);
        JobQueueRepository::markFailed($runId, $customerMessage);
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

        $creds = TenantRecordRepository::platformCredentials($record);
        $tokenProvider = new TokenProvider($creds['region'], $creds['tenant_id'], $creds['client_id'], $creds['client_secret']);
        $graphToken = $tokenProvider->getAccessToken();

        $storage = BackupStorageFactory::createForTenantRecord($record);
        $dest = self::resolveDestination($record, $storage);

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
        $parentPhysicalKey = PhysicalKeyHelper::baseKey($physicalKey);
        $shard = PhysicalKeyHelper::parseShard($physicalKey);
        $previousManifest = KopiaRepoBootstrapService::latestManifestForSource($tenantRecordId, $physicalKey);
        $deltaStates = DeltaStateRepository::getStatesForSource($tenantRecordId, $physicalKey);
        if ($deltaStates === [] && $parentPhysicalKey !== $physicalKey) {
            $deltaStates = DeltaStateRepository::getStatesForSource($tenantRecordId, $parentPhysicalKey);
        }
        if ($deltaStates === []) {
            $legacy = self::latestDeltaStates($tenantRecordId, $physicalKey);
            if (is_array($legacy) && $legacy !== []) {
                $deltaStates = $legacy;
            } elseif ($parentPhysicalKey !== $physicalKey) {
                $legacyParent = self::latestDeltaStates($tenantRecordId, $parentPhysicalKey);
                if (is_array($legacyParent)) {
                    $deltaStates = $legacyParent;
                }
            }
        }

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
            'scope' => $scope,
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
            'workloads' => self::workloadsForRun($run, $scope),
            'lease_expires_at' => WorkerLeaseService::leaseExpiresAt($runId),
            'kopia_source_path' => PhysicalKeyHelper::kopiaSourcePath((string) ($creds['tenant_id'] ?? ''), $physicalKey),
        ];
        if ($shard !== null) {
            $shardMeta = [];
            if (is_array($scope['_shard'] ?? null)) {
                $shardMeta = $scope['_shard'];
            }
            $payload['shard'] = [
                'index' => (int) ($shardMeta['index'] ?? $shard['index'] ?? 0),
                'total' => (int) ($shardMeta['total'] ?? $shard['total'] ?? 0),
                'kind' => (string) ($shard['kind'] ?? 'range'),
                'segment' => (string) ($shard['segment'] ?? ''),
                'parent_physical_key' => (string) ($shardMeta['parent_physical_key'] ?? $parentPhysicalKey),
            ];
        }

        return $payload;
    }

    /**
     * Most recent successful delta_states for a source, emitted verbatim into the claim payload.
     * Returns an object (stdClass) when none exist so JSON encodes to {} for the Go map decoder.
     *
     * @return \stdClass|array<string, array<string, string>>
     */
    private static function latestDeltaStates(int $tenantRecordId, string $physicalKey)
    {
        $empty = new \stdClass();
        if ($tenantRecordId <= 0 || $physicalKey === '') {
            return $empty;
        }
        if (!Capsule::schema()->hasColumn('ms365_backup_runs', 'delta_states_json')) {
            return $empty;
        }
        $raw = Capsule::table('ms365_backup_runs')
            ->where('tenant_record_id', $tenantRecordId)
            ->where('physical_key', $physicalKey)
            ->where('status', 'success')
            ->whereNotNull('delta_states_json')
            ->where('delta_states_json', '!=', '')
            ->orderByDesc('finished_at')
            ->value('delta_states_json');
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

        $creds = TenantRecordRepository::platformCredentials($record);
        $tokenProvider = new TokenProvider($creds['region'], $creds['tenant_id'], $creds['client_id'], $creds['client_secret']);
        $graphToken = $tokenProvider->getAccessToken();
        $dest = self::destinationForTenantRecord($record);

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

        return [
            'run_id' => $restoreRunId,
            'job_type' => 'restore',
            'tenant_record_id' => $tenantRecordId,
            'whmcs_client_id' => (int) ($run['whmcs_client_id'] ?? 0),
            'azure_tenant_id' => (string) ($creds['tenant_id'] ?? ''),
            'graph_id' => (string) ($run['target_graph_id'] ?? ''),
            'resource_type' => (string) ($run['resource_type'] ?? ''),
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
            ],
            'lease_expires_at' => WorkerLeaseService::leaseExpiresAt($restoreRunId),
        ];
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
        } elseif (str_starts_with($physical, 'drive:') || str_starts_with($physical, 'onedrive:')) {
            $only = ['onedrive'];
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

    /** @return array{endpoint: string, region: string, bucket: string, prefix: string, access_key: string, secret_key: string, repo_password: string, kopia_repo_id: string} */
    public static function destinationForTenantRecord(array $record): array
    {
        $storage = BackupStorageFactory::createForTenantRecord($record);

        return self::resolveDestination($record, $storage);
    }

    /** @return array{endpoint: string, region: string, bucket: string, prefix: string, access_key: string, secret_key: string, repo_password: string, kopia_repo_id: string} */
    private static function resolveDestination(array $record, BackupStorageInterface $storage): array
    {
        $bucket = trim((string) ($record['s3_bucket_name'] ?? $record['s3_bucket'] ?? ''));
        $settings = Capsule::table('tbladdonmodules')->where('module', 'cloudstorage')->pluck('value', 'setting');
        $endpoint = trim((string) ($settings['s3_endpoint'] ?? ''));
        $region = trim((string) ($settings['s3_region'] ?? 'us-east-1'));

        $ownerUserId = (int) ($record['s3_user_id'] ?? 0);
        $accessKey = '';
        $secretKey = '';
        if ($ownerUserId > 0) {
            $keyRow = Capsule::table('s3_user_access_keys')->where('user_id', $ownerUserId)->orderByDesc('id')->first();
            if ($keyRow) {
                $encKeyPrimary = trim((string) ($settings['cloudbackup_encryption_key'] ?? ''));
                $encKeySecondary = trim((string) ($settings['encryption_key'] ?? ''));
                foreach ([$encKeyPrimary, $encKeySecondary] as $encKey) {
                    if ($encKey === '') {
                        continue;
                    }
                    $accessKey = (string) HelperController::decryptKey((string) $keyRow->access_key, $encKey);
                    $secretKey = (string) HelperController::decryptKey((string) $keyRow->secret_key, $encKey);
                    if ($accessKey !== '' && $secretKey !== '') {
                        break;
                    }
                }
            }
        }

        $repoMeta = KopiaRepoBootstrapService::ensureForTenantRecord($record);
        $repoPassword = (string) ($repoMeta['repo_password'] ?? '');
        $kopiaRepoId = (string) ($repoMeta['repository_id'] ?? '');

        return [
            'endpoint' => $endpoint,
            'region' => $region,
            'bucket' => $bucket,
            'prefix' => 'kopia/',
            'access_key' => $accessKey,
            'secret_key' => $secretKey,
            'repo_password' => $repoPassword,
            'kopia_repo_id' => $kopiaRepoId,
        ];
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
        if ($nodeId === '' || $reportedLoad > 0) {
            return 0;
        }

        $now = time();
        $cutoff = $now - max(60, $staleProgressSeconds);
        $rows = Capsule::table('ms365_job_queue as q')
            ->join('ms365_backup_runs as r', 'r.id', '=', 'q.run_id')
            ->where('q.status', 'running')
            ->where('q.worker_node_id', $nodeId)
            ->where('q.claimed_at', '<', $cutoff)
            ->where(function ($q) {
                $q->where('r.percent', '<=', 0)
                    ->orWhereNull('r.percent');
            })
            ->where(function ($q) {
                $q->whereNull('r.phase')->orWhere('r.phase', '');
            })
            ->pluck('q.run_id')
            ->all();
        if ($rows === []) {
            return 0;
        }
        self::requeueRuns($rows, 'Orphaned claim released (worker idle)');

        return count($rows);
    }

    public static function releaseClaim(string $nodeId, string $runId, string $message = 'Worker released claim'): bool
    {
        if ($nodeId === '' || $runId === '') {
            return false;
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
        BackupRunRepository::update($runId, [
            'status' => 'queued',
            'updated_at' => $now,
        ]);

        return true;
    }

    /** @param list<string> $runIds */
    private static function requeueRuns(array $runIds, string $message): void
    {
        if ($runIds === []) {
            return;
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
                'error_message' => mb_substr($message, 0, 500),
            ]);
        foreach ($runIds as $runId) {
            BackupRunRepository::update($runId, [
                'status' => 'queued',
                'updated_at' => $now,
            ]);
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
        return (int) Capsule::table('ms365_job_queue as q')
            ->join('ms365_backup_runs as r', 'r.id', '=', 'q.run_id')
            ->where('q.status', 'running')
            ->where('r.tenant_record_id', $tenantRecordId)
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
}
