<?php

namespace WHMCS\Module\Addon\CloudStorage\Beta;

use WHMCS\Database\Capsule;

/**
 * Layered feature flag for the new e3 Cloud Backup product card on the
 * Welcome page (and any other consumer that needs gated visibility).
 *
 * Decision order:
 *   1. Host allowlist  - HTTP_HOST is in addon setting e3backup_beta_hosts
 *   2. Client allowlist - WHMCS client_id is in addon setting e3backup_beta_client_ids
 *   3. Client custom field "Beta tester" = 1 or "yes"
 *   4. Admin URL override - ?eb_beta=1 AND the request is from a WHMCS admin
 *      OR an SSO-impersonation session, AND e3backup_beta_admin_override=yes
 *
 * Each layer can shortcircuit. Default: hidden on prod, visible on dev.
 */
class BetaGate
{
    /**
     * Top-level check used by the Welcome card.
     */
    public static function isE3BackupVisible(?int $clientId = null): bool
    {
        if (self::isHostInBetaList()) {
            return true;
        }
        if ($clientId !== null && self::isClientInAllowlist($clientId)) {
            return true;
        }
        if ($clientId !== null && self::clientHasBetaCustomField($clientId)) {
            return true;
        }
        if (self::isAdminUrlOverride()) {
            return true;
        }
        return false;
    }

    public static function isHostInBetaList(): bool
    {
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host === '') {
            return false;
        }
        $raw = (string) self::getSetting('e3backup_beta_hosts', 'dev.eazybackup.ca');
        $hosts = array_filter(array_map('trim', preg_split('/[,\s]+/', strtolower($raw))));
        return in_array($host, $hosts, true);
    }

    public static function isClientInAllowlist(int $clientId): bool
    {
        $raw = (string) self::getSetting('e3backup_beta_client_ids', '');
        if ($raw === '' || $clientId <= 0) {
            return false;
        }
        $ids = array_filter(array_map('intval', preg_split('/[,\s]+/', $raw)));
        return in_array($clientId, $ids, true);
    }

    public static function clientHasBetaCustomField(int $clientId): bool
    {
        if ($clientId <= 0) {
            return false;
        }
        try {
            $fieldId = (int) Capsule::table('tblcustomfields')
                ->where('type', 'client')
                ->where('fieldname', 'Beta tester')
                ->value('id');
            if ($fieldId <= 0) {
                return false;
            }
            $val = (string) Capsule::table('tblcustomfieldsvalues')
                ->where('fieldid', $fieldId)
                ->where('relid', $clientId)
                ->value('value');
            $val = strtolower(trim($val));
            return in_array($val, ['1', 'yes', 'true', 'on'], true);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Honour ?eb_beta=1 when the requester is an admin or an SSO-impersonation
     * session, AND the addon setting allows admin overrides (default yes).
     */
    public static function isAdminUrlOverride(): bool
    {
        $param = (string) ($_GET['eb_beta'] ?? '');
        if ($param !== '1') {
            return false;
        }
        $allowed = (string) self::getSetting('e3backup_beta_admin_override', 'on');
        if (!in_array(strtolower($allowed), ['on', 'yes', '1', 'true'], true)) {
            return false;
        }
        // Admin session ID is present whenever a WHMCS staff member is logged
        // in (or impersonating). Different versions name it differently, so
        // we check both.
        try {
            if (!empty($_SESSION['adminid'])) {
                return true;
            }
            if (!empty($_SESSION['adm_unique_token'])) {
                return true;
            }
        } catch (\Throwable $e) {
        }
        return false;
    }

    /**
     * Should `trial_skip_verification_emails` actually be honoured for this
     * request? Only when the host is in the beta list (i.e. dev environment).
     */
    public static function skipVerificationFor(string $email): bool
    {
        if (!self::isHostInBetaList()) {
            return false;
        }
        $raw = (string) self::getSetting('trial_skip_verification_emails', '');
        if ($raw === '' || $email === '') {
            return false;
        }
        $list = array_filter(array_map(function ($v) {
            return strtolower(trim($v));
        }, preg_split('/[,\s]+/', $raw)));
        return in_array(strtolower(trim($email)), $list, true);
    }

    private static function getSetting(string $key, $default = null)
    {
        try {
            $val = Capsule::table('tbladdonmodules')
                ->where('module', 'cloudstorage')
                ->where('setting', $key)
                ->value('value');
            return ($val !== null && $val !== '') ? $val : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }
}
