<?php
// File: includes/hooks/redirectBasedOnProducts.php

use WHMCS\Database\Capsule;

add_hook('ClientAreaPage', 500, function($vars) {
    // Ensure the client is logged in.
    if (!isset($_SESSION['uid'])) {
        return;
    }

    // Only fire this hook when the URL is exactly '/clientarea.php'
    // This prevents the redirect from triggering on other pages.
    if (($_SERVER['REQUEST_URI'] ?? '') !== '/clientarea.php') {
        return;
    }

    $clientId = $_SESSION['uid'];
    $nonStorageFound = false;

    // Query active products for the client.
    $activeProducts = Capsule::table('tblhosting')
        ->where('userid', $clientId)
        ->where('domainstatus', 'Active')
        ->get();

    // Loop through each active product.
    foreach ($activeProducts as $product) {
        if ($product->packageid) {
            // Get the product group id (gid) from tblproducts.
            $groupId = Capsule::table('tblproducts')
                ->where('id', $product->packageid)
                ->value('gid');
            // If any active product is not in group 11, set the flag.
            if ($groupId != 11) {
                $nonStorageFound = true;
                break;
            }
        }
    }

    // Determine redirect URL based on the products.
    $redirectUrl = $nonStorageFound ? '/index.php?m=eazybackup&a=dashboard' : '/index.php?m=cloudstorage&page=dashboard';

    // Respect subaccount permission for eazybackup dashboard to avoid redirect loops
    if (strpos($redirectUrl, 'm=eazybackup') !== false) {
        try {
            // Resolve owner client id
            $ownerId = isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : 0;
            if (class_exists('WHMCS\\Authentication\\Auth')) {
                $c = \WHMCS\Authentication\Auth::client();
                if ($c && isset($c->id)) { $ownerId = (int) $c->id; }
            }
            // Resolve user id (tblusers) with fallbacks
            $currentUserId = 0;
            if (class_exists('WHMCS\\Authentication\\Auth') && method_exists('WHMCS\\Authentication\\Auth','user')) {
                $u = \WHMCS\Authentication\Auth::user();
                if ($u && isset($u->id)) { $currentUserId = (int)$u->id; }
            }
            if ($currentUserId <= 0) {
                if (isset($_SESSION['contactid']) && (int)$_SESSION['contactid']>0) { $currentUserId = (int)$_SESSION['contactid']; }
                else if (isset($_SESSION['cid']) && (int)$_SESSION['cid']>0) { $currentUserId = (int)$_SESSION['cid']; }
            }
            if ($currentUserId <= 0) {
                try {
                    $loginToken = isset($_SESSION['login_auth_tk']) ? (string) $_SESSION['login_auth_tk'] : (isset($_COOKIE['login_auth_tk']) ? (string) $_COOKIE['login_auth_tk'] : '');
                    if ($loginToken !== '') {
                        $sess = Capsule::table('tblusers_sessions')->where('token', $loginToken)->orWhere('session_id',$loginToken)->orWhere('remember_me_token',$loginToken)->first();
                        if ($sess && isset($sess->user_id)) { $currentUserId = (int) $sess->user_id; }
                    }
                } catch (\Throwable $ignored) {}
            }
            if ($currentUserId <= 0) {
                try {
                    $tkval = isset($_COOKIE['tkval']) ? (string) $_COOKIE['tkval'] : (isset($_SESSION['tkval']) ? (string) $_SESSION['tkval'] : '');
                    if ($tkval !== '') {
                        $sess = Capsule::table('tblusers_sessions')->where('token', $tkval)->orWhere('session_id',$tkval)->orWhere('remember_me_token',$tkval)->first();
                        if ($sess && isset($sess->user_id)) { $currentUserId = (int) $sess->user_id; }
                    }
                } catch (\Throwable $ignored) {}
            }
            // Owner bypass; treat missing link as owner
            $isOwner = true;
            try {
                $link = Capsule::table('tblusers_clients')->where('clientid',$ownerId)->where('userid',$currentUserId)->first();
                $isOwner = (!$link || (isset($link->owner) && (int)$link->owner===1));
            } catch (\Throwable $ignored) { $isOwner = true; }
            if (!$isOwner) {
                $perm = Capsule::table('eazybackup_user_permissions')->where('userid', $ownerId)->where('subaccountid', $currentUserId)->first();
                if ($perm && (int)$perm->can_access_eazybackup === 0) {
                    $redirectUrl = '/index.php?m=eazybackup&a=denied&msg=' . urlencode('You do not have permission to access the eazyBackup dashboard.');
                }
            }
        } catch (\Throwable $ignored) {}
    }
    header('Location: ' . $redirectUrl);
    exit;
});
