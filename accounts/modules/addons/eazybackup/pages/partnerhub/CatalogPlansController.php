<?php

require_once __DIR__ . '/TenantsController.php';

use WHMCS\Database\Capsule;
use PartnerHub\StripeService;
use PartnerHub\CatalogService;

function eb_ph_plan_normalize_billing_type($kind): string
{
    $kind = (string)($kind ?? 'recurring');
    if ($kind === 'metered') {
        return 'metered';
    }
    if ($kind === 'one_time') {
        return 'one_time';
    }
    return 'per_unit';
}

function eb_ph_plan_normalize_interval($interval): string
{
    $interval = strtolower(trim((string)($interval ?? 'month')));
    return in_array($interval, ['month', 'year'], true) ? $interval : 'month';
}

function eb_ph_plan_normalize_currency($currency): string
{
    $currency = strtoupper(trim((string)($currency ?? 'CAD')));
    if (!preg_match('/^[A-Z]{3}$/', $currency)) {
        return 'CAD';
    }
    return $currency;
}

function eb_ph_plan_normalize_status($status, $default = 'draft'): string
{
    $status = strtolower(trim((string)($status ?? $default)));
    return in_array($status, ['draft', 'active', 'archived'], true) ? $status : $default;
}

function eb_ph_plan_validation_error(string $message, string $code = 'invalid'): array
{
    return ['ok' => false, 'message' => $message, 'code' => $code];
}

function eb_ph_plan_validate_component_rows(int $mspId, array $components, string $currency, string $interval, bool $requireComponents, bool $requireActivePrices): array
{
    if ($requireComponents && count($components) === 0) {
        return eb_ph_plan_validation_error('At least one recurring price is required before publishing.', 'no_components');
    }

    if (count($components) === 0) {
        return ['ok' => true, 'rows' => []];
    }

    $priceIds = [];
    foreach ($components as $component) {
        $priceId = (int)($component['price_id'] ?? 0);
        if ($priceId > 0) {
            $priceIds[] = $priceId;
        }
    }
    $priceIds = array_values(array_unique($priceIds));

    if (empty($priceIds)) {
        return $requireComponents
            ? eb_ph_plan_validation_error('At least one recurring price is required before publishing.', 'no_components')
            : ['ok' => true, 'rows' => []];
    }

    $rows = Capsule::table('eb_catalog_prices as p')
        ->join('eb_catalog_products as pr', 'pr.id', '=', 'p.product_id')
        ->where('pr.msp_id', $mspId)
        ->whereIn('p.id', $priceIds)
        ->get([
            'p.id',
            'p.product_id',
            'p.name',
            'p.kind',
            'p.metric_code',
            'p.unit_amount',
            'p.currency',
            'p.interval',
            'p.unit_label',
            'p.active',
            'p.stripe_price_id',
            'pr.name as product_name',
            'pr.description as product_description',
            'pr.base_metric_code as product_base_metric',
            'pr.active as product_active',
            'pr.stripe_product_id',
        ]);

    $byId = [];
    foreach ($rows as $row) {
        $byId[(int)$row->id] = (array)$row;
    }

    foreach ($components as $component) {
        $priceId = (int)($component['price_id'] ?? 0);
        if ($priceId <= 0) {
            continue;
        }
        if (!isset($byId[$priceId])) {
            return eb_ph_plan_validation_error('One or more selected prices are no longer available.', 'missing_price');
        }

        $row = $byId[$priceId];
        $rowCurrency = eb_ph_plan_normalize_currency($row['currency'] ?? 'CAD');
        $rowInterval = eb_ph_plan_normalize_interval($row['interval'] ?? 'month');
        $kind = (string)($row['kind'] ?? 'recurring');

        if ($kind === 'one_time') {
            return eb_ph_plan_validation_error('One-time prices are not supported in plan templates.', 'one_time_not_supported');
        }
        if ($rowCurrency !== $currency) {
            return eb_ph_plan_validation_error('All recurring plan components must use the same currency.', 'currency_mismatch');
        }
        if ($rowInterval !== $interval) {
            return eb_ph_plan_validation_error('All recurring plan components must use the same billing interval.', 'interval_mismatch');
        }
        if ($requireActivePrices && !(int)($row['active'] ?? 0)) {
            return eb_ph_plan_validation_error('Archived prices cannot be used when publishing a plan.', 'archived_price');
        }
        if ($requireActivePrices && trim((string)($row['stripe_price_id'] ?? '')) === '') {
            return eb_ph_plan_validation_error('Only published catalog prices can be used when publishing a plan.', 'draft_price');
        }
    }

    $metrics = [];
    foreach ($byId as $row) {
        $metric = strtoupper(trim((string)($row['metric_code'] ?? '')));
        if ($metric === '') {
            $metric = strtoupper(trim((string)($row['product_base_metric'] ?? '')));
        }
        if ($metric === '') {
            $metric = 'GENERIC';
        }
        $metrics[$metric] = true;
    }

    if (isset($metrics['E3_STORAGE_GIB']) && count($metrics) > 1) {
        return eb_ph_plan_validation_error(
            'e3 Object Storage components cannot be combined with other metric types in the same plan.',
            'mixed_e3_metrics'
        );
    }

    return ['ok' => true, 'rows' => $byId];
}

function eb_ph_plan_validate_existing_plan_state($plan, int $mspId, bool $requireActivePrices): array
{
    $components = Capsule::table('eb_plan_components')
        ->where('plan_id', (int)$plan->id)
        ->get(['id', 'price_id', 'default_qty', 'overage_mode']);

    $incoming = [];
    foreach ($components as $component) {
        $incoming[] = [
            'id' => (int)$component->id,
            'price_id' => (int)$component->price_id,
            'default_qty' => (int)$component->default_qty,
            'overage_mode' => (string)$component->overage_mode,
        ];
    }

    return eb_ph_plan_validate_component_rows(
        $mspId,
        $incoming,
        eb_ph_plan_normalize_currency($plan->currency ?? 'CAD'),
        eb_ph_plan_normalize_interval($plan->billing_interval ?? 'month'),
        true,
        $requireActivePrices
    );
}

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
        ->whereIn('p.kind', ['recurring', 'metered', 'one_time'])
        ->orderBy('pr.name','asc')
        ->orderBy('p.name','asc')
        ->get([
            'p.*',
            'pr.name as product_name',
            'pr.description as product_description',
            'pr.base_metric_code as product_base_metric',
            'pr.active as product_active',
            'pr.stripe_product_id',
        ]);

    $products = Capsule::table('eb_catalog_products as p')
        ->where('p.msp_id', (int)$msp->id)
        ->orderBy('p.name', 'asc')
        ->get([
            'p.id',
            'p.name',
            'p.description',
            'p.active',
            'p.base_metric_code',
            'p.stripe_product_id',
        ]);

    $tenants = Capsule::table('eb_tenants')->where('msp_id',(int)$msp->id)->where('status', '!=', 'deleted')->orderBy('id','desc')->limit(200)->get(['public_id', 'name']);
    $s3Users = eb_ph_discover_msp_s3_users($clientId);

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
    $cometAccountIndex = [];
    try {
        if (Capsule::schema()->hasTable('eb_tenant_comet_accounts')) {
            $ca = Capsule::table('eb_tenant_comet_accounts as tca')
                ->join('eb_tenants as t','t.id','=','tca.tenant_id')
                ->where('t.msp_id',(int)$msp->id)
                ->where('t.status','!=','deleted')
                ->get(['t.public_id as tenant_public_id', 'tca.comet_username', 'tca.comet_user_id', 't.name as tenant_name']);
            foreach ($ca as $row) {
                $r = (array)$row;
                $identifier = trim((string)($r['comet_user_id'] ?? $r['comet_username'] ?? ''));
                $display = trim((string)($r['comet_username'] ?? $r['comet_user_id'] ?? ''));
                $tenantPublicId = trim((string)($r['tenant_public_id'] ?? ''));
                if ($tenantPublicId === '' || $identifier === '') {
                    continue;
                }
                $key = $tenantPublicId . '|' . $identifier;
                $cometAccountIndex[$key] = [
                    'tenant_public_id' => $tenantPublicId,
                    'comet_user_id' => $identifier,
                    'comet_username' => $display !== '' ? $display : $identifier,
                    'tenant_name' => (string)($r['tenant_name'] ?? ''),
                ];
            }
        }
    } catch (\Throwable $__) {}
    try {
        if (Capsule::schema()->hasTable('eb_service_links')) {
            $links = Capsule::table('eb_service_links as sl')
                ->join('eb_tenants as t', 't.id', '=', 'sl.tenant_id')
                ->where('sl.msp_id', (int)$msp->id)
                ->where('t.status', '!=', 'deleted')
                ->whereNotNull('sl.comet_user_id')
                ->where('sl.comet_user_id', '!=', '')
                ->get(['t.public_id as tenant_public_id', 'sl.comet_user_id', 't.name as tenant_name']);
            foreach ($links as $row) {
                $r = (array)$row;
                $identifier = trim((string)($r['comet_user_id'] ?? ''));
                $tenantPublicId = trim((string)($r['tenant_public_id'] ?? ''));
                if ($tenantPublicId === '' || $identifier === '') {
                    continue;
                }
                $key = $tenantPublicId . '|' . $identifier;
                if (!isset($cometAccountIndex[$key])) {
                    $cometAccountIndex[$key] = [
                        'tenant_public_id' => $tenantPublicId,
                        'comet_user_id' => $identifier,
                        'comet_username' => $identifier,
                        'tenant_name' => (string)($r['tenant_name'] ?? ''),
                    ];
                }
            }
        }
    } catch (\Throwable $__) {}

    $whmcsCometUsernames = eb_ph_discover_msp_comet_usernames($clientId);
    foreach ($whmcsCometUsernames as $username) {
        $alreadyKnown = false;
        foreach ($cometAccountIndex as $entry) {
            if (($entry['comet_user_id'] ?? '') === $username) {
                $alreadyKnown = true;
                break;
            }
        }
        if (!$alreadyKnown) {
            $cometAccountIndex['_whmcs_' . $username] = [
                'tenant_public_id' => '',
                'comet_user_id' => $username,
                'comet_username' => $username,
                'tenant_name' => '',
            ];
        }
    }

    $cometAccounts = array_values($cometAccountIndex);

    // Normalize to arrays for Smarty
    $plansArr = [];
    foreach ($plans as $r) {
        $row = (array)$r;
        $row['active_subs'] = $subCounts[(int)$row['id']] ?? 0;
        $plansArr[] = $row;
    }
    $componentsArr = []; foreach ($components as $r) { $componentsArr[] = (array)$r; }
    $pricesArr = []; foreach ($prices as $r) { $pricesArr[] = (array)$r; }
    $tenantsArr = []; foreach ($tenants as $r) { $tenantsArr[] = (array)$r; }

    $catalogByProduct = [];
    foreach ($products as $row) {
        $arr = (array)$row;
        $catalogByProduct[(int)$arr['id']] = [
            'id' => (int)$arr['id'],
            'name' => (string)($arr['name'] ?? ''),
            'description' => (string)($arr['description'] ?? ''),
            'active' => (int)($arr['active'] ?? 0) === 1,
            'base_metric_code' => (string)($arr['base_metric_code'] ?? 'GENERIC'),
            'stripe_product_id' => (string)($arr['stripe_product_id'] ?? ''),
            'prices' => [],
        ];
    }
    foreach ($pricesArr as $row) {
        $productId = (int)($row['product_id'] ?? 0);
        if (!isset($catalogByProduct[$productId])) {
            continue;
        }
        $kind = (string)($row['kind'] ?? 'recurring');
        $billingType = eb_ph_plan_normalize_billing_type($kind);
        $catalogByProduct[$productId]['prices'][] = [
            'id' => (int)($row['id'] ?? 0),
            'product_id' => $productId,
            'name' => (string)($row['name'] ?? ''),
            'kind' => $kind,
            'billing_type' => $billingType,
            'metric_code' => (string)($row['metric_code'] ?? ($catalogByProduct[$productId]['base_metric_code'] ?? 'GENERIC')),
            'unit_amount' => (int)($row['unit_amount'] ?? 0),
            'currency' => eb_ph_plan_normalize_currency($row['currency'] ?? 'CAD'),
            'interval' => eb_ph_plan_normalize_interval($row['interval'] ?? 'month'),
            'unit_label' => (string)($row['unit_label'] ?? ''),
            'active' => (int)($row['active'] ?? 0) === 1,
            'stripe_price_id' => (string)($row['stripe_price_id'] ?? ''),
        ];
    }
    $catalogProducts = array_values($catalogByProduct);
    $componentsByPlan = [];
    foreach ($componentsArr as $row) {
        $componentsByPlan[(int)($row['plan_id'] ?? 0)][] = $row;
    }
    $assignPlans = [];
    foreach ($plansArr as $planRow) {
        $planId = (int)($planRow['id'] ?? 0);
        if ($planId <= 0) {
            continue;
        }
        $assignPlans[] = [
            'id' => $planId,
            'name' => (string)($planRow['name'] ?? ''),
            'assignment_mode' => eb_ph_plan_assignment_mode($planId, $componentsByPlan[$planId] ?? []),
        ];
    }

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
            'catalog_products' => $catalogProducts,
            'catalog_products_json' => json_encode($catalogProducts, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT),
            'tenants' => $tenantsArr,
            'customers' => $tenantsArr,
            'assign_tenants' => array_values(array_map(static function (array $t): array {
                return [
                    'public_id' => (string) ($t['public_id'] ?? ''),
                    'name' => (string) ($t['name'] ?? ''),
                ];
            }, $tenantsArr)),
            'assign_plans_json' => json_encode($assignPlans, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
            'assign_tenants_json' => json_encode(array_values(array_map(static function (array $t): array {
                return [
                    'public_id' => (string) ($t['public_id'] ?? ''),
                    'name' => (string) ($t['name'] ?? ''),
                ];
            }, $tenantsArr)), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
            's3_users' => $s3Users,
            's3_users_json' => json_encode($s3Users, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
            'comet_accounts' => $cometAccounts,
            'comet_accounts_json' => json_encode($cometAccounts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
            'modulelink' => $vars['modulelink'] ?? ('index.php?m=eazybackup'),
            'token' => function_exists('generate_token') ? generate_token('plain') : '',
        ],
    ];
}

function eb_ph_catalog_plans_resolve_tenant_for_msp(int $mspId, string $tenantPublicId)
{
    $tenantPublicId = trim($tenantPublicId);
    if ($tenantPublicId === '') {
        return null;
    }

    return Capsule::table('eb_tenants')
        ->where('public_id', $tenantPublicId)
        ->where('msp_id', $mspId)
        ->where('status', '!=', 'deleted')
        ->first(['id', 'public_id', 'name']);
}

function eb_ph_plan_storage_assignment_key($tenant): string
{
    $publicId = trim((string)($tenant->public_id ?? ''));
    if ($publicId !== '') {
        return 'storage:' . $publicId;
    }

    return 'storage:' . (int)($tenant->id ?? 0);
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
    $billingInterval = eb_ph_plan_normalize_interval($_POST['billing_interval'] ?? 'month');
    $currency = eb_ph_plan_normalize_currency($_POST['currency'] ?? 'CAD');
    $status = eb_ph_plan_normalize_status($_POST['status'] ?? 'draft', 'draft');
    if ($name === '' || $trialDays < 0) { echo json_encode(['status'=>'error','message'=>'invalid']); return; }

    $id = Capsule::table('eb_plan_templates')->insertGetId([
        'msp_id' => (int)$msp->id,
        'name' => $name,
        'description' => $description !== '' ? $description : null,
        'trial_days' => $trialDays,
        'billing_interval' => $billingInterval,
        'currency' => $currency,
        'version' => 1,
        'status' => $status === 'archived' ? 'archived' : 'draft',
        'active' => 0,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ]);
    try { if (function_exists('logModuleCall')) { @logModuleCall('eazybackup','ph-plan-template-create',[ 'name'=>$name,'trial_days'=>$trialDays,'billing_interval'=>$billingInterval,'currency'=>$currency,'status'=>$status ],[ 'plan_id'=>$id ]); } } catch (\Throwable $__) {}
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

    $tenantPublicId = trim((string)($_POST['tenant_id'] ?? ''));
    $cometUserId = (string)($_POST['comet_user_id'] ?? '');
    $s3UserId = (int)($_POST['s3_user_id'] ?? 0);
    $planId = (int)($_POST['plan_id'] ?? 0);
    $feePercent = isset($_POST['application_fee_percent']) && $_POST['application_fee_percent'] !== '' ? (float)$_POST['application_fee_percent'] : null;

    if ($tenantPublicId === '' || $planId <= 0) { echo json_encode(['status'=>'error','message'=>'invalid']); return; }

    $plan = Capsule::table('eb_plan_templates')->where('id',$planId)->first();
    if (!$plan || (int)$plan->msp_id !== (int)$msp->id) { echo json_encode(['status'=>'error','message'=>'scope']); return; }
    if (eb_ph_plan_normalize_status($plan->status ?? ($plan->active ? 'active' : 'draft')) !== 'active') {
        echo json_encode(['status'=>'error','message'=>'plan_not_active']); return;
    }
    $tenant = eb_ph_catalog_plans_resolve_tenant_for_msp((int)$msp->id, $tenantPublicId);
    if (!$tenant) { echo json_encode(['status'=>'error','message'=>'tenant_not_found']); return; }
    $tenantId = (int)$tenant->id;
    $assignmentMode = eb_ph_plan_assignment_mode((int)$plan->id);
    if (($assignmentMode['mode'] ?? 'comet_user') === 'e3_storage') {
        if ($s3UserId <= 0) { echo json_encode(['status'=>'error','message'=>'invalid']); return; }

        $ownedS3Users = eb_ph_discover_msp_s3_users($clientId);
        $ownedS3UserIds = [];
        foreach ($ownedS3Users as $ownedS3User) {
            $ownedS3UserIds[(int)($ownedS3User['id'] ?? 0)] = true;
        }
        if (!isset($ownedS3UserIds[$s3UserId])) { echo json_encode(['status'=>'error','message'=>'s3_user_not_found']); return; }

        $cometUserId = 'e3:' . $s3UserId;
        $existingStorageInstanceForUser = Capsule::table('eb_plan_instances')
            ->where('comet_user_id', $cometUserId)
            ->whereIn('status', ['active', 'trialing', 'past_due', 'paused'])
            ->first();
        if ($existingStorageInstanceForUser) {
            echo json_encode(['status'=>'error','message'=>'This S3 user is already assigned to another active storage plan.']);
            return;
        }
    } else {
        if ($assignmentMode['requires_comet_user'] && $cometUserId === '') { echo json_encode(['status'=>'error','message'=>'invalid']); return; }

        $ownedCometAccount = null;
        try {
            if (Capsule::schema()->hasTable('eb_tenant_comet_accounts')) {
                $ownedCometAccount = Capsule::table('eb_tenant_comet_accounts as tca')
                    ->join('eb_tenants as t', 't.id', '=', 'tca.tenant_id')
                    ->where('t.id', $tenantId)
                    ->where('t.msp_id', (int)$msp->id)
                    ->where('t.status', '!=', 'deleted')
                    ->where(function ($query) use ($cometUserId) {
                        $query->where('tca.comet_user_id', $cometUserId)
                            ->orWhere('tca.comet_username', $cometUserId);
                    })
                    ->first(['tca.id']);
            }
        } catch (\Throwable $__) {}
        if (!$ownedCometAccount) {
            $ownedCometAccount = Capsule::table('eb_service_links as sl')
                ->join('eb_tenants as t', 't.id', '=', 'sl.tenant_id')
                ->where('t.id', $tenantId)
                ->where('t.msp_id', (int)$msp->id)
                ->where('t.status', '!=', 'deleted')
                ->where('sl.comet_user_id', $cometUserId)
                ->first(['sl.id']);
        }
        if (!$ownedCometAccount) {
            $whmcsUsernames = eb_ph_discover_msp_comet_usernames($clientId);
            if (in_array($cometUserId, $whmcsUsernames, true)) {
                $ownedCometAccount = (object)['id' => 0];
            }
        }
        if (!$ownedCometAccount) { echo json_encode(['status'=>'error','message'=>'comet_user_not_found']); return; }
    }
    $components = Capsule::table('eb_plan_components')->where('plan_id',$planId)->get();
    if (count($components) === 0) { echo json_encode(['status'=>'error','message'=>'no_components']); return; }

    $acct = (string)($msp->stripe_connect_id ?? '');
    if ($acct === '') { echo json_encode(['status'=>'error','message'=>'not_connected']); return; }

    $existingInstance = Capsule::table('eb_plan_instances')
        ->where('tenant_id', $tenantId)
        ->where('plan_id', (int)$plan->id)
        ->where('comet_user_id', $cometUserId)
        ->whereIn('status', ['active', 'trialing', 'past_due', 'paused'])
        ->first();
    if ($existingInstance) {
        echo json_encode(['status'=>'error','message'=>'This plan is already assigned to this tenant with this backup user.']);
        return;
    }

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
            if ((string)$pr->kind === 'recurring') {
                $it['quantity'] = max(0, (int)$c->default_qty);
            }
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
                $instanceItemId = Capsule::table('eb_plan_instance_items')->insertGetId([
                    'plan_instance_id' => $instanceId,
                    'plan_component_id' => (int)$c->id,
                    'stripe_subscription_item_id' => (string)($match['id'] ?? ''),
                    'metric_code' => (string)$pr->metric_code,
                    'last_qty' => isset($match['quantity']) ? (int)$match['quantity'] : null,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                if ((string)$pr->kind === 'metered' && $instanceItemId) {
                    try {
                        Capsule::table('eb_plan_instance_usage_map')->insert([
                            'plan_instance_item_id' => $instanceItemId,
                            'metric_code' => (string)$pr->metric_code,
                            'stripe_subscription_item_id' => (string)($match['id'] ?? ''),
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]);
                    } catch (\Throwable $__) {}
                }
            }
        }
        try { if (function_exists('logModuleCall')) { @logModuleCall('eazybackup','ph-plan-assign',[ 'tenant_public_id'=>$tenantPublicId,'plan_id'=>$planId,'items'=>count($items) ],[ 'subscription_id'=>$subId,'plan_instance_id'=>$instanceId ]); } } catch (\Throwable $__) {}
        echo json_encode(['status'=>'success','subscription_id'=>$subId,'plan_instance_id'=>$instanceId]);
        return;
    } catch (\Throwable $e) {
        try { if (function_exists('logModuleCall')) { @logModuleCall('eazybackup','ph-plan-assign-error',[ 'tenant_public_id'=>$tenantPublicId,'plan_id'=>$planId ], $e->getMessage()); } } catch (\Throwable $__) {}
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
        ->join('eb_catalog_products as p','p.id','=','pr.product_id')
        ->where('pc.plan_id',$id)
        ->get([
            'pc.*',
            'pr.product_id',
            'pr.name as price_name',
            'pr.metric_code as price_metric',
            'pr.unit_amount as price_amount',
            'pr.currency as price_currency',
            'pr.interval as price_interval',
            'pr.kind as price_kind',
            'pr.unit_label as price_unit_label',
            'pr.active as price_active',
            'pr.stripe_price_id',
            'p.name as product_name',
            'p.description as product_description',
            'p.base_metric_code as product_base_metric',
            'p.active as product_active',
        ]);
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
    if (!is_array($json)) {
        if (isset($_POST['payload'])) {
            $payloadRaw = (string)$_POST['payload'];
            $candidates = [];
            $candidates[] = $payloadRaw;
            if (strlen($payloadRaw) > 2 && $payloadRaw[0] === '\'' && substr($payloadRaw, -1) === '\'') {
                $candidates[] = substr($payloadRaw, 1, -1);
            }
            $candidates[] = html_entity_decode($payloadRaw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $candidates[] = stripslashes($payloadRaw);
            $candidates[] = urldecode($payloadRaw);
            foreach ($candidates as $cand) {
                $try = json_decode((string)$cand, true);
                if (is_array($try)) {
                    $json = $try;
                    break;
                }
            }
        }
        if (!is_array($json) && is_string($raw) && $raw !== '') {
            $form = [];
            parse_str($raw, $form);
            if (isset($form['payload'])) {
                $try = json_decode(html_entity_decode((string)$form['payload'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), true);
                if (is_array($try)) {
                    $json = $try;
                }
            }
        }
    }
    if (!is_array($json)) { echo json_encode(['status'=>'error','message'=>'bad_json']); return; }

    $planId = (int)($json['plan_id'] ?? 0);
    $plan = Capsule::table('eb_plan_templates')->where('id',$planId)->first();
    if (!$plan || (int)$plan->msp_id !== (int)$msp->id) { echo json_encode(['status'=>'error','message'=>'scope']); return; }

    $incoming = (array)($json['components'] ?? []);
    $name = trim((string)($json['name'] ?? $plan->name));
    $description = isset($json['description']) ? trim((string)$json['description']) : (string)$plan->description;
    $trialDays = (int)($json['trial_days'] ?? $plan->trial_days);
    $billingInterval = eb_ph_plan_normalize_interval($json['billing_interval'] ?? $plan->billing_interval ?? 'month');
    $currency = eb_ph_plan_normalize_currency($json['currency'] ?? $plan->currency ?? 'CAD');
    $status = eb_ph_plan_normalize_status($json['status'] ?? $plan->status ?? ($plan->active ? 'active' : 'draft'), $plan->active ? 'active' : 'draft');
    if ($name === '' || $trialDays < 0) { echo json_encode(['status'=>'error','message'=>'invalid']); return; }

    $validation = eb_ph_plan_validate_component_rows(
        (int)$msp->id,
        $incoming,
        $currency,
        $billingInterval,
        $status === 'active',
        $status === 'active'
    );
    if (!$validation['ok']) {
        echo json_encode(['status' => 'error', 'message' => $validation['code'], 'detail' => $validation['message']]);
        return;
    }

    Capsule::table('eb_plan_templates')->where('id',$planId)->update([
        'name' => $name,
        'description' => $description !== '' ? $description : null,
        'trial_days' => $trialDays,
        'billing_interval' => $billingInterval,
        'currency' => $currency,
        'status' => $status,
        'active' => $status === 'active' ? 1 : 0,
        'version' => (int)$plan->version + 1,
        'updated_at' => date('Y-m-d H:i:s'),
    ]);

    // Sync components: diff-based add/update/remove
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
        'currency' => $plan->currency ?? 'CAD', 'version' => 1, 'active' => 0, 'status' => 'draft',
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
    if ($newStatus === 'active') {
        $validation = eb_ph_plan_validate_existing_plan_state($plan, (int)$msp->id, true);
        if (!$validation['ok']) {
            echo json_encode(['status' => 'error', 'message' => $validation['code'], 'detail' => $validation['message']]);
            return;
        }
    }
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

function eb_ph_plan_subscription_payload(): array
{
    $raw = file_get_contents('php://input');
    $json = json_decode((string)$raw, true);
    if (is_array($json)) {
        if (isset($json['token']) && (string)$json['token'] !== '') {
            $_POST['token'] = (string)$json['token'];
        }
        return $json;
    }
    return $_POST;
}

function eb_ph_plan_subscription_find_instance(int $mspId, int $instanceId)
{
    return Capsule::table('eb_plan_instances as pi')
        ->leftJoin('eb_tenants as t', 't.id', '=', 'pi.tenant_id')
        ->leftJoin('eb_plan_templates as pt', 'pt.id', '=', 'pi.plan_id')
        ->where('pi.msp_id', $mspId)
        ->where('pi.id', $instanceId)
        ->first([
            'pi.id', 'pi.msp_id', 'pi.tenant_id', 'pi.comet_user_id',
            'pi.plan_id', 'pi.plan_version', 'pi.stripe_account_id',
            'pi.stripe_customer_id', 'pi.stripe_subscription_id',
            'pi.anchor_date', 'pi.status', 'pi.cancelled_at',
            'pi.cancel_reason', 'pi.created_at', 'pi.updated_at',
            't.name as tenant_name',
            't.public_id as tenant_public_id',
            'pt.name as plan_name',
            'pt.billing_interval as plan_billing_interval',
            'pt.currency as plan_currency',
            'pt.version as plan_template_version',
        ]);
}

function eb_ph_plan_subscription_component_rows(int $planId): array
{
    $rows = Capsule::table('eb_plan_components as pc')
        ->join('eb_catalog_prices as pr', 'pr.id', '=', 'pc.price_id')
        ->where('pc.plan_id', $planId)
        ->orderBy('pc.id', 'asc')
        ->get([
            'pc.id as plan_component_id',
            'pc.plan_id',
            'pc.price_id',
            'pc.metric_code',
            'pc.default_qty',
            'pc.overage_mode',
            'pr.name as price_name',
            'pr.kind',
            'pr.unit_label',
            'pr.unit_amount',
            'pr.currency',
            'pr.interval',
            'pr.stripe_price_id',
        ]);
    return array_map(static function ($row) {
        return (array)$row;
    }, iterator_to_array($rows));
}

function eb_ph_plan_subscription_instance_item_rows(int $instanceId): array
{
    $rows = Capsule::table('eb_plan_instance_items as pii')
        ->join('eb_plan_components as pc', 'pc.id', '=', 'pii.plan_component_id')
        ->join('eb_catalog_prices as pr', 'pr.id', '=', 'pc.price_id')
        ->where('pii.plan_instance_id', $instanceId)
        ->orderBy('pii.id', 'asc')
        ->get([
            'pii.id as plan_instance_item_id',
            'pii.plan_instance_id',
            'pii.plan_component_id',
            'pii.stripe_subscription_item_id',
            'pii.metric_code',
            'pii.last_qty',
            'pc.price_id',
            'pc.default_qty',
            'pc.overage_mode',
            'pr.name as price_name',
            'pr.kind',
            'pr.unit_label',
            'pr.unit_amount',
            'pr.currency',
            'pr.interval',
            'pr.stripe_price_id',
        ]);
    return array_map(static function ($row) {
        return (array)$row;
    }, iterator_to_array($rows));
}

function eb_ph_plan_subscription_available_plans(int $mspId, $instance): array
{
    $rows = Capsule::table('eb_plan_templates')
        ->where('msp_id', $mspId)
        ->where('id', '!=', (int)($instance->plan_id ?? 0))
        ->where('status', 'active')
        ->where('billing_interval', (string)($instance->plan_billing_interval ?? 'month'))
        ->where('currency', (string)($instance->plan_currency ?? 'CAD'))
        ->orderBy('name', 'asc')
        ->get(['id', 'name', 'billing_interval', 'currency', 'version']);

    $plans = [];
    foreach ($rows as $row) {
        $plan = (array)$row;
        $plan['components'] = eb_ph_plan_subscription_component_rows((int)$row->id);
        $plans[] = $plan;
    }
    return $plans;
}

function eb_ph_plan_subscription_items_from_stripe(array $subscription): array
{
    $map = [];
    foreach ((array)($subscription['items']['data'] ?? []) as $item) {
        $map[(string)($item['id'] ?? '')] = $item;
    }
    return $map;
}

function eb_ph_plan_subscription_editor_rows(array $localRows, array $stripeItemMap): array
{
    $items = [];
    foreach ($localRows as $row) {
        $stripeItemId = (string)($row['stripe_subscription_item_id'] ?? '');
        $stripeItem = $stripeItemMap[$stripeItemId] ?? [];
        $kind = (string)($row['kind'] ?? 'recurring');
        $quantity = array_key_exists('quantity', $stripeItem)
            ? (int)$stripeItem['quantity']
            : (($row['last_qty'] !== null) ? (int)$row['last_qty'] : (int)($row['default_qty'] ?? 0));
        $items[] = [
            'plan_component_id' => (int)($row['plan_component_id'] ?? 0),
            'plan_instance_item_id' => (int)($row['plan_instance_item_id'] ?? 0),
            'subscription_item_id' => $stripeItemId,
            'price_id' => (int)($row['price_id'] ?? 0),
            'stripe_price_id' => (string)($row['stripe_price_id'] ?? ''),
            'price_name' => (string)($row['price_name'] ?? ''),
            'metric_code' => (string)($row['metric_code'] ?? 'GENERIC'),
            'kind' => $kind,
            'currency' => (string)($row['currency'] ?? 'CAD'),
            'interval' => (string)($row['interval'] ?? 'month'),
            'unit_label' => (string)($row['unit_label'] ?? ''),
            'unit_amount' => (int)($row['unit_amount'] ?? 0),
            'default_qty' => (int)($row['default_qty'] ?? 0),
            'quantity' => $quantity,
            'editable_quantity' => $kind === 'recurring',
            'removable' => true,
            'remove' => false,
        ];
    }
    return $items;
}

function eb_ph_plan_subscription_update_params(array $desiredItems, array $currentRows): array
{
    $params = [
        'proration_behavior' => 'create_prorations',
    ];
    $index = 0;
    $seenSubscriptionItems = [];

    foreach ($desiredItems as $item) {
        if (!empty($item['remove'])) {
            continue;
        }
        $subscriptionItemId = trim((string)($item['subscription_item_id'] ?? ''));
        if ($subscriptionItemId !== '') {
            $params['items['.$index.'][id]'] = $subscriptionItemId;
            $seenSubscriptionItems[$subscriptionItemId] = true;
        } else {
            $params['items['.$index.'][price]'] = (string)($item['stripe_price_id'] ?? '');
        }

        if ((string)($item['kind'] ?? 'recurring') === 'recurring') {
            $params['items['.$index.'][quantity]'] = max(0, (int)($item['quantity'] ?? 0));
        }
        $index++;
    }

    foreach ($currentRows as $row) {
        $subscriptionItemId = trim((string)($row['stripe_subscription_item_id'] ?? ''));
        if ($subscriptionItemId === '' || isset($seenSubscriptionItems[$subscriptionItemId])) {
            continue;
        }
        $params['items['.$index.'][id]'] = $subscriptionItemId;
        $params['items['.$index.'][deleted]'] = 'true';
        $index++;
    }

    return $params;
}

function eb_ph_plan_subscription_preview_params($instance, array $updateParams): array
{
    $params = [
        'customer' => (string)($instance->stripe_customer_id ?? ''),
        'subscription' => (string)($instance->stripe_subscription_id ?? ''),
    ];
    foreach ($updateParams as $key => $value) {
        if (strpos($key, 'items[') === 0) {
            $params['subscription_'.$key] = $value;
            continue;
        }
        $params['subscription_'.$key] = $value;
    }
    return $params;
}

function eb_ph_plan_subscription_sync_local_state(int $instanceId, int $planId, int $planVersion, array $desiredItems, array $subscription): void
{
    $status = (string)($subscription['status'] ?? 'active');
    $subscriptionItems = [];
    foreach ((array)($subscription['items']['data'] ?? []) as $item) {
        $priceId = (string)($item['price']['id'] ?? '');
        if ($priceId === '') {
            continue;
        }
        if (!isset($subscriptionItems[$priceId])) {
            $subscriptionItems[$priceId] = [];
        }
        $subscriptionItems[$priceId][] = $item;
    }

    Capsule::table('eb_plan_instances')->where('id', $instanceId)->update([
        'plan_id' => $planId,
        'plan_version' => $planVersion,
        'status' => $status === 'active' || $status === 'trialing' || $status === 'past_due' ? $status : 'active',
        'updated_at' => date('Y-m-d H:i:s'),
    ]);

    Capsule::table('eb_plan_instance_usage_map')->whereIn('plan_instance_item_id', function ($query) use ($instanceId) {
        $query->select('id')->from('eb_plan_instance_items')->where('plan_instance_id', $instanceId);
    })->delete();
    Capsule::table('eb_plan_instance_items')->where('plan_instance_id', $instanceId)->delete();

    foreach ($desiredItems as $item) {
        if (!empty($item['remove'])) {
            continue;
        }
        $stripePriceId = (string)($item['stripe_price_id'] ?? '');
        $bucket = $subscriptionItems[$stripePriceId] ?? [];
        $match = array_shift($bucket);
        $subscriptionItems[$stripePriceId] = $bucket;
        if (!$match) {
            continue;
        }

        $instanceItemId = Capsule::table('eb_plan_instance_items')->insertGetId([
            'plan_instance_id' => $instanceId,
            'plan_component_id' => (int)($item['plan_component_id'] ?? 0),
            'stripe_subscription_item_id' => (string)($match['id'] ?? ''),
            'metric_code' => (string)($item['metric_code'] ?? 'GENERIC'),
            'last_qty' => isset($match['quantity']) ? (int)$match['quantity'] : null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if ((string)($item['kind'] ?? 'recurring') === 'metered') {
            Capsule::table('eb_plan_instance_usage_map')->insert([
                'plan_instance_item_id' => $instanceItemId,
                'metric_code' => (string)($item['metric_code'] ?? 'GENERIC'),
                'stripe_subscription_item_id' => (string)($match['id'] ?? ''),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }
}

function eb_ph_plan_subscription_detail(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id', $clientId)->first();
    if (!$msp) { echo json_encode(['status'=>'error','message'=>'msp']); return; }
    $instanceId = (int)($_GET['instance_id'] ?? 0);
    $instance = eb_ph_plan_subscription_find_instance((int)$msp->id, $instanceId);
    if (!$instance) { echo json_encode(['status'=>'error','message'=>'scope']); return; }

    try {
        $subscription = (new StripeService())->retrieveSubscription((string)$instance->stripe_subscription_id, (string)$instance->stripe_account_id);
        $localRows = eb_ph_plan_subscription_instance_item_rows((int)$instance->id);
        $stripeItemMap = eb_ph_plan_subscription_items_from_stripe($subscription);
        echo json_encode([
            'status' => 'success',
            'subscription' => [
                'instance_id' => (int)$instance->id,
                'plan_id' => (int)$instance->plan_id,
                'plan_name' => (string)($instance->plan_name ?? ''),
                'tenant_name' => (string)($instance->tenant_name ?? ''),
                'tenant_public_id' => (string)($instance->tenant_public_id ?? ''),
                'comet_user_id' => (string)($instance->comet_user_id ?? ''),
                'status' => (string)($instance->status ?? ''),
            ],
            'items' => eb_ph_plan_subscription_editor_rows($localRows, $stripeItemMap),
            'available_plans' => eb_ph_plan_subscription_available_plans((int)$msp->id, $instance),
        ]);
    } catch (\Throwable $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
}

function eb_ph_plan_subscription_preview(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    if (function_exists('check_token')) { try { check_token('WHMCS.default'); } catch (\Throwable $__) { echo json_encode(['status'=>'error','message'=>'csrf']); return; } }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id', $clientId)->first();
    if (!$msp) { echo json_encode(['status'=>'error','message'=>'msp']); return; }
    $payload = eb_ph_plan_subscription_payload();
    $instanceId = (int)($payload['instance_id'] ?? 0);
    $instance = eb_ph_plan_subscription_find_instance((int)$msp->id, $instanceId);
    if (!$instance) { echo json_encode(['status'=>'error','message'=>'scope']); return; }
    $desiredItems = is_array($payload['items'] ?? null) ? $payload['items'] : [];
    $currentRows = eb_ph_plan_subscription_instance_item_rows((int)$instance->id);
    $updateParams = eb_ph_plan_subscription_update_params($desiredItems, $currentRows);

    if (!array_filter($desiredItems, static function ($item) { return empty($item['remove']); })) {
        echo json_encode(['status'=>'error','message'=>'empty_subscription']);
        return;
    }

    try {
        $preview = (new StripeService())->previewUpcomingInvoice(
            eb_ph_plan_subscription_preview_params($instance, $updateParams),
            (string)$instance->stripe_account_id
        );
        echo json_encode([
            'status' => 'success',
            'preview' => [
                'amount_due' => (int)($preview['amount_due'] ?? 0),
                'subtotal' => (int)($preview['subtotal'] ?? 0),
                'total' => (int)($preview['total'] ?? 0),
                'currency' => strtoupper((string)($preview['currency'] ?? 'CAD')),
                'line_count' => count((array)($preview['lines']['data'] ?? [])),
            ],
        ]);
    } catch (\Throwable $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
}

function eb_ph_plan_subscription_update(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    if (function_exists('check_token')) { try { check_token('WHMCS.default'); } catch (\Throwable $__) { echo json_encode(['status'=>'error','message'=>'csrf']); return; } }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id', $clientId)->first();
    if (!$msp) { echo json_encode(['status'=>'error','message'=>'msp']); return; }
    $payload = eb_ph_plan_subscription_payload();
    $instanceId = (int)($payload['instance_id'] ?? 0);
    $instance = eb_ph_plan_subscription_find_instance((int)$msp->id, $instanceId);
    if (!$instance) { echo json_encode(['status'=>'error','message'=>'scope']); return; }
    $desiredItems = is_array($payload['items'] ?? null) ? $payload['items'] : [];
    $swapPlanId = (int)($payload['swap_plan_id'] ?? 0);
    $targetPlanId = $swapPlanId > 0 ? $swapPlanId : (int)$instance->plan_id;
    $targetPlanVersion = $swapPlanId > 0
        ? (int)(Capsule::table('eb_plan_templates')->where('id', $swapPlanId)->value('version') ?? $instance->plan_template_version ?? 1)
        : (int)($instance->plan_template_version ?? 1);

    if (!array_filter($desiredItems, static function ($item) { return empty($item['remove']); })) {
        echo json_encode(['status'=>'error','message'=>'empty_subscription']);
        return;
    }

    try {
        $svc = new StripeService();
        $currentRows = eb_ph_plan_subscription_instance_item_rows((int)$instance->id);
        $updated = $svc->updateSubscription(
            (string)$instance->stripe_subscription_id,
            eb_ph_plan_subscription_update_params($desiredItems, $currentRows),
            (string)$instance->stripe_account_id
        );
        eb_ph_plan_subscription_sync_local_state((int)$instance->id, $targetPlanId, $targetPlanVersion, $desiredItems, $updated);
        echo json_encode(['status'=>'success']);
    } catch (\Throwable $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
}

function eb_ph_plan_subscription_pause(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    if (function_exists('check_token')) { try { check_token('WHMCS.default'); } catch (\Throwable $__) { echo json_encode(['status'=>'error','message'=>'csrf']); return; } }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id', $clientId)->first();
    if (!$msp) { echo json_encode(['status'=>'error','message'=>'msp']); return; }
    $instanceId = (int)($_POST['instance_id'] ?? 0);
    $instance = eb_ph_plan_subscription_find_instance((int)$msp->id, $instanceId);
    if (!$instance) { echo json_encode(['status'=>'error','message'=>'scope']); return; }
    try {
        (new StripeService())->pauseSubscription((string)$instance->stripe_subscription_id, (string)$instance->stripe_account_id);
        Capsule::table('eb_plan_instances')->where('id', $instanceId)->update([
            'status' => 'paused',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        echo json_encode(['status'=>'success']);
    } catch (\Throwable $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
}

function eb_ph_plan_subscription_resume(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    if (function_exists('check_token')) { try { check_token('WHMCS.default'); } catch (\Throwable $__) { echo json_encode(['status'=>'error','message'=>'csrf']); return; } }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id', $clientId)->first();
    if (!$msp) { echo json_encode(['status'=>'error','message'=>'msp']); return; }
    $instanceId = (int)($_POST['instance_id'] ?? 0);
    $instance = eb_ph_plan_subscription_find_instance((int)$msp->id, $instanceId);
    if (!$instance) { echo json_encode(['status'=>'error','message'=>'scope']); return; }
    try {
        $sub = (new StripeService())->resumeSubscription((string)$instance->stripe_subscription_id, (string)$instance->stripe_account_id);
        Capsule::table('eb_plan_instances')->where('id', $instanceId)->update([
            'status' => (string)($sub['status'] ?? 'active'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        echo json_encode(['status'=>'success']);
    } catch (\Throwable $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
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
        ->get(['pi.id', 'pi.comet_user_id', 'pi.status', 'pi.created_at', 't.name as tenant_name', 't.public_id as tenant_public_id']);
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
        echo json_encode(['status'=>'error','message'=>'stripe_cancel_failed']);
        return;
    }

    Capsule::table('eb_plan_instances')->where('id',$instanceId)->update([
        'status' => 'canceled', 'cancelled_at' => date('Y-m-d H:i:s'), 'cancel_reason' => $reason !== '' ? $reason : null, 'updated_at' => date('Y-m-d H:i:s'),
    ]);
    echo json_encode(['status'=>'success']);
}

function eb_ph_plan_export(array $vars): void
{
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { header('HTTP/1.1 401 Unauthorized'); exit; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { header('HTTP/1.1 403 Forbidden'); exit; }
    $format = (string)($_GET['format'] ?? 'json');
    $plans = Capsule::table('eb_plan_templates')->where('msp_id',(int)$msp->id)->orderBy('id','asc')->get();
    $allComponents = Capsule::table('eb_plan_components as pc')
        ->join('eb_catalog_prices as pr','pr.id','=','pc.price_id')
        ->join('eb_plan_templates as pt','pt.id','=','pc.plan_id')
        ->where('pt.msp_id',(int)$msp->id)
        ->get(['pc.*','pr.name as price_name','pr.metric_code as price_metric','pr.unit_amount as price_amount','pr.currency as price_currency']);

    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="plans-export.csv"');
        $out = fopen('php://output','w');
        fprintf($out, "\xEF\xBB\xBF");
        fputcsv($out, ['plan_id','plan_name','description','trial_days','billing_interval','currency','status','component_price','component_metric','component_qty','component_overage']);
        foreach ($plans as $p) {
            $hasComps = false;
            foreach ($allComponents as $c) {
                if ((int)$c->plan_id === (int)$p->id) {
                    fputcsv($out, [$p->id, $p->name, $p->description, $p->trial_days, $p->billing_interval ?? 'month', $p->currency ?? 'CAD', $p->status ?? 'active', $c->price_name, $c->price_metric, $c->default_qty, $c->overage_mode]);
                    $hasComps = true;
                }
            }
            if (!$hasComps) { fputcsv($out, [$p->id, $p->name, $p->description, $p->trial_days, $p->billing_interval ?? 'month', $p->currency ?? 'CAD', $p->status ?? 'active', '', '', '', '']); }
        }
        fclose($out); exit;
    }

    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="plans-export.json"');
    $result = [];
    foreach ($plans as $p) {
        $comps = [];
        foreach ($allComponents as $c) { if ((int)$c->plan_id === (int)$p->id) { $comps[] = (array)$c; } }
        $result[] = ['plan' => (array)$p, 'components' => $comps];
    }
    echo json_encode($result, JSON_PRETTY_PRINT);
    exit;
}
