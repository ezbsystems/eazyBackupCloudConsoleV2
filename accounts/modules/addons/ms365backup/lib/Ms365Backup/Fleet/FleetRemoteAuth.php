<?php
declare(strict_types=1);

namespace Ms365Backup\Fleet;

use Ms365Backup\Ms365EngineConfig;

/**
 * Shared-secret auth for dev→prod fleet remote APIs.
 */
final class FleetRemoteAuth
{
    public const HEADER = 'X-MS365-Fleet-Deploy-Token';

    public static function authenticate(): ?array
    {
        $expected = self::sharedToken();
        if ($expected === '') {
            return ['ok' => false, 'error' => 'Fleet deploy shared secret not configured', 'code' => 503];
        }

        $provided = trim((string) ($_SERVER['HTTP_X_MS365_FLEET_DEPLOY_TOKEN'] ?? ''));
        if ($provided === '') {
            $provided = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
        }
        if ($provided === '' || !hash_equals($expected, $provided)) {
            return ['ok' => false, 'error' => 'Unauthorized', 'code' => 401];
        }

        return null;
    }

    public static function sharedToken(): string
    {
        $stored = trim(Ms365EngineConfig::moduleSettingPublic('ms365_fleet_deploy_shared_secret', ''));
        if ($stored === '') {
            return '';
        }
        // Match workerToken(): use the stored addon value as-is. WHMCS password fields persist
        // an encrypted blob that must be compared identically on dev and prod; decrypt() here
        // produced binary secrets with null bytes and broke HTTP headers (Apache 400).
        if (self::isHttpHeaderSafeSecret($stored)) {
            return $stored;
        }

        return '';
    }

    private static function isHttpHeaderSafeSecret(string $value): bool
    {
        return $value !== ''
            && preg_match('/^[\x21-\x7E]+$/', $value) === 1
            && strpos($value, "\0") === false;
    }

    public static function authHeaders(): array
    {
        $token = self::sharedToken();
        if ($token === '') {
            return [];
        }

        return [
            self::HEADER . ': ' . $token,
            'Accept: application/json',
            'User-Agent: ms365-fleet-remote/1.0',
        ];
    }
}
