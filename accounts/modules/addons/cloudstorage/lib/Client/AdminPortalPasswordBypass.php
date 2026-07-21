<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

use WHMCS\Database\Capsule;

require_once dirname(__DIR__) . '/Beta/BetaGate.php';

use WHMCS\Module\Addon\CloudStorage\Beta\BetaGate;

/**
 * Dev-only bypass for existing-client portal-password confirmation on the
 * Welcome page. Admins impersonating a client can enter a configured master
 * password instead of the customer's portal password.
 *
 * Only honoured when HTTP_HOST is in e3backup_beta_hosts and the addon
 * setting e3backup_admin_portal_bypass_password is non-empty.
 */
final class AdminPortalPasswordBypass
{
    public static function isEnabled(): bool
    {
        if (!BetaGate::isHostInBetaList()) {
            return false;
        }

        return self::configuredPassword() !== '';
    }

    public static function matchesMasterPassword(string $candidate): bool
    {
        if (!self::isEnabled() || $candidate === '') {
            return false;
        }

        return hash_equals(self::configuredPassword(), $candidate);
    }

    /**
     * Random backup-user password when the admin bypass is used (the master
     * password must never be stored as the backup user credential).
     */
    public static function generateBackupUserPassword(): string
    {
        return 'Eb!' . bin2hex(random_bytes(12));
    }

    private static function configuredPassword(): string
    {
        try {
            $val = Capsule::table('tbladdonmodules')
                ->where('module', 'cloudstorage')
                ->where('setting', 'e3backup_admin_portal_bypass_password')
                ->value('value');

            return is_string($val) ? trim($val) : '';
        } catch (\Throwable $e) {
            return '';
        }
    }
}
