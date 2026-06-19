<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;

/**
 * Reads MS365 billing settings from tbladdonmodules (ms365backup addon).
 */
final class Ms365BillingConfig
{
    public const METRIC_PROTECTED_USERS = 'protected_users';
    public const METRIC_ONEDRIVE_OVERAGE_GIB = 'onedrive_overage_gib';

    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    /** @return list<string> */
    public static function metricKeys(): array
    {
        return [self::METRIC_PROTECTED_USERS, self::METRIC_ONEDRIVE_OVERAGE_GIB];
    }

    public static function metricFriendlyName(string $metric): string
    {
        return match ($metric) {
            self::METRIC_PROTECTED_USERS => 'Protected Users',
            self::METRIC_ONEDRIVE_OVERAGE_GIB => 'OneDrive Overage (GiB)',
            default => ucfirst(str_replace('_', ' ', $metric)),
        };
    }

    public static function getPid(): int
    {
        $raw = (string) self::setting('pid_ms365_backup', '');
        foreach (preg_split('/\s*,\s*/', $raw) ?: [] as $part) {
            $pid = (int) $part;
            if ($pid > 0) {
                return $pid;
            }
        }

        return 0;
    }

    /** @return list<int> */
    public static function getPids(): array
    {
        $raw = (string) self::setting('pid_ms365_backup', '');
        $pids = [];
        foreach (preg_split('/\s*,\s*/', $raw) ?: [] as $part) {
            $pid = (int) $part;
            if ($pid > 0) {
                $pids[] = $pid;
            }
        }

        return $pids;
    }

    public static function onedriveIncludedGib(): int
    {
        $v = (int) self::setting('onedrive_included_gib', '1024');

        return $v > 0 ? $v : 1024;
    }

    public static function onedriveIncludedBytes(): int
    {
        return self::onedriveIncludedGib() * 1024 * 1024 * 1024;
    }

    public static function protectedUserPriceCad(): float
    {
        return max(0.0, (float) self::setting('protected_user_price_cad', '0'));
    }

    public static function onedriveOveragePricePerGibCad(): float
    {
        return max(0.0, (float) self::setting('onedrive_overage_price_per_gib_cad', '0'));
    }

    public static function trialDays(): int
    {
        $days = (int) self::setting('ms365_trial_days', '30');

        return $days > 0 ? $days : 30;
    }

    /** @return array<string, int> */
    public static function getConfigOptionMap(): array
    {
        $raw = (string) self::setting('ms365_config_option_ids', '');
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }
        $clean = [];
        foreach ($decoded as $metric => $cid) {
            if (!is_string($metric)) {
                continue;
            }
            $cid = (int) $cid;
            if ($cid > 0) {
                $clean[$metric] = $cid;
            }
        }

        return $clean;
    }

    public static function unitPriceForMetric(string $metric): float
    {
        return match ($metric) {
            self::METRIC_PROTECTED_USERS => self::protectedUserPriceCad(),
            self::METRIC_ONEDRIVE_OVERAGE_GIB => self::onedriveOveragePricePerGibCad(),
            default => 0.0,
        };
    }

    private static function setting(string $key, string $default = ''): string
    {
        if (self::$cache === null) {
            self::$cache = [];
            try {
                $rows = Capsule::table('tbladdonmodules')
                    ->where('module', 'ms365backup')
                    ->pluck('value', 'setting');
                foreach ($rows as $k => $v) {
                    self::$cache[(string) $k] = (string) $v;
                }
            } catch (\Throwable $_) {
            }
        }

        return (string) (self::$cache[$key] ?? $default);
    }
}
