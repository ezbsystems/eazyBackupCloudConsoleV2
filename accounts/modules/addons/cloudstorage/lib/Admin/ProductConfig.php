<?php

namespace WHMCS\Module\Addon\CloudStorage\Admin;

use Illuminate\Database\Capsule\Manager as Capsule;

class ProductConfig {
    /**
     * Default WHMCS product ID for the e3 Object Storage / Cloud Storage
     * product. Despite the name, this is **not** the e3 Cloud Backup product
     * (Comet/local-agent); it is the S3 / RGW object-storage product used by
     * the bucket browse, list, upload, lifecycle, etc. API endpoints.
     *
     * Most callers should prefer cloudStoragePid() / e3CloudBackupPid()
     * below, which honour the per-install settings persisted in
     * tbladdonmodules (pid_cloud_storage / pid_e3_cloud_backup).
     */
    public static int $E3_PRODUCT_ID = 48;

    /**
     * Resolve the configured WHMCS product ID for the e3 Cloud Backup
     * (Comet-backed local-agent) product. Reads pid_e3_cloud_backup from
     * the cloudstorage addon settings; returns 0 if unset.
     */
    public static function e3CloudBackupPid(): int
    {
        try {
            return (int) Capsule::table('tbladdonmodules')
                ->where('module', 'cloudstorage')
                ->where('setting', 'pid_e3_cloud_backup')
                ->value('value');
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Resolve the configured WHMCS product ID for the e3 Object Storage /
     * Cloud Storage product. Reads pid_cloud_storage from the cloudstorage
     * addon settings; falls back to the static default when unset.
     */
    public static function cloudStoragePid(): int
    {
        try {
            $v = (int) Capsule::table('tbladdonmodules')
                ->where('module', 'cloudstorage')
                ->where('setting', 'pid_cloud_storage')
                ->value('value');
            if ($v > 0) {
                return $v;
            }
        } catch (\Throwable $e) {
        }
        return self::$E3_PRODUCT_ID;
    }

    /**
     * Resolve the configured WHMCS product ID for Microsoft 365 Backup.
     * Reads pid_ms365_backup from the ms365backup addon settings.
     */
    public static function ms365BackupPid(): int
    {
        try {
            return (int) Capsule::table('tbladdonmodules')
                ->where('module', 'ms365backup')
                ->where('setting', 'pid_ms365_backup')
                ->value('value');
        } catch (\Throwable $e) {
            return 0;
        }
    }
}