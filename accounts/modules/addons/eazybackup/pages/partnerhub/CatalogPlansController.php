<?php

use WHMCS\Database\Capsule;
use PartnerHub\StripeService;
use PartnerHub\CatalogService;

function eb_ph_catalog_plans_index(array $vars)
{
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { header('Location: clientarea.php'); exit; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { header('Location: '.$vars['modulelink'].'&a=ph-tenants-manage'); exit; }

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
        ->get(['pc.*','pr.name as price_name','pr.metric_code as price_metric','pr.stripe_price_id','pr.unit_amount as price_amount','pr.currency as price_currency','pr.interval as price_interval','pr.kind as price_kind']);

    $prices = Capsule::table('eb_catalog_prices as p')
        ->join('eb_catalog_products as pr','pr.id','=','p.product_id')
        ->where('pr.msp_id',(int)$msp->id)
        ->where('p.active',1)
        ->orderBy('p.name','asc')
        ->get(['p.*']);

    $tenants = Capsule::table('eb_tenants')->where('msp_id',(int)$msp->id)->orderBy('id','desc')->limit(200)->get();

    // Subscription counts per plan
    $subCounts = [];
    try {
        $counts = Capsule::table('eb_plan_instances')
            ->selectRaw('plan_id, COUNT(*) as cnt')
            ->where('msp_id', (int)$msp->id)
            ->where('status', 'active')
            ->groupBy('plan_id')
            ->get();
        foreach ($counts as $c) { $subCounts[(int)$c->plan_id] = (int)$c->cnt; }
    } catch (\Throwable $__) {}

    // eazyBackup users for assignment picker
    $cometAccounts = [];
    try {
        $ca = Capsule::table('eb_tenant_comet_accounts as tca')
            ->join('eb_tenants as t','t.id','=','tca.tenant_id')
            ->where('t.msp_id',(int)$msp->id)
            ->get(['tca.tenant_id','tca.comet_username','tca.comet_server','t.name as tenant_name']);
        foreach ($ca as $r) { $cometAccounts[] = (array)$r; }
    } catch (\Throwable $__) {}

    // Normalize to arrays for Smarty
    $plansArr = [];
    foreach ((array)$plans as $r) {
        $row = (array)$r;
        $row['active_subs'] = $subCounts[(int)$row['id']] ?? 0;
        $plansArr[] = $row;
    }
    $componentsArr = []; foreach ((array)$components as $r) { $componentsArr[] = (array)$r; }
    $pricesArr = []; foreach ((array)$prices as $r) { $pricesArr[] = (array)$r; }
    $tenantsArr = []; foreach ((array)$tenants as $r) { $tenantsArr[] = (array)$r; }

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
            'tenants' => $tenantsArr,
            'customers' => $tenantsArr,
            'comet_accounts' => $cometAccounts,
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

    $tenantId = (int)($_POST['tenant_id'] ?? 0);
    $cometUserId = (string)($_POST['comet_user_id'] ?? '');
    $planId = (int)($_POST['plan_id'] ?? 0);
    $feePercent = isset($_POST['application_fee_percent']) && $_POST['application_fee_percent'] !== '' ? (float)$_POST['application_fee_percent'] : null;

    if ($tenantId <= 0 || $planId <= 0 || $cometUserId === '') { echo json_encode(['status'=>'error','message'=>'invalid']); return; }

    $plan = Capsule::table('eb_plan_templates')->where('id',$planId)->first();
    if (!$plan || (int)$plan->msp_id !== (int)$msp->id) { echo json_encode(['status'=>'error','message'=>'scope']); return; }
    $components = Capsule::table('eb_plan_components')->where('plan_id',$planId)->get();
    if (count($components) === 0) { echo json_encode(['status'=>'error','message'=>'no_components']); return; }

    $acct = (string)($msp->stripe_connect_id ?? '');
    if ($acct === '') { echo json_encode(['status'=>'error','message'=>'not_connected']); return; }

    try {
        $svc = new StripeService();
        $cSvc = new CatalogService();
        $scus = $svc->ensureStripeCustomerFor($tenantId, $acct);

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
            'tenant_id' => $tenantId,
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
        try { if (function_exists('logModuleCall')) { @logModuleCall('eazybackup','ph-plan-assign',[ 'tenant_id'=>$tenantId,'plan_id'=>$planId,'items'=>count($items) ],[ 'subscription_id'=>$subId,'plan_instance_id'=>$instanceId ]); } } catch (\Throwable $__) {}
        echo json_encode(['status'=>'success','subscription_id'=>$subId,'plan_instance_id'=>$instanceId]);
        return;
    } catch (\Throwable $e) {
        try { if (function_exists('logModuleCall')) { @logModuleCall('eazybackup','ph-plan-assign-error',[ 'tenant_id'=>$tenantId,'plan_id'=>$planId ], $e->getMessage()); } } catch (\Throwable $__) {}
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
        return;
    }
}

function eb_ph_plan_template_get(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { echo json_encode(['status'=>'error','message'=>'msp']); return; }
    $id = (int)($_GET['id'] ?? 0);
    $plan = Capsule::table('eb_plan_templates')->where('id',$id)->first();
    if (!$plan || (int)$plan->msp_id !== (int)$msp->id) { echo json_encode(['status'=>'error','message'=>'scope']); return; }
    $components = Capsule::table('eb_plan_components as pc')
        ->join('eb_catalog_prices as pr','pr.id','=','pc.price_id')
        ->where('pc.plan_id',$id)
        ->get(['pc.*','pr.name as price_name','pr.metric_code as price_metric','pr.unit_amount as price_amount','pr.currency as price_currency','pr.interval as price_interval','pr.kind as price_kind']);
    $subCount = Capsule::table('eb_plan_instances')->where('plan_id',$id)->where('status','active')->count();
    echo json_encode(['status'=>'success','plan'=>(array)$plan,'components'=>array_map(function($c){ return (array)$c; }, iterator_to_array($components)),'active_subs'=>$subCount]);
}

function eb_ph_plan_template_update(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    if (function_exists('check_token')) { try { check_token('WHMCS.default'); } catch (\Throwable $__) { echo json_encode(['status'=>'error','message'=>'csrf']); return; } }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { echo json_encode(['status'=>'error','message'=>'msp']); return; }

    $raw = file_get_contents('php://input');
    $json = json_decode((string)$raw, true);
    if (!is_array($json) && isset($_POST['payload'])) { $json = json_decode((string)$_POST['payload'], true); }
    if (!is_array($json)) { echo json_encode(['status'=>'error','message'=>'bad_json']); return; }

    $planId = (int)($json['plan_id'] ?? 0);
    $plan = Capsule::table('eb_plan_templates')->where('id',$planId)->first();
    if (!$plan || (int)$plan->msp_id !== (int)$msp->id) { echo json_encode(['status'=>'error','message'=>'scope']); return; }

    $name = trim((string)($json['name'] ?? $plan->name));
    if ($name === '') { echo json_encode(['status'=>'error','message'=>'invalid']); return; }

    Capsule::table('eb_plan_templates')->where('id',$planId)->update([
        'name' => $name,
        'description' => isset($json['description']) ? trim((string)$json['description']) : $plan->description,
        'trial_days' => (int)($json['trial_days'] ?? $plan->trial_days),
        'billing_interval' => (string)($json['billing_interval'] ?? $plan->billing_interval ?? 'month'),
        'currency' => strtoupper((string)($json['currency'] ?? $plan->currency ?? 'CAD')),
        'version' => (int)$plan->version + 1,
        'updated_at' => date('Y-m-d H:i:s'),
    ]);

    // Sync components: diff-based add/update/remove
    $incoming = (array)($json['components'] ?? []);
    $existingIds = Capsule::table('eb_plan_components')->where('plan_id',$planId)->pluck('id')->toArray();
    $incomingIds = array_filter(array_map(function($c){ return (int)($c['id'] ?? 0); }, $incoming));
    $toRemove = array_diff($existingIds, $incomingIds);
    if (!empty($toRemove)) { Capsule::table('eb_plan_components')->whereIn('id',$toRemove)->delete(); }

    foreach ($incoming as $c) {
        $cid = (int)($c['id'] ?? 0);
        $priceId = (int)($c['price_id'] ?? 0);
        $defaultQty = (int)($c['default_qty'] ?? 0);
        $overageMode = (string)($c['overage_mode'] ?? 'bill_all');
        if (!in_array($overageMode,['bill_all','cap_at_default'],true)) { $overageMode = 'bill_all'; }
        if ($priceId <= 0) continue;
        $metricCode = (string)Capsule::table('eb_catalog_prices')->where('id',$priceId)->value('metric_code');
        if ($cid > 0 && in_array($cid,$existingIds)) {
            Capsule::table('eb_plan_components')->where('id',$cid)->update([
                'price_id' => $priceId, 'metric_code' => $metricCode, 'default_qty' => $defaultQty, 'overage_mode' => $overageMode, 'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } else {
            Capsule::table('eb_plan_components')->insert([
                'plan_id' => $planId, 'price_id' => $priceId, 'metric_code' => $metricCode, 'default_qty' => $defaultQty, 'overage_mode' => $overageMode,
                'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }
    echo json_encode(['status'=>'success','plan_id'=>$planId]);
}

function eb_ph_plan_template_duplicate(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    if (function_exists('check_token')) { try { check_token('WHMCS.default'); } catch (\Throwable $__) { echo json_encode(['status'=>'error','message'=>'csrf']); return; } }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { echo json_encode(['status'=>'error','message'=>'msp']); return; }
    $id = (int)($_POST['plan_id'] ?? 0);
    $plan = Capsule::table('eb_plan_templates')->where('id',$id)->first();
    if (!$plan || (int)$plan->msp_id !== (int)$msp->id) { echo json_encode(['status'=>'error','message'=>'scope']); return; }

    $newId = Capsule::table('eb_plan_templates')->insertGetId([
        'msp_id' => (int)$msp->id, 'name' => $plan->name . ' (Copy)', 'description' => $plan->description,
        'trial_days' => (int)$plan->trial_days, 'billing_interval' => $plan->billing_interval ?? 'month',
        'currency' => $plan->currency ?? 'CAD', 'version' => 1, 'active' => 1, 'status' => 'draft',
        'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
    ]);
    $components = Capsule::table('eb_plan_components')->where('plan_id',$id)->get();
    foreach ($components as $c) {
        Capsule::table('eb_plan_components')->insert([
            'plan_id' => $newId, 'price_id' => (int)$c->price_id, 'metric_code' => (string)$c->metric_code,
            'default_qty' => (int)$c->default_qty, 'overage_mode' => (string)$c->overage_mode,
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
    echo json_encode(['status'=>'success','new_plan_id'=>$newId]);
}

function eb_ph_plan_template_toggle(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    if (function_exists('check_token')) { try { check_token('WHMCS.default'); } catch (\Throwable $__) { echo json_encode(['status'=>'error','message'=>'csrf']); return; } }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { echo json_encode(['status'=>'error','message'=>'msp']); return; }
    $id = (int)($_POST['plan_id'] ?? 0);
    $newStatus = (string)($_POST['status'] ?? '');
    if (!in_array($newStatus,['active','archived','draft'],true)) { echo json_encode(['status'=>'error','message'=>'invalid']); return; }
    $plan = Capsule::table('eb_plan_templates')->where('id',$id)->first();
    if (!$plan || (int)$plan->msp_id !== (int)$msp->id) { echo json_encode(['status'=>'error','message'=>'scope']); return; }
    Capsule::table('eb_plan_templates')->where('id',$id)->update([
        'status' => $newStatus, 'active' => ($newStatus === 'active' ? 1 : 0), 'updated_at' => date('Y-m-d H:i:s'),
    ]);
    echo json_encode(['status'=>'success']);
}

function eb_ph_plan_template_delete(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    if (function_exists('check_token')) { try { check_token('WHMCS.default'); } catch (\Throwable $__) { echo json_encode(['status'=>'error','message'=>'csrf']); return; } }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { echo json_encode(['status'=>'error','message'=>'msp']); return; }
    $id = (int)($_POST['plan_id'] ?? 0);
    $plan = Capsule::table('eb_plan_templates')->where('id',$id)->first();
    if (!$plan || (int)$plan->msp_id !== (int)$msp->id) { echo json_encode(['status'=>'error','message'=>'scope']); return; }
    $activeSubs = Capsule::table('eb_plan_instances')->where('plan_id',$id)->where('status','active')->count();
    if ($activeSubs > 0) { echo json_encode(['status'=>'error','message'=>'has_active_subscriptions','count'=>$activeSubs]); return; }
    Capsule::table('eb_plan_components')->where('plan_id',$id)->delete();
    Capsule::table('eb_plan_templates')->where('id',$id)->delete();
    echo json_encode(['status'=>'success']);
}

function eb_ph_plan_component_remove(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    if (function_exists('check_token')) { try { check_token('WHMCS.default'); } catch (\Throwable $__) { echo json_encode(['status'=>'error','message'=>'csrf']); return; } }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { echo json_encode(['status'=>'error','message'=>'msp']); return; }
    $componentId = (int)($_POST['component_id'] ?? 0);
    $comp = Capsule::table('eb_plan_components as pc')
        ->join('eb_plan_templates as pt','pt.id','=','pc.plan_id')
        ->where('pc.id',$componentId)->first(['pc.*','pt.msp_id']);
    if (!$comp || (int)$comp->msp_id !== (int)$msp->id) { echo json_encode(['status'=>'error','message'=>'scope']); return; }
    Capsule::table('eb_plan_components')->where('id',$componentId)->delete();
    echo json_encode(['status'=>'success']);
}

function eb_ph_plan_subscriptions_list(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { echo json_encode(['status'=>'error','message'=>'msp']); return; }
    $planId = (int)($_GET['plan_id'] ?? 0);
    $instances = Capsule::table('eb_plan_instances as pi')
        ->leftJoin('eb_tenants as t','t.id','=','pi.tenant_id')
        ->where('pi.msp_id',(int)$msp->id)
        ->when($planId > 0, function($q) use ($planId){ $q->where('pi.plan_id',$planId); })
        ->orderBy('pi.created_at','desc')
        ->limit(200)
        ->get(['pi.*','t.name as tenant_name','t.company as tenant_company']);
    $arr = [];
    foreach ($instances as $r) { $arr[] = (array)$r; }
    echo json_encode(['status'=>'success','subscriptions'=>$arr]);
}

function eb_ph_plan_subscription_cancel(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    if (function_exists('check_token')) { try { check_token('WHMCS.default'); } catch (\Throwable $__) { echo json_encode(['status'=>'error','message'=>'csrf']); return; } }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { echo json_encode(['status'=>'error','message'=>'msp']); return; }
    $instanceId = (int)($_POST['instance_id'] ?? 0);
    $reason = trim((string)($_POST['reason'] ?? ''));
    $instance = Capsule::table('eb_plan_instances')->where('id',$instanceId)->first();
    if (!$instance || (int)$instance->msp_id !== (int)$msp->id) { echo json_encode(['status'=>'error','message'=>'scope']); return; }
    if ((string)$instance->status === 'canceled') { echo json_encode(['status'=>'error','message'=>'already_canceled']); return; }

    try {
        $svc = new StripeService();
        $svc->cancelSubscription((string)$instance->stripe_subscription_id, (string)$instance->stripe_account_id);
    } catch (\Throwable $e) {
        try { if (function_exists('logModuleCall')) { @logModuleCall('eazybackup','ph-plan-sub-cancel-error',['instance'=>$instanceId],$e->getMessage()); } } catch (\Throwable $__) {}
    }

    Capsule::table('eb_plan_instances')->where('id',$instanceId)->update([
        'status' => 'canceled', 'cancelled_at' => date('Y-m-d H:i:s'), 'cancel_reason' => $reason !== '' ? $reason : null, 'updated_at' => date('Y-m-d H:i:s'),
    ]);
    echo json_encode(['status'=>'success']);
}
