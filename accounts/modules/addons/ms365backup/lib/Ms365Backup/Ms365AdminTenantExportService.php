<?php
declare(strict_types=1);

namespace Ms365Backup;

use Ms365Backup\Fleet\FleetContext;

/**
 * Admin export of MS365 tenant manual-connect credentials for dev reconnection.
 */
final class Ms365AdminTenantExportService
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function searchBackupUsers(string $query, int $limit = 15): array
    {
        return Ms365AdminBackupUserSearch::search($query, $limit);
    }

    /** @return array<string, mixed> */
    public static function getBackupUserDetail(int $backupUserId): array
    {
        $user = Ms365AdminBackupUserSearch::getBackupUser($backupUserId);
        if ($user === null) {
            throw new \RuntimeException('Backup user not found.');
        }

        $clientId = (int) ($user['client_id'] ?? 0);
        $tenant = TenantRecordRepository::getForBackupUser($clientId, $backupUserId);
        $preview = Ms365CustomerConnectService::credentialPreviewForRecord($tenant);
        $canExport = self::canExportTenant($tenant);

        return [
            'backup_user' => [
                'id' => $backupUserId,
                'username' => (string) ($user['username'] ?? ''),
                'public_id' => isset($user['public_id']) ? (string) $user['public_id'] : null,
                'status' => (string) ($user['status'] ?? ''),
                'backup_type' => (string) ($user['backup_type'] ?? ''),
                'client_id' => $clientId,
            ],
            'tenant' => $tenant !== null ? [
                'id' => (int) ($tenant['id'] ?? 0),
                'connection_status' => (string) ($tenant['connection_status'] ?? ''),
                'connection_auth_mode' => (string) ($tenant['connection_auth_mode'] ?? TenantRecordRepository::AUTH_MODE_PLATFORM),
                'azure_tenant_id' => (string) ($tenant['azure_tenant_id'] ?? ''),
                'label' => (string) ($tenant['label'] ?? ''),
            ] : null,
            'credentials_preview' => $preview,
            'can_export' => $canExport,
            'export_block_reason' => $canExport ? '' : self::exportBlockReason($tenant),
            'is_production_server' => FleetContext::isProductionServer(),
        ];
    }

    /** @return array<string, mixed> */
    public static function exportForBackupUser(int $backupUserId, int $adminId): array
    {
        $user = Ms365AdminBackupUserSearch::getBackupUser($backupUserId);
        if ($user === null) {
            throw new \RuntimeException('Backup user not found.');
        }

        $clientId = (int) ($user['client_id'] ?? 0);
        $tenant = TenantRecordRepository::getForBackupUser($clientId, $backupUserId);
        if (!self::canExportTenant($tenant)) {
            throw new \RuntimeException(self::exportBlockReason($tenant));
        }

        $creds = TenantRecordRepository::resolvedCredentialsForRecord($tenant);
        $authMode = (string) ($tenant['connection_auth_mode'] ?? TenantRecordRepository::AUTH_MODE_PLATFORM);
        $payload = self::buildExportPayload(
            $backupUserId,
            $clientId,
            (string) ($user['username'] ?? ''),
            $tenant,
            $creds,
            $authMode
        );

        self::auditExport($backupUserId, $tenant, $authMode, $adminId);

        return $payload;
    }

    /**
     * @param array<string, mixed> $tenant
     * @param array{region: string, tenant_id: string, client_id: string, client_secret: string} $creds
     * @return array<string, mixed>
     */
    public static function buildExportPayload(
        int $backupUserId,
        int $clientId,
        string $backupUsername,
        array $tenant,
        array $creds,
        string $authMode,
    ): array {
        $notes = 'Use Job wizard → Manual connect → Test connection → Save credentials.';
        if ($authMode === TenantRecordRepository::AUTH_MODE_PLATFORM) {
            $notes .= ' Exported platform app credentials for this tenant; saving via manual connect will store as customer_app.';
        }

        return [
            'exported_at' => gmdate('c'),
            'backup_user_id' => $backupUserId,
            'whmcs_client_id' => $clientId,
            'backup_username' => $backupUsername,
            'tenant_record_id' => (int) ($tenant['id'] ?? 0),
            'connection_auth_mode' => $authMode,
            'connection_status' => (string) ($tenant['connection_status'] ?? ''),
            'manual_connect' => [
                'region' => (string) ($creds['region'] ?? 'GlobalPublicCloud'),
                'client_id' => (string) ($creds['client_id'] ?? ''),
                'tenant_id' => (string) ($creds['tenant_id'] ?? ''),
                'app_secret' => (string) ($creds['client_secret'] ?? ''),
            ],
            'notes' => $notes,
        ];
    }

    /** @param array<string, mixed>|null $tenant */
    public static function canExportTenant(?array $tenant): bool
    {
        if ($tenant === null) {
            return false;
        }
        if ((string) ($tenant['connection_status'] ?? '') !== 'connected') {
            return false;
        }
        $azure = trim((string) ($tenant['azure_tenant_id'] ?? $tenant['tenant_id'] ?? ''));

        return $azure !== '';
    }

    /** @param array<string, mixed>|null $tenant */
    public static function exportBlockReason(?array $tenant): string
    {
        if ($tenant === null) {
            return 'No MS365 tenant record for this backup user.';
        }
        if ((string) ($tenant['connection_status'] ?? '') !== 'connected') {
            return 'Tenant is not connected (status: ' . (string) ($tenant['connection_status'] ?? 'unknown') . ').';
        }
        if (trim((string) ($tenant['azure_tenant_id'] ?? $tenant['tenant_id'] ?? '')) === '') {
            return 'Tenant is missing Azure tenant ID.';
        }

        return 'Cannot export credentials for this tenant.';
    }

    /** @param array<string, mixed> $tenant */
    private static function auditExport(int $backupUserId, array $tenant, string $authMode, int $adminId): void
    {
        $tenantRecordId = (int) ($tenant['id'] ?? 0);
        try {
            logModuleCall('ms365backup', 'admin_tenant_export', [
                'backup_user_id' => $backupUserId,
                'tenant_record_id' => $tenantRecordId,
                'auth_mode' => $authMode,
                'admin_id' => $adminId > 0 ? $adminId : null,
            ], 'ok');
        } catch (\Throwable $_) {
        }

        if (function_exists('logActivity')) {
            logActivity(sprintf(
                'MS365 tenant export: backup user #%d tenant record #%d (%s) by admin #%d',
                $backupUserId,
                $tenantRecordId,
                $authMode,
                $adminId
            ));
        }
    }
}
