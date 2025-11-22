<?php

namespace WHMCS\Module\Addon\Eazybackup;

use WHMCS\Database\Capsule as Capsule;

class Nfr
{
    private static function isTruthy($val): bool
    {
        if (is_bool($val)) { return $val; }
        $s = strtolower(trim((string)$val));
        if ($s === '') { return false; }
        if (in_array($s, ['1','on','yes','true'], true)) { return true; }
        if (is_numeric($s)) { return ((int)$s) !== 0; }
        return false;
    }

    public static function getSetting(string $key, $default = null)
    {
        try {
            $val = Capsule::table('tbladdonmodules')
                ->where('module', 'eazybackup')
                ->where('setting', $key)
                ->value('value');
            if ($val === null || $val === '') {
                return $default;
            }
            return $val;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    public static function enabled(): bool
    {
        return self::isTruthy(self::getSetting('nfr_enable', 0));
    }

    /**
     * @return int[]
     */
    public static function productIds(): array
    {
        $raw = (string)self::getSetting('nfr_pids', '');
        $out = [];
        foreach (preg_split('/[,;\s]+/', $raw) as $p) {
            $p = trim($p);
            if ($p !== '' && ctype_digit($p)) {
                $out[] = (int)$p;
            }
        }
        return array_values(array_unique($out));
    }

    public static function requireApproval(): bool
    {
        $explicit = self::isTruthy(self::getSetting('nfr_require_approval', 1));
        $pids = self::productIds();
        if (count($pids) > 1) {
            return true; // 2c: force manual approval when multiple PIDs
        }
        return $explicit;
    }

    public static function adminEmail(): string
    {
        return (string)self::getSetting('nfr_admin_email', '');
    }

    public static function defaultDurationDays(): int
    {
        return max(1, (int)self::getSetting('nfr_default_duration_days', 365));
    }

    public static function defaultQuotaGiB(): int
    {
        return max(0, (int)self::getSetting('nfr_default_quota_gib', 0));
    }

    public static function maxActivePerClient(): int
    {
        $v = (int)self::getSetting('nfr_max_active_per_client', 1);
        return $v > 0 ? $v : 1;
    }

    public static function captchaEnabled(): bool
    {
        return self::isTruthy(self::getSetting('nfr_captcha', 0));
    }

    public static function autoCreateTicket(): bool
    {
        return self::isTruthy(self::getSetting('nfr_auto_ticket', 0));
    }

    /**
     * @return array{mode:string,pid:int|null}
     */
    public static function conversionBehavior(): array
    {
        $raw = trim((string)self::getSetting('nfr_conversion_behavior', 'suspend'));
        if ($raw === 'suspend') { return ['mode' => 'suspend', 'pid' => null]; }
        if ($raw === 'do_nothing') { return ['mode' => 'do_nothing', 'pid' => null]; }
        if (strpos($raw, 'convert_to_pid:') === 0) {
            $pid = (int)substr($raw, strlen('convert_to_pid:'));
            return ['mode' => 'convert', 'pid' => ($pid > 0 ? $pid : null)];
        }
        return ['mode' => 'suspend', 'pid' => null];
    }

    public static function hasActiveGrant(int $clientId): bool
    {
        try {
            $today = date('Y-m-d');
            return Capsule::table('eb_nfr')
                ->where('client_id', $clientId)
                ->whereIn('status', ['approved','provisioned'])
                ->where(function($q) use ($today){
                    $q->whereNull('end_date')->orWhere('end_date', '>=', $today);
                })
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function activeGrant(int $clientId)
    {
        try {
            $today = date('Y-m-d');
            return Capsule::table('eb_nfr')
                ->where('client_id', $clientId)
                ->whereIn('status', ['approved','provisioned'])
                ->where(function($q) use ($today){
                    $q->whereNull('end_date')->orWhere('end_date', '>=', $today);
                })
                ->orderByDesc('id')
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }
}


