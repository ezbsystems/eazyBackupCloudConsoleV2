<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;

/**
 * MS365 backup engine configuration (Kopia Go worker fleet only).
 */
final class Ms365EngineConfig
{
    public const MODE_KOPIA = 'kopia';

    public static function engineMode(): string
    {
        return self::MODE_KOPIA;
    }

    public static function usesKopiaWorker(): bool
    {
        return true;
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
            'sharepoint_lists' => true,
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
        return max(1, (int) self::moduleSetting('ms365_per_tenant_max_concurrent', '16'));
    }

    /** Max concurrent child workloads claimed per Entra tenant (distinct from Graph HTTP budget). */
    public static function perTenantMaxConcurrentWorkloads(): int
    {
        return max(1, (int) self::moduleSetting('ms365_per_tenant_max_concurrent_workloads', '24'));
    }

    public static function perClientMaxConcurrent(): int
    {
        return max(1, (int) self::moduleSetting('ms365_per_client_max_concurrent', '96'));
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
        return strtolower(trim(self::moduleSetting('ms365_sharding_enabled', '1'))) !== '0';
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
        return max(2, min(64, (int) self::moduleSetting('ms365_shard_max_count', '48')));
    }

    public static function shardItemThreshold(): int
    {
        return max(1, (int) self::moduleSetting('ms365_shard_item_threshold', '10000'));
    }

    public static function shardTargetItems(): int
    {
        return max(1, (int) self::moduleSetting('ms365_shard_target_items', '8000'));
    }

    public static function listJobItemThreshold(): int
    {
        return max(1, (int) self::moduleSetting('ms365_list_job_item_threshold', '25000'));
    }

    public static function listShardItemThreshold(): int
    {
        return max(1, (int) self::moduleSetting('ms365_list_shard_item_threshold', '500000'));
    }

    public static function listShardTargetItems(): int
    {
        return max(1, (int) self::moduleSetting('ms365_list_shard_target_items', '100000'));
    }

    public static function listShardMaxCount(): int
    {
        return max(2, min(64, (int) self::moduleSetting('ms365_list_shard_max_count', '16')));
    }

    /**
     * @return array<string, array{max_pages: int, on_cap: string}>
     */
    public static function paginationLimits(): array
    {
        $defaults = [
            'default' => ['max_pages' => 500, 'on_cap' => 'fail'],
            'sharepoint' => ['max_pages' => 2500, 'on_cap' => 'warn_continue'],
            'sharepoint_lists' => ['max_pages' => 2500, 'on_cap' => 'warn_continue'],
            'mail' => ['max_pages' => 500, 'on_cap' => 'fail'],
        ];
        $raw = trim(self::moduleSetting('ms365_graph_pagination_json', ''));
        if ($raw === '') {
            return $defaults;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return $defaults;
        }
        foreach ($decoded as $workload => $cfg) {
            if (!is_string($workload) || !is_array($cfg)) {
                continue;
            }
            $entry = $defaults[$workload] ?? ['max_pages' => 500, 'on_cap' => 'fail'];
            if (isset($cfg['max_pages'])) {
                $entry['max_pages'] = max(1, (int) $cfg['max_pages']);
            }
            if (isset($cfg['on_cap']) && is_string($cfg['on_cap'])) {
                $entry['on_cap'] = strtolower(trim($cfg['on_cap']));
            }
            $defaults[$workload] = $entry;
        }

        return $defaults;
    }

    /**
     * @param array<string, bool> $workloads
     * @return array<string, array{max_pages: int, on_cap: string}>
     */
    public static function paginationLimitsForWorkloads(array $workloads): array
    {
        $all = self::paginationLimits();
        $out = [];
        if (isset($all['default'])) {
            $out['default'] = $all['default'];
        }
        foreach ($workloads as $name => $enabled) {
            if (!$enabled || !is_string($name)) {
                continue;
            }
            if (isset($all[$name])) {
                $out[$name] = $all[$name];
            }
        }

        return $out;
    }

    public static function kopiaMaintenanceIntervalDays(): int
    {
        return max(1, (int) self::moduleSetting('ms365_kopia_maintenance_interval_days', '7'));
    }

    public static function fairSchedulingEnabled(): bool
    {
        return strtolower(trim(self::moduleSetting('ms365_fair_scheduling_enabled', '1'))) !== '0';
    }

    public static function batchAutoRetryEnabled(): bool
    {
        return strtolower(trim(self::moduleSetting('ms365_batch_auto_retry_enabled', '1'))) !== '0';
    }

    public static function batchAutoRetryMaxRounds(): int
    {
        return max(1, min(10, (int) self::moduleSetting('ms365_batch_auto_retry_max_rounds', '2')));
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
