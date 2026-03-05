<?php

use WHMCS\Database\Capsule;
use PartnerHub\StripeService;

function eb_ph_overview_index(array $vars)
{
    require_once __DIR__ . '/TenantsController.php';
    [$clientId, $msp] = eb_ph_tenants_require_context($vars);

    $mspId = (int)$msp->id;
    $baseLink = (string)($vars['modulelink'] ?? 'index.php?m=eazybackup');

    // --- DB Counts ---
    $tenantCount = 0;
    $productCount = 0;
    $planCount = 0;
    $activeSubCount = 0;
    $pendingApprovalCount = 0;
    $wlTenantCount = 0;

    try {
        if (Capsule::schema()->hasTable('eb_tenants')) {
            $tenantCount = (int)Capsule::table('eb_tenants')
                ->where('msp_id', $mspId)
                ->where('status', '!=', 'deleted')
                ->count();
        }
    } catch (\Throwable $__) {}

    try {
        if (Capsule::schema()->hasTable('eb_catalog_products')) {
            $productCount = (int)Capsule::table('eb_catalog_products')
                ->where('msp_id', $mspId)
                ->count();
        }
    } catch (\Throwable $__) {}

    try {
        if (Capsule::schema()->hasTable('eb_plans')) {
            $planCount = (int)Capsule::table('eb_plans')
                ->where('msp_id', $mspId)
                ->count();
        }
    } catch (\Throwable $__) {}

    try {
        if (Capsule::schema()->hasTable('eb_subscriptions')) {
            $activeSubCount = (int)Capsule::table('eb_subscriptions')
                ->where('msp_id', $mspId)
                ->whereIn('stripe_status', ['active', 'trialing'])
                ->count();
        }
    } catch (\Throwable $__) {}

    try {
        if (Capsule::schema()->hasTable('eb_whitelabel_signup_events')
            && Capsule::schema()->hasTable('eb_whitelabel_tenants')) {
            $pendingApprovalCount = (int)Capsule::table('eb_whitelabel_signup_events as e')
                ->join('eb_whitelabel_tenants as t', 't.id', '=', 'e.tenant_id')
                ->where('t.client_id', $clientId)
                ->where('e.status', 'pending_approval')
                ->count();
        }
    } catch (\Throwable $__) {}

    try {
        if (Capsule::schema()->hasTable('eb_whitelabel_tenants')) {
            $wlTenantCount = (int)Capsule::table('eb_whitelabel_tenants')
                ->where('client_id', $clientId)
                ->whereNotIn('status', ['removing', 'removed'])
                ->count();
        }
    } catch (\Throwable $__) {}

    // --- Stripe Connect Status ---
    $connect = [
        'hasAccount' => false,
        'chargesEnabled' => false,
        'payoutsEnabled' => false,
        'detailsSubmitted' => false,
    ];
    $connectDue = [];
    $connectIdMasked = '';
    $stripeConnectId = '';
    $stripeCurrency = 'usd';
    $svc = null;

    try {
        $stripeConnectId = (string)($msp->stripe_connect_id ?? '');
        if ($stripeConnectId !== '') {
            $svc = new StripeService();
            $acct = $svc->retrieveAccount($stripeConnectId);
            $connect = [
                'hasAccount' => true,
                'chargesEnabled' => (bool)($acct['charges_enabled'] ?? false),
                'payoutsEnabled' => (bool)($acct['payouts_enabled'] ?? false),
                'detailsSubmitted' => (bool)($acct['details_submitted'] ?? false),
            ];
            $reqs = $acct['requirements'] ?? [];
            if (is_array($reqs) && isset($reqs['currently_due']) && is_array($reqs['currently_due'])) {
                $connectDue = $reqs['currently_due'];
            }
            $connectIdMasked = strlen($stripeConnectId) > 8
                ? substr($stripeConnectId, 0, 5) . '...' . substr($stripeConnectId, -4)
                : $stripeConnectId;
            $stripeCurrency = (string)($acct['default_currency'] ?? 'usd');
        }
    } catch (\Throwable $__) {}

    // --- Live Billing Snapshot ---
    $billing = null;
    $billingUnavailable = false;

    if ($connect['chargesEnabled'] && $stripeConnectId !== '') {
        try {
            $svc = $svc ?? new StripeService();
            $monthStart = (int)strtotime(date('Y-m-01 00:00:00'));

            $invResponse = $svc->listInvoicesForAccount($stripeConnectId, [
                'created[gte]' => $monthStart,
                'limit' => 100,
            ]);
            $invoices = $invResponse['data'] ?? [];

            $revenueThisMonth = 0;
            $invoiceCountThisMonth = count($invoices);
            foreach ($invoices as $inv) {
                if (($inv['status'] ?? '') === 'paid') {
                    $revenueThisMonth += (int)($inv['amount_paid'] ?? 0);
                }
            }

            $openResponse = $svc->listInvoicesForAccount($stripeConnectId, [
                'status' => 'open',
                'limit' => 100,
            ]);
            $outstandingInvoices = count($openResponse['data'] ?? []);

            $chargeResponse = $svc->listChargesForAccount($stripeConnectId, [
                'created[gte]' => $monthStart,
                'limit' => 100,
            ]);
            $failedPayments = 0;
            foreach (($chargeResponse['data'] ?? []) as $ch) {
                if (($ch['status'] ?? '') === 'failed') {
                    $failedPayments++;
                }
            }

            $billing = [
                'revenue_this_month' => $revenueThisMonth,
                'invoices_this_month' => $invoiceCountThisMonth,
                'outstanding_invoices' => $outstandingInvoices,
                'failed_payments' => $failedPayments,
                'currency' => strtoupper($stripeCurrency),
            ];
        } catch (\Throwable $__) {
            $billingUnavailable = true;
        }
    }

    // --- Recent Tenants ---
    $recentTenants = [];
    try {
        if (Capsule::schema()->hasTable('eb_tenants')) {
            $rows = Capsule::table('eb_tenants')
                ->where('msp_id', $mspId)
                ->where('status', '!=', 'deleted')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(['id', 'name', 'slug', 'contact_email', 'status', 'created_at']);
            foreach ($rows as $row) {
                $recentTenants[] = (array)$row;
            }
        }
    } catch (\Throwable $__) {}

    // --- Setup Wizard ---
    $stripeConnected = $connect['chargesEnabled'];
    $setup = [
        'stripe_connected' => $stripeConnected,
        'has_products' => $productCount > 0,
        'has_plans' => $planCount > 0,
        'has_tenants' => $tenantCount > 0,
        'has_subscriptions' => $activeSubCount > 0,
    ];
    $setup['all_complete'] = $setup['stripe_connected']
        && $setup['has_products']
        && $setup['has_plans']
        && $setup['has_tenants']
        && $setup['has_subscriptions'];

    // --- Onboard flags ---
    $onboardError = isset($_GET['onboard_error']) && $_GET['onboard_error'] !== '';
    $onboardSuccess = isset($_GET['onboard_success']) && $_GET['onboard_success'] !== '';
    $onboardRefresh = isset($_GET['onboard_refresh']) && $_GET['onboard_refresh'] !== '';

    return [
        'pagetitle' => 'Partner Hub',
        'templatefile' => 'whitelabel/overview',
        'breadcrumb' => ['index.php?m=eazybackup' => 'eazyBackup'],
        'requirelogin' => true,
        'forcessl' => true,
        'vars' => [
            'modulelink' => $baseLink,
            'msp' => (array)$msp,
            'token' => function_exists('generate_token') ? generate_token('plain') : '',
            'setup' => $setup,
            'connect' => $connect,
            'connect_due' => $connectDue,
            'connect_id_masked' => $connectIdMasked,
            'counts' => [
                'tenants' => $tenantCount,
                'active_subscriptions' => $activeSubCount,
                'products' => $productCount,
                'plans' => $planCount,
                'pending_approvals' => $pendingApprovalCount,
                'whitelabel_tenants' => $wlTenantCount,
            ],
            'billing' => $billing,
            'billing_unavailable' => $billingUnavailable,
            'recent_tenants' => $recentTenants,
            'onboardError' => $onboardError,
            'onboardSuccess' => $onboardSuccess,
            'onboardRefresh' => $onboardRefresh,
        ],
    ];
}
