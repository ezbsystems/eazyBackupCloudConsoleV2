<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

use WHMCS\Database\Capsule;

class TimezoneHelper
{
    /**
     * Resolve the best user timezone for displaying run timestamps.
     *
     * @param int $clientId
     * @param int|null $jobId
     * @return \DateTimeZone
     */
    public static function resolveUserTimezone($clientId, $jobId = null)
    {
        $tz = '';
        try {
            if (!empty($jobId)) {
                $tz = (string) Capsule::table('s3_cloudbackup_jobs')
                    ->where('id', (int) $jobId)
                    ->value('timezone');
            }
        } catch (\Throwable $e) {
            // Best-effort only
        }

        if ($tz === '' && !empty($clientId)) {
            try {
                $tz = (string) Capsule::table('s3_cloudbackup_settings')
                    ->where('client_id', (int) $clientId)
                    ->value('default_timezone');
            } catch (\Throwable $e) {
                // Best-effort only
            }
        }

        $tz = trim($tz);
        if ($tz !== '') {
            try {
                return new \DateTimeZone($tz);
            } catch (\Throwable $e) {
                // Fall through to server timezone
            }
        }

        $serverTz = date_default_timezone_get();
        if (!$serverTz) {
            $serverTz = 'UTC';
        }
        return new \DateTimeZone($serverTz);
    }

    /**
     * Format a timestamp (stored in server timezone) into the user timezone.
     *
     * @param string|null $timestamp
     * @param \DateTimeZone $userTz
     * @param string|null $format
     * @return string
     */
    public static function formatTimestamp($timestamp, \DateTimeZone $userTz, $format = null)
    {
        if ($timestamp === null || $timestamp === '') {
            return '';
        }

        try {
            $serverTz = date_default_timezone_get() ?: 'UTC';
            $dt = new \DateTime((string) $timestamp, new \DateTimeZone($serverTz));
            $dt->setTimezone($userTz);
            if ($format === null) {
                $format = (strpos((string) $timestamp, '.') !== false) ? 'Y-m-d H:i:s.u' : 'Y-m-d H:i:s';
            }
            return $dt->format($format);
        } catch (\Throwable $e) {
            return (string) $timestamp;
        }
    }

    /**
     * Format a timestamp into time-only in the user timezone.
     *
     * @param string|null $timestamp
     * @param \DateTimeZone $userTz
     * @return string|null
     */
    public static function formatTimeOnly($timestamp, \DateTimeZone $userTz)
    {
        if ($timestamp === null || $timestamp === '') {
            return null;
        }
        try {
            $serverTz = date_default_timezone_get() ?: 'UTC';
            $dt = new \DateTime((string) $timestamp, new \DateTimeZone($serverTz));
            $dt->setTimezone($userTz);
            return $dt->format('H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
