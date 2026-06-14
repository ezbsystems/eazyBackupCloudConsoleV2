<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;

/**
 * Engine mode and per-workload feature flags for MS365 Kopia worker migration.
 */
final class Ms365EngineConfig
{
    public const MODE_PHP = 'php';
    public const MODE_KOPIA = 'kopia';
    public const MODE_KOPIA_SHADOW = 'kopia_shadow';

    public static function engineMode(): string
    {
        $mode = strtolower(trim(self::moduleSetting('ms365_engine_mode', self::MODE_PHP)));
        if (!in_array($mode, [self::MODE_PHP, self::MODE_KOPIA, self::MODE_KOPIA_SHADOW], true)) {
            return self::MODE_PHP;
        }

        return $mode;
    }

    public static function usesKopiaWorker(): bool
    {
        return in_array(self::engineMode(), [self::MODE_KOPIA, self::MODE_KOPIA_SHADOW], true);
    }

    public static function usesPhpWorker(): bool
    {
        $mode = self::engineMode();
        return $mode === self::MODE_PHP || $mode === self::MODE_KOPIA_SHADOW;
    }

    /** @return array<string, bool> */
    public static function workloadFlags(): array
    {
        $defaults = [
            'mail' => true,
            'calendar' => true,
            'contacts' => true,
            'tasks' => true,
            'onedrive' => true,
            'sharepoint' => true,
            'teams' => true,
            'planner' => true,
            'onenote' => true,
            'directory' => true,
        ];
        $raw = trim(self::moduleSetting('ms365_kopia_workloads_json', ''));
        if ($raw === '') {
            return $defaults;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return $defaults;
        }
        foreach ($defaults as $key => $enabled) {
            if (array_key_exists($key, $decoded)) {
                $defaults[$key] = (bool) $decoded[$key];
            }
        }

        return $defaults;
    }

    public static function platformMaxConcurrent(): int
    {
        return max(1, (int) self::moduleSetting('ms365_platform_max_concurrent', '100'));
    }

    public static function perTenantMaxConcurrent(): int
    {
        return max(1, (int) self::moduleSetting('ms365_per_tenant_max_concurrent', '3'));
    }

    public static function perClientMaxConcurrent(): int
    {
        return max(1, (int) self::moduleSetting('ms365_per_client_max_concurrent', '10'));
    }

    public static function workerToken(): string
    {
        return trim(self::moduleSetting('ms365_worker_token', ''));
    }

    public static function leaseSeconds(): int
    {
        return max(300, (int) self::moduleSetting('ms365_worker_lease_seconds', '7200'));
    }

    public static function shardingEnabled(): bool
    {
        return self::usesKopiaWorker()
            && strtolower(trim(self::moduleSetting('ms365_sharding_enabled', '1'))) !== '0';
    }

    /** Bytes above which a drive/site/user mailbox is split into shards. */
    public static function shardThresholdBytes(): int
    {
        return max(1, (int) self::moduleSetting('ms365_shard_threshold_bytes', '107374182400'));
    }

    /** Target bytes per content shard when splitting large drives/sites. */
    public static function shardTargetBytes(): int
    {
        return max(1, (int) self::moduleSetting('ms365_shard_target_bytes', '53687091200'));
    }

    public static function shardMaxCount(): int
    {
        return max(2, min(64, (int) self::moduleSetting('ms365_shard_max_count', '16')));
    }

    public static function kopiaMaintenanceIntervalDays(): int
    {
        return max(1, (int) self::moduleSetting('ms365_kopia_maintenance_interval_days', '7'));
    }

    public static function moduleSettingPublic(string $key, string $default = ''): string
    {
        return self::moduleSetting($key, $default);
    }

    private static function moduleSetting(string $key, string $default = ''): string
    {
        if (!class_exists(Capsule::class)) {
            return $default;
        }
        $val = Capsule::table('tbladdonmodules')
            ->where('module', 'ms365backup')
            ->where('setting', $key)
            ->value('value');

        return $val !== null && $val !== '' ? (string) $val : $default;
    }
}
