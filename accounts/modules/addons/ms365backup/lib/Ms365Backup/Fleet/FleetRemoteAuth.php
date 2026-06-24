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
        $enc = trim(Ms365EngineConfig::moduleSettingPublic('ms365_fleet_deploy_shared_secret', ''));
        if ($enc === '') {
            return '';
        }
        try {
            if (function_exists('localAPI')) {
                $r = localAPI('DecryptPassword', ['password2' => $enc]);
                if (is_array($r) && isset($r['password']) && $r['password'] !== '') {
                    return trim((string) $r['password']);
                }
            }
            if (function_exists('decrypt')) {
                $plain = decrypt($enc);
                if (is_string($plain) && $plain !== '') {
                    return trim($plain);
                }
            }
        } catch (\Throwable $e) {
            // fall through to raw value (non-encrypted test setups)
        }

        return $enc;
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
