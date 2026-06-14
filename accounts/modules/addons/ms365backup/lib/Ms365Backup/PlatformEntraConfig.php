<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;

/**
 * Platform-wide Entra app used for customer admin consent (multi-tenant SaaS app).
 */
final class PlatformEntraConfig
{
    public static function clientId(): string
    {
        return trim(self::moduleSetting('platform_entra_client_id'));
    }

    public static function clientSecret(): string
    {
        $enc = self::moduleSetting('platform_entra_secret_enc');
        if ($enc !== '') {
            return TenantRepository::decryptSecret($enc);
        }

        return trim(self::moduleSetting('platform_entra_secret'));
    }

    /** Customer admin-consent callback (e3 client area — never the admin seeder). */
    public static function customerConnectCallbackUrl(): string
    {
        $systemUrl = rtrim((string) (\WHMCS\Config\Setting::getValue('SystemURL') ?? ''), '/');

        return $systemUrl . '/index.php?m=cloudstorage&page=e3backup&view=ms365_connect_callback';
    }

    public static function redirectUri(): string
    {
        $configured = trim(self::moduleSetting('platform_entra_redirect_uri'));
        if ($configured !== '') {
            if (!self::isCustomerCallbackUri($configured)) {
                return self::customerConnectCallbackUrl();
            }

            return $configured;
        }

        return self::customerConnectCallbackUrl();
    }

    public static function isCustomerCallbackUri(string $uri): bool
    {
        return str_contains($uri, 'ms365_connect_callback')
            && !str_contains($uri, '/admin/')
            && !str_contains($uri, 'seeder');
    }

    public static function assertCustomerRedirectUri(): void
    {
        $configured = trim(self::moduleSetting('platform_entra_redirect_uri'));
        if ($configured !== '' && !self::isCustomerCallbackUri($configured)) {
            throw new \RuntimeException(
                'Platform Entra redirect URI is misconfigured. Customer backups must use the e3 client callback '
                . self::customerConnectCallbackUrl()
                . ' — not the admin Tenant Seeder URL. Clear "OAuth redirect URI" in MS365 Backup module settings '
                . 'or update the platform Entra app registration to match.'
            );
        }
    }

    public static function region(): string
    {
        $r = trim(self::moduleSetting('platform_entra_region'));

        return $r !== '' ? $r : 'GlobalPublicCloud';
    }

    public static function isConfigured(): bool
    {
        return self::clientId() !== '' && self::clientSecret() !== '';
    }

    private static function moduleSetting(string $key): string
    {
        if (!class_exists(Capsule::class)) {
            return '';
        }
        try {
            $val = Capsule::table('tbladdonmodules')
                ->where('module', 'ms365backup')
                ->where('setting', $key)
                ->value('value');

            return is_string($val) ? trim($val) : '';
        } catch (\Throwable $_) {
            return '';
        }
    }
}
