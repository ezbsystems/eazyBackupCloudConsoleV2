<?php

use WHMCS\Database\Capsule;
use PartnerHub\StripeService;

function eb_ph_subscriptions_new(array $vars)
{
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { header('Location: clientarea.php'); exit; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    $customerId = (int)($_GET['customer_id'] ?? 0);
    $cust = Capsule::table('eb_customers')->where('id',$customerId)->where('msp_id',(int)($msp->id ?? 0))->first();
    if (!$cust) { header('Location: '.$vars['modulelink'].'&a=ph-clients'); exit; }

    // Simple placeholder page to create subscriptions in Phase 2
    return [
        'pagetitle' => 'New Subscription',
        'templatefile' => 'whitelabel/subscriptions-new',
        'breadcrumb' => [ 'index.php?m=eazybackup' => 'eazyBackup', $vars['modulelink'].'&a=ph-client&id='.$cust->id => 'Customer' ],
        'requirelogin' => true,
        'forcessl' => true,
        'vars' => [ 'customer' => $cust, 'msp' => $msp,
            'plans' => Capsule::table('eb_plans')->where('msp_id',(int)$msp->id)->get(),
            'prices' => Capsule::table('eb_plan_prices')->join('eb_plans','eb_plans.id','=','eb_plan_prices.plan_id')->where('eb_plans.msp_id',(int)$msp->id)->get(['eb_plan_prices.*']),
            'priceFeeMap' => (function() use ($msp){
                $rows = Capsule::table('eb_plan_prices')->join('eb_plans','eb_plans.id','=','eb_plan_prices.plan_id')->where('eb_plans.msp_id',(int)$msp->id)->get(['eb_plan_prices.stripe_price_id','eb_plan_prices.application_fee_percent']);
                $out = [];
                foreach ($rows as $r) { $out[$r->stripe_price_id] = $r->application_fee_percent; }
                return $out;
            })(),
            'moduleDefaultFee' => (string)(Capsule::table('tbladdonmodules')->where('module','eazybackup')->where('setting','partnerhub_default_fee_percent')->value('value') ?? '0')
        ],
    ];
}

function eb_ph_stripe_subscribe(array $vars)
{
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { header('Location: clientarea.php'); exit; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    $customerId = (int)($_POST['customer_id'] ?? 0);
    $priceId = (string)($_POST['stripe_price_id'] ?? '');
    $applicationFeePercent = isset($_POST['application_fee_percent']) ? (float)$_POST['application_fee_percent'] : null;
    $cust = Capsule::table('eb_customers')->where('id',$customerId)->where('msp_id',(int)($msp->id ?? 0))->first();
    if (!$cust || $priceId === '') { header('Location: '.$vars['modulelink'].'&a=ph-client&id='.$customerId); exit; }
    try {
        $svc = new StripeService();
        $acct = (string)($msp->stripe_connect_id ?? '');
        $scus = $svc->ensureStripeCustomerFor((int)$cust->id, $acct ?: null);
        // Apply cascade default if not provided: price default -> module default
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
                'customer_id' => (int)$cust->id,
                'plan_id' => (int)($planRow->plan_id ?? 0),
                'stripe_subscription_id' => $sid,
                'stripe_status' => (string)($sub['status'] ?? ''),
                'current_price_id' => (int)($planRow->id ?? 0),
                'started_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    } catch (\Throwable $__) { /* ignore */ }
    header('Location: '.$vars['modulelink'].'&a=ph-client&id='.(int)$cust->id);
    exit;
}


