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
            // Fallback: use 'message' param if provided by the agent
            if (isset($params['message']) && is_string($params['message']) && $params['message'] !== '') {
                return $params['message'];
            }
            // Final fallback generic message
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
            // Kopia-specific
            'KOPIA_MANIFEST_RECORDED' => 'Snapshot manifest recorded: {manifest_id}.',
            'KOPIA_MANIFEST_MISSING' => 'Snapshot manifest missing. Source={source} Bucket={bucket} Prefix={prefix} Repo={repo_path}.',
            'KOPIA_MANIFEST_UPDATE_FAILED' => 'Failed to record manifest: {error}.',
            'KOPIA_UPLOAD_FAILED' => 'Kopia upload failed: {error}.',
            'KOPIA_MANIFEST_FALLBACK' => 'Snapshot manifest recovered from repository: {manifest_id}.',
            'KOPIA_STAGE' => 'Stage: {stage}.',
            'KOPIA_POLICY' => 'Policy: compression={compression}, parallel_uploads={parallel_uploads}.',
            'KOPIA_UPLOAD_START' => 'Starting upload from {source} to {bucket}/{prefix}.',
            'KOPIA_UPLOAD_COMPLETE' => 'Upload completed.',
            'KOPIA_UPLOAD_NIL_MANIFEST' => 'Upload returned nil manifest.',
            'KOPIA_SAVE_SNAPSHOT_FAILED' => 'Failed to save snapshot: {error}.',
            'KOPIA_SOURCE_EMPTY' => 'Source path appears empty: {source}.',
            'KOPIA_THROTTLE_SET' => 'Upload throttled to {upload_bytes_per_sec} bytes/s.',
            'KOPIA_THROTTLE_SET_FAILED' => 'Failed to set throttle: {error}.',
            
            // Restore-specific
            'RESTORE_QUEUED' => 'Restore queued for snapshot {manifest_id} to {target_path}.',
            'RESTORE_STARTING' => 'Starting restore of snapshot {manifest_id} to {target_path}.',
            'RESTORE_COMPLETED' => 'Restore completed successfully to {target_path}.',
            'RESTORE_FAILED' => 'Restore failed: {error}.',
            'RESTORE_PROGRESS' => 'Restored {files_restored} files, {dirs_restored} directories ({bytes_restored}).',
            'KOPIA_RESTORE_CONNECTING' => 'Connecting to repository at {bucket}/{prefix}.',
            'KOPIA_RESTORE_LOADING' => 'Loading snapshot {manifest_id} for restore.',
            'KOPIA_RESTORE_SNAPSHOT_LOADED' => 'Snapshot loaded: source={source_path}, time={start_time}.',
            'KOPIA_RESTORE_STARTED' => 'Restore started to {target_path}.',
            'KOPIA_RESTORE_STATS' => 'Restore stats: {files_restored} files, {dirs_restored} dirs, {bytes_restored} bytes.',
            
            // Maintenance-specific
            'MAINTENANCE_STARTING' => 'Starting maintenance ({mode}).',
            'MAINTENANCE_COMPLETED' => 'Maintenance ({mode}) completed successfully.',
            'MAINTENANCE_FAILED' => 'Maintenance ({mode}) failed: {error}.',
            
            // Hyper-V specific
            'HYPERV_NO_VMS' => 'No VMs configured for backup.',
            'HYPERV_VM_STARTING' => 'Starting backup of VM "{vm_name}".',
            'HYPERV_VM_COMPLETE' => 'VM "{vm_name}" backed up successfully ({backup_type}, {consistency}).',
            'HYPERV_VM_FAILED' => 'VM "{vm_name}" backup failed: {message}',
            'HYPERV_CHECKPOINTS_DISABLED' => '⚠️ {message}',
            'HYPERV_DISK_STARTING' => 'Backing up disk: {disk_path} ({size}).',
            'HYPERV_BACKUP_COMPLETE' => '{message}',
            
            // Disk Image specific
            'DISK_IMAGE_STARTING' => 'Starting disk image backup of {volume}.',
            'DISK_IMAGE_STREAM_START' => 'Streaming disk image to cloud storage.',
            'DISK_IMAGE_STREAM_COMPLETED' => 'Disk image stream completed.',
            'DISK_IMAGE_COMPLETED' => 'Disk image backup completed.',
            'DISK_IMAGE_FAILED' => 'Disk image backup failed: {error}.',
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
        if (isset($params['bytes_restored'])) {
            $out['bytes_restored'] = HelperController::formatSizeUnitsPlain((int) $params['bytes_restored']);
        }
        // Hyper-V specific sizes
        if (isset($params['size']) && is_numeric($params['size'])) {
            $out['size'] = HelperController::formatSizeUnitsPlain((int) $params['size']);
        }
        if (isset($params['total_bytes']) && is_numeric($params['total_bytes'])) {
            $out['total_bytes'] = HelperController::formatSizeUnitsPlain((int) $params['total_bytes']);
        }
        if (isset($params['changed_bytes']) && is_numeric($params['changed_bytes'])) {
            $out['changed_bytes'] = HelperController::formatSizeUnitsPlain((int) $params['changed_bytes']);
        }
        if (isset($params['speed_bps'])) {
            $out['speed'] = HelperController::formatSizeUnitsPlain((int) $params['speed_bps']);
        }
        if (isset($params['upload_bytes_per_sec'])) {
            $out['upload_bytes_per_sec'] = HelperController::formatSizeUnitsPlain((int) $params['upload_bytes_per_sec']);
        }
        if (isset($params['eta_seconds'])) {
            $out['eta'] = self::formatEta((int) $params['eta_seconds']);
        }
        if (isset($params['pct'])) {
            $out['pct'] = number_format((float) $params['pct'], 2);
        }
        // Truncate prefixes and paths
        foreach (['source_prefix_trunc', 'dest_prefix_trunc', 'target_path', 'source_path'] as $k) {
            if (isset($params[$k])) {
                $out[$k] = self::truncatePath((string) $params[$k], 60);
            }
        }
        // Truncate manifest IDs for display
        if (isset($params['manifest_id']) && strlen($params['manifest_id']) > 20) {
            $out['manifest_id'] = substr($params['manifest_id'], 0, 16) . '...';
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


