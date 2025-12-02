<?php
use WHMCS\Database\Capsule;

/**
 * Enforce onboarding: keep user on Welcome page until password/provision step completes.
 * Priority 3 so TOS gate (priority 2) executes first.
 */
add_hook('ClientAreaPage', 3, function ($vars) {
    $clientId = (int)($_SESSION['uid'] ?? 0);
    if ($clientId <= 0) {
        return;
    }

    $uri = (string)($_SERVER['REQUEST_URI'] ?? '');

    // Whitelist: welcome page, onboarding APIs, auth pages, TOS flow
    $isWelcomePage = (isset($_GET['m']) && $_GET['m'] === 'cloudstorage' && (isset($_GET['page']) ? $_GET['page'] : '') === 'welcome');
    $isWelcomeApi  = (strpos($uri, 'modules/addons/cloudstorage/api/') !== false);
    $isAuthRoute   = (strpos($uri, 'logout.php') !== false) || (strpos($uri, 'pwreset') !== false);
    $isTosRoute    = (isset($_GET['m']) && $_GET['m'] === 'eazybackup' && isset($_GET['a']) && strpos($_GET['a'], 'tos-') === 0);
    if ($isWelcomePage || $isWelcomeApi || $isAuthRoute || $isTosRoute) {
        return;
    }

    // Check onboarding flag
    $mustSet = false;
    try {
        $mustSet = Capsule::table('eb_password_onboarding')
            ->where('client_id', $clientId)
            ->where('must_set', 1)
            ->exists();
    } catch (\Throwable $e) {
        $mustSet = false; // fail open
    }

    if ($mustSet) {
        header('Location: index.php?m=cloudstorage&page=welcome');
        exit;
    }
});


