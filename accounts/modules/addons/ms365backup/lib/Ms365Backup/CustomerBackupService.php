<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * Customer-facing backup operations (e3 Cloud Backup UI).
 */
final class CustomerBackupService
{
    public const PRESET_USER_MAIL_CALENDAR = CustomerPresetCatalog::PRESET_USER_MAIL_CALENDAR;

    /** @return array<string, mixed> */
    public static function statusForBackupUser(int $clientId, int $backupUserId): array
    {
        $record = $backupUserId > 0
            ? TenantRecordRepository::getForBackupUser($clientId, $backupUserId)
            : TenantRecordRepository::getPrimaryForClient($clientId);
        if ($record === null) {
            return [
                'connected' => false,
                'needs_reconnect' => false,
                'connection_status' => 'not_connected',
                'connection_auth_mode' => 'none',
                'credentials_preview' => Ms365CustomerConnectService::credentialPreviewForRecord(null),
                'azure_tenant_id' => '',
                'bucket_name' => '',
                'health_error' => '',
                'last_run' => null,
                'inventory' => CustomerInventoryService::summaryForBackupUser($clientId, $backupUserId),
                'onboarding' => Ms365Onboarding::computeForBackupUser($clientId, $backupUserId),
            ];
        }

        $runs = BackupRunRepository::listRecentForClient($clientId, 1);
        $lastRun = $runs[0] ?? null;
        $connectionStatus = (string) ($record['connection_status'] ?? 'pending');

        return [
            'connected' => $connectionStatus === 'connected',
            'needs_reconnect' => $connectionStatus === 'action_required',
            'connection_status' => $connectionStatus,
            'connection_auth_mode' => self::connectionAuthModeForRecord($record),
            'credentials_preview' => Ms365CustomerConnectService::credentialPreviewForRecord($record),
            'tenant_record_id' => (int) $record['id'],
            'azure_tenant_id' => (string) ($record['azure_tenant_id'] ?? ''),
            'bucket_name' => (string) ($record['s3_bucket_name'] ?? $record['s3_bucket'] ?? ''),
            'health_error' => Ms365CustomerError::sanitizeStored($record['health_error'] ?? null),
            'consent_granted_at' => $record['consent_granted_at'] ?? null,
            'last_run' => $lastRun ? [
                'id' => $lastRun['id'],
                'status' => $lastRun['status'],
                'created_at' => $lastRun['created_at'],
                'user_display_name' => $lastRun['user_display_name'] ?? '',
            ] : null,
            'inventory' => CustomerInventoryService::summaryForBackupUser($clientId, $backupUserId),
            'onboarding' => Ms365Onboarding::computeForBackupUser($clientId, $backupUserId),
        ];
    }

    /** @return array<string, mixed> */
    public static function statusForClient(int $clientId): array
    {
        $record = TenantRecordRepository::getPrimaryForClient($clientId);
        if ($record === null) {
            return [
                'connected' => false,
                'needs_reconnect' => false,
                'connection_status' => 'not_connected',
                'connection_auth_mode' => 'none',
                'credentials_preview' => Ms365CustomerConnectService::credentialPreviewForRecord(null),
                'azure_tenant_id' => '',
                'bucket_name' => '',
                'health_error' => '',
                'last_run' => null,
                'inventory' => CustomerInventoryService::summaryForClient($clientId),
                'onboarding' => Ms365Onboarding::compute($clientId),
            ];
        }

        $runs = BackupRunRepository::listRecentForClient($clientId, 1);
        $lastRun = $runs[0] ?? null;
        $connectionStatus = (string) ($record['connection_status'] ?? 'pending');

        return [
            'connected' => $connectionStatus === 'connected',
            'needs_reconnect' => $connectionStatus === 'action_required',
            'connection_status' => $connectionStatus,
            'connection_auth_mode' => self::connectionAuthModeForRecord($record),
            'credentials_preview' => Ms365CustomerConnectService::credentialPreviewForRecord($record),
            'tenant_record_id' => (int) $record['id'],
            'azure_tenant_id' => (string) ($record['azure_tenant_id'] ?? ''),
            'bucket_name' => (string) ($record['s3_bucket_name'] ?? $record['s3_bucket'] ?? ''),
            'health_error' => Ms365CustomerError::sanitizeStored($record['health_error'] ?? null),
            'consent_granted_at' => $record['consent_granted_at'] ?? null,
            'last_run' => $lastRun ? [
                'id' => $lastRun['id'],
                'status' => $lastRun['status'],
                'created_at' => $lastRun['created_at'],
                'user_display_name' => $lastRun['user_display_name'] ?? '',
            ] : null,
            'inventory' => CustomerInventoryService::summaryForClient($clientId),
            'onboarding' => Ms365Onboarding::compute($clientId),
        ];
    }

    /**
     * @return array{run_ids: list<string>, count: int}
     */
    public static function startPresetBackup(int $clientId, string $preset): array
    {
        $record = TenantRecordRepository::getPrimaryForClient($clientId);
        if ($record === null || ($record['connection_status'] ?? '') !== 'connected') {
            throw new \RuntimeException('Connect Microsoft 365 before starting a backup.');
        }

        $onboarding = Ms365Onboarding::compute($clientId);
        if (empty($onboarding['can_start_backup'])) {
            throw new \RuntimeException('Refresh tenant inventory before starting a backup.');
        }

        $tenantRecordId = (int) $record['id'];

        try {
            TenantRecordRepository::ensureCloudStorageBucketForClient($clientId);

            $ctx = RunTenantContext::forClientRecord($record);
            $storageLayout = $ctx->storageLayout;

            $inventoryService = new InventoryService(
                $ctx->graph,
                $storageLayout,
                new DiscoveryService($ctx->graph, $storageLayout),
            );
            $inventory = $inventoryService->load();
            if ($inventory === null || !is_array($inventory['resources'] ?? null) || $inventory['resources'] === []) {
                throw new \RuntimeException('No inventory found. Refresh inventory first.');
            }

            $resolved = CustomerPresetCatalog::resolve($preset, $inventory);
            $selectedIds = $resolved['selected_ids'];
            if ($selectedIds === []) {
                throw new \RuntimeException('No resources match the selected preset. Try refreshing inventory.');
            }

            $planner = new BackupPlanner();
            $queue = $planner->buildPhysicalQueue($selectedIds, $inventory, $resolved['scope'], []);

            $runnableJobs = array_values(array_filter(
                $queue['physical_jobs'],
                static fn (PhysicalBackupJob $job) => $job->isRunnable(),
            ));
            $runIds = BackupRunRepository::createManyFromPhysicalJobs(
                $runnableJobs,
                $storageLayout,
                $tenantRecordId,
                $clientId,
            );
            JobQueueRepository::enqueueMany($runIds, 50);

            if ($runIds === []) {
                throw new \RuntimeException('No runnable backup jobs for the selected preset.');
            }

            return ['run_ids' => $runIds, 'count' => count($runIds)];
        } catch (\Throwable $e) {
            Ms365ConnectionGuard::throwIfReconnectRequired($tenantRecordId, $e);
        }
    }

    /**
     * @param list<string> $selectedIds
     * @param array<string, array<string, bool>> $scopeOverridesByResourceId
     * @return array{run_ids: list<string>, batch_run_id: string, count: int}
     */
    public static function startCustomBackup(
        int $clientId,
        int $backupUserId,
        array $selectedIds,
        string $e3JobId,
        string $triggerType = 'manual',
        array $scopeOverridesByResourceId = [],
    ): array {
        $record = TenantRecordRepository::getForBackupUser($clientId, $backupUserId)
            ?? TenantRecordRepository::getPrimaryForClient($clientId);
        if ($record === null || ($record['connection_status'] ?? '') !== 'connected') {
            throw new \RuntimeException('Connect Microsoft 365 before starting a backup.');
        }

        $onboarding = Ms365Onboarding::computeForBackupUser($clientId, $backupUserId);
        if (empty($onboarding['can_start_backup'])) {
            throw new \RuntimeException('Refresh tenant inventory before starting a backup.');
        }

        $tenantRecordId = (int) $record['id'];

        try {
            TenantRecordRepository::ensureCloudStorageBucketForBackupUser($clientId, $backupUserId);

            $ctx = RunTenantContext::forClientRecord($record);
            $storageLayout = $ctx->storageLayout;

            $inventoryService = new InventoryService(
                $ctx->graph,
                $storageLayout,
                new DiscoveryService($ctx->graph, $storageLayout),
            );
            $inventory = $inventoryService->load();
            if ($inventory === null || !is_array($inventory['resources'] ?? null) || $inventory['resources'] === []) {
                throw new \RuntimeException('No inventory found. Refresh inventory first.');
            }

            $planner = new BackupPlanner();
            $queue = $planner->buildPhysicalQueue(
                $selectedIds,
                $inventory,
                BackupScope::empty(),
                CustomerSelectionCodec::normalizeScopeOverrides($scopeOverridesByResourceId),
            );

            $batchRunId = Ms365BatchRunRepository::create($clientId, $e3JobId, $triggerType);

            $runnableJobs = array_values(array_filter(
                $queue['physical_jobs'],
                static fn (PhysicalBackupJob $job) => $job->isRunnable(),
            ));
            $runIds = BackupRunRepository::createManyFromPhysicalJobs(
                $runnableJobs,
                $storageLayout,
                $tenantRecordId,
                $clientId,
                $backupUserId,
                $e3JobId,
                $batchRunId,
            );
            JobQueueRepository::enqueueMany($runIds, 50);

            if ($runIds === []) {
                Ms365BatchRunRepository::finalize($batchRunId, 'failed');
                throw new \RuntimeException('No runnable backup jobs for the selected resources.');
            }

            if (function_exists('logActivity')) {
                logActivity(sprintf(
                    'MS365 batch %s queued %d workload(s) for client %d',
                    $batchRunId,
                    count($runIds),
                    $clientId,
                ));
            }

            return ['run_ids' => $runIds, 'batch_run_id' => $batchRunId, 'count' => count($runIds)];
        } catch (\Throwable $e) {
            Ms365ConnectionGuard::throwIfReconnectRequired($tenantRecordId, $e);
        }
    }

    /** @return array<string, mixed> */
    public static function runDetailForClient(int $clientId, string $runId): array
    {
        $run = BackupRunRepository::getForClient($runId, $clientId);
        if ($run === null) {
            throw new \RuntimeException('Backup run not found.');
        }

        return [
            'id' => $run['id'],
            'status' => $run['status'] ?? '',
            'phase' => $run['phase'] ?? '',
            'percent' => (int) ($run['percent'] ?? 0),
            'physical_key' => $run['physical_key'] ?? '',
            'resource_type' => $run['resource_type'] ?? '',
            'user_display_name' => $run['user_display_name'] ?? '',
            'user_upn' => $run['user_upn'] ?? '',
            'graph_id' => $run['graph_id'] ?? '',
            'error_message' => $run['error_message'] ?? '',
            'created_at' => $run['created_at'] ?? null,
            'started_at' => $run['started_at'] ?? null,
            'finished_at' => $run['finished_at'] ?? null,
        ];
    }

    /**
     * @return array{lines: list<array<string, mixed>>, last_id: int}
     */
    public static function runLogsForClient(int $clientId, string $runId, int $sinceId = 0): array
    {
        $run = BackupRunRepository::getForClient($runId, $clientId);
        if ($run === null) {
            throw new \RuntimeException('Backup run not found.');
        }

        $lines = ProgressLogger::tail($runId, $sinceId);
        $lastId = $sinceId;
        foreach ($lines as $line) {
            $lastId = max($lastId, (int) ($line['id'] ?? 0));
        }

        return ['lines' => $lines, 'last_id' => $lastId];
    }

    /** @param array<string, mixed> $record */
    private static function connectionAuthModeForRecord(array $record): string
    {
        if (TenantRecordRepository::usesCustomerAppCredentials($record)) {
            return TenantRecordRepository::AUTH_MODE_CUSTOMER;
        }

        return TenantRecordRepository::AUTH_MODE_PLATFORM;
    }
}
