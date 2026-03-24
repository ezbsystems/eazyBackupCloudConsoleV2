<?php

use WHMCS\Database\Capsule;
use PartnerHub\StripeService;

function eb_ph_overview_notice_dismiss(array $vars): void
{
    header('Content-Type: application/json');

    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'auth']);
        return;
    }

    require_once __DIR__ . '/TenantsController.php';

    try {
        [, $msp] = eb_ph_tenants_require_context($vars);
    } catch (\Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => 'context']);
        return;
    }

    $token = trim((string)($_POST['token'] ?? $_POST['csrf'] ?? ''));
    if (function_exists('check_token')) {
        try {
            if (!check_token('plain', $token)) {
                echo json_encode(['status' => 'error', 'message' => 'csrf']);
                return;
            }
        } catch (\Throwable $__) {
            echo json_encode(['status' => 'error', 'message' => 'csrf']);
            return;
        }
    }

    $noticeKey = trim((string)($_POST['notice_key'] ?? ''));
    if ($noticeKey === '') {
        echo json_encode(['status' => 'error', 'message' => 'notice_key']);
        return;
    }

    Capsule::table('eb_partnerhub_notices')
        ->where('msp_id', (int)($msp->id ?? 0))
        ->where('notice_key', $noticeKey)
        ->update([
            'dismissed_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

    echo json_encode(['status' => 'success']);
}

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
        if (Capsule::schema()->hasTable('eb_plan_templates')) {
            $planCount = (int)Capsule::table('eb_plan_templates')
                ->where('msp_id', $mspId)
                ->whereIn('status', ['active', 'draft'])
                ->count();
        }
    } catch (\Throwable $__) {}

    try {
        if (Capsule::schema()->hasTable('eb_plan_instances')) {
            $activeSubCount = (int)Capsule::table('eb_plan_instances')
                ->where('msp_id', $mspId)
                ->whereIn('status', ['active', 'trialing'])
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
                ->get(['id', 'public_id', 'name', 'slug', 'contact_email', 'status', 'created_at']);
            foreach ($rows as $row) {
                $recentTenants[] = (array)$row;
            }
        }
    } catch (\Throwable $__) {}

    $partnerHubNotices = [];
    try {
        if (Capsule::schema()->hasTable('eb_partnerhub_notices')) {
            $rows = Capsule::table('eb_partnerhub_notices')
                ->where('msp_id', $mspId)
                ->whereNull('dismissed_at')
                ->whereNull('resolved_at')
                ->orderByRaw('CASE WHEN effective_at IS NULL THEN 1 ELSE 0 END ASC')
                ->orderBy('effective_at', 'asc')
                ->orderBy('created_at', 'desc')
                ->limit(6)
                ->get([
                    'notice_key',
                    'notice_type',
                    'title',
                    'message',
                    'action_url',
                    'action_label',
                    'effective_at',
                ]);

            foreach ($rows as $row) {
                $partnerHubNotices[] = [
                    'notice_key' => (string)($row->notice_key ?? ''),
                    'notice_type' => (string)($row->notice_type ?? 'info'),
                    'title' => (string)($row->title ?? 'Notice'),
                    'message' => (string)($row->message ?? ''),
                    'action_url' => (string)($row->action_url ?? ''),
                    'action_label' => (string)($row->action_label ?? 'Review'),
                    'effective_at' => (string)($row->effective_at ?? ''),
                ];
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
            'partnerhub_notice_dismiss_url' => $baseLink . '&a=ph-overview-notice-dismiss',
            'setup' => $setup,
            'connect' => $connect,
            'connect_due' => $connectDue,
            'connect_id_masked' => $connectIdMasked,
            'partnerhub_notices' => $partnerHubNotices,
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
