<?php
declare(strict_types=1);

namespace Ms365Backup;

final class StoragePermissions
{
    public static function webUser(): string
    {
        if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
            $info = posix_getpwuid(posix_geteuid());
            if (is_array($info) && !empty($info['name'])) {
                return (string) $info['name'];
            }
        }
        return 'www-data';
    }

    public static function applyToTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $user = self::webUser();
        @chmod($path, 0770);
        if (function_exists('chown')) {
            @chown($path, $user);
        }
        if (function_exists('chgrp')) {
            $group = $user;
            if (function_exists('posix_getgrgid') && function_exists('posix_getegid')) {
                $g = posix_getgrgid(posix_getegid());
                if (is_array($g) && !empty($g['name'])) {
                    $group = (string) $g['name'];
                }
            }
            @chgrp($path, $group);
        }
    }

    public static function ensureWritableBase(): void
    {
        StorageLayout::ensureBase();
        $base = StorageLayout::BASE_PATH;
        if (!is_dir($base)) {
            throw new \RuntimeException('Backup base directory missing: ' . $base);
        }
        if (!is_writable($base)) {
            self::applyToTree($base);
        }
        if (!is_writable($base)) {
            throw new \RuntimeException(
                'Backup directory is not writable by ' . self::webUser() . ': ' . $base
                . ' — run: chown -R www-data:www-data ' . $base . ' && chmod 0770 ' . $base
            );
        }
        $logs = $base . '/_logs';
        if (!is_dir($logs)) {
            mkdir($logs, 0770, true);
        }
        self::applyToTree($logs);
    }
}
