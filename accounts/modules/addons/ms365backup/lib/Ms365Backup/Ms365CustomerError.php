<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * Safe error text for e3 customer APIs and UI (no infra paths, IPs, or SDK dumps).
 */
final class Ms365CustomerError
{
    public static function message(\Throwable $e): string
    {
        $raw = trim($e->getMessage());

        if ($raw === '') {
            return self::generic();
        }

        if (self::isStorageBucketMissing($raw)) {
            return 'Backup storage is not ready yet. Wait a moment and use Refresh inventory again, or contact support if this continues.';
        }

        if (preg_match('/AADSTS\d+/i', $raw) || stripos($raw, 'invalid_client') !== false) {
            return 'Microsoft 365 connection could not be verified. Ask your administrator to reconnect, or contact support.';
        }

        if (stripos($raw, 'Connect Microsoft 365') !== false
            || stripos($raw, 'Refresh inventory') !== false
            || stripos($raw, 'No inventory') !== false
            || stripos($raw, 'Unknown backup preset') !== false
            || stripos($raw, 'not connected') !== false) {
            return $raw;
        }

        if (self::looksInternal($raw)) {
            return self::generic();
        }

        if (strlen($raw) <= 180) {
            return $raw;
        }

        return self::generic();
    }

    public static function sanitizeStored(?string $stored): string
    {
        if ($stored === null || trim($stored) === '') {
            return '';
        }

        return self::message(new \RuntimeException($stored));
    }

    public static function log(string $context, \Throwable $e): void
    {
        $line = 'MS365 [' . $context . ']: ' . $e->getMessage();
        if (function_exists('logActivity')) {
            logActivity($line);
        }
    }

    private static function generic(): string
    {
        return 'Something went wrong. Please try again or contact support.';
    }

    private static function isStorageBucketMissing(string $raw): bool
    {
        return stripos($raw, 'NoSuchBucket') !== false
            || stripos($raw, 'backup storage bucket') !== false
            || stripos($raw, 'MS365 cloud bucket') !== false
            || stripos($raw, 'Failed to ensure MS365 storage') !== false;
    }

    private static function looksInternal(string $raw): bool
    {
        if (preg_match('/\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}(:\d+)?\b/', $raw)) {
            return true;
        }

        $needles = [
            'PutObject',
            'GetObject',
            'HeadObject',
            'AWS HTTP error',
            'Client error:',
            'oauth2/v2.0/token',
            'login.microsoftonline.com',
            'e3ms365-',
            '<?xml',
            'GuzzleHttp',
            'stack trace',
            '/var/www/',
            'discovery/users.json',
            'ceph_',
            's3_endpoint',
        ];

        foreach ($needles as $needle) {
            if (stripos($raw, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
