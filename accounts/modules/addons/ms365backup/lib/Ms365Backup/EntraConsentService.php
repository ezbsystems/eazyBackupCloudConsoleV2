<?php
declare(strict_types=1);

namespace Ms365Backup;

final class EntraConsentService
{
    private const STATE_TTL = 3600;

    public static function buildAdminConsentUrl(
        int $clientId,
        int $tenantRecordId = 0,
        int $backupUserId = 0,
        string $returnPath = '',
        string $consentMode = 'redirect',
    ): string {
        if (!PlatformEntraConfig::isConfigured()) {
            throw new \RuntimeException('Platform Entra app is not configured in MS365 Backup module settings.');
        }
        PlatformEntraConfig::assertCustomerRedirectUri();

        $consentMode = $consentMode === 'popup' ? 'popup' : 'redirect';

        $state = self::encodeState([
            'client_id' => $clientId,
            'tenant_record_id' => $tenantRecordId,
            'backup_user_id' => $backupUserId,
            'return_path' => $returnPath,
            'consent_mode' => $consentMode,
            'ts' => time(),
            'nonce' => bin2hex(random_bytes(16)),
        ]);

        $params = [
            'client_id' => PlatformEntraConfig::clientId(),
            'redirect_uri' => PlatformEntraConfig::redirectUri(),
            'state' => $state,
        ];

        $loginHost = RegionEndpoints::forRegion(PlatformEntraConfig::region())['login'];

        return rtrim($loginHost, '/') . '/common/adminconsent?' . http_build_query($params);
    }

    /**
     * @return array{client_id: int, tenant_record_id: int, azure_tenant_id: string, admin_consent: bool}
     */
    public static function handleCallback(array $query): array
    {
        $stateRaw = (string) ($query['state'] ?? '');
        $state = self::decodeState($stateRaw);
        $clientId = (int) ($state['client_id'] ?? 0);
        if ($clientId <= 0) {
            throw new \RuntimeException('Invalid OAuth state.');
        }

        $error = (string) ($query['error'] ?? '');
        if ($error !== '') {
            $desc = (string) ($query['error_description'] ?? $error);
            throw new \RuntimeException('Microsoft consent failed: ' . $desc);
        }

        $azureTenantId = trim((string) ($query['tenant'] ?? ''));
        if ($azureTenantId === '') {
            throw new \RuntimeException('Missing tenant ID from Microsoft consent response.');
        }

        $adminConsent = filter_var($query['admin_consent'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (!$adminConsent) {
            throw new \RuntimeException('Admin consent was not granted.');
        }

        $backupUserId = (int) ($state['backup_user_id'] ?? 0);
        $tenantRecordId = (int) ($state['tenant_record_id'] ?? 0);
        if ($tenantRecordId <= 0) {
            $tenantRecordId = TenantRecordRepository::ensureForClient($clientId, $azureTenantId, $backupUserId);
        }

        TenantRecordRepository::markConnected($tenantRecordId, $azureTenantId, [
            'platform_app_id' => PlatformEntraConfig::clientId(),
            'consent_granted_by_upn' => (string) ($query['scope'] ?? ''),
        ]);

        self::probeAndUpdateHealth($tenantRecordId);

        return [
            'client_id' => $clientId,
            'tenant_record_id' => $tenantRecordId,
            'azure_tenant_id' => $azureTenantId,
            'admin_consent' => true,
            'backup_user_id' => $backupUserId,
            'return_path' => (string) ($state['return_path'] ?? ''),
            'consent_mode' => (string) ($state['consent_mode'] ?? 'redirect'),
        ];
    }

    public static function buildWizardReturnUrl(
        int $clientId,
        int $backupUserId,
        bool $connectOk,
        string $error = '',
    ): string {
        if ($backupUserId <= 0) {
            return '';
        }

        try {
            $user = BackupUserResolver::resolveByIdForClient($clientId, $backupUserId);
        } catch (\Throwable $_) {
            return '';
        }

        $params = [
            'm' => 'cloudstorage',
            'page' => 'e3backup',
            'view' => 'user_detail',
            'user_id' => $user['public_id'],
            'ms365_wizard' => '1',
        ];
        if ($connectOk) {
            $params['connect_ok'] = '1';
        } elseif ($error !== '') {
            $params['connect_error'] = $error;
        }

        return 'index.php?' . http_build_query($params) . '#jobs';
    }

    public static function probeAndUpdateHealth(int $tenantRecordId): void
    {
        $row = TenantRecordRepository::getById($tenantRecordId);
        if ($row === null) {
            return;
        }

        try {
            $creds = TenantRecordRepository::platformCredentials($row);
            $graph = new GraphClient(
                new TokenProvider(
                    $creds['region'],
                    $creds['tenant_id'],
                    $creds['client_id'],
                    $creds['client_secret'],
                ),
                $creds['region'],
            );
            $graph->get('organization', ['$select' => 'id,displayName']);
            TenantRecordRepository::updateHealth($tenantRecordId, 'connected', '');
        } catch (\Throwable $e) {
            Ms365CustomerError::log('probeAndUpdateHealth', $e);
            $customerMessage = Ms365CustomerError::message($e);
            TenantRecordRepository::updateHealth($tenantRecordId, 'action_required', $customerMessage);
            throw new \RuntimeException($customerMessage, 0, $e);
        }
    }

    /** @return array<string, mixed> */
    private static function encodeState(array $payload): string
    {
        $json = json_encode($payload);
        $sig = hash_hmac('sha256', $json, self::stateSecret());

        return rtrim(strtr(base64_encode($json . '|' . $sig), '+/', '-_'), '=');
    }

    /** @return array<string, mixed> */
    public static function peekReturnPath(string $stateRaw): string
    {
        try {
            $state = self::decodeState($stateRaw);

            return trim((string) ($state['return_path'] ?? ''));
        } catch (\Throwable $_) {
            return '';
        }
    }

    public static function peekConsentMode(string $stateRaw): string
    {
        try {
            $state = self::decodeState($stateRaw);
            $mode = (string) ($state['consent_mode'] ?? 'redirect');

            return $mode === 'popup' ? 'popup' : 'redirect';
        } catch (\Throwable $_) {
            return 'redirect';
        }
    }

    public static function peekBackupUserId(string $stateRaw): int
    {
        try {
            $state = self::decodeState($stateRaw);

            return (int) ($state['backup_user_id'] ?? 0);
        } catch (\Throwable $_) {
            return 0;
        }
    }

    public static function peekClientId(string $stateRaw): int
    {
        try {
            $state = self::decodeState($stateRaw);

            return (int) ($state['client_id'] ?? 0);
        } catch (\Throwable $_) {
            return 0;
        }
    }

    private static function decodeState(string $state): array
    {
        $decoded = base64_decode(strtr($state, '-_', '+/'), true);
        if ($decoded === false || !str_contains($decoded, '|')) {
            throw new \RuntimeException('Invalid OAuth state payload.');
        }
        [$json, $sig] = explode('|', $decoded, 2);
        $expected = hash_hmac('sha256', $json, self::stateSecret());
        if (!hash_equals($expected, $sig)) {
            throw new \RuntimeException('OAuth state signature mismatch.');
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new \RuntimeException('OAuth state JSON invalid.');
        }
        $ts = (int) ($data['ts'] ?? 0);
        if ($ts <= 0 || (time() - $ts) > self::STATE_TTL) {
            throw new \RuntimeException('OAuth state expired.');
        }

        return $data;
    }

    private static function stateSecret(): string
    {
        if (class_exists(\WHMCS\Config\Setting::class)) {
            $hash = (string) \WHMCS\Config\Setting::getValue('cc_encryption_hash');
            if ($hash !== '') {
                return $hash;
            }
        }

        return 'ms365backup-oauth-state-dev';
    }
}
