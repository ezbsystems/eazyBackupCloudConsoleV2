<?php
// File: includes/hooks/redirectBasedOnProducts.php

use WHMCS\Database\Capsule;

add_hook('ClientAreaPage', 1, function($vars) {
    // Ensure the client is logged in.
    if (!isset($_SESSION['uid'])) {
        return;
    }

    // Only fire this hook when the URL is exactly '/clientarea.php'
    // This prevents the redirect from triggering on other pages.
    if ($_SERVER['REQUEST_URI'] !== '/clientarea.php') {
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
    if ($nonStorageFound) {
        $redirectUrl = '/index.php?m=eazybackup&a=dashboard';
    } else {
        $redirectUrl = '/index.php?m=cloudstorage&page=dashboard';
    }

    // Immediately redirect and stop further processing.
    header("Location: " . $redirectUrl);
    exit;
});
