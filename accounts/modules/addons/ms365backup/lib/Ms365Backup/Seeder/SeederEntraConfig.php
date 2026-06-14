<?php
declare(strict_types=1);

namespace Ms365Backup\Seeder;

final class SeederEntraConfig
{
    public static function redirectUri(): string
    {
        $adminPath = self::adminBaseUrl();

        return rtrim($adminPath, '/') . '/addonmodules.php?module=ms365backup&action=seeder_oauth_callback';
    }

    /** @return list<string> */
    public static function delegatedScopes(): array
    {
        return [
            'openid',
            'profile',
            'offline_access',
            'ChannelMessage.Send',
            'ChatMessage.Send',
        ];
    }

    private static function adminBaseUrl(): string
    {
        if (class_exists(\WHMCS\Config\Setting::class)) {
            try {
                $systemUrl = rtrim((string) \WHMCS\Config\Setting::getValue('SystemURL'), '/');
                if ($systemUrl !== '') {
                    return $systemUrl . '/admin';
                }
            } catch (\Throwable $_) {
            }
        }

        $scheme = 'http';
        if (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
            || (isset($_SERVER['REQUEST_SCHEME']) && strtolower((string) $_SERVER['REQUEST_SCHEME']) === 'https')
        ) {
            $scheme = 'https';
        }
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return $scheme . '://' . $host . '/admin';
    }
}
