<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

use WHMCS\Database\Capsule;

/**
 * Welcome-page client detection for legacy Comet/OBC customers.
 */
class WelcomeClientState
{
    /**
     * True when the client has an active legacy Comet-backed backup service.
     */
    public static function clientHasLegacyCometBackup(int $clientId): bool
    {
        if ($clientId <= 0) {
            return false;
        }

        if (!function_exists('eazybackup_comet_product_ids')) {
            require_once dirname(__DIR__, 3) . '/eazybackup/functions.php';
        }

        $productIds = eazybackup_comet_product_ids();
        if ($productIds === []) {
            return false;
        }

        try {
            return Capsule::table('tblhosting')
                ->where('domainstatus', 'Active')
                ->where('userid', $clientId)
                ->whereIn('packageid', $productIds)
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * True when the client has active Comet/OBC backup and portal password is already set.
     */
    public static function isWelcomeExistingClient(int $clientId): bool
    {
        if (!self::clientHasLegacyCometBackup($clientId)) {
            return false;
        }

        if (!function_exists('eazybackup_must_set_password')) {
            require_once dirname(__DIR__, 3) . '/eazybackup/functions.php';
        }

        return !eazybackup_must_set_password($clientId);
    }
}
