<?php

use WHMCS\Database\Capsule;
use PartnerHub\StripeService;

require_once __DIR__ . '/TenantsController.php';

function eb_ph_subscriptions_new(array $vars)
{
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { header('Location: clientarea.php'); exit; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { header('Location: '.$vars['modulelink'].'&a=ph-tenants-manage'); exit; }
    $tenantReference = trim((string)($_GET['tenant_id'] ?? $_GET['customer_id'] ?? ''));
    $tenant = eb_ph_tenants_find_owned_tenant_by_reference((int)$msp->id, $tenantReference);
    if (!$tenant || trim((string)($tenant->public_id ?? '')) === '') { header('Location: '.$vars['modulelink'].'&a=ph-tenants-manage'); exit; }

    eb_ph_tenant_redirect($vars, (string)$tenant->public_id, 'legacy=ph-subscriptions');
}

function eb_ph_stripe_subscribe(array $vars)
{
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { header('Location: clientarea.php'); exit; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { header('Location: '.$vars['modulelink'].'&a=ph-tenants-manage'); exit; }
    $tenantReference = trim((string)($_POST['tenant_id'] ?? $_POST['customer_id'] ?? ''));
    $token = (string)($_POST['token'] ?? '');
    $priceId = (string)($_POST['stripe_price_id'] ?? '');
    $applicationFeePercent = isset($_POST['application_fee_percent']) ? (float)$_POST['application_fee_percent'] : null;
    $tenant = eb_ph_tenants_find_owned_tenant_by_reference((int)$msp->id, $tenantReference);
    if (!$tenant || trim((string)($tenant->public_id ?? '')) === '') {
        header('Location: '.$vars['modulelink'].'&a=ph-tenants-manage'); exit;
    }
    eb_ph_tenants_require_csrf_or_redirect($vars, $token, (string)$tenant->public_id);
    if ($priceId === '') {
        if ($tenant && trim((string)($tenant->public_id ?? '')) !== '') {
            eb_ph_tenant_redirect($vars, (string)$tenant->public_id);
        }
        header('Location: '.$vars['modulelink'].'&a=ph-tenants-manage');
        exit;
    }
    try {
        $svc = new StripeService();
        $acct = (string)($msp->stripe_connect_id ?? '');
        $scus = $svc->ensureStripeCustomerFor((int)$tenant->id, $acct ?: null);
        if ($applicationFeePercent === null) {
            $priceRow = Capsule::table('eb_plan_prices')->where('stripe_price_id',$priceId)->first();
            if ($priceRow && $priceRow->application_fee_percent !== null) {
                $applicationFeePercent = (float)$priceRow->application_fee_percent;
            } else {
                $modDefault = (string)(Capsule::table('tbladdonmodules')->where('module','eazybackup')->where('setting','partnerhub_default_fee_percent')->value('value') ?? '0');
                $applicationFeePercent = (float)$modDefault;
            }
        }
        $sub = $svc->createSubscription($scus, $priceId, $acct, $applicationFeePercent);
        $sid = (string)($sub['id'] ?? '');
        if ($sid !== '') {
            $planRow = Capsule::table('eb_plan_prices')->where('stripe_price_id',$priceId)->first();
            Capsule::table('eb_subscriptions')->insert([
                'msp_id' => (int)$msp->id,
                'tenant_id' => (int)$tenant->id,
                'plan_id' => (int)($planRow->plan_id ?? 0),
                'stripe_subscription_id' => $sid,
                'stripe_status' => (string)($sub['status'] ?? ''),
                'current_price_id' => (int)($planRow->id ?? 0),
                'started_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    } catch (\Throwable $__) {
        try { if (function_exists('logActivity')) { @logActivity('eazybackup: ph-stripe-subscribe error: '.$__->getMessage()); } } catch (\Throwable $___) {}
        eb_ph_tenant_redirect($vars, (string)$tenant->public_id, 'subscribe_error=1');
        return;
    }
    eb_ph_tenant_redirect($vars, (string)$tenant->public_id);
}


