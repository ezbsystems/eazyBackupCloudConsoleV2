<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;

final class TenantRecordRepository
{
    public const AUTH_MODE_PLATFORM = 'platform_consent';
    public const AUTH_MODE_CUSTOMER = 'customer_app';

    public static function usesCustomerAppCredentials(array $row): bool
    {
        if (!self::hasConnectionAuthModeColumn()) {
            return false;
        }

        return (string) ($row['connection_auth_mode'] ?? self::AUTH_MODE_PLATFORM) === self::AUTH_MODE_CUSTOMER;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function saveCustomerCredentials(int $tenantRecordId, array $data): void
    {
        if (!class_exists(Capsule::class) || $tenantRecordId <= 0) {
            throw new \RuntimeException('Database not available');
        }

        $azureTenantId = trim((string) ($data['azure_tenant_id'] ?? $data['tenant_id'] ?? ''));
        $update = [
            'region' => (string) ($data['region'] ?? 'GlobalPublicCloud'),
            'tenant_id' => trim((string) ($data['tenant_id'] ?? $azureTenantId)),
            'azure_tenant_id' => $azureTenantId,
            'client_id' => trim((string) ($data['client_id'] ?? '')),
            'updated_at' => time(),
        ];

        if (!empty($data['app_secret'])) {
            $update['app_secret_enc'] = TenantRepository::encryptSecret((string) $data['app_secret']);
        }

        if (self::hasConnectionAuthModeColumn()) {
            $update['connection_auth_mode'] = self::AUTH_MODE_CUSTOMER;
        }

        Capsule::table('ms365_tenant_records')->where('id', $tenantRecordId)->update($update);
    }

    public static function getById(int $id): ?array
    {
        if (!class_exists(Capsule::class) || $id <= 0) {
            return null;
        }
        $row = Capsule::table('ms365_tenant_records')->where('id', $id)->where('is_active', 1)->first();

        return $row ? (array) $row : null;
    }

    public static function getPrimaryForClient(int $clientId): ?array
    {
        $rows = self::listForClient($clientId);

        return $rows[0] ?? null;
    }

    public static function getForBackupUser(int $clientId, int $backupUserId): ?array
    {
        if ($clientId <= 0 || $backupUserId <= 0) {
            return null;
        }
        if (!self::hasBackupUserColumn()) {
            return self::getPrimaryForClient($clientId);
        }

        $row = Capsule::table('ms365_tenant_records')
            ->where('whmcs_client_id', $clientId)
            ->where('backup_user_id', $backupUserId)
            ->where('is_active', 1)
            ->orderByDesc('id')
            ->first();

        return $row ? (array) $row : null;
    }

    /** @return list<array<string, mixed>> */
    public static function listForClient(int $clientId): array
    {
        if (!class_exists(Capsule::class) || $clientId <= 0) {
            return [];
        }

        return Capsule::table('ms365_tenant_records')
            ->where('whmcs_client_id', $clientId)
            ->where('is_active', 1)
            ->orderBy('id')
            ->get()
            ->map(static fn ($row) => (array) $row)
            ->all();
    }

    public static function ensureForClient(int $clientId, string $azureTenantId = '', int $backupUserId = 0): int
    {
        if ($backupUserId > 0 && self::hasBackupUserColumn()) {
            $existing = self::getForBackupUser($clientId, $backupUserId);
            if ($existing !== null) {
                return (int) $existing['id'];
            }

            return self::create($clientId, [
                'label' => 'Microsoft 365',
                'azure_tenant_id' => $azureTenantId,
                'tenant_id' => $azureTenantId,
                'client_id' => PlatformEntraConfig::clientId(),
                'connection_status' => 'pending',
                'backup_user_id' => $backupUserId,
            ]);
        }

        $existing = self::getPrimaryForClient($clientId);
        if ($existing !== null) {
            return (int) $existing['id'];
        }

        return self::create($clientId, [
            'label' => 'Microsoft 365',
            'azure_tenant_id' => $azureTenantId,
            'tenant_id' => $azureTenantId,
            'client_id' => PlatformEntraConfig::clientId(),
            'connection_status' => 'pending',
            'backup_user_id' => $backupUserId > 0 ? $backupUserId : null,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function create(int $clientId, array $data): int
    {
        if (!class_exists(Capsule::class)) {
            throw new \RuntimeException('Database not available');
        }
        $now = time();
        $secretEnc = '';
        if (!empty($data['app_secret'])) {
            $secretEnc = TenantRepository::encryptSecret((string) $data['app_secret']);
        }

        $azureTenantId = trim((string) ($data['azure_tenant_id'] ?? $data['tenant_id'] ?? ''));

        $insert = [
            'whmcs_client_id' => $clientId,
            'whmcs_service_id' => (int) ($data['whmcs_service_id'] ?? 0),
            'label' => trim((string) ($data['label'] ?? 'Microsoft 365')),
            'region' => (string) ($data['region'] ?? PlatformEntraConfig::region()),
            'tenant_id' => trim((string) ($data['tenant_id'] ?? $azureTenantId)),
            'azure_tenant_id' => $azureTenantId,
            'connection_status' => (string) ($data['connection_status'] ?? 'pending'),
            'client_id' => trim((string) ($data['client_id'] ?? PlatformEntraConfig::clientId())),
            'app_secret_enc' => $secretEnc,
            'consent_granted_at' => $data['consent_granted_at'] ?? null,
            'consent_granted_by_upn' => trim((string) ($data['consent_granted_by_upn'] ?? '')),
            'platform_app_id' => trim((string) ($data['platform_app_id'] ?? PlatformEntraConfig::clientId())),
            's3_endpoint' => trim((string) ($data['s3_endpoint'] ?? '')),
            's3_bucket' => trim((string) ($data['s3_bucket'] ?? '')),
            's3_region' => trim((string) ($data['s3_region'] ?? 'us-east-1')),
            's3_access_key_enc' => !empty($data['s3_access_key'])
                ? TenantRepository::encryptSecret((string) $data['s3_access_key']) : null,
            's3_secret_key_enc' => !empty($data['s3_secret_key'])
                ? TenantRepository::encryptSecret((string) $data['s3_secret_key']) : null,
            's3_bucket_id' => $data['s3_bucket_id'] ?? null,
            's3_bucket_name' => trim((string) ($data['s3_bucket_name'] ?? '')),
            's3_user_id' => $data['s3_user_id'] ?? null,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        if (self::hasBackupUserColumn() && isset($data['backup_user_id']) && (int) $data['backup_user_id'] > 0) {
            $insert['backup_user_id'] = (int) $data['backup_user_id'];
        }
        if (self::hasConnectionAuthModeColumn()) {
            $insert['connection_auth_mode'] = (string) ($data['connection_auth_mode'] ?? self::AUTH_MODE_PLATFORM);
        }

        return (int) Capsule::table('ms365_tenant_records')->insertGetId($insert);
    }

    /** @param array<string, mixed> $meta */
    public static function markConnected(int $tenantRecordId, string $azureTenantId, array $meta = []): void
    {
        if (!class_exists(Capsule::class)) {
            return;
        }
        $now = time();
        $update = [
            'azure_tenant_id' => $azureTenantId,
            'tenant_id' => $azureTenantId,
            'connection_status' => 'connected',
            'consent_granted_at' => $now,
            'consent_granted_by_upn' => trim((string) ($meta['consent_granted_by_upn'] ?? '')),
            'platform_app_id' => trim((string) ($meta['platform_app_id'] ?? PlatformEntraConfig::clientId())),
            'client_id' => PlatformEntraConfig::clientId(),
            'region' => PlatformEntraConfig::region(),
            'health_error' => null,
            'last_health_check_at' => $now,
            'updated_at' => $now,
        ];
        if (self::hasConnectionAuthModeColumn()) {
            $update['connection_auth_mode'] = self::AUTH_MODE_PLATFORM;
        }
        Capsule::table('ms365_tenant_records')->where('id', $tenantRecordId)->update($update);

        self::bootstrapStorageIfAvailable($tenantRecordId, (int) (Capsule::table('ms365_tenant_records')->where('id', $tenantRecordId)->value('whmcs_client_id') ?? 0));
    }

    public static function markConnectedWithCustomerApp(int $tenantRecordId, string $azureTenantId): void
    {
        if (!class_exists(Capsule::class)) {
            return;
        }
        $now = time();
        $update = [
            'azure_tenant_id' => $azureTenantId,
            'tenant_id' => $azureTenantId,
            'connection_status' => 'connected',
            'consent_granted_at' => $now,
            'health_error' => null,
            'last_health_check_at' => $now,
            'updated_at' => $now,
        ];
        if (self::hasConnectionAuthModeColumn()) {
            $update['connection_auth_mode'] = self::AUTH_MODE_CUSTOMER;
        }
        Capsule::table('ms365_tenant_records')->where('id', $tenantRecordId)->update($update);

        self::bootstrapStorageIfAvailable($tenantRecordId, (int) (Capsule::table('ms365_tenant_records')->where('id', $tenantRecordId)->value('whmcs_client_id') ?? 0));
    }

    public static function markDisconnected(int $tenantRecordId): void
    {
        if (!class_exists(Capsule::class) || $tenantRecordId <= 0) {
            return;
        }
        $now = time();
        Capsule::table('ms365_tenant_records')->where('id', $tenantRecordId)->update([
            'connection_status' => 'disconnected',
            'health_error' => null,
            'last_health_check_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public static function linkCloudStorageBucket(int $tenantRecordId, int $bucketId, string $bucketName, int $ownerUserId): void
    {
        if (!class_exists(Capsule::class)) {
            return;
        }
        Capsule::table('ms365_tenant_records')->where('id', $tenantRecordId)->update([
            's3_bucket_id' => $bucketId,
            's3_bucket_name' => $bucketName,
            's3_user_id' => $ownerUserId,
            's3_bucket' => $bucketName,
            'updated_at' => time(),
        ]);
    }

    public static function updateHealth(int $tenantRecordId, string $status, string $error = ''): void
    {
        if (!class_exists(Capsule::class)) {
            return;
        }
        Capsule::table('ms365_tenant_records')->where('id', $tenantRecordId)->update([
            'connection_status' => $status,
            'last_health_check_at' => time(),
            'health_error' => $error !== '' ? $error : null,
            'updated_at' => time(),
        ]);
    }

    /** @return array{region: string, tenant_id: string, client_id: string, client_secret: string} */
    public static function resolvedCredentialsForRecord(array $row): array
    {
        if (self::usesCustomerAppCredentials($row)) {
            return TenantRepository::credentials($row);
        }

        $azure = trim((string) ($row['azure_tenant_id'] ?? ''));
        if ($azure !== '' && PlatformEntraConfig::isConfigured()) {
            return self::platformCredentials($row);
        }

        return TenantRepository::credentials($row);
    }

    /** @return array{region: string, tenant_id: string, client_id: string, client_secret: string} */
    public static function platformCredentials(array $row): array
    {
        $azureTenant = trim((string) ($row['azure_tenant_id'] ?? $row['tenant_id'] ?? ''));
        if ($azureTenant === '') {
            throw new \RuntimeException('Tenant is not connected.');
        }
        if (!PlatformEntraConfig::isConfigured()) {
            throw new \RuntimeException('Platform Entra app is not configured.');
        }

        return [
            'region' => PlatformEntraConfig::region() ?: (string) ($row['region'] ?? 'GlobalPublicCloud'),
            'tenant_id' => $azureTenant,
            'client_id' => PlatformEntraConfig::clientId(),
            'client_secret' => PlatformEntraConfig::clientSecret(),
        ];
    }

    /** @return array{region: string, tenant_id: string, client_id: string, client_secret: string} */
    public static function credentials(?int $tenantRecordId = null): array
    {
        if ($tenantRecordId !== null && $tenantRecordId > 0) {
            $row = self::getById($tenantRecordId);
            if ($row !== null) {
                return self::resolvedCredentialsForRecord($row);
            }
        }

        return TenantRepository::credentials();
    }

    /**
     * Ensure ms365 tenant record is linked to an RGW bucket that exists on storage.
     *
     * @throws \RuntimeException customer-safe message on failure
     */
    public static function ensureCloudStorageBucketForClient(int $clientId): void
    {
        self::ensureCloudStorageBucketForBackupUser($clientId, 0);
    }

    public static function ensureCloudStorageBucketForBackupUser(int $clientId, int $backupUserId): void
    {
        if ($clientId <= 0) {
            throw new \RuntimeException('Invalid client.');
        }

        $record = $backupUserId > 0
            ? (self::getForBackupUser($clientId, $backupUserId) ?? self::getPrimaryForClient($clientId))
            : self::getPrimaryForClient($clientId);
        if ($record === null) {
            throw new \RuntimeException('Connect Microsoft 365 before using backup storage.');
        }

        $bootstrapClass = 'WHMCS\\Module\\Addon\\CloudStorage\\Client\\Ms365StorageBootstrapService';
        if (!class_exists($bootstrapClass)) {
            throw new \RuntimeException('Backup storage is not available. Please contact support.');
        }

        $effectiveBackupUserId = $backupUserId > 0
            ? $backupUserId
            : (int) ($record['backup_user_id'] ?? 0);
        if ($effectiveBackupUserId <= 0) {
            throw new \RuntimeException('Microsoft 365 storage requires a backup user. Open Users → select a user → connect Microsoft 365.');
        }

        $result = $bootstrapClass::ensureForBackupUser($clientId, $effectiveBackupUserId);
        if (($result['status'] ?? '') !== 'success' || !isset($result['bucket'], $result['owner_user'])) {
            Ms365CustomerError::log('ensureCloudStorageBucket', new \RuntimeException((string) ($result['message'] ?? 'bootstrap failed')));
            throw new \RuntimeException(Ms365CustomerError::message(new \RuntimeException((string) ($result['message'] ?? ''))));
        }

        self::linkCloudStorageBucket(
            (int) $record['id'],
            (int) $result['bucket']->id,
            (string) $result['bucket']->name,
            (int) $result['owner_user']->id,
        );
    }

    private static function hasConnectionAuthModeColumn(): bool
    {
        return class_exists(Capsule::class)
            && Capsule::schema()->hasTable('ms365_tenant_records')
            && Capsule::schema()->hasColumn('ms365_tenant_records', 'connection_auth_mode');
    }

    private static function hasBackupUserColumn(): bool
    {
        return class_exists(Capsule::class)
            && Capsule::schema()->hasTable('ms365_tenant_records')
            && Capsule::schema()->hasColumn('ms365_tenant_records', 'backup_user_id');
    }

    private static function bootstrapStorageIfAvailable(int $tenantRecordId, int $clientId): void
    {
        if ($clientId <= 0) {
            return;
        }
        try {
            $backupUserId = (int) (Capsule::table('ms365_tenant_records')->where('id', $tenantRecordId)->value('backup_user_id') ?? 0);
            self::ensureCloudStorageBucketForBackupUser($clientId, $backupUserId);
        } catch (\Throwable $e) {
            Ms365CustomerError::log('bootstrapStorageIfAvailable', $e);
            self::updateHealth($tenantRecordId, 'action_required', Ms365CustomerError::message($e));
        }
    }
}
