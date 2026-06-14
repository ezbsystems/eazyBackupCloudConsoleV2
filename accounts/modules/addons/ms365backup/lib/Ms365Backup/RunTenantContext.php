<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * Resolves Graph credentials, storage layout, and backup adapter for a backup run.
 */
final class RunTenantContext
{
    /** @param array{region: string, tenant_id: string, client_id: string, client_secret: string} $creds */
    public function __construct(
        public readonly array $creds,
        public readonly StorageLayout $storageLayout,
        public readonly ?array $tenantRecord,
        public readonly BackupStorageInterface $backupStorage,
        public readonly GraphClient $graph,
        public readonly TokenProvider $tokenProvider,
    ) {
    }

    /** @param array<string, mixed> $run ms365_backup_runs row */
    public static function fromRun(array $run): self
    {
        $record = self::resolveTenantRecord($run);
        if ($record !== null) {
            $creds = TenantRecordRepository::platformCredentials($record);
            $backupStorage = BackupStorageFactory::createForTenantRecord($record);
        } else {
            $creds = TenantRepository::credentials();
            $backupStorage = BackupStorageFactory::createDefault();
        }

        $layout = new StorageLayout($creds['tenant_id'], $backupStorage);
        $tokens = new TokenProvider(
            $creds['region'],
            $creds['tenant_id'],
            $creds['client_id'],
            $creds['client_secret'],
        );
        $graph = new GraphClient($tokens, $creds['region']);

        return new self($creds, $layout, $record, $backupStorage, $graph, $tokens);
    }

    /** @param array<string, mixed> $run */
    private static function resolveTenantRecord(array $run): ?array
    {
        $tenantRecordId = (int) ($run['tenant_record_id'] ?? 0);
        if ($tenantRecordId <= 0) {
            return null;
        }

        return TenantRecordRepository::getById($tenantRecordId);
    }

    public static function forClientRecord(array $record): self
    {
        $creds = TenantRecordRepository::platformCredentials($record);
        $backupStorage = BackupStorageFactory::createForTenantRecord($record);
        $backupUserId = (int) ($record['backup_user_id'] ?? 0);
        $layout = new StorageLayout($creds['tenant_id'], $backupStorage, $backupUserId);
        $tokens = new TokenProvider(
            $creds['region'],
            $creds['tenant_id'],
            $creds['client_id'],
            $creds['client_secret'],
        );
        $graph = new GraphClient($tokens, $creds['region']);

        return new self($creds, $layout, $record, $backupStorage, $graph, $tokens);
    }
}
