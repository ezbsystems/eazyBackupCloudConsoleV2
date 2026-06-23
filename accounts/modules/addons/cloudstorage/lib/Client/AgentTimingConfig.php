<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

/**
 * Shared watchdog / reclaim timing for agent_next_run and agent_watchdog cron.
 */
class AgentTimingConfig
{
    public static function get(): array
    {
        $defaultWatchdog = 720;
        $defaultReclaim = 180;
        $defaultReclaimEnabled = true;

        $dbWatchdog = (int) AgentIngestSupport::getModuleSetting(
            'cloudbackup_agent_watchdog_timeout_seconds',
            $defaultWatchdog
        );
        $dbReclaim = (int) AgentIngestSupport::getModuleSetting(
            'cloudbackup_agent_reclaim_grace_seconds',
            $defaultReclaim
        );
        $dbReclaimEnabledRaw = AgentIngestSupport::getModuleSetting(
            'cloudbackup_agent_reclaim_enabled',
            $defaultReclaimEnabled ? '1' : '0'
        );
        $dbReclaimEnabled = !in_array(
            strtolower((string) $dbReclaimEnabledRaw),
            ['0', 'false', 'off', 'no'],
            true
        );

        $watchdog = self::getIntEnv('AGENT_WATCHDOG_TIMEOUT_SECONDS', $dbWatchdog);
        $reclaim = self::getIntEnv('AGENT_RECLAIM_GRACE_SECONDS', $dbReclaim);
        $reclaimEnabled = self::getBoolEnv('AGENT_RECLAIM_ENABLED', $dbReclaimEnabled);

        if ($reclaim >= $watchdog) {
            $reclaim = max(60, (int) floor($watchdog * 0.25));
            if ($reclaim >= $watchdog) {
                $reclaim = max(60, $watchdog - 60);
            }
        }

        return [
            'watchdog_timeout_seconds' => $watchdog,
            'reclaim_grace_seconds' => $reclaim,
            'reclaim_enabled' => $reclaimEnabled,
        ];
    }

    private static function getIntEnv(string $key, int $default): int
    {
        $val = getenv($key);
        if ($val === false) {
            return $default;
        }
        $val = (int) $val;
        return $val > 0 ? $val : $default;
    }

    private static function getBoolEnv(string $key, bool $default): bool
    {
        $val = getenv($key);
        if ($val === false) {
            return $default;
        }
        $normalized = strtolower(trim((string) $val));
        if (in_array($normalized, ['0', 'false', 'off', 'no'], true)) {
            return false;
        }
        if (in_array($normalized, ['1', 'true', 'on', 'yes'], true)) {
            return true;
        }
        return $default;
    }
}
