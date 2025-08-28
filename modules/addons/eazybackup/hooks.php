<?php

require_once __DIR__ . "/functions.php";

use WHMCS\Database\Capsule;
use WHMCS\Config\Setting;

add_hook("EmailPreSend", 1, function ($vars) {
    $email_template_name = $vars['messagename'];
    $relid = $vars['relid'];
    $merge_fields = [];

    // Checking for certain template name, if so - this is our case
    if ($email_template_name == "Invoice Created" || $email_template_name == "Invoice Payment Confirmation") {
        // Getting total of the invoice
        $result = Capsule::table('tblinvoices')->where('id', $relid)->value('total');
        
        // If it is equal to '0.00' we disable email sending
        if ($result == '0.00') {
            $merge_fields['abortsend'] = true;
        }
    }
    return $merge_fields;
});

/**
 * Hides domain permissions from the Contacts/Sub-Accounts page.
 */
add_hook('ClientAreaPageContacts', 1, function ($vars) {
    $permissionsToDrop = [
        "managedomains", "productsso", "domains", "quotes", "affiliates"
    ];

    foreach ($vars["allPermissions"] as $i => $permission) {
        if (in_array($permission, $permissionsToDrop)) {
            unset($vars["allPermissions"][$i]);
        }
    }

    return $vars;
});

/**
 * Adds additional service information to submit ticket page.
 */
add_hook('ClientAreaPageSubmitTicket', 1, function ($vars) {
    $clientsproducts = localAPI('GetClientsProducts', ['clientid' => $vars['client']->id])['products']['product'];
    $relatedservices = [];

    foreach ($clientsproducts as $product) {
        $username = empty($product['domain']) ? $product['username'] : $product['domain'];
        $relatedservices[$product['groupname']][] = [
            'id' => 'S' . $product['id'],
            'name' => $product['name'],
            'username' => $username,
            'status' => $product['status'],
        ];
    }

    $vars['relatedservices'] = $relatedservices;
    return $vars;
});

/**
 * Helper: build a versioned <script> or <link> tag for a module asset.
 * $relPath should start with /modules/...
 */
function eazybackup_asset_tag(string $relPath, string $type = 'script'): string
{
    // Absolute base URL (handles http/https + path)
    $webRoot = rtrim(Setting::getValue('SystemURL'), '/');
    // Filesystem path so we can cache-bust with filemtime
    $fsPath  = rtrim(ROOTDIR, '/') . $relPath;
    $ver     = is_readable($fsPath) ? (int) @filemtime($fsPath) : time();

    if ($type === 'style') {
        return sprintf('<link rel="stylesheet" href="%s%s?v=%d">', $webRoot, $relPath, $ver);
    }
    // default: script
    return sprintf('<script defer src="%s%s?v=%d"></script>', $webRoot, $relPath, $ver);
}

/**
 * Asset injection for the eazybackup addon.
 * Loads the email reporting component script where needed.
 */
add_hook('ClientAreaHeadOutput', 1, function ($vars) {
    // Only when we are viewing the eazybackup addon pages
    if (!isset($_GET['m']) || $_GET['m'] !== 'eazybackup') {
        return '';
    }    
    $action   = $_GET['a'] ?? '';
    $allowedA = ['user-profile']; // e.g. add 'protected-items', 'storage-vaults' as needed
    if ($action && !in_array($action, $allowedA, true)) {
        return '';
    }
    $tags = [];    
    $tags[] = eazybackup_asset_tag('/modules/addons/eazybackup/assets/js/email-reports.js', 'script');
    return implode("\n", $tags);
});






