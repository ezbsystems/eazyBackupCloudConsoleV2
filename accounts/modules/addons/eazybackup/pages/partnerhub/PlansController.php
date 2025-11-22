<?php

use WHMCS\Database\Capsule;
use PartnerHub\StripeService;

function eb_ph_plans_index(array $vars)
{
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { header('Location: clientarea.php'); exit; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { header('Location: '.$vars['modulelink'].'&a=ph-clients'); exit; }

    // Handle create plan POST (name, currency, amount)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eb_create_plan'])) {
        try {
            $name = trim((string)($_POST['name'] ?? ''));
            $currency = strtoupper(trim((string)($_POST['currency'] ?? 'USD')));
            $amount = (int)($_POST['amount_minor'] ?? 0); // minor units
            if ($name !== '' && $amount > 0) {
                $svc = new StripeService();
                $acct = (string)($msp->stripe_connect_id ?? '');
                $prod = $svc->createProduct($name, '', $acct ?: null);
                $price = $svc->createPrice((string)$prod['id'], $currency, $amount, 'month', false, $acct ?: null);
                $planId = Capsule::table('eb_plans')->insertGetId([
                    'msp_id' => (int)$msp->id,
                    'name' => $name,
                    'currency' => $currency,
                    'stripe_product_id' => (string)$prod['id'],
                    'status' => 'active',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                Capsule::table('eb_plan_prices')->insert([
                    'plan_id' => $planId,
                    'nickname' => 'Standard',
                    'billing_cycle' => 'month',
                    'stripe_price_id' => (string)$price['id'],
                    'is_metered' => 0,
                    'metric_code' => null,
                    'application_fee_percent' => null,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                header('Location: '.$vars['modulelink'].'&a=ph-plans');
                exit;
            }
        } catch (\Throwable $__) { /* ignore */ }
    }

    $plans = Capsule::table('eb_plans')->where('msp_id',(int)$msp->id)->orderBy('created_at','desc')->get();

    return [
        'pagetitle' => 'Plans',
        'templatefile' => 'whitelabel/plans',
        'breadcrumb' => [ 'index.php?m=eazybackup' => 'eazyBackup' ],
        'requirelogin' => true,
        'forcessl' => true,
        'vars' => [ 'plans' => $plans, 'msp' => $msp ],
    ];
}


