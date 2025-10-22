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
 * Legacy: Hiding domain permissions on Contacts/Sub-Accounts page.
 * Disabled for custom client-area theme/nav; keep hook returning unchanged vars.
 */
add_hook('ClientAreaPageContacts', 1, function ($vars) {
    return $vars; // no-op
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

/**
 * Provide Partner Hub navigation variables to the theme header
 * Exposes: {$eb_partner_hub_enabled}, {$eb_partner_hub_links}
 */
add_hook('ClientAreaPage', 1, function ($vars) {
    try {
        $enabled = false;
        $links = [];
        // Feature flag from addon setting
        $flag = '';
        try {
            $flag = (string)(Capsule::table('tbladdonmodules')
                ->where('module','eazybackup')
                ->where('setting','PARTNER_HUB_SIGNUP_ENABLED')
                ->value('value') ?? '');
        } catch (\Throwable $__) { $flag = ''; }
        $enabled = (strtolower($flag) === 'on' || strtolower($flag) === 'yes' || $flag === '1');

        // Gate by reseller membership and tenant presence
        $clientId = (int)($_SESSION['uid'] ?? 0);
        $isReseller = false;
        if ($clientId > 0) {
            try {
                $resellerGroupsSetting = (string)(Capsule::table('tbladdonmodules')
                    ->where('module','eazybackup')
                    ->where('setting','resellergroups')
                    ->value('value') ?? '');
                if ($resellerGroupsSetting !== '') {
                    $gid = (int)(Capsule::table('tblclients')->where('id', $clientId)->value('groupid') ?? 0);
                    if ($gid > 0) {
                        $ids = array_map('intval', array_filter(array_map('trim', explode(',', $resellerGroupsSetting))));
                        $isReseller = in_array($gid, $ids, true);
                    }
                }
            } catch (\Throwable $__) { $isReseller = false; }
        }

        // Tenant context and signup domain
        $tenant = null;
        $signupHost = '';
        if ($clientId > 0) {
            try {
                $tenant = Capsule::table('eb_whitelabel_tenants')
                    ->where('client_id', $clientId)
                    ->where('status','active')
                    ->orderBy('updated_at','desc')
                    ->first();
                if ($tenant) {
                    $dom = Capsule::table('eb_whitelabel_signup_domains')
                        ->where('tenant_id', (int)$tenant->id)
                        ->whereIn('status', ['verified','cert_ok'])
                        ->orderBy('updated_at','desc')
                        ->value('hostname');
                    if (is_string($dom) && $dom !== '') { $signupHost = $dom; }
                }
            } catch (\Throwable $__) { $tenant = null; }
        }

        // If either feature flag off or no reseller, disable
        if (!($enabled && $isReseller)) {
            return [ 'eb_partner_hub_enabled' => false, 'eb_partner_hub_links' => [] ];
        }

        // Build links (intake, branding, futures)
        $m = $_GET['m'] ?? '';
        $a = $_GET['a'] ?? '';
        // $links[] = [ 'label' => 'White Label Intake', 'href' => 'index.php?m=eazybackup&a=whitelabel', 'isActive' => ($m==='eazybackup' && $a==='whitelabel'), 'external' => 0 ];
        $links[] = [ 'label' => 'White-Label Tenants', 'href' => 'index.php?m=eazybackup&a=whitelabel-branding', 'isActive' => ($m==='eazybackup' && $a==='whitelabel-branding'), 'external' => 0 ];
        // Future placeholders (not active yet)
        // $links[] = [ 'label' => 'Signups (Events)', 'href' => 'index.php?m=eazybackup&a=whitelabel-signups', 'isActive' => ($m==='eazybackup' && $a==='whitelabel-signups'), 'external' => 0 ];
        // $links[] = [ 'label' => 'Domains', 'href' => 'index.php?m=eazybackup&a=whitelabel-domains', 'isActive' => ($m==='eazybackup' && $a==='whitelabel-domains'), 'external' => 0 ];
        // $links[] = [ 'label' => 'Emails', 'href' => 'index.php?m=eazybackup&a=whitelabel-emails', 'isActive' => ($m==='eazybackup' && $a==='whitelabel-emails'), 'external' => 0 ];
        if ($signupHost !== '') {
            $links[] = [ 'label' => 'View Signup Page', 'href' => 'https://' . $signupHost . '/', 'isActive' => false, 'external' => 1 ];
            $links[] = [ 'label' => 'View Download Page', 'href' => 'https://' . $signupHost . '/download', 'isActive' => false, 'external' => 1 ];
        }

        return [ 'eb_partner_hub_enabled' => true, 'eb_partner_hub_links' => $links ];
    } catch (\Throwable $_) {
        return [ 'eb_partner_hub_enabled' => false, 'eb_partner_hub_links' => [] ];
    }
});






