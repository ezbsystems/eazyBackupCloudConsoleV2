<?php

use WHMCS\Database\Capsule;

function eb_ph_client_view(array $vars)
{
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { header('Location: clientarea.php'); exit; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { header('Location: index.php?m=eazybackup&a=ph-clients'); exit; }
    $id = (int)($_GET['id'] ?? 0);
    $cust = Capsule::table('eb_customers')->where('id',$id)->where('msp_id',(int)$msp->id)->first();
    if (!$cust) { header('Location: index.php?m=eazybackup&a=ph-clients'); exit; }

    // WHMCS client profile (for Summary/Profile tabs)
    try { $wc = Capsule::table('tblclients')->where('id', (int)$cust->whmcs_client_id)->first(); }
    catch (\Throwable $__) { $wc = null; }

    // KPIs via cached tables (fallback to 0)
    try { $paid = (int) Capsule::table('eb_invoice_cache')->where('customer_id',$cust->id)->where('status','paid')->sum('amount_total'); }
    catch (\Throwable $__) { $paid = 0; }
    try { $unpaid = (int) Capsule::table('eb_invoice_cache')->where('customer_id',$cust->id)->whereIn('status',['open','uncollectible','draft'])->sum('amount_total'); }
    catch (\Throwable $__) { $unpaid = 0; }
    $refunded = 0; $cancelled = 0; $collections = 0; // placeholders; can be derived from payments/charges
    try { $gross = (int) Capsule::table('eb_invoice_cache')->where('customer_id',$cust->id)->sum('amount_total'); }
    catch (\Throwable $__) { $gross = 0; }
    $net = $gross; // application fees not subtracted here (platform accounting)

    try { $subs = Capsule::table('eb_subscriptions')->where('customer_id',$cust->id)->orderBy('created_at','desc')->get(); }
    catch (\Throwable $__) { $subs = []; }
    try { $invoices = Capsule::table('eb_invoice_cache')->where('customer_id',$cust->id)->orderBy('created','desc')->limit(20)->get(); }
    catch (\Throwable $__) { $invoices = []; }
    try { $payments = Capsule::table('eb_payment_cache')->where('customer_id',$cust->id)->orderBy('created','desc')->limit(20)->get(); }
    catch (\Throwable $__) { $payments = []; }

    // Services tab data: list candidate services (tblhosting) and Comet users
    try { $services = Capsule::table('tblhosting')->where('userid',(int)$cust->whmcs_client_id)->orderBy('id','desc')->limit(100)->get(); }
    catch (\Throwable $__) { $services = []; }
    try { $cometUsers = Capsule::table('comet_users')->where('client_id',(int)$cust->whmcs_client_id)->orderBy('username','asc')->get(['username']); }
    catch (\Throwable $__) { $cometUsers = []; }

    // Normalize all data structures to arrays for Smarty
    $customerArr = (array)$cust;
    $wcArr = $wc ? (array)$wc : [];
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
            'pagetitle' => 'Customer — ' . (string)$cust->name,
            'templatefile' => 'whitelabel/client-view',
            'breadcrumb' => [ 'index.php?m=eazybackup' => 'eazyBackup', 'index.php?m=eazybackup&a=ph-clients' => 'Clients' ],
            'requirelogin' => true,
            'forcessl' => true,
            'vars' => [
                'modulelink' => $vars['modulelink'],
            'msp' => $msp,
            'customer' => $customerArr,
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
            'pagetitle' => 'Customer',
            'templatefile' => 'whitelabel/client-view',
            'breadcrumb' => [ 'index.php?m=eazybackup' => 'eazyBackup', 'index.php?m=eazybackup&a=ph-clients' => 'Clients' ],
            'requirelogin' => true,
            'forcessl' => true,
            'vars' => [
                'modulelink' => $vars['modulelink'],
                'msp' => $msp,
                'customer' => $customerArr,
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


