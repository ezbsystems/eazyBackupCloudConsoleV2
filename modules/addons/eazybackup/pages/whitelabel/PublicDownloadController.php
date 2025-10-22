<?php

use WHMCS\Database\Capsule;

/**
 * Public Download Controller (GET only)
 */
function eazybackup_public_download(array $vars)
{
    if (!(int)($vars['PARTNER_HUB_SIGNUP_ENABLED'] ?? 0)) {
        return [ 'pagetitle'=>'Signup Unavailable', 'templatefile'=>'templates/whitelabel/public-invalid-host', 'vars'=>['reason'=>'disabled'] ];
    }
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $tenant = null;
    try {
        $row = Capsule::table('eb_whitelabel_signup_domains')->where('hostname',$host)->where('status','verified')->first();
        if ($row) { $tenant = Capsule::table('eb_whitelabel_tenants')->where('id',(int)$row->tenant_id)->first(); }
    } catch (\Throwable $__) {}
    if (!$tenant) {
        return [ 'pagetitle'=>'Invalid Signup URL', 'templatefile'=>'templates/whitelabel/public-invalid-host', 'vars'=>['reason'=>'invalid_host'] ];
    }

    // Build base download URL (prefer tenant vanity fqdn)
    $base = 'https://' . (string)($tenant->fqdn ?? $host) . '/';
    $links = [
        'win_any'   => $base . 'dl/1',
        'win_x64'   => $base . 'dl/5',
        'win_x86'   => $base . 'dl/3',
        'linux_deb' => $base . 'dl/21',
        'linux_tgz' => $base . 'dl/7',
        'mac_x64'   => $base . 'dl/8',
        'mac_arm'   => $base . 'dl/20',
        'syn_dsm6'  => $base . 'dl/18',
        'syn_dsm7'  => $base . 'dl/19',
    ];
    return [
        'pagetitle' => 'Download client software',
        'templatefile' => 'templates/whitelabel/public-download',
        'forcessl' => true,
        'vars' => [
            'tenant' => (array)$tenant,
            'host' => $host,
            'base' => $base,
            'dl' => $links,
        ],
    ];
}


