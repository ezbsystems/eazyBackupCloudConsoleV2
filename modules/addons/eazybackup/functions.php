<?php
/**
 * eazyBackup Addon Module Functions
 *
 * @copyright (c) 2019 eazyBackup Systems Ltd.
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

// Require any libraries needed for the module to function.
require_once __DIR__ . "/../../servers/comet/functions.php";

// Also, perform any initialization required by the service's library.

/**
 * Determine whether the given client has accepted the currently active TOS
 * version that requires acceptance.
 *
 * Mirrors the logic used in the ClientAreaPage TOS gating hook, but returns
 * a simple boolean instead of performing redirects.
 *
 * @param int      $clientId
 * @param int|null $contactId
 * @return bool
 */
function eazybackup_has_accepted_current_tos(int $clientId, ?int $contactId = null): bool
{
    try {
        $active = \WHMCS\Database\Capsule::table('eb_tos_versions')
            ->where('is_active', 1)
            ->orderBy('published_at', 'desc')
            ->first();

        if (!$active || (int) $active->require_acceptance !== 1) {
            // No active TOS requiring acceptance → treat as accepted for gating purposes
            return true;
        }

        $version = (string) $active->version;

        $q = \WHMCS\Database\Capsule::table('eb_tos_user_acceptances')
            ->where('client_id', $clientId)
            ->where('tos_version', $version);

        if ($contactId && $contactId > 0) {
            $q->where('contact_id', $contactId);
        } else {
            $q->whereNull('user_id')->whereNull('contact_id');
        }

        return (bool) $q->exists();
    } catch (\Throwable $e) {
        // Fail open on error to avoid blocking logins
        return true;
    }
}

/**
 * Mark a client as requiring password setup on next client-area visit.
 *
 * @param int $clientId
 * @return void
 */
function eazybackup_mark_must_set_password(int $clientId): void
{
    if ($clientId <= 0) {
        return;
    }

    try {
        $row = \WHMCS\Database\Capsule::table('eb_password_onboarding')
            ->where('client_id', $clientId)
            ->first();

        $data = [
            'must_set'     => 1,
            'created_at'   => date('Y-m-d H:i:s'),
            'completed_at' => null,
        ];

        if ($row) {
            \WHMCS\Database\Capsule::table('eb_password_onboarding')
                ->where('client_id', $clientId)
                ->update($data);
        } else {
            $data['client_id'] = $clientId;
            \WHMCS\Database\Capsule::table('eb_password_onboarding')
                ->insert($data);
        }
    } catch (\Throwable $e) {
        // Swallow errors – onboarding is a UX enhancement, not critical path
    }
}

/**
 * Check whether a client must currently set a password.
 *
 * @param int $clientId
 * @return bool
 */
function eazybackup_must_set_password(int $clientId): bool
{
    if ($clientId <= 0) {
        return false;
    }

    try {
        return \WHMCS\Database\Capsule::table('eb_password_onboarding')
            ->where('client_id', $clientId)
            ->where('must_set', 1)
            ->exists();
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * Clear the must-set-password flag for a client and mark completion time.
 *
 * @param int $clientId
 * @return void
 */
function eazybackup_clear_must_set_password(int $clientId): void
{
    if ($clientId <= 0) {
        return;
    }

    try {
        \WHMCS\Database\Capsule::table('eb_password_onboarding')
            ->where('client_id', $clientId)
            ->update([
                'must_set'     => 0,
                'completed_at' => date('Y-m-d H:i:s'),
            ]);
    } catch (\Throwable $e) {
        // Non-fatal
    }
}

