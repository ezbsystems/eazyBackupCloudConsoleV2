<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

use WHMCS\ClientArea;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;

/**
 * Shared access checks for e3 Cloud Backup client-area routes (agent + MS365).
 */
class E3BackupAccess
{
    public static function clientHasE3BackupAccess(int $clientId): bool
    {
        if ($clientId <= 0) {
            return false;
        }

        $e3Pid = (int) ProductConfig::e3CloudBackupPid();
        if ($e3Pid > 0) {
            $product = DBController::getProduct($clientId, $e3Pid);
            if ($product && !empty($product->username)) {
                return true;
            }
        }

        $ms365Pid = (int) ProductConfig::ms365BackupPid();
        if ($ms365Pid > 0) {
            $product = DBController::getProduct($clientId, $ms365Pid);
            if ($product && !empty($product->username)) {
                return true;
            }
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
     * Client-area login + access gate shared by e3backup routes.
     * Redirects to clientarea.php / welcome when appropriate.
     */
    public static function requireE3BackupClientAreaAccess(string $view = ''): int
    {
        $ca = new ClientArea();
        if (!$ca->isLoggedIn()) {
            header('Location: clientarea.php');
            exit;
        }

        $clientId = (int) $ca->getUserID();
        if (!self::clientHasE3BackupAccess($clientId)) {
            header('Location: index.php?m=cloudstorage&page=welcome');
            exit;
        }

        return $clientId;
    }

    /**
     * Relaxed shell access for dashboard, enable pages, and getting started.
     * Includes object-storage customers who have not yet enabled a backup product.
     */
    public static function requireE3BackupShellAccess(string $view = ''): int
    {
        $ca = new ClientArea();
        if (!$ca->isLoggedIn()) {
            header('Location: clientarea.php');
            exit;
        }

        $clientId = (int) $ca->getUserID();
        require_once __DIR__ . '/E3BackupClientState.php';
        if (!E3BackupClientState::clientCanAccessE3BackupShell($clientId)) {
            header('Location: index.php?m=cloudstorage&page=welcome');
            exit;
        }

        return $clientId;
    }

    /**
     * Primary WHMCS service username for object-storage lookups (e3 agent product, else MS365).
     */
    public static function resolveServiceUsername(int $clientId): string
    {
        $e3Pid = (int) ProductConfig::e3CloudBackupPid();
        if ($e3Pid > 0) {
            $product = DBController::getProduct($clientId, $e3Pid);
            if ($product && !empty($product->username)) {
                return (string) $product->username;
            }
        }

        $ms365Pid = (int) ProductConfig::ms365BackupPid();
        if ($ms365Pid > 0) {
            $product = DBController::getProduct($clientId, $ms365Pid);
            if ($product && !empty($product->username)) {
                return (string) $product->username;
            }
        }

        $default = self::defaultBackupUser($clientId);

        return $default ? trim((string) ($default['username'] ?? '')) : '';
    }

    /**
     * True when the client has MS365 backup provisioned but not e3 Cloud Backup (agent product).
     */
    public static function clientIsMs365Only(int $clientId): bool
    {
        if ($clientId <= 0) {
            return false;
        }

        $ms365Pid = (int) ProductConfig::ms365BackupPid();
        $hasMs365 = false;
        if ($ms365Pid > 0) {
            $product = DBController::getProduct($clientId, $ms365Pid);
            if ($product && !empty($product->username)) {
                $hasMs365 = true;
            } elseif (DBController::getActiveProduct($clientId, $ms365Pid) !== null) {
                $hasMs365 = true;
            }
        }
        if (!$hasMs365) {
            try {
                if (Capsule::schema()->hasTable('s3_backup_users')
                    && Capsule::schema()->hasColumn('s3_backup_users', 'backup_type')) {
                    $hasMs365 = Capsule::table('s3_backup_users')
                        ->where('client_id', $clientId)
                        ->where('backup_type', 'cloud_only')
                        ->exists();
                }
            } catch (\Throwable $_) {
            }
        }

        if (!$hasMs365) {
            return false;
        }

        $e3Pid = (int) ProductConfig::e3CloudBackupPid();
        if ($e3Pid > 0) {
            $e3Product = DBController::getProduct($clientId, $e3Pid);
            if ($e3Product && !empty($e3Product->username)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{id: int, username: string, public_id: string}|null
     */
    public static function defaultBackupUser(int $clientId): ?array
    {
        if ($clientId <= 0) {
            return null;
        }
        try {
            if (!Capsule::schema()->hasTable('s3_backup_users')) {
                return null;
            }
            $hasPublicId = Capsule::schema()->hasColumn('s3_backup_users', 'public_id');
            $cols = $hasPublicId ? ['id', 'username', 'public_id'] : ['id', 'username'];
            $row = Capsule::table('s3_backup_users')
                ->where('client_id', $clientId)
                ->orderBy('id', 'asc')
                ->first($cols);
            if (!$row) {
                return null;
            }

            return [
                'id' => (int) $row->id,
                'username' => (string) ($row->username ?? ''),
                'public_id' => $hasPublicId ? (string) ($row->public_id ?? '') : '',
            ];
        } catch (\Throwable $_) {
            return null;
        }
    }
}
