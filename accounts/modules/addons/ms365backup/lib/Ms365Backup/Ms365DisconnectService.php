<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;

/**
 * Customer-initiated Microsoft 365 disconnect (local state only).
 */
final class Ms365DisconnectService
{
    public static function disconnectForBackupUser(int $clientId, int $backupUserId): void
    {
        $record = $backupUserId > 0
            ? TenantRecordRepository::getForBackupUser($clientId, $backupUserId)
            : TenantRecordRepository::getPrimaryForClient($clientId);
        if ($record === null) {
            throw new \RuntimeException('Microsoft 365 is not connected for this user.');
        }

        $status = (string) ($record['connection_status'] ?? '');
        if (!in_array($status, ['connected', 'action_required'], true)) {
            throw new \RuntimeException('Microsoft 365 is not connected for this user.');
        }

        $tenantRecordId = (int) $record['id'];
        self::pauseActiveJobs($clientId, $backupUserId);
        self::clearInventoryCache($record);
        TenantRecordRepository::markDisconnected($tenantRecordId);
    }

    private static function pauseActiveJobs(int $clientId, int $backupUserId): void
    {
        if (!class_exists(Capsule::class)) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $q = Capsule::table('s3_cloudbackup_jobs')
            ->where('client_id', $clientId)
            ->where('source_type', Ms365CustomerJobService::SOURCE_TYPE)
            ->where('status', 'active');

        if ($backupUserId > 0 && Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'backup_user_id')) {
            $q->where('backup_user_id', $backupUserId);
        }

        $q->update([
            'status' => 'paused',
            'updated_at' => $now,
        ]);
    }

    /** @param array<string, mixed> $record */
    private static function clearInventoryCache(array $record): void
    {
        $azureTenantId = trim((string) ($record['azure_tenant_id'] ?? $record['tenant_id'] ?? ''));
        if ($azureTenantId === '') {
            return;
        }

        try {
            $storage = BackupStorageFactory::createForTenantRecord($record);
            $layout = new StorageLayout($azureTenantId, $storage);
            $layout->writeJson($layout->inventoryPath(), [
                'resources' => [],
                'counts' => [],
                'fetched_at' => '',
                'warnings' => [],
            ]);
        } catch (\Throwable $e) {
            Ms365CustomerError::log('disconnect_clear_inventory', $e);
        }
    }
}
