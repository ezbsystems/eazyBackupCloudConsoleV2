<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * Runs tenant inventory refresh outside the web request (avoids FPM/proxy timeouts).
 */
final class InventoryBackgroundRefresh
{
    private const PROGRESS_STALE_SECONDS = 600;
    private const RUNNING_STALE_SECONDS = 90;

    /** @return array{status: string, refresh_in_progress?: bool, message?: string} */
    public static function start(int $clientId, int $backupUserId): array
    {
        if ($clientId <= 0 || $backupUserId <= 0) {
            throw new \RuntimeException('Invalid client or backup user.');
        }

        if (self::isInProgress($clientId, $backupUserId)) {
            return [
                'status' => 'accepted',
                'refresh_in_progress' => true,
                'message' => 'Inventory refresh is already running.',
            ];
        }

        try {
            self::spawnWorker($clientId, $backupUserId);
        } catch (\Throwable $e) {
            self::markError($clientId, $backupUserId, $e->getMessage());

            throw $e;
        }

        return [
            'status' => 'accepted',
            'refresh_in_progress' => true,
            'message' => 'Inventory refresh started in the background.',
        ];
    }

    public static function isInProgress(int $clientId, int $backupUserId): bool
    {
        $progress = self::readProgress($clientId, $backupUserId);
        if ($progress === null) {
            return false;
        }
        $phase = (string) ($progress['phase'] ?? '');
        if ($phase === 'complete' || $phase === 'error') {
            return false;
        }
        $updatedAt = strtotime((string) ($progress['updated_at'] ?? ''));
        $staleSeconds = $phase === 'running' ? self::RUNNING_STALE_SECONDS : self::PROGRESS_STALE_SECONDS;
        if ($updatedAt > 0 && (time() - $updatedAt) > $staleSeconds) {
            return false;
        }

        return true;
    }

    /** @return array<string, mixed> */
    public static function run(int $clientId, int $backupUserId): array
    {
        $tenantRecordId = Ms365ConnectionGuard::tenantRecordIdForBackupUser($clientId, $backupUserId);

        try {
            TenantRecordRepository::ensureCloudStorageBucketForBackupUser($clientId, $backupUserId);
            self::markRunning($clientId, $backupUserId);
            $ctx = CustomerInventoryService::clientContextForRefresh($clientId, $backupUserId);
            $inventory = new InventoryService(
                $ctx->graph,
                $ctx->storageLayout,
                new DiscoveryService($ctx->graph, $ctx->storageLayout),
            );
            $data = $inventory->refresh(lightweight: true);

            return [
                'fetched_at' => (string) ($data['fetched_at'] ?? ''),
                'counts' => is_array($data['counts'] ?? null) ? $data['counts'] : [],
                'total_resources' => count(is_array($data['resources'] ?? null) ? $data['resources'] : []),
                'warnings' => array_values(array_map('strval', is_array($data['warnings'] ?? null) ? $data['warnings'] : [])),
            ];
        } catch (\Throwable $e) {
            self::markError($clientId, $backupUserId, $e->getMessage());
            Ms365ConnectionGuard::throwIfReconnectRequired($tenantRecordId, $e);

            throw $e;
        }
    }

    private static function markRunning(int $clientId, int $backupUserId): void
    {
        $layout = self::storageLayout($clientId, $backupUserId);
        $layout->writeJson($layout->discoveryDir() . '/progress.json', [
            'phase' => 'running',
            'message' => 'Inventory refresh started…',
            'counts' => [],
            'updated_at' => gmdate('c'),
        ]);
    }

    private static function markError(int $clientId, int $backupUserId, string $message): void
    {
        try {
            Ms365CustomerError::log('inventory_refresh', new \RuntimeException($message));
            $layout = self::storageLayout($clientId, $backupUserId);
            $layout->writeJson($layout->discoveryDir() . '/progress.json', [
                'phase' => 'error',
                'message' => 'Inventory refresh failed',
                'detail' => Ms365CustomerError::sanitizeRaw($message),
                'counts' => [],
                'updated_at' => gmdate('c'),
            ]);
        } catch (\Throwable $_) {
        }
    }

    /** @return array<string, mixed>|null */
    private static function readProgress(int $clientId, int $backupUserId): ?array
    {
        try {
            $layout = self::storageLayout($clientId, $backupUserId);

            return $layout->readJson($layout->discoveryDir() . '/progress.json');
        } catch (\Throwable $_) {
            return null;
        }
    }

    private static function storageLayout(int $clientId, int $backupUserId): StorageLayout
    {
        $record = TenantRecordRepository::getForBackupUser($clientId, $backupUserId)
            ?? TenantRecordRepository::getPrimaryForClient($clientId);
        if ($record === null) {
            throw new \RuntimeException('Connect Microsoft 365 before refreshing inventory.');
        }
        $creds = TenantRecordRepository::resolvedCredentialsForRecord($record);
        $backupStorage = BackupStorageFactory::createForTenantRecord($record);

        return new StorageLayout($creds['tenant_id'], $backupStorage, $backupUserId);
    }

    private static function spawnWorker(int $clientId, int $backupUserId): void
    {
        if (WorkerSpawner::isExecDisabled()) {
            throw new \RuntimeException('Cannot start inventory refresh: PHP exec() is disabled on this server.');
        }

        $script = dirname(__DIR__, 2) . '/bin/ms365_customer_inventory_refresh.php';
        if (!is_file($script)) {
            throw new \RuntimeException('Inventory refresh worker is not installed.');
        }

        $php = WorkerSpawner::resolvePhpBinary();

        $log = self::workerLogPath($clientId, $backupUserId);
        $cmd = sprintf(
            'nohup %s %s --client-id=%d --backup-user-id=%d >> %s 2>&1 &',
            escapeshellarg($php),
            escapeshellarg($script),
            $clientId,
            $backupUserId,
            escapeshellarg($log),
        );
        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        // #region agent log
        Ms365AgentDebugLog::write(
            'InventoryBackgroundRefresh::spawnWorker',
            'spawn worker exec',
            [
                'client_id' => $clientId,
                'backup_user_id' => $backupUserId,
                'exit_code' => $exitCode,
                'php_binary' => $php,
                'log_path' => $log,
                'log_writable' => is_writable(dirname($log)),
            ],
            'F',
        );
        // #endregion

        if ($exitCode !== 0) {
            throw new \RuntimeException('Failed to start inventory refresh worker (exit ' . $exitCode . ').');
        }
    }

    private static function workerLogPath(int $clientId, int $backupUserId): string
    {
        return sprintf(
            '%s/ms365_inventory_refresh_%d_%d.log',
            rtrim(sys_get_temp_dir(), '/'),
            $clientId,
            $backupUserId,
        );
    }
}
