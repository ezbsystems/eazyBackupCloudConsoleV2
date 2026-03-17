<?php

use WHMCS\Database\Capsule;

require_once __DIR__ . '/TenantsController.php';

function eb_ph_billing_subscriptions(array $vars)
{
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { header('Location: clientarea.php'); exit; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { header('Location: '.$vars['modulelink'].'&a=ph-tenants-manage'); exit; }

    $q = trim((string)($_GET['q'] ?? ''));
    $page = max(1, (int)($_GET['p'] ?? 1));
    $per = min(100, max(10, (int)($_GET['per'] ?? 25)));

    try { if (function_exists('eazybackup_migrate_schema')) { @eazybackup_migrate_schema(); } } catch (\Throwable $__) {}

    $base = Capsule::table('eb_subscriptions as s')
        ->leftJoin('eb_tenants as t','t.id','=','s.tenant_id')
        ->leftJoin('eb_plans as p','p.id','=','s.plan_id')
        ->leftJoin('eb_plan_prices as pp','pp.id','=','s.current_price_id')
        ->where('s.msp_id', (int)$msp->id);

    if ($q !== '') {
        $base->where(function($w) use ($q){
            $w->where('t.name','like','%'.$q.'%')
              ->orWhere('s.stripe_subscription_id','like','%'.$q.'%')
              ->orWhere('p.name','like','%'.$q.'%')
              ->orWhere('pp.nickname','like','%'.$q.'%');
        });
    }

    $total = (int)$base->count();
    $rowsCol = $base->orderBy('s.created_at','desc')
        ->forPage($page, $per)
        ->get([
            's.*',
            't.name as tenant_name',
            't.public_id as tenant_public_id',
            't.status as tenant_status',
            'p.name as plan_name',
            'pp.nickname as price_nickname',
            'pp.billing_cycle as price_cycle'
        ]);

    $rows = [];
    foreach ($rowsCol as $r) { $rows[] = (array)$r; }

    return [
        'pagetitle' => 'Billing — Subscriptions',
        'templatefile' => 'whitelabel/billing-subscriptions',
        'breadcrumb' => [ 'index.php?m=eazybackup' => 'eazyBackup' ],
        'requirelogin' => true,
        'forcessl' => true,
        'vars' => [
            'modulelink' => $vars['modulelink'],
            'msp' => $msp,
            'rows' => $rows,
            'q' => $q,
            'page' => $page,
            'per' => $per,
            'total' => $total,
            'token' => function_exists('generate_token') ? generate_token('plain') : '',
        ],
    ];
}

function eb_ph_billing_invoices(array $vars)
{
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { header('Location: clientarea.php'); exit; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { header('Location: '.$vars['modulelink'].'&a=ph-tenants-manage'); exit; }

    $q = trim((string)($_GET['q'] ?? ''));
    $page = max(1, (int)($_GET['p'] ?? 1));
    $per = min(100, max(10, (int)($_GET['per'] ?? 25)));
    try { if (function_exists('eazybackup_migrate_schema')) { @eazybackup_migrate_schema(); } } catch (\Throwable $__) {}

    $base = Capsule::table('eb_invoice_cache as i')
        ->leftJoin('eb_tenants as t','t.id','=','i.tenant_id')
        ->where('t.msp_id', (int)$msp->id);

    if ($q !== '') {
        $base->where(function($w) use ($q){
            $w->where('t.name','like','%'.$q.'%')
              ->orWhere('i.stripe_invoice_id','like','%'.$q.'%')
              ->orWhere('i.status','like','%'.$q.'%');
        });
    }

    $total = (int)$base->count();
    $rowsCol = $base->orderBy('i.created','desc')
        ->forPage($page, $per)
        ->get([
            'i.*',
            't.name as tenant_name',
            't.public_id as tenant_public_id'
        ]);

    $rows = [];
    foreach ($rowsCol as $r) { $rows[] = (array)$r; }

    return [
        'pagetitle' => 'Billing — Invoices',
        'templatefile' => 'whitelabel/billing-invoices',
        'breadcrumb' => [ 'index.php?m=eazybackup' => 'eazyBackup' ],
        'requirelogin' => true,
        'forcessl' => true,
        'vars' => [
            'modulelink' => $vars['modulelink'],
            'msp' => $msp,
            'rows' => $rows,
            'q' => $q,
            'page' => $page,
            'per' => $per,
            'total' => $total,
            'token' => function_exists('generate_token') ? generate_token('plain') : '',
        ],
    ];
}

function eb_ph_billing_payments(array $vars)
{
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { header('Location: clientarea.php'); exit; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { header('Location: '.$vars['modulelink'].'&a=ph-tenants-manage'); exit; }

    $q = trim((string)($_GET['q'] ?? ''));
    $page = max(1, (int)($_GET['p'] ?? 1));
    $per = min(100, max(10, (int)($_GET['per'] ?? 25)));
    try { if (function_exists('eazybackup_migrate_schema')) { @eazybackup_migrate_schema(); } } catch (\Throwable $__) {}

    $base = Capsule::table('eb_payment_cache as p')
        ->leftJoin('eb_tenants as t','t.id','=','p.tenant_id')
        ->where('t.msp_id', (int)$msp->id);

    if ($q !== '') {
        $base->where(function($w) use ($q){
            $w->where('t.name','like','%'.$q.'%')
              ->orWhere('p.stripe_payment_intent_id','like','%'.$q.'%')
              ->orWhere('p.status','like','%'.$q.'%');
        });
    }

    $total = (int)$base->count();
    $rowsCol = $base->orderBy('p.created','desc')
        ->forPage($page, $per)
        ->get([
            'p.*',
            't.name as tenant_name',
            't.public_id as tenant_public_id'
        ]);

    $rows = [];
    foreach ($rowsCol as $r) { $rows[] = (array)$r; }

    $tenants = Capsule::table('eb_tenants')->where('msp_id',(int)$msp->id)->where('status', '!=', 'deleted')->orderBy('name','asc')->get(['public_id','name','contact_email','stripe_customer_id']);
    $tenantArr = [];
    foreach ($tenants as $t) { $tenantArr[] = (array)$t; }

    return [
        'pagetitle' => 'Billing — Payments',
        'templatefile' => 'whitelabel/billing-payments',
        'breadcrumb' => [ 'index.php?m=eazybackup' => 'eazyBackup' ],
        'requirelogin' => true,
        'forcessl' => true,
        'vars' => [
            'modulelink' => $vars['modulelink'],
            'msp' => $msp,
            'rows' => $rows,
            'q' => $q,
            'page' => $page,
            'per' => $per,
            'total' => $total,
            'tenants' => $tenantArr,
            'token' => function_exists('generate_token') ? generate_token('plain') : '',
        ],
    ];
}

function eb_ph_billing_payment_new(array $vars)
{
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { header('Location: clientarea.php'); exit; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { header('Location: '.$vars['modulelink'].'&a=ph-tenants-manage'); exit; }

    $tenants = Capsule::table('eb_tenants')->where('msp_id',(int)$msp->id)->where('status', '!=', 'deleted')->orderBy('name','asc')->get(['public_id','name','contact_email']);
    $tenantArr = [];
    foreach ($tenants as $t) { $tenantArr[] = (array)$t; }

    return [
        'pagetitle' => 'New Payment',
        'templatefile' => 'whitelabel/billing-payment-new',
        'breadcrumb' => [ 'index.php?m=eazybackup' => 'eazyBackup', $vars['modulelink'].'&a=ph-billing-payments' => 'Payments' ],
        'requirelogin' => true,
        'forcessl' => true,
        'vars' => [ 'modulelink' => $vars['modulelink'], 'msp' => $msp, 'tenants' => $tenantArr, 'customers' => $tenantArr, 'token' => function_exists('generate_token') ? generate_token('plain') : '' ],
    ];
}

function eb_ph_billing_resolve_tenant_for_msp(int $mspId, string $tenantPublicId)
{
    $tenantPublicId = trim($tenantPublicId);
    if ($tenantPublicId === '') {
        return null;
    }

    return Capsule::table('eb_tenants')
        ->where('public_id', $tenantPublicId)
        ->where('msp_id', $mspId)
        ->where('status', '!=', 'deleted')
        ->first(['id', 'public_id', 'name', 'stripe_customer_id']);
}

function eb_ph_billing_create_payment(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    if (!eb_ph_tenants_require_csrf_or_json_error((string)($_POST['token'] ?? ''))) { return; }
    try {
        $clientId = (int)$_SESSION['uid'];
        $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
        if (!$msp || (string)($msp->stripe_connect_id ?? '') === '') { echo json_encode(['status'=>'error','message'=>'no_account']); return; }
        $tenantPublicId = trim((string)($_POST['tenant_id'] ?? ''));
        $amountDec = (string)($_POST['amount'] ?? '0'); // decimal as string
        $feeDec = (string)($_POST['application_fee'] ?? '0');
        $currency = strtoupper(trim((string)($_POST['currency'] ?? 'USD')));

        $toMinor = function(string $dec): int { $v = (float)$dec; return (int)round($v * 100); };
        $amountMinor = $toMinor($amountDec);
        $feeMinor = $toMinor($feeDec);
        if ($amountMinor <= 0) { echo json_encode(['status'=>'error','message'=>'invalid_amount']); return; }

        $acct = (string)$msp->stripe_connect_id;
        $params = [
            'amount' => $amountMinor,
            'currency' => strtolower($currency),
            'payment_method_types[]' => 'card',
        ];
        if ($feeMinor > 0) { $params['application_fee_amount'] = $feeMinor; }
        $paymentMethodId = trim((string)($_POST['payment_method_id'] ?? ''));
        if ($paymentMethodId !== '') { $params['payment_method'] = $paymentMethodId; }

        $tenant = null;
        $tenantId = 0;
        if ($tenantPublicId !== '') {
            $tenant = eb_ph_billing_resolve_tenant_for_msp((int)$msp->id, $tenantPublicId);
            if (!$tenant) {
                echo json_encode(['status' => 'error', 'message' => 'tenant_not_found']);
                return;
            }
            $tenantId = (int)$tenant->id;
        }

        if ($tenantId > 0 && $tenant) {
            $svc = new \PartnerHub\StripeService();
            $scus = $svc->ensureStripeCustomerFor($tenantId, $acct);
            if ($scus !== '') { $params['customer'] = $scus; }
            $pi = $svc->createPaymentIntentOneTime($acct, $params);
            echo json_encode(['status'=>'success','client_secret'=>$pi['client_secret'] ?? null, 'publishable'=>(new \PartnerHub\StripeService())->getPublishable()]);
            return;
        }
        // No customer: still create PI on connected account
        $svc = new \PartnerHub\StripeService();
        $pi = $svc->createPaymentIntentOneTime($acct, $params);
        echo json_encode(['status'=>'success','client_secret'=>$pi['client_secret'] ?? null, 'publishable'=>$svc->getPublishable()]);
        return;
    } catch (\Throwable $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
        return;
    }
}

function eb_ph_billing_payment_methods(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status' => 'error', 'message' => 'auth']); return; }

    try {
        $clientId = (int)$_SESSION['uid'];
        $tenantPublicId = trim((string)($_GET['tenant_id'] ?? $_POST['tenant_id'] ?? ''));
        $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id', $clientId)->first();
        if (!$msp || (string)($msp->stripe_connect_id ?? '') === '') {
            echo json_encode(['status' => 'error', 'message' => 'no_account']);
            return;
        }
        if ($tenantPublicId === '') {
            echo json_encode(['status' => 'success', 'methods' => []]);
            return;
        }

        $tenant = eb_ph_billing_resolve_tenant_for_msp((int)$msp->id, $tenantPublicId);
        if (!$tenant) {
            echo json_encode(['status' => 'error', 'message' => 'tenant_not_found']);
            return;
        }

        $svc = new \PartnerHub\StripeService();
        $acct = (string)$msp->stripe_connect_id;
        $stripeCustomerId = trim((string)($tenant->stripe_customer_id ?? ''));
        if ($stripeCustomerId === '') {
            echo json_encode(['status' => 'success', 'methods' => []]);
            return;
        }

        $customer = $svc->retrieveCustomer($stripeCustomerId, $acct);
        $defaultPaymentMethodId = (string)($customer['invoice_settings']['default_payment_method'] ?? '');
        $paymentMethods = $svc->listCustomerPaymentMethods($stripeCustomerId, 'card', $acct);
        $methods = [];

        foreach (($paymentMethods['data'] ?? []) as $paymentMethod) {
            if (!is_array($paymentMethod)) {
                continue;
            }
            $card = is_array($paymentMethod['card'] ?? null) ? $paymentMethod['card'] : [];
            $methods[] = [
                'id' => (string)($paymentMethod['id'] ?? ''),
                'brand' => (string)($card['brand'] ?? 'card'),
                'last4' => (string)($card['last4'] ?? ''),
                'exp_month' => (int)($card['exp_month'] ?? 0),
                'exp_year' => (int)($card['exp_year'] ?? 0),
                'is_default' => ((string)($paymentMethod['id'] ?? '') !== '' && (string)($paymentMethod['id'] ?? '') === $defaultPaymentMethodId),
            ];
        }

        echo json_encode(['status' => 'success', 'methods' => $methods]);
        return;
    } catch (\Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        return;
    }
}

function eb_ph_money_payouts(array $vars)
{
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { header('Location: clientarea.php'); exit; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { header('Location: '.$vars['modulelink'].'&a=ph-tenants-manage'); exit; }

    $q = trim((string)($_GET['q'] ?? ''));
    $page = max(1, (int)($_GET['p'] ?? 1));
    $per = min(100, max(10, (int)($_GET['per'] ?? 25)));

    $base = Capsule::table('eb_payouts as p')
        ->where('p.msp_id', (int)$msp->id);

    if ($q !== '') {
        $base->where(function($w) use ($q){
            $w->where('p.stripe_payout_id','like','%'.$q.'%')
              ->orWhere('p.status','like','%'.$q.'%')
              ->orWhere('p.currency','like','%'.$q.'%');
        });
    }

    $total = (int)$base->count();
    $rowsCol = $base->orderBy('p.created','desc')
        ->forPage($page, $per)
        ->get(['p.*']);

    $rows = [];
    foreach ($rowsCol as $r) { $rows[] = (array)$r; }

    return [
        'pagetitle' => 'Money — Payouts',
        'templatefile' => 'whitelabel/money-payouts',
        'breadcrumb' => [ 'index.php?m=eazybackup' => 'eazyBackup' ],
        'requirelogin' => true,
        'forcessl' => true,
        'vars' => [
            'modulelink' => $vars['modulelink'],
            'msp' => $msp,
            'rows' => $rows,
            'q' => $q,
            'page' => $page,
            'per' => $per,
            'total' => $total,
            'token' => function_exists('generate_token') ? generate_token('plain') : '',
        ],
    ];
}

function eb_ph_money_disputes(array $vars)
{
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { header('Location: clientarea.php'); exit; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { header('Location: '.$vars['modulelink'].'&a=ph-tenants-manage'); exit; }

    $q = trim((string)($_GET['q'] ?? ''));
    $page = max(1, (int)($_GET['p'] ?? 1));
    $per = min(100, max(10, (int)($_GET['per'] ?? 25)));

    $base = Capsule::table('eb_disputes as d')
        ->where('d.msp_id', (int)$msp->id);

    if ($q !== '') {
        $base->where(function($w) use ($q){
            $w->where('d.stripe_dispute_id','like','%'.$q.'%')
              ->orWhere('d.status','like','%'.$q.'%')
              ->orWhere('d.currency','like','%'.$q.'%');
        });
    }

    $total = (int)$base->count();
    $rowsCol = $base->orderBy('d.created','desc')
        ->forPage($page, $per)
        ->get(['d.*']);

    $rows = [];
    foreach ($rowsCol as $r) { $rows[] = (array)$r; }

    return [
        'pagetitle' => 'Money — Disputes',
        'templatefile' => 'whitelabel/money-disputes',
        'breadcrumb' => [ 'index.php?m=eazybackup' => 'eazyBackup' ],
        'requirelogin' => true,
        'forcessl' => true,
        'vars' => [
            'modulelink' => $vars['modulelink'],
            'msp' => $msp,
            'rows' => $rows,
            'q' => $q,
            'page' => $page,
            'per' => $per,
            'total' => $total,
            'token' => function_exists('generate_token') ? generate_token('plain') : '',
        ],
    ];
}

function eb_ph_money_balance(array $vars)
{
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { header('Location: clientarea.php'); exit; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { header('Location: '.$vars['modulelink'].'&a=ph-tenants-manage'); exit; }

    // Filters
    $from = trim((string)($_GET['from'] ?? ''));
    $to = trim((string)($_GET['to'] ?? ''));
    $type = trim((string)($_GET['type'] ?? ''));
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 50)));

    $createdGte = 0; $createdLte = 0;
    if ($from !== '') { $ts = strtotime($from . ' 00:00:00'); if ($ts) { $createdGte = $ts; } }
    if ($to !== '') { $ts = strtotime($to . ' 23:59:59'); if ($ts) { $createdLte = $ts; } }

    $acct = (string)($msp->stripe_connect_id ?? '');
    $svc = new \PartnerHub\StripeService();
    $balance = [];
    $tx = [];
    try {
        if ($acct !== '') {
            $balance = $svc->getBalance($acct);
            $params = [ 'limit' => $limit ];
            if ($createdGte > 0) { $params['created[gte]'] = $createdGte; }
            if ($createdLte > 0) { $params['created[lte]'] = $createdLte; }
            if ($type !== '') { $params['type'] = $type; }
            $bt = $svc->listBalanceTransactions($acct, $params);
            if (isset($bt['data']) && is_array($bt['data'])) { $tx = $bt['data']; }
        }
    } catch (\Throwable $__) { /* ignore */ }

    // CSV export
    if (isset($_GET['export']) && (string)$_GET['export'] === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="balance_transactions.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['id','amount','currency','type','description','created','available_on','fee','net']);
        foreach ($tx as $row) {
            fputcsv($out, [
                (string)($row['id'] ?? ''),
                (int)($row['amount'] ?? 0),
                strtoupper((string)($row['currency'] ?? 'usd')),
                (string)($row['type'] ?? ''),
                (string)($row['description'] ?? ''),
                (int)($row['created'] ?? 0),
                (int)($row['available_on'] ?? 0),
                (int)($row['fee'] ?? 0),
                (int)($row['net'] ?? 0),
            ]);
        }
        fclose($out);
        return; // end response
    }

    // Cached aggregates for context
    $totPayouts = (int)Capsule::table('eb_payouts')->where('msp_id',(int)$msp->id)->sum('amount');
    $totDisputes = (int)Capsule::table('eb_disputes')->where('msp_id',(int)$msp->id)->sum('amount');

    $dashboardUrl = $acct !== '' ? ('https://dashboard.stripe.com/connect/accounts/' . $acct) : '';

    return [
        'pagetitle' => 'Money — Balance & Reports',
        'templatefile' => 'whitelabel/money-balance',
        'breadcrumb' => [ 'index.php?m=eazybackup' => 'eazyBackup' ],
        'requirelogin' => true,
        'forcessl' => true,
        'vars' => [
            'modulelink' => $vars['modulelink'],
            'msp' => $msp,
            'balance' => $balance,
            'transactions' => $tx,
            'filters' => [ 'from'=>$from, 'to'=>$to, 'type'=>$type, 'limit'=>$limit ],
            'totPayouts' => $totPayouts,
            'totDisputes' => $totDisputes,
            'dashboardUrl' => $dashboardUrl,
        ],
    ];
}


