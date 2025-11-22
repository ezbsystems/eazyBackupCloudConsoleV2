<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

class CloudBackupEventFormatter
{
    /**
     * Render an event message from a message_id and params.
     *
     * @param string $messageId
     * @param array $params
     * @return string
     */
    public static function render($messageId, array $params = [])
    {
        $dict = self::dictionary();
        $id = (string) $messageId;
        $tmpl = isset($dict[$id]) ? $dict[$id] : '';
        if ($tmpl === '') {
            // Fallback generic message
            return 'Update received.';
        }
        // Sanitize and humanize known params
        $safe = self::sanitizeParams($params);
        return self::interpolate($tmpl, $safe);
    }

    /**
     * Message dictionary
     *
     * @return array
     */
    private static function dictionary()
    {
        return [
            'BACKUP_QUEUED' => 'Backup queued.',
            'BACKUP_STARTING' => 'Starting backup.',
            'SCANNING_SOURCE' => 'Scanning source for changes…',
            'PROGRESS_UPDATE' => 'Transferred {files_done} of {files_total} files ({pct}%), {bytes_done}/{bytes_total} at {speed}/s, ETA {eta}.',
            'SUMMARY_TOTAL' => 'Total transferred: {bytes_done}.',
            'NO_CHANGES' => 'Backup completed — no files to transfer.',
            'COMPLETED_SUCCESS' => 'Backup completed successfully.',
            'COMPLETED_WARNING' => 'Backup completed with warnings.',
            'COMPLETED_FAILED' => 'Backup failed.',
            'CANCEL_REQUESTED' => 'Cancellation requested — stopping the backup…',
            'CANCELLED' => 'Backup cancelled.',
            'VALIDATION_START' => 'Starting validation check.',
            'VALIDATION_SUCCESS' => 'Validation completed successfully — all files verified.',
            'VALIDATION_FAILED' => 'Validation failed — differences detected.',
            'ERROR_AUTH' => 'Authentication failed. Check credentials or re‑authorize the connection.',
            'ERROR_PERMISSION' => 'Permission denied on source or destination path.',
            'ERROR_NOT_FOUND' => 'Path not found at source.',
            'ERROR_NETWORK' => 'Network issue communicating with storage — retrying…',
            'ERROR_RATE_LIMIT' => 'Provider rate limited requests — backing off and retrying…',
            'ERROR_QUOTA' => 'Storage provider quota exceeded.',
            'ERROR_INTERNAL' => 'Unexpected error — our team has been notified.',
            'LOG_TRUNCATED' => 'Display log truncated due to size. Showing summary only.',
        ];
    }

    /**
     * Replace placeholders like {name} with values.
     *
     * @param string $template
     * @param array $params
     * @return string
     */
    private static function interpolate($template, array $params)
    {
        return preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function ($m) use ($params) {
            $key = $m[1];
            if (!array_key_exists($key, $params)) {
                return $m[0];
            }
            return (string) $params[$key];
        }, $template);
    }

    /**
     * Sanitize and humanize known params.
     *
     * @param array $params
     * @return array
     */
    private static function sanitizeParams(array $params)
    {
        $out = $params;
        // Humanize sizes (plain text for log rendering)
        if (isset($params['bytes_done'])) {
            $out['bytes_done'] = HelperController::formatSizeUnitsPlain((int) $params['bytes_done']);
        }
        if (isset($params['bytes_total'])) {
            $out['bytes_total'] = HelperController::formatSizeUnitsPlain((int) $params['bytes_total']);
        }
        if (isset($params['speed_bps'])) {
            $out['speed'] = HelperController::formatSizeUnitsPlain((int) $params['speed_bps']);
        }
        if (isset($params['eta_seconds'])) {
            $out['eta'] = self::formatEta((int) $params['eta_seconds']);
        }
        if (isset($params['pct'])) {
            $out['pct'] = number_format((float) $params['pct'], 2);
        }
        // Truncate prefixes
        foreach (['source_prefix_trunc','dest_prefix_trunc'] as $k) {
            if (isset($params[$k])) {
                $out[$k] = self::truncatePath((string) $params[$k], 60);
            }
        }
        return $out;
    }

    private static function formatEta($seconds)
    {
        if ($seconds <= 0) {
            return '-';
        }
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;
        $parts = [];
        if ($h > 0) $parts[] = $h . 'h';
        if ($m > 0) $parts[] = $m . 'm';
        $parts[] = $s . 's';
        return implode(' ', $parts);
    }

    private static function truncatePath($path, $max = 60)
    {
        $p = trim((string) $path);
        if ($p === '' || mb_strlen($p) <= $max) {
            return $p;
        }
        // Keep first segment and last 1-2 segments
        $parts = preg_split('#[\\/]+#', $p);
        if (!$parts || count($parts) < 3) {
            return mb_substr($p, 0, (int) ($max - 3)) . '...';
        }
        $first = $parts[0];
        $tail = array_slice($parts, -2);
        $mid = '...';
        $candidate = $first . '/' . $mid . '/' . implode('/', $tail);
        if (mb_strlen($candidate) > $max) {
            return mb_substr($candidate, 0, $max - 3) . '...';
        }
        return $candidate;
    }
}


