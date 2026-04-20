<?php
use WHMCS\Database\Capsule;

/**
 * MSP Onboarding gate.
 *
 * Clients flagged in `eb_msp_onboarding.must_complete = 1` (set by the public
 * reseller signup handler in modules/addons/eazybackup/eazybackup.php) are held
 * on the guided onboarding flow (welcome tour + terms acceptance) until they
 * finish it. Runs at priority 1 so it fires before the global TOS gate
 * (priority 2) and the password-onboarding gate (priority 3).
 */
add_hook('ClientAreaPage', 1, function ($vars) {
    $clientId = (int)($_SESSION['uid'] ?? 0);
    if ($clientId <= 0) {
        return;
    }

    $uri = (string)($_SERVER['REQUEST_URI'] ?? '');

    // Whitelist: onboarding pages themselves, legal viewers, auth flows.
    $m = isset($_GET['m']) ? (string)$_GET['m'] : '';
    $a = isset($_GET['a']) ? (string)$_GET['a'] : '';
    $isOnboardingRoute = ($m === 'eazybackup' && in_array($a, [
        'msp-onboarding',
        'msp-onboarding-accept',
        'msp-welcome',
        'tos-view',
        'privacy-view',
    ], true));
    $isAuthRoute = (strpos($uri, 'logout.php') !== false) || (strpos($uri, 'pwreset') !== false);
    if ($isOnboardingRoute || $isAuthRoute) {
        return;
    }

    $mustComplete = false;
    try {
        $mustComplete = Capsule::table('eb_msp_onboarding')
            ->where('client_id', $clientId)
            ->where('must_complete', 1)
            ->exists();
    } catch (\Throwable $e) {
        // Fail open so a DB hiccup never locks the client area.
        $mustComplete = false;
    }

    if ($mustComplete) {
        header('Location: index.php?m=eazybackup&a=msp-onboarding');
        exit;
    }
});
