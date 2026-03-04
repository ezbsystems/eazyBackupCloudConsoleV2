<?php

use WHMCS\Database\Capsule;

function eb_ph_client_view(array $vars)
{
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { header('Location: clientarea.php'); exit; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { header('Location: index.php?m=eazybackup&a=ph-clients'); exit; }
    $id = (int)($_GET['id'] ?? 0);
    $tenant = Capsule::table('eb_tenants')->where('id', $id)->where('msp_id', (int)$msp->id)->first();
    if (!$tenant) { header('Location: index.php?m=eazybackup&a=ph-clients'); exit; }

    // KPIs via cached tables (fallback to 0)
    try { $paid = (int) Capsule::table('eb_invoice_cache')->where('tenant_id', $tenant->id)->where('status','paid')->sum('amount_total'); }
    catch (\Throwable $__) { $paid = 0; }
    try { $unpaid = (int) Capsule::table('eb_invoice_cache')->where('tenant_id', $tenant->id)->whereIn('status',['open','uncollectible','draft'])->sum('amount_total'); }
    catch (\Throwable $__) { $unpaid = 0; }
    $refunded = 0; $cancelled = 0; $collections = 0;
    try { $gross = (int) Capsule::table('eb_invoice_cache')->where('tenant_id', $tenant->id)->sum('amount_total'); }
    catch (\Throwable $__) { $gross = 0; }
    $net = $gross;

    try { $subs = Capsule::table('eb_subscriptions')->where('tenant_id', $tenant->id)->orderBy('created_at','desc')->get(); }
    catch (\Throwable $__) { $subs = []; }
    try { $invoices = Capsule::table('eb_invoice_cache')->where('tenant_id', $tenant->id)->orderBy('created','desc')->limit(20)->get(); }
    catch (\Throwable $__) { $invoices = []; }
    try { $payments = Capsule::table('eb_payment_cache')->where('tenant_id', $tenant->id)->orderBy('created','desc')->limit(20)->get(); }
    catch (\Throwable $__) { $payments = []; }

    // Services: from eb_tenant_services (MSP's hosting rows linked to this tenant)
    $services = [];
    if (Capsule::schema()->hasTable('eb_tenant_services')) {
        try {
            $hostingIds = Capsule::table('eb_tenant_services')->where('tenant_id', $tenant->id)->whereNotNull('hosting_id')->pluck('hosting_id')->toArray();
            if (!empty($hostingIds)) {
                $services = Capsule::table('tblhosting')->whereIn('id', $hostingIds)->orderBy('id','desc')->limit(100)->get();
            }
        } catch (\Throwable $__) { /* ignore */ }
    }
    // Comet users linked to this tenant via eb_tenant_comet_accounts
    $cometUsers = [];
    if (Capsule::schema()->hasTable('eb_tenant_comet_accounts')) {
        try {
            $usernames = Capsule::table('eb_tenant_comet_accounts')->where('tenant_id', $tenant->id)->pluck('comet_user_id')->toArray();
            if (!empty($usernames) && Capsule::schema()->hasTable('comet_users')) {
                $cometUsers = Capsule::table('comet_users')->whereIn('username', $usernames)->orderBy('username','asc')->get(['username']);
            }
        } catch (\Throwable $__) { /* ignore */ }
    }

    $tenantArr = (array)$tenant;
    $wcArr = [];
    $subsArr = [];
    foreach ($subs as $r) { $subsArr[] = (array)$r; }
    $invoicesArr = [];
    foreach ($invoices as $r) { $invoicesArr[] = (array)$r; }
    $paymentsArr = [];
    foreach ($payments as $r) { $paymentsArr[] = (array)$r; }
    $servicesArr = [];
    foreach ($services as $r) { $servicesArr[] = (array)$r; }
    $cometUsersArr = [];
    foreach ($cometUsers as $r) { $cometUsersArr[] = (array)$r; }

    try {
        return [
            'pagetitle' => 'Tenant — ' . (string)$tenant->name,
            'templatefile' => 'whitelabel/client-view',
            'breadcrumb' => [ 'index.php?m=eazybackup' => 'eazyBackup', 'index.php?m=eazybackup&a=ph-clients' => 'Clients' ],
            'requirelogin' => true,
            'forcessl' => true,
            'vars' => [
                'modulelink' => $vars['modulelink'],
                'msp' => $msp,
                'tenant' => $tenantArr,
                'customer' => $tenantArr,
                'wc' => $wcArr,
                'kpis' => [
                    'paid' => $paid,
                    'unpaid' => $unpaid,
                    'cancelled' => $cancelled,
                    'refunded' => $refunded,
                    'collections' => $collections,
                    'gross' => $gross,
                    'net' => $net,
                ],
            'subscriptions' => $subsArr,
            'invoices' => $invoicesArr,
            'payments' => $paymentsArr,
            'services' => $servicesArr,
            'cometUsers' => $cometUsersArr,
            ],
        ];
    } catch (\Throwable $__) {
        // Failsafe: avoid 500 on template render
        return [
            'pagetitle' => 'Tenant',
            'templatefile' => 'whitelabel/client-view',
            'breadcrumb' => [ 'index.php?m=eazybackup' => 'eazyBackup', 'index.php?m=eazybackup&a=ph-clients' => 'Clients' ],
            'requirelogin' => true,
            'forcessl' => true,
            'vars' => [
                'modulelink' => $vars['modulelink'],
                'msp' => $msp,
                'tenant' => $tenantArr,
                'customer' => $tenantArr,
                'wc' => $wcArr,
                'kpis' => [ 'paid'=>0,'unpaid'=>0,'cancelled'=>0,'refunded'=>0,'collections'=>0,'gross'=>0,'net'=>0 ],
                'subscriptions' => [],
                'invoices' => [],
                'payments' => [],
                'services' => [],
                'cometUsers' => [],
            ],
        ];
    }
}


