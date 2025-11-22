<?php

use WHMCS\Database\Capsule;
use PartnerHub\StripeService;
use PartnerHub\CatalogService;

function eb_ph_catalog_plans_index(array $vars)
{
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { header('Location: clientarea.php'); exit; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { header('Location: '.$vars['modulelink'].'&a=ph-clients'); exit; }

    // Ensure schema exists for plan tables
    try {
        $schema = Capsule::schema();
        $need = [];
        foreach (['eb_plan_templates','eb_plan_components','eb_catalog_prices','eb_catalog_products'] as $t) { if (!$schema->hasTable($t)) { $need[] = $t; } }
        if (!empty($need) && function_exists('eazybackup_migrate_schema')) { @eazybackup_migrate_schema(); }
    } catch (\Throwable $__) { /* ignore */ }

    $plans = Capsule::table('eb_plan_templates')->where('msp_id',(int)$msp->id)->orderBy('updated_at','desc')->get();
    $components = Capsule::table('eb_plan_components as pc')
        ->join('eb_catalog_prices as pr','pr.id','=','pc.price_id')
        ->join('eb_plan_templates as pt','pt.id','=','pc.plan_id')
        ->where('pt.msp_id',(int)$msp->id)
        ->get(['pc.*','pr.name as price_name','pr.metric_code as price_metric','pr.stripe_price_id']);

    $prices = Capsule::table('eb_catalog_prices as p')
        ->join('eb_catalog_products as pr','pr.id','=','p.product_id')
        ->where('pr.msp_id',(int)$msp->id)
        ->where('p.active',1)
        ->orderBy('p.name','asc')
        ->get(['p.*']);

    $customers = Capsule::table('eb_customers')->where('msp_id',(int)$msp->id)->orderBy('id','desc')->limit(200)->get();

    // Normalize to arrays for Smarty
    $plansArr = []; foreach ((array)$plans as $r) { $plansArr[] = (array)$r; }
    $componentsArr = []; foreach ((array)$components as $r) { $componentsArr[] = (array)$r; }
    $pricesArr = []; foreach ((array)$prices as $r) { $pricesArr[] = (array)$r; }
    $customersArr = []; foreach ((array)$customers as $r) { $customersArr[] = (array)$r; }

    return [
        'pagetitle' => 'Catalog — Plans',
        'templatefile' => 'whitelabel/catalog-plans',
        'breadcrumb' => [ 'index.php?m=eazybackup' => 'eazyBackup' ],
        'requirelogin' => true,
        'forcessl' => true,
        'vars' => [
            'msp' => (array)$msp,
            'plans' => $plansArr,
            'components' => $componentsArr,
            'prices' => $pricesArr,
            'customers' => $customersArr,
            'modulelink' => $vars['modulelink'] ?? ('index.php?m=eazybackup'),
            'token' => function_exists('generate_token') ? generate_token('plain') : '',
        ],
    ];
}

function eb_ph_plan_template_create(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    if (function_exists('check_token')) { try { check_token('WHMCS.default'); } catch (\Throwable $__) { echo json_encode(['status'=>'error','message'=>'csrf']); return; } }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { echo json_encode(['status'=>'error','message'=>'msp']); return; }

    try { if (!Capsule::schema()->hasTable('eb_plan_templates') && function_exists('eazybackup_migrate_schema')) { @eazybackup_migrate_schema(); } } catch (\Throwable $__) {}

    $name = trim((string)($_POST['name'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $trialDays = (int)($_POST['trial_days'] ?? 0);
    if ($name === '' || $trialDays < 0) { echo json_encode(['status'=>'error','message'=>'invalid']); return; }

    $id = Capsule::table('eb_plan_templates')->insertGetId([
        'msp_id' => (int)$msp->id,
        'name' => $name,
        'description' => $description !== '' ? $description : null,
        'trial_days' => $trialDays,
        'version' => 1,
        'active' => 1,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ]);
    try { if (function_exists('logModuleCall')) { @logModuleCall('eazybackup','ph-plan-template-create',[ 'name'=>$name,'trial_days'=>$trialDays ],[ 'plan_id'=>$id ]); } } catch (\Throwable $__) {}
    echo json_encode(['status'=>'success','id'=>$id]);
}

function eb_ph_plan_component_add(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    if (function_exists('check_token')) { try { check_token('WHMCS.default'); } catch (\Throwable $__) { echo json_encode(['status'=>'error','message'=>'csrf']); return; } }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { echo json_encode(['status'=>'error','message'=>'msp']); return; }

    try { if (!Capsule::schema()->hasTable('eb_plan_components') && function_exists('eazybackup_migrate_schema')) { @eazybackup_migrate_schema(); } } catch (\Throwable $__) {}

    $planId = (int)($_POST['plan_id'] ?? 0);
    $priceId = (int)($_POST['price_id'] ?? 0);
    $defaultQty = (int)($_POST['default_qty'] ?? 0);
    $overageMode = (string)($_POST['overage_mode'] ?? 'bill_all');
    $price = Capsule::table('eb_catalog_prices as p')
        ->join('eb_catalog_products as pr','pr.id','=','p.product_id')
        ->where('p.id',$priceId)->first(['p.metric_code','pr.msp_id']);
    $plan = Capsule::table('eb_plan_templates')->where('id',$planId)->first();
    if (!$price || !$plan || (int)$price->msp_id !== (int)$msp->id || (int)$plan->msp_id !== (int)$msp->id) {
        echo json_encode(['status'=>'error','message'=>'scope']); return;
    }
    if (!in_array($overageMode,['bill_all','cap_at_default'],true)) { echo json_encode(['status'=>'error','message'=>'invalid']); return; }

    $id = Capsule::table('eb_plan_components')->insertGetId([
        'plan_id' => $planId,
        'price_id' => $priceId,
        'metric_code' => (string)$price->metric_code,
        'default_qty' => $defaultQty,
        'overage_mode' => $overageMode,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ]);
    try { if (function_exists('logModuleCall')) { @logModuleCall('eazybackup','ph-plan-component-add',[ 'plan_id'=>$planId,'price_id'=>$priceId ],[ 'component_id'=>$id ]); } } catch (\Throwable $__) {}
    echo json_encode(['status'=>'success','id'=>$id]);
}

function eb_ph_plan_assign(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    if (function_exists('check_token')) { try { check_token('WHMCS.default'); } catch (\Throwable $__) { echo json_encode(['status'=>'error','message'=>'csrf']); return; } }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { echo json_encode(['status'=>'error','message'=>'msp']); return; }

    try { if (!Capsule::schema()->hasTable('eb_plan_instances') && function_exists('eazybackup_migrate_schema')) { @eazybackup_migrate_schema(); } } catch (\Throwable $__) {}

    $customerId = (int)($_POST['customer_id'] ?? 0);
    $cometUserId = (string)($_POST['comet_user_id'] ?? '');
    $planId = (int)($_POST['plan_id'] ?? 0);
    $feePercent = isset($_POST['application_fee_percent']) && $_POST['application_fee_percent'] !== '' ? (float)$_POST['application_fee_percent'] : null;

    if ($customerId <= 0 || $planId <= 0 || $cometUserId === '') { echo json_encode(['status'=>'error','message'=>'invalid']); return; }

    $plan = Capsule::table('eb_plan_templates')->where('id',$planId)->first();
    if (!$plan || (int)$plan->msp_id !== (int)$msp->id) { echo json_encode(['status'=>'error','message'=>'scope']); return; }
    $components = Capsule::table('eb_plan_components')->where('plan_id',$planId)->get();
    if (count($components) === 0) { echo json_encode(['status'=>'error','message'=>'no_components']); return; }

    $acct = (string)($msp->stripe_connect_id ?? '');
    if ($acct === '') { echo json_encode(['status'=>'error','message'=>'not_connected']); return; }

    try {
        $svc = new StripeService();
        $cSvc = new CatalogService();
        // Ensure stripe customer on connected account
        $scus = $svc->ensureStripeCustomerFor($customerId, $acct);

        // Build items
        $priceRows = Capsule::table('eb_catalog_prices')->whereIn('id', array_map(function($c){ return (int)$c->price_id; }, iterator_to_array($components)))->get();
        $byId = [];
        foreach ($priceRows as $pr) { $byId[$pr->id] = $pr; }
        $items = [];
        foreach ($components as $c) {
            $pr = $byId[$c->price_id] ?? null;
            if (!$pr) { continue; }
            $it = ['price' => (string)$pr->stripe_price_id];
            if ((string)$pr->kind === 'recurring' && (int)$c->default_qty > 0) { $it['quantity'] = (int)$c->default_qty; }
            $items[] = $it;
        }
        if (count($items) === 0) { echo json_encode(['status'=>'error','message'=>'no_items']); return; }

        $sub = $cSvc->createSubscriptionMulti($scus, $items, $acct, $feePercent, (int)$plan->trial_days ?: null);
        $subId = (string)($sub['id'] ?? '');
        if ($subId === '') { echo json_encode(['status'=>'error','message'=>'sub_failed']); return; }

        $instanceId = Capsule::table('eb_plan_instances')->insertGetId([
            'msp_id' => (int)$msp->id,
            'customer_id' => $customerId,
            'comet_user_id' => $cometUserId,
            'plan_id' => (int)$plan->id,
            'plan_version' => (int)$plan->version,
            'stripe_account_id' => $acct,
            'stripe_customer_id' => $scus,
            'stripe_subscription_id' => $subId,
            'anchor_date' => date('Y-m-d'),
            'status' => (string)($sub['status'] ?? 'active'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Persist item mapping
        $itemsData = (array)($sub['items']['data'] ?? []);
        foreach ($components as $c) {
            $pr = $byId[$c->price_id] ?? null;
            if (!$pr) { continue; }
            // try find matching item by price
            $match = null;
            foreach ($itemsData as $sid) { if ((string)($sid['price']['id'] ?? '') === (string)$pr->stripe_price_id) { $match = $sid; break; } }
            if ($match) {
                Capsule::table('eb_plan_instance_items')->insert([
                    'plan_instance_id' => $instanceId,
                    'plan_component_id' => (int)$c->id,
                    'stripe_subscription_item_id' => (string)($match['id'] ?? ''),
                    'metric_code' => (string)$pr->metric_code,
                    'last_qty' => isset($match['quantity']) ? (int)$match['quantity'] : null,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
        try { if (function_exists('logModuleCall')) { @logModuleCall('eazybackup','ph-plan-assign',[ 'customer_id'=>$customerId,'plan_id'=>$planId,'items'=>count($items) ],[ 'subscription_id'=>$subId,'plan_instance_id'=>$instanceId ]); } } catch (\Throwable $__) {}
        echo json_encode(['status'=>'success','subscription_id'=>$subId,'plan_instance_id'=>$instanceId]);
        return;
    } catch (\Throwable $e) {
        try { if (function_exists('logModuleCall')) { @logModuleCall('eazybackup','ph-plan-assign-error',[ 'customer_id'=>$customerId,'plan_id'=>$planId ], $e->getMessage()); } } catch (\Throwable $__) {}
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
        return;
    }
}


