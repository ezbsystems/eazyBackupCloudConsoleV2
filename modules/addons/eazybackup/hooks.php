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

/**
 * Provide branding-aware download variables for the theme header (flyout + modals)
 * Exposes: {$eb_brand_download.base}, {$eb_brand_download.base_urlenc}, {$eb_brand_download.productName}, {$eb_brand_download.accent}, {$eb_brand_download.isBranded}
 */
add_hook('ClientAreaPage', 1, function ($vars) {
    try {
        $clientId = (int)($_SESSION['uid'] ?? 0);
        $out = [
            'base' => 'https://panel.obcbackup.com/',
            'base_urlenc' => rawurlencode('https://panel.obcbackup.com/'),
            'productName' => 'OBC Branded Client',
            'accent' => '#4f46e5', // indigo-600
            'isBranded' => 0,
        ];
        if ($clientId > 0) {
            $row = Capsule::table('eb_whitelabel_tenants')
                ->where('client_id', $clientId)
                ->where('status', 'active')
                ->orderBy('updated_at', 'desc')
                ->first();
            if ($row) {
                $brand = json_decode((string)($row->brand_json ?? '{}'), true) ?: [];
                $product = (string)($brand['ProductName'] ?? $brand['BrandName'] ?? '');
                $accent = (string)($brand['AccentColor'] ?? '#4f46e5');
                $host = '';
                $cd = (string)($row->custom_domain ?? '');
                $cdStatus = (string)($row->custom_domain_status ?? '');
                if ($cd !== '' && in_array($cdStatus, ['verified','org_updated','cert_ok','dns_ok'], true)) { $host = $cd; }
                if ($host === '') { $host = (string)$row->fqdn; }
                if ($host !== '') {
                    $base = 'https://' . $host . '/';
                    $out['base'] = $base;
                    $out['base_urlenc'] = rawurlencode($base);
                }
                if ($product !== '') { $out['productName'] = $product; }
                if ($accent !== '') { $out['accent'] = $accent; }
                $out['isBranded'] = 1;
            }
        }
        return ['eb_brand_download' => $out];
    } catch (\Throwable $_) { return []; }
});






