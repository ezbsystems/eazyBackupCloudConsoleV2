<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;

/**
 * Product flags and smart landing resolution for e3 Cloud Backup client area.
 */
class E3BackupClientState
{
    public static function clientHasE3AgentProduct(int $clientId): bool
    {
        if ($clientId <= 0) {
            return false;
        }

        $e3Pid = (int) ProductConfig::e3CloudBackupPid();
        if ($e3Pid <= 0) {
            return false;
        }

        $product = DBController::getActiveProduct($clientId, $e3Pid);
        if (!$product) {
            $product = DBController::getProduct($clientId, $e3Pid);
        }

        return $product && !empty($product->username);
    }

    public static function clientHasMs365Product(int $clientId): bool
    {
        if ($clientId <= 0) {
            return false;
        }

        $ms365Pid = (int) ProductConfig::ms365BackupPid();
        if ($ms365Pid <= 0) {
            return false;
        }

        $product = DBController::getActiveProduct($clientId, $ms365Pid);
        if (!$product) {
            $product = DBController::getProduct($clientId, $ms365Pid);
        }

        return $product && !empty($product->username);
    }

    public static function clientHasCloudStorageProduct(int $clientId): bool
    {
        if ($clientId <= 0) {
            return false;
        }

        $storagePid = (int) ProductConfig::cloudStoragePid();
        if ($storagePid <= 0) {
            return false;
        }

        return DBController::getActiveProduct($clientId, $storagePid) !== null;
    }

    public static function clientCanAccessE3BackupShell(int $clientId): bool
    {
        if ($clientId <= 0) {
            return false;
        }

        if (self::clientHasE3AgentProduct($clientId)
            || self::clientHasMs365Product($clientId)
            || self::clientHasCloudStorageProduct($clientId)) {
            return true;
        }

        try {
            if (Capsule::schema()->hasTable('s3_backup_users')) {
                return Capsule::table('s3_backup_users')->where('client_id', $clientId)->exists();
            }
        } catch (\Throwable $_) {
        }

        return false;
    }

    /**
     * @return 'ms365_getting_started'|'getting_started'|'dashboard'
     */
    public static function resolveLandingView(int $clientId): string
    {
        if ($clientId <= 0) {
            return 'dashboard';
        }

        if (self::clientHasMs365Product($clientId)) {
            $defaultBu = E3BackupAccess::defaultBackupUser($clientId);
            if ($defaultBu) {
                $ms365Autoload = dirname(__DIR__, 3) . '/ms365backup/ms365backup_autoload.php';
                if (is_file($ms365Autoload)) {
                    require_once $ms365Autoload;
                }
                if (class_exists('\\Ms365Backup\\Ms365Onboarding')) {
                    try {
                        $msOb = \Ms365Backup\Ms365Onboarding::computeForBackupUser(
                            $clientId,
                            (int) $defaultBu['id']
                        );
                        if (empty($msOb['all_complete'])) {
                            return 'ms365_getting_started';
                        }
                    } catch (\Throwable $_) {
                        return 'ms365_getting_started';
                    }
                }
            } else {
                return 'ms365_getting_started';
            }
        }

        if (self::clientHasE3AgentProduct($clientId)) {
            if (class_exists('\\WHMCS\\Module\\Addon\\CloudStorage\\Client\\OnboardingState')) {
                try {
                    $obState = OnboardingState::compute($clientId);
                    if (empty($obState['all_complete'])) {
                        return 'getting_started';
                    }
                } catch (\Throwable $_) {
                    return 'getting_started';
                }
            } else {
                $obPath = __DIR__ . '/OnboardingState.php';
                if (is_file($obPath)) {
                    require_once $obPath;
                    try {
                        $obState = OnboardingState::compute($clientId);
                        if (empty($obState['all_complete'])) {
                            return 'getting_started';
                        }
                    } catch (\Throwable $_) {
                        return 'getting_started';
                    }
                }
            }
        }

        return 'dashboard';
    }

    public static function resolveLandingUrl(int $clientId): string
    {
        $view = self::resolveLandingView($clientId);
        if ($view === 'dashboard') {
            return 'index.php?m=cloudstorage&page=e3backup';
        }

        return 'index.php?m=cloudstorage&page=e3backup&view=' . rawurlencode($view);
    }

    public static function showEnableAgentCard(int $clientId, bool $ms365OnboardingComplete = false): bool
    {
        if ($clientId <= 0 || self::clientHasE3AgentProduct($clientId)) {
            return false;
        }

        $hasMs365 = self::clientHasMs365Product($clientId);
        $hasStorage = self::clientHasCloudStorageProduct($clientId);

        if ($hasStorage && !$hasMs365) {
            return true;
        }

        return $hasMs365 && $ms365OnboardingComplete;
    }

    public static function showEnableMs365Card(int $clientId, bool $e3OnboardingComplete = false): bool
    {
        if ($clientId <= 0 || self::clientHasMs365Product($clientId)) {
            return false;
        }

        $hasAgent = self::clientHasE3AgentProduct($clientId);
        $hasStorage = self::clientHasCloudStorageProduct($clientId);

        if ($hasStorage && !$hasAgent) {
            return true;
        }

        return $hasAgent && $e3OnboardingComplete;
    }
}
