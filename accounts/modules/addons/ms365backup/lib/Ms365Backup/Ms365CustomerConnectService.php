<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * Customer manual Entra app credential connect (wizard Step 1 manual mode).
 */
final class Ms365CustomerConnectService
{
    /** @return list<string> */
    public static function allowedRegions(): array
    {
        return RegionEndpoints::allowedRegions();
    }

    /**
     * @param array<string, mixed>|null $record
     * @return array{region: string, client_id: string, tenant_id: string, has_secret: bool}
     */
    public static function credentialPreviewForRecord(?array $record): array
    {
        if ($record === null) {
            return [
                'region' => 'GlobalPublicCloud',
                'client_id' => '',
                'tenant_id' => '',
                'has_secret' => false,
            ];
        }

        return [
            'region' => (string) ($record['region'] ?? 'GlobalPublicCloud'),
            'client_id' => (string) ($record['client_id'] ?? ''),
            'tenant_id' => trim((string) ($record['azure_tenant_id'] ?? $record['tenant_id'] ?? '')),
            'has_secret' => !empty($record['app_secret_enc']),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed>|null $existingRecord
     * @return array{organization: string}
     */
    public static function testCredentials(array $input, ?array $existingRecord = null): array
    {
        $creds = self::resolveCredentialInput($input, $existingRecord);
        $tokens = new TokenProvider(
            $creds['region'],
            $creds['tenant_id'],
            $creds['client_id'],
            $creds['client_secret'],
        );
        $graph = new GraphClient($tokens, $creds['region']);
        $org = $graph->get('organization', ['$top' => '1']);
        $name = trim((string) ($org['value'][0]['displayName'] ?? ''));

        return ['organization' => $name !== '' ? $name : 'Connected'];
    }

    /**
     * Atomic: test credentials, persist, mark connected, bootstrap storage.
     *
     * @param array<string, mixed> $input
     */
    public static function saveAndConnect(int $clientId, int $backupUserId, array $input): void
    {
        if ($clientId <= 0 || $backupUserId <= 0) {
            throw new \RuntimeException('Invalid backup user.');
        }

        $existing = TenantRecordRepository::getForBackupUser($clientId, $backupUserId);
        if ($existing !== null
            && ($existing['connection_status'] ?? '') === 'connected'
            && !TenantRecordRepository::usesCustomerAppCredentials($existing)) {
            throw new \RuntimeException('Disconnect the current Microsoft 365 connection before saving manual credentials.');
        }

        $creds = self::resolveCredentialInput($input, $existing);
        self::testCredentials($input, $existing);

        $tenantRecordId = $existing !== null
            ? (int) $existing['id']
            : TenantRecordRepository::ensureForClient($clientId, $creds['tenant_id'], $backupUserId);

        TenantRecordRepository::saveCustomerCredentials($tenantRecordId, [
            'region' => $creds['region'],
            'tenant_id' => $creds['tenant_id'],
            'azure_tenant_id' => $creds['tenant_id'],
            'client_id' => $creds['client_id'],
            'app_secret' => $creds['client_secret'],
        ]);

        TenantRecordRepository::markConnectedWithCustomerApp($tenantRecordId, $creds['tenant_id']);
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed>|null $existingRecord
     * @return array{region: string, tenant_id: string, client_id: string, client_secret: string}
     */
    public static function resolveCredentialInput(array $input, ?array $existingRecord = null): array
    {
        $region = self::normalizeRegion((string) ($input['region'] ?? ''));
        $tenantId = self::normalizeGuid((string) ($input['tenant_id'] ?? ''), 'TENANT_ID');
        $clientId = self::normalizeGuid((string) ($input['client_id'] ?? ''), 'CLIENT_ID');

        $secret = trim((string) ($input['app_secret'] ?? ''));
        if ($secret === '' && $existingRecord !== null && !empty($existingRecord['app_secret_enc'])) {
            $secret = TenantRepository::decryptSecret((string) $existingRecord['app_secret_enc']);
        }
        if ($secret === '') {
            throw new \RuntimeException('APP_SECRET is required.');
        }

        return [
            'region' => $region,
            'tenant_id' => $tenantId,
            'client_id' => $clientId,
            'client_secret' => $secret,
        ];
    }

    public static function normalizeRegion(string $region): string
    {
        $region = trim($region);
        if ($region === '') {
            return 'GlobalPublicCloud';
        }
        if (!in_array($region, self::allowedRegions(), true)) {
            throw new \RuntimeException('Invalid cloud region.');
        }

        return $region;
    }

    public static function normalizeGuid(string $value, string $label): string
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            throw new \RuntimeException($label . ' is required.');
        }
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $value)) {
            throw new \RuntimeException($label . ' must be a valid GUID.');
        }

        return $value;
    }
}
