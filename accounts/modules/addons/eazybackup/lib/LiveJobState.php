<?php

namespace WHMCS\Module\Addon\Eazybackup;

/**
 * Derive non-terminal display status for live Comet jobs based on device liveness.
 */
class LiveJobState
{
    public const DEFAULT_INTERRUPTED_GRACE_SECS = 300;
    public const DEFAULT_OFFLINE_CLEANUP_SECS = 3600;

    public static function interruptedGraceSecs(): int
    {
        $v = getenv('EB_INTERRUPTED_GRACE_SECS');
        return ($v !== false && $v !== '' && is_numeric($v))
            ? max(0, (int)$v)
            : self::DEFAULT_INTERRUPTED_GRACE_SECS;
    }

    public static function offlineCleanupSecs(): int
    {
        $v = getenv('EB_OFFLINE_CLEANUP_SECS');
        return ($v !== false && $v !== '' && is_numeric($v))
            ? max(60, (int)$v)
            : self::DEFAULT_OFFLINE_CLEANUP_SECS;
    }

    public static function offlineSinceToUnix($offlineSince): int
    {
        if ($offlineSince === null || $offlineSince === '') {
            return 0;
        }
        if (is_numeric($offlineSince)) {
            $n = (int)$offlineSince;
            return $n > 0 ? $n : 0;
        }
        $ts = strtotime((string)$offlineSince);
        return ($ts !== false && $ts > 0) ? $ts : 0;
    }

    /**
     * @param bool|null $deviceIsActive null when no matching device row was found
     * @return array{status:string,status_reason:?string,offline_since:?string}
     */
    public static function deriveStatus(?bool $deviceIsActive, $offlineSince, ?int $now = null): array
    {
        $now = $now ?? time();
        $out = [
            'status' => 'Running',
            'status_reason' => null,
            'offline_since' => null,
        ];

        if ($deviceIsActive !== false) {
            return $out;
        }

        $offlineTs = self::offlineSinceToUnix($offlineSince);
        if ($offlineTs <= 0) {
            return $out;
        }

        $out['offline_since'] = gmdate('c', $offlineTs);
        if (($now - $offlineTs) >= self::interruptedGraceSecs()) {
            $out['status'] = 'Interrupted';
            $out['status_reason'] = 'device_offline';
        }

        return $out;
    }

    public static function isInterrupted(?bool $deviceIsActive, $offlineSince, ?int $now = null): bool
    {
        return self::deriveStatus($deviceIsActive, $offlineSince, $now)['status'] === 'Interrupted';
    }

    public static function progressHeartbeatTs(array $props): int
    {
        $p = $props['Progress'] ?? [];
        if (!is_array($p)) {
            return 0;
        }
        $sent = isset($p['SentTime']) ? (int)$p['SentTime'] : 0;
        $recv = isset($p['RecievedTime']) ? (int)$p['RecievedTime'] : 0;
        return max($sent, $recv);
    }
}
