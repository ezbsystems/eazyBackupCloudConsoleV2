<?php

namespace WHMCS\Module\Addon\Eazybackup;

class Helper {

    /**
     * @param int $bytes
     * @param int $decimals
     * @return string
     */
    public static function humanFileSize($bytes, $decimals = 0)
    {
        $size = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = (int) floor((strlen($bytes) - 1) / 3);

        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$size[$factor];
    }

    /**
     * Map Comet DestinationType to friendly label.
     * @param int|string $code
     * @return string
     */
    public static function vaultTypeLabel($code)
    {
        $map = [
            0 => 'INVALID',
            1000 => 'S3-compatible',
            1001 => 'SFTP',
            1002 => 'Local Path',
            1003 => 'eazyBackup',
            1004 => 'FTP',
            1005 => 'Azure',
            1006 => 'SPANNED',
            1007 => 'OpenStack',
            1008 => 'Backblaze B2',
            1100 => 'latest',
            1101 => 'All',
        ];
        $k = is_numeric($code) ? (int)$code : $code;
        return $map[$k] ?? (string)$code;
    }

    /**
     * Format a Unix timestamp (seconds) into YYYY-mm-dd HH:MM:SS.
     * @param int|string|null $ts
     * @return string
     */
    public static function formatDateTime($ts)
    {
        $t = (int) $ts;
        if ($t <= 0) return '';
        return gmdate('Y-m-d H:i:s', $t);
    }

    /**
     * Format duration in seconds to h:mm:ss or m:ss.
     * @param int $seconds
     * @return string
     */
    public static function formatDurationShort($seconds)
    {
        $s = max(0, (int)$seconds);
        $h = intdiv($s, 3600);
        $m = intdiv($s % 3600, 60);
        $sec = $s % 60;
        if ($h > 0) {
            return sprintf('%d:%02d:%02d', $h, $m, $sec);
        }
        return sprintf('%d:%02d', $m, $sec);
    }
}