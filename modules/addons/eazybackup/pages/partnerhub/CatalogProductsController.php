<?php

use WHMCS\Database\Capsule;
use PartnerHub\StripeService;
use PartnerHub\CatalogService;

function eb_ph_catalog_products_index(array $vars)
{
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { header('Location: clientarea.php'); exit; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { header('Location: '.$vars['modulelink'].'&a=ph-clients'); exit; }

    // Ensure schema exists (defensive) to avoid 500s on first deploy
    try {
        $schema = Capsule::schema();
        $need = [];
        foreach (['eb_catalog_products','eb_catalog_prices'] as $t) {
            if (!$schema->hasTable($t)) { $need[] = $t; }
        }
        $needsColumn = false;
        try { if ($schema->hasTable('eb_catalog_products') && !$schema->hasColumn('eb_catalog_products','base_metric_code')) { $needsColumn = true; } } catch (\Throwable $__) {}
        if ((!empty($need) || $needsColumn) && function_exists('eazybackup_migrate_schema')) { @eazybackup_migrate_schema(); }
    } catch (\Throwable $__) { /* ignore */ }

    $products = Capsule::table('eb_catalog_products as p')
        ->where('p.msp_id',(int)$msp->id)
        ->orderBy('p.updated_at','desc')
        ->get();

    $prices = Capsule::table('eb_catalog_prices as pr')
        ->join('eb_catalog_products as p','p.id','=','pr.product_id')
        ->where('p.msp_id',(int)$msp->id)
        ->orderBy('pr.updated_at','desc')
        ->get(['pr.*']);

    // Normalize to arrays for Smarty (avoid stdClass access errors)
    $productsArr = [];
    foreach ((array)$products as $r) {
        $row = (array)$r;
        // Decode features_json for template convenience
        try {
            $fx = isset($row['features_json']) ? (string)$row['features_json'] : '';
            if ($fx !== '') {
                $arr = json_decode($fx, true);
                if (is_array($arr)) { $row['features'] = array_values(array_filter($arr, 'strlen')); }
            }
        } catch (\Throwable $___) {}
        $productsArr[] = $row;
    }

    // Map product_id => prices (arrays)
    $priceMapArr = [];
    foreach ((array)$prices as $row) {
        $pid = (string)$row->product_id;
        if (!isset($priceMapArr[$pid])) { $priceMapArr[$pid] = []; }
        $priceMapArr[$pid][] = (array)$row;
    }

    // MSP readiness + default currency (fallback to live Stripe if DB flags are stale)
    $chargesEnabled = (int)($msp->charges_enabled ?? 0) === 1;
    $payoutsEnabled = (int)($msp->payouts_enabled ?? 0) === 1;
    try {
        if (!$chargesEnabled || !$payoutsEnabled) {
            $acctId = (string)($msp->stripe_connect_id ?? '');
            if ($acctId !== '') {
                $svcStripe = new StripeService();
                $acct = $svcStripe->retrieveAccount($acctId);
                $chargesEnabled = (bool)($acct['charges_enabled'] ?? $chargesEnabled);
                $payoutsEnabled = (bool)($acct['payouts_enabled'] ?? ($acct['transfers_enabled'] ?? $payoutsEnabled));
            }
        }
    } catch (\Throwable $__) { /* ignore live fallback errors */ }
    $mspCurrency = (string)($msp->default_currency ?? 'CAD');

    // Fetch Stripe (connected account) products so UI can list all owned products
    $stripeProductsArr = [];
    try {
        $acctId = (string)($msp->stripe_connect_id ?? '');
        if ($acctId !== '') {
            $svcCatalog = new CatalogService();
            $remote = $svcCatalog->listProducts($acctId, 100);
            if (isset($remote['data']) && is_array($remote['data'])) {
                foreach ($remote['data'] as $p) {
                    if (!is_array($p)) { continue; }
                    // Fetch prices (active + inactive) to render status badges like Stripe UI
                    $pricesData = [];
                    try {
                        $lp = $svcCatalog->listPrices((string)($p['id'] ?? ''), $acctId, 100, false);
                        if (isset($lp['data']) && is_array($lp['data'])) {
                            foreach ($lp['data'] as $pr) {
                                $kind = 'recurring';
                                if ((string)($pr['type'] ?? '') === 'one_time') { $kind = 'one_time'; }
                                else if (isset($pr['recurring']['usage_type']) && $pr['recurring']['usage_type'] === 'metered') { $kind = 'metered'; }
                                $pricesData[] = [
                                    'id' => (string)($pr['id'] ?? ''),
                                    'nickname' => (string)($pr['nickname'] ?? ''),
                                    'currency' => strtoupper((string)($pr['currency'] ?? ($msp->default_currency ?? 'CAD'))),
                                    'unit_amount' => (int)($pr['unit_amount'] ?? 0),
                                    'interval' => ($kind === 'one_time') ? 'none' : (string)($pr['recurring']['interval'] ?? 'month'),
                                    'kind' => $kind,
                                    'active' => (bool)($pr['active'] ?? true),
                                    'unit_label' => (string)($pr['unit_label'] ?? ''),
                                ];
                            }
                        }
                    } catch (\Throwable $___) {}
                    // Attempt to map local features
                    $features = [];
                    try {
                        $local = Capsule::table('eb_catalog_products')->where('msp_id',(int)$msp->id)->where('stripe_product_id',(string)($p['id'] ?? ''))->first();
                        if ($local) { $rawf = (string)($local->features_json ?? ''); if ($rawf !== '') { $arr = json_decode($rawf, true); if (is_array($arr)) { $features = array_values(array_filter($arr, 'strlen')); } } }
                    } catch (\Throwable $____) {}
                    $stripeProductsArr[] = [
                        'id' => (string)($p['id'] ?? ''),
                        'name' => (string)($p['name'] ?? ''),
                        'description' => isset($p['description']) ? (string)$p['description'] : '',
                        'active' => (int)((bool)($p['active'] ?? true) ? 1 : 0),
                        'created' => (int)($p['created'] ?? 0),
                        'prices' => $pricesData,
                        'features' => $features,
                    ];
                }
            }
        }
    } catch (\Throwable $__) { /* non-fatal; keep page rendering */ }

    return [
        'pagetitle' => 'Catalog — Products',
        'templatefile' => 'whitelabel/catalog-products',
        'breadcrumb' => [ 'index.php?m=eazybackup' => 'eazyBackup' ],
        'requirelogin' => true,
        'forcessl' => true,
        'vars' => [
            'msp' => (array)$msp,
            'msp_ready' => ($chargesEnabled && $payoutsEnabled) ? 1 : 0,
            'msp_currency' => $mspCurrency,
            'products' => $productsArr,
            'priceMap' => $priceMapArr,
            'modulelink' => $vars['modulelink'] ?? ('index.php?m=eazybackup'),
            'token' => function_exists('generate_token') ? generate_token('plain') : '',
            'stripe_products' => $stripeProductsArr,
        ],
    ];
}

/** Stripe-style Products list page (Stripe-connected products only) */
function eb_ph_catalog_products_list(array $vars)
{
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { header('Location: clientarea.php'); exit; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { header('Location: '.$vars['modulelink'].'&a=ph-clients'); exit; }

    // Company name (fallbacks)
    $company = '';
    try {
        $c = Capsule::table('tblclients')->where('id',(int)$clientId)->first();
        if ($c) { $company = (string)($c->companyname ?: trim(((string)$c->firstname.' '.(string)$c->lastname))); }
    } catch (\Throwable $__) {}

    // Stripe account readiness
    $acctInfo = [ 'charges_enabled' => (int)($msp->charges_enabled ?? 0) === 1, 'payouts_enabled' => (int)($msp->payouts_enabled ?? 0) === 1 ];
    try {
        $acctId = (string)($msp->stripe_connect_id ?? '');
        if ($acctId !== '') {
            $svcStripe = new StripeService();
            $acct = $svcStripe->retrieveAccount($acctId);
            $acctInfo['charges_enabled'] = (bool)($acct['charges_enabled'] ?? $acctInfo['charges_enabled']);
            $acctInfo['payouts_enabled'] = (bool)($acct['payouts_enabled'] ?? ($acct['transfers_enabled'] ?? $acctInfo['payouts_enabled']));
        }
    } catch (\Throwable $__) {}

    // Stripe products (with price summaries)
    $stripeProducts = [];
    $countAll = 0; $countActive = 0; $countArchived = 0;
    try {
        $acctId = (string)($msp->stripe_connect_id ?? '');
        if ($acctId !== '') {
            $svcCatalog = new CatalogService();
            $remote = $svcCatalog->listProducts($acctId, 100);
            if (isset($remote['data']) && is_array($remote['data'])) {
                foreach ($remote['data'] as $p) {
                    if (!is_array($p)) { continue; }
                    $countAll++;
                    $isActive = (bool)($p['active'] ?? true);
                    if ($isActive) { $countActive++; } else { $countArchived++; }
                    $prices = [];
                    try {
                        $lp = $svcCatalog->listPrices((string)($p['id'] ?? ''), $acctId, 100, null);
                        if (isset($lp['data']) && is_array($lp['data'])) {
                            foreach ($lp['data'] as $pr) {
                                $kind = 'recurring';
                                if ((string)($pr['type'] ?? '') === 'one_time') { $kind = 'one_time'; }
                                else if (isset($pr['recurring']['usage_type']) && $pr['recurring']['usage_type'] === 'metered') { $kind = 'metered'; }
                                $prices[] = [
                                    'id' => (string)($pr['id'] ?? ''),
                                    'nickname' => (string)($pr['nickname'] ?? ''),
                                    'currency' => strtoupper((string)($pr['currency'] ?? ($msp->default_currency ?? 'CAD'))),
                                    'unit_amount' => (int)($pr['unit_amount'] ?? 0),
                                    'interval' => ($kind === 'one_time') ? 'none' : (string)($pr['recurring']['interval'] ?? 'month'),
                                    'kind' => $kind,
                                    'active' => (bool)($pr['active'] ?? true),
                                    'created' => (int)($pr['created'] ?? 0),
                                ];
                            }
                        }
                    } catch (\Throwable $___) {}
                    // Simple summary: prefer first active price; else show count
                    $summary = '';
                    foreach ($prices as $pr) {
                        if ($pr['active']) { $summary = $pr['currency'].' '.number_format($pr['unit_amount']/100,2).(($pr['interval']!=='none')?' / '.$pr['interval']:''); break; }
                    }
                    if ($summary === '') { $summary = (count($prices) > 0 ? (string)count($prices).' prices' : '—'); }
                    $updated = 0; foreach ($prices as $pr) { $updated = max($updated, (int)$pr['created']); }
                    $stripeProducts[] = [
                        'id' => (string)($p['id'] ?? ''),
                        'name' => (string)($p['name'] ?? ''),
                        'description' => isset($p['description']) ? (string)$p['description'] : '',
                        'active' => $isActive ? 1 : 0,
                        'created' => (int)($p['created'] ?? 0),
                        'updated' => (int)$updated,
                        'pricing_summary' => $summary,
                        'price_count' => (int)count($prices),
                    ];
                }
            }
        }
    } catch (\Throwable $__) {}

    return [
        'pagetitle' => 'Catalog — Products',
        'templatefile' => 'whitelabel/catalog-products-list',
        'breadcrumb' => [ 'index.php?m=eazybackup' => 'eazyBackup' ],
        'requirelogin' => true,
        'forcessl' => true,
        'vars' => [
            'msp' => (array)$msp,
            'company' => $company,
            'msp_ready' => ($acctInfo['charges_enabled'] && $acctInfo['payouts_enabled']) ? 1 : 0,
            'acct_info' => $acctInfo,
            'stripe_products' => $stripeProducts,
            'count_all' => $countAll,
            'count_active' => $countActive,
            'count_archived' => $countArchived,
            'modulelink' => $vars['modulelink'] ?? ('index.php?m=eazybackup'),
            'token' => function_exists('generate_token') ? generate_token('plain') : '',
        ],
    ];
}

/** Product detail page wrapper (reuses existing template/UI) */
function eb_ph_catalog_product_show(array $vars)
{
    // For now, reuse the current composite template to edit products/prices
    return eb_ph_catalog_products_index($vars);
}

function eb_ph_catalog_product_save(array $vars): void
{
    header('Content-Type: application/json');
    // Lightweight diagnostics
    try { if (function_exists('logModuleCall')) { @logModuleCall('eazybackup','ph-catalog-product-save:enter',[ 'ctype'=>($_SERVER['CONTENT_TYPE'] ?? ''), 'len'=>strlen((string)file_get_contents('php://input')), 'post_keys'=>array_keys($_POST ?? []) ],''); } } catch (\Throwable $__) {}
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { echo json_encode(['status'=>'error','message'=>'msp']); return; }
    $raw = file_get_contents('php://input');
    $body = (string)$raw;
    $json = json_decode($body, true);
    if (!is_array($json)) {
        // Fallback: form-encoded payload
        if (isset($_POST['payload'])) {
            $json = json_decode((string)$_POST['payload'], true);
        }
    }
    if (!is_array($json)) { echo json_encode(['status'=>'error','message'=>'bad_json']); return; }
    $mode = (string)($json['mode'] ?? 'draft');
    $productIdExisting = (int)($json['product_id'] ?? 0);
    $product = (array)($json['product'] ?? []);
    $baseMetric = (string)($json['base_metric_code'] ?? '');
    $items = (array)($json['items'] ?? []);
    $features = (array)($json['features'] ?? []);
    $name = trim((string)($product['name'] ?? ''));
    $category = (string)($product['category'] ?? '');
    $description = trim((string)($product['description'] ?? ''));
    // Derive sensible category if omitted or invalid; store as 'Backup' to match DB enum
    if ($category === '' || !in_array($category,['Backup','Cloud Storage','Services','Other'],true)) {
        $category = 'Backup';
    }
    if ($name === '') { echo json_encode(['status'=>'error','message'=>'invalid_product']); return; }
    if ($mode === 'publish' && count($items) < 1) { echo json_encode(['status'=>'error','message'=>'no_items']); return; }

    // MSP currency & readiness gate
    $mspCurrency = (string)($msp->default_currency ?? 'CAD');
    $chargesEnabled = (int)($msp->charges_enabled ?? 0) === 1;
    $payoutsEnabled = (int)($msp->payouts_enabled ?? 0) === 1;
    if ($mode === 'publish' && (!$chargesEnabled || !$payoutsEnabled)) {
        // Fallback: re-check live account flags in case DB is stale
        try {
            $acct = (string)($msp->stripe_connect_id ?? '');
            if ($acct !== '') {
                $svcStripeCheck = new StripeService();
                $acc = $svcStripeCheck->retrieveAccount($acct);
                $chargesEnabled = (bool)($acc['charges_enabled'] ?? false);
                $payoutsEnabled = (bool)($acc['payouts_enabled'] ?? ($acc['transfers_enabled'] ?? false));
            }
        } catch (\Throwable $__) { /* ignore */ }
        if (!$chargesEnabled || !$payoutsEnabled) { echo json_encode(['status'=>'error','message'=>'stripe_not_ready']); return; }
    }

    // Ensure tables exist
    try {
        $sch = Capsule::schema();
        $missing = false;
        if (!$sch->hasTable('eb_catalog_products')) { $missing = true; }
        else if (!$sch->hasColumn('eb_catalog_products','base_metric_code')) { $missing = true; }
        // Ensure features_json column exists for marketing features
        try { if ($sch->hasTable('eb_catalog_products') && !$sch->hasColumn('eb_catalog_products','features_json')) { $sch->table('eb_catalog_products', function($table){ try { $table->text('features_json')->nullable(); } catch (\Throwable $___) {} }); } } catch (\Throwable $___) {}
        if ($missing && function_exists('eazybackup_migrate_schema')) { @eazybackup_migrate_schema(); }
    } catch (\Throwable $__) {}

    // Create or update product draft
    try {
        if ($productIdExisting > 0) {
            $existing = Capsule::table('eb_catalog_products')->where('id',$productIdExisting)->first();
            if (!$existing || (int)$existing->msp_id !== (int)$msp->id) { echo json_encode(['status'=>'error','message'=>'scope']); return; }
            Capsule::table('eb_catalog_products')->where('id',$productIdExisting)->update([
                'name' => $name,
                'description' => $description !== '' ? $description : null,
                'category' => $category,
                'base_metric_code' => ($baseMetric !== '' ? $baseMetric : ($existing->base_metric_code ?? null)),
                'features_json' => (!empty($features) ? json_encode(array_values(array_filter($features, 'strlen'))) : null),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $productId = $productIdExisting;
        } else {
            $productId = Capsule::table('eb_catalog_products')->insertGetId([
                'msp_id' => (int)$msp->id,
                'name' => $name,
                'description' => $description !== '' ? $description : null,
                'category' => $category,
                'stripe_product_id' => null,
                'active' => 1,
                'is_published' => 0,
                'default_currency' => $mspCurrency,
                'base_metric_code' => ($baseMetric !== '' ? $baseMetric : null),
                'features_json' => (!empty($features) ? json_encode(array_values(array_filter($features, 'strlen'))) : null),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    } catch (\Throwable $e) { echo json_encode(['status'=>'error','message'=>'persist_fail']); return; }

    $persisted = [];
    foreach ($items as $it) {
        $label = trim((string)($it['label'] ?? ''));
        $billingType = (string)($it['billingType'] ?? 'per_unit');
        $metric = (string)($it['metric'] ?? 'GENERIC');
        $unitLabel = trim((string)($it['unitLabel'] ?? 'unit'));
        $amount = (float)($it['amount'] ?? 0);
        $interval = (string)($it['interval'] ?? 'month');
        $active = (int)((bool)($it['active'] ?? true)) ? 1 : 0;
        $rowId = (int)($it['id'] ?? 0);
        if ($label === '' || $amount < 0.01) { continue; }
        // Enforce product base metric when present
        try {
            $prodBase = (string)Capsule::table('eb_catalog_products')->where('id',(int)$productId)->value('base_metric_code');
            if ($prodBase !== '' && $prodBase !== $metric) { echo json_encode(['status'=>'error','message'=>'mismatched_metric']); return; }
        } catch (\Throwable $__) {}
        if ($metric === 'STORAGE_TB') { $billingType = 'metered'; if ($unitLabel === '') { $unitLabel = 'GB'; } }
        if (in_array($metric,['DEVICE_COUNT','DISK_IMAGE','HYPERV_VM','PROXMOX_VM','VMWARE_VM','M365_USER'])) { $billingType = 'per_unit'; }
        if ($billingType === 'one_time') { $interval = 'none'; }
        $amountCents = (int)round($amount * 100);
        $amountPerGbCents = null; $displayPerTbMoney = null;
        if ($metric === 'STORAGE_TB') {
            // Interpret amount based on unit label: GiB or TiB
            if (strtoupper($unitLabel) === 'GIB') {
                $amountPerGbCents = $amountCents; // entered per GiB
                $displayPerTbMoney = (int)($amountCents * 1024);
            } elseif (strtoupper($unitLabel) === 'TIB') {
                $displayPerTbMoney = $amountCents; // entered per TiB
                $amountPerGbCents = (int)round($amountCents/1024);
            } else {
                // fallback for legacy
                $displayPerTbMoney = $amountCents;
                $amountPerGbCents = (int)round($amountCents/1024);
            }
        }
        $kind = $billingType === 'metered' ? 'metered' : ($billingType === 'one_time' ? 'one_time' : 'recurring');

        if ($rowId > 0) {
            // Update or version price row
            $existing = Capsule::table('eb_catalog_prices')->where('id',$rowId)->first();
            if ($existing && (int)$existing->product_id === (int)$productId) {
                if ((string)($existing->stripe_price_id ?? '') === '') {
                    // Unpublished: update in place
                    Capsule::table('eb_catalog_prices')->where('id',$rowId)->update([
                        'name' => $label,
                        'kind' => $kind,
                        'currency' => $mspCurrency,
                        'unit_label' => $unitLabel,
                        'unit_amount' => $amountCents,
                        'interval' => $interval,
                        'aggregate_usage' => $billingType === 'metered' ? 'sum' : null,
                        'metric_code' => $metric,
                        'billing_type' => $billingType,
                        'amount_per_gb_cents' => $amountPerGbCents,
                        'display_per_tb_money' => $displayPerTbMoney,
                        'active' => $active,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    $persisted[] = $rowId;
                } else {
                    // Published: create a new version row
                    $newId = Capsule::table('eb_catalog_prices')->insertGetId([
                        'product_id' => (int)$productId,
                        'name' => $label,
                        'kind' => $kind,
                        'currency' => $mspCurrency,
                        'unit_label' => $unitLabel,
                        'unit_amount' => $amountCents,
                        'interval' => $interval,
                        'aggregate_usage' => $billingType === 'metered' ? 'sum' : null,
                        'metric_code' => $metric,
                        'billing_type' => $billingType,
                        'version' => (int)$existing->version + 1,
                        'supersedes_price_id' => (int)$existing->id,
                        'is_published' => 0,
                        'published_at' => null,
                        'last_publish_request_id' => null,
                        'amount_per_gb_cents' => $amountPerGbCents,
                        'display_per_tb_money' => $displayPerTbMoney,
                        'active' => $active,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    $persisted[] = $newId;
                }
            }
        } else {
            // New price row
            $priceId = Capsule::table('eb_catalog_prices')->insertGetId([
                'product_id' => (int)$productId,
                'name' => $label,
                'kind' => $kind,
                'currency' => $mspCurrency,
                'unit_label' => $unitLabel,
                'unit_amount' => $amountCents,
                'interval' => $interval,
                'aggregate_usage' => $billingType === 'metered' ? 'sum' : null,
                'metric_code' => $metric,
                'billing_type' => $billingType,
                'version' => 1,
                'supersedes_price_id' => null,
                'is_published' => 0,
                'published_at' => null,
                'last_publish_request_id' => null,
                'amount_per_gb_cents' => $amountPerGbCents,
                'display_per_tb_money' => $displayPerTbMoney,
                'active' => $active,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $persisted[] = $priceId;
        }
    }

    if ($mode === 'draft') { echo json_encode(['status'=>'success','product_id'=>$productId,'prices'=>$persisted]); return; }

    // Publish on Stripe
    try {
        $svc = new CatalogService();
        $acct = (string)($msp->stripe_connect_id ?? '');
        if ($acct === '') { echo json_encode(['status'=>'error','message'=>'not_connected']); return; }
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i','-', $name));
        // Pre-check for duplicate product name on Stripe (case-insensitive) to provide clear UX
        try {
            $existing = $svc->listProducts($acct, 100);
            if (isset($existing['data']) && is_array($existing['data'])) {
                foreach ($existing['data'] as $p) {
                    if (isset($p['name']) && strtolower((string)$p['name']) === strtolower($name)) {
                        echo json_encode(['status'=>'error','message'=>'stripe_name_exists','stripe_product_id'=>(string)($p['id'] ?? '')]);
                        return;
                    }
                }
            }
        } catch (\Throwable $__) { /* non-fatal */ }
        $prodRes = [];
        try {
            $prodRes = $svc->createProduct($name, $description ?: null, $acct, 'product:'.$msp->id.':'.$slug);
        } catch (\Throwable $se) {
            // Try to reconcile: if idempotency key mismatch, look for an existing product with the same name and adopt it
            $detail = $se->getMessage();
            try { if (function_exists('logModuleCall')) { @logModuleCall('eazybackup','ph-catalog-publish-product',[ 'name'=>$name ], $detail); } } catch (\Throwable $__) {}
            if (stripos($detail, 'idempotent') !== false || stripos($detail, 'Idempotency') !== false) {
                try {
                    $list = $svc->listProducts($acct, 100);
                    if (isset($list['data']) && is_array($list['data'])) {
                        foreach ($list['data'] as $p) {
                            if (isset($p['name']) && strtolower((string)$p['name']) === strtolower($name)) { $prodRes = $p; break; }
                        }
                    }
                } catch (\Throwable $__) { /* ignore */ }
            }
            if (empty($prodRes)) { echo json_encode(['status'=>'error','message'=>'stripe_product_fail','detail'=>$detail]); return; }
        }
        $stripeProductId = (string)($prodRes['id'] ?? '');
        if ($stripeProductId === '') { echo json_encode(['status'=>'error','message'=>'stripe_product_fail']); return; }
        Capsule::table('eb_catalog_products')->where('id',(int)$productId)->update([
            'stripe_product_id' => $stripeProductId,
            'is_published' => 1,
            'published_at' => date('Y-m-d H:i:s'),
        ]);
        $rows = Capsule::table('eb_catalog_prices')->whereIn('id',$persisted)->get();
        $created=0; $total=count($rows);
        foreach ($rows as $row) {
            $params = [ 'product'=>$stripeProductId, 'currency'=>strtolower($mspCurrency), 'unit_amount'=>(int)$row->unit_amount, 'nickname'=>(string)$row->name ];
            if ((string)$row->kind === 'recurring') { $params['recurring[interval]'] = (string)$row->interval; $params['billing_scheme']='per_unit'; }
            elseif ((string)$row->kind === 'metered') { $params['recurring[interval]']=(string)$row->interval; $params['recurring[usage_type]']='metered'; $params['recurring[aggregate_usage]']='sum'; }
            try {
                $priceRes = $svc->createPrice($params, $acct, 'price:'.$productId.':v'.$row->version.':'.$row->id);
            } catch (\Throwable $se) {
                try { if (function_exists('logModuleCall')) { @logModuleCall('eazybackup','ph-catalog-publish-price',[ 'params'=>$params ], $se->getMessage()); } } catch (\Throwable $__) {}
                echo json_encode(['status'=>'error','message'=>'stripe_price_fail','detail'=>$se->getMessage()]); return;
            }
            $spid = (string)($priceRes['id'] ?? ''); if ($spid==='') { throw new \RuntimeException('price_fail'); }
            Capsule::table('eb_catalog_prices')->where('id',(int)$row->id)->update([
                'stripe_price_id'=>$spid,
                'is_published'=>1,
                'published_at'=>date('Y-m-d H:i:s'),
                'last_publish_request_id'=>'price:'.$productId.':v'.$row->version.':'.$row->id,
            ]);
            $created++;
        }
        echo json_encode(['status'=>'success','product_id'=>$productId,'stripe_product_id'=>$stripeProductId,'created'=>$created,'total'=>$total]);
        return;
    } catch (\Throwable $e) {
        try { if (function_exists('logModuleCall')) { @logModuleCall('eazybackup','ph-catalog-publish-fail',[], $e->getMessage()); } } catch (\Throwable $__) {}
        echo json_encode(['status'=>'error','message'=>'publish_fail','detail'=>$e->getMessage()]); return;
    }
}

function eb_ph_catalog_product_get(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { echo json_encode(['status'=>'error','message'=>'msp']); return; }
    $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    if ($id <= 0) { echo json_encode(['status'=>'error','message'=>'id']); return; }
    try {
        $prod = Capsule::table('eb_catalog_products')->where('id',$id)->first();
        if (!$prod || (int)$prod->msp_id !== (int)$msp->id) { echo json_encode(['status'=>'error','message'=>'scope']); return; }
        $prices = Capsule::table('eb_catalog_prices')->where('product_id',$id)->orderBy('updated_at','desc')->get();
        $pricesArr = [];
        $metricsSet = [];
        foreach ((array)$prices as $r) { $pricesArr[] = (array)$r; $metricsSet[(string)$r->metric_code] = true; }
        $mixed = (count(array_keys($metricsSet)) > 1);
        $features = [];
        try { $rawf = (string)($prod->features_json ?? ''); if ($rawf !== '') { $arr = json_decode($rawf, true); if (is_array($arr)) { $features = array_values(array_filter($arr, 'strlen')); } } } catch (\Throwable $__) {}
        $outProd = (array)$prod; $outProd['features'] = $features;
        echo json_encode(['status'=>'success','product'=>$outProd,'prices'=>$pricesArr,'mixed_metrics'=>$mixed]);
        return;
    } catch (\Throwable $e) {
        echo json_encode(['status'=>'error','message'=>'exception']);
        return;
    }
}

function eb_ph_catalog_product_split(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { echo json_encode(['status'=>'error','message'=>'msp']); return; }
    $productId = (int)($_POST['product_id'] ?? 0);
    $metric = (string)($_POST['metric_code'] ?? '');
    if ($productId <= 0 || $metric === '') { echo json_encode(['status'=>'error','message'=>'bad_input']); return; }
    try {
        $prod = Capsule::table('eb_catalog_products')->where('id',$productId)->first();
        if (!$prod || (int)$prod->msp_id !== (int)$msp->id) { echo json_encode(['status'=>'error','message'=>'scope']); return; }
        $prices = Capsule::table('eb_catalog_prices')->where('product_id',$productId)->where('metric_code',$metric)->get();
        if (count($prices) === 0) { echo json_encode(['status'=>'error','message'=>'none']); return; }
        $newId = Capsule::table('eb_catalog_products')->insertGetId([
            'msp_id' => (int)$msp->id,
            'name' => (string)$prod->name . ' — ' . $metric,
            'description' => (string)($prod->description ?? ''),
            'category' => (string)($prod->category ?? 'Backup'),
            'stripe_product_id' => null,
            'active' => 1,
            'is_published' => 0,
            'default_currency' => (string)($prod->default_currency ?? 'CAD'),
            'base_metric_code' => $metric,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        Capsule::table('eb_catalog_prices')->where('product_id',$productId)->where('metric_code',$metric)->update([
            'product_id' => (int)$newId,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        echo json_encode(['status'=>'success','new_product_id'=>$newId]);
        return;
    } catch (\Throwable $e) {
        echo json_encode(['status'=>'error','message'=>'exception']);
        return;
    }
}

function eb_ph_catalog_products_create(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { echo json_encode(['status'=>'error','message'=>'msp']); return; }

    // Ensure tables exist
    try { if (!Capsule::schema()->hasTable('eb_catalog_products') && function_exists('eazybackup_migrate_schema')) { @eazybackup_migrate_schema(); } } catch (\Throwable $__) {}

    $name = trim((string)($_POST['name'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $category = (string)($_POST['category'] ?? 'Backup');
    if ($name === '' || !in_array($category,['Backup','Services','Other'],true)) { echo json_encode(['status'=>'error','message'=>'invalid']); return; }

    $id = Capsule::table('eb_catalog_products')->insertGetId([
        'msp_id' => (int)$msp->id,
        'name' => $name,
        'description' => $description !== '' ? $description : null,
        'category' => $category,
        'stripe_product_id' => null,
        'active' => 1,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ]);
    try { if (function_exists('logModuleCall')) { @logModuleCall('eazybackup','ph-catalog-products-create',[ 'name'=>$name,'category'=>$category ],[ 'product_id'=>$id ]); } } catch (\Throwable $__) {}
    echo json_encode(['status'=>'success','id'=>$id]);
}

function eb_ph_catalog_price_create(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    if (function_exists('check_token')) { try { check_token('WHMCS.default'); } catch (\Throwable $__) { echo json_encode(['status'=>'error','message'=>'csrf']); return; } }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { echo json_encode(['status'=>'error','message'=>'msp']); return; }

    // Ensure tables exist
    try { if (!Capsule::schema()->hasTable('eb_catalog_prices') && function_exists('eazybackup_migrate_schema')) { @eazybackup_migrate_schema(); } } catch (\Throwable $__) {}

    $productId = (int)($_POST['product_id'] ?? 0);
    $name = trim((string)($_POST['name'] ?? ''));
    $kind = (string)($_POST['kind'] ?? 'recurring');
    $currency = strtoupper((string)($_POST['currency'] ?? 'CAD'));
    $unitLabel = trim((string)($_POST['unit_label'] ?? ''));
    $unitAmountCents = (int)($_POST['unit_amount'] ?? 0);
    $interval = (string)($_POST['interval'] ?? 'month');
    $aggregateUsage = isset($_POST['aggregate_usage']) ? (string)$_POST['aggregate_usage'] : null;
    $metricCode = (string)($_POST['metric_code'] ?? 'GENERIC');

    if ($productId <= 0) {
        // Fallback: if MSP has exactly one product, default to it; otherwise error with explicit message
        try {
            $only = Capsule::table('eb_catalog_products')->where('msp_id',(int)$msp->id)->limit(2)->get(['id']);
            if (count($only) === 1) { $productId = (int)$only[0]->id; }
        } catch (\Throwable $__) {}
        if ($productId <= 0) { echo json_encode(['status'=>'error','message'=>'missing_product_id']); return; }
    }
    if ($name === '' || $unitAmountCents <= 0) { echo json_encode(['status'=>'error','message'=>'invalid']); return; }
    if (!in_array($kind,['recurring','metered','one_time'],true)) { echo json_encode(['status'=>'error','message'=>'invalid_kind']); return; }
    if (!in_array($interval,['month','year','none'],true)) { echo json_encode(['status'=>'error','message'=>'invalid_interval']); return; }
    if ($kind !== 'one_time' && $interval === 'none') { echo json_encode(['status'=>'error','message'=>'interval_required']); return; }

    try {
        $prod = Capsule::table('eb_catalog_products')->where('id',$productId)->first();
        if (!$prod) { echo json_encode(['status'=>'error','message'=>'product']); return; }
        if ((int)$prod->msp_id !== (int)$msp->id) { echo json_encode(['status'=>'error','message'=>'scope']); return; }

        $acct = (string)($msp->stripe_connect_id ?? '');
        if ($acct === '') { echo json_encode(['status'=>'error','message'=>'not_connected']); return; }

        // Ensure a Stripe Product exists for this local product
        $stripeProductId = (string)($prod->stripe_product_id ?? '');
        $cSvc = new CatalogService();
        if ($stripeProductId === '') {
            $created = $cSvc->createProduct((string)$prod->name, (string)($prod->description ?? ''), $acct);
            $stripeProductId = (string)($created['id'] ?? '');
            if ($stripeProductId !== '') {
                Capsule::table('eb_catalog_products')->where('id',$productId)->update([
                    'stripe_product_id' => $stripeProductId,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        // Create a Stripe Price according to kind
        $params = [
            'product' => $stripeProductId,
            'currency' => strtolower($currency),
            'unit_amount' => $unitAmountCents,
            'nickname' => $name,
        ];
        if ($kind === 'recurring') {
            $params['recurring[interval]'] = $interval;
            $params['billing_scheme'] = 'per_unit';
        } else if ($kind === 'metered') {
            $params['recurring[interval]'] = $interval;
            $params['recurring[usage_type]'] = 'metered';
            $params['recurring[aggregate_usage]'] = $aggregateUsage ?: 'sum';
        } else if ($kind === 'one_time') {
            // no recurring block; one-time prices have no interval
        }

        $price = $cSvc->createPrice($params, $acct);
        $stripePriceId = (string)($price['id'] ?? '');

        $id = Capsule::table('eb_catalog_prices')->insertGetId([
            'product_id' => $productId,
            'name' => $name,
            'kind' => $kind,
            'currency' => $currency,
            'unit_label' => $unitLabel !== '' ? $unitLabel : null,
            'unit_amount' => $unitAmountCents,
            'interval' => $interval,
            'aggregate_usage' => $aggregateUsage,
            'metric_code' => $metricCode,
            'stripe_price_id' => $stripePriceId ?: null,
            'active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        try { if (function_exists('logModuleCall')) { @logModuleCall('eazybackup','ph-catalog-price-create',[ 'product_id'=>$productId,'name'=>$name,'kind'=>$kind ],[ 'price_id'=>$id,'stripe_price_id'=>$stripePriceId ]); } } catch (\Throwable $__) {}
        echo json_encode(['status'=>'success','id'=>$id,'stripe_price_id'=>$stripePriceId]);
        return;
    } catch (\Throwable $e) {
        try { if (function_exists('logModuleCall')) { @logModuleCall('eazybackup','ph-catalog-price-create-error',[ 'product_id'=>$productId,'name'=>$name ], $e->getMessage()); } } catch (\Throwable $__) {}
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
        return;
    }
}

function eb_ph_catalog_product_toggle(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    if (function_exists('check_token')) { try { check_token('WHMCS.default'); } catch (\Throwable $__) { echo json_encode(['status'=>'error','message'=>'csrf']); return; } }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    $id = (int)($_POST['id'] ?? 0);
    $active = (int)($_POST['active'] ?? 1) ? 1 : 0;
    $row = Capsule::table('eb_catalog_products')->where('id',$id)->first();
    if (!$row || (int)$row->msp_id !== (int)$msp->id) { echo json_encode(['status'=>'error','message'=>'scope']); return; }
    Capsule::table('eb_catalog_products')->where('id',$id)->update(['active'=>$active,'updated_at'=>date('Y-m-d H:i:s')]);
    try { if (function_exists('logModuleCall')) { @logModuleCall('eazybackup','ph-catalog-product-toggle',[ 'id'=>$id,'active'=>$active ],'ok'); } } catch (\Throwable $__) {}
    echo json_encode(['status'=>'success']);
}

function eb_ph_catalog_price_toggle(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    if (function_exists('check_token')) { try { check_token('WHMCS.default'); } catch (\Throwable $__) { echo json_encode(['status'=>'error','message'=>'csrf']); return; } }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    $id = (int)($_POST['id'] ?? 0);
    $active = (int)($_POST['active'] ?? 1) ? 1 : 0;
    $row = Capsule::table('eb_catalog_prices')->join('eb_catalog_products','eb_catalog_products.id','=','eb_catalog_prices.product_id')->where('eb_catalog_prices.id',$id)->first(['eb_catalog_prices.id as pid','eb_catalog_products.msp_id as owner']);
    if (!$row || (int)$row->owner !== (int)$msp->id) { echo json_encode(['status'=>'error','message'=>'scope']); return; }
    Capsule::table('eb_catalog_prices')->where('id',$id)->update(['active'=>$active,'updated_at'=>date('Y-m-d H:i:s')]);
    try { if (function_exists('logModuleCall')) { @logModuleCall('eazybackup','ph-catalog-price-toggle',[ 'id'=>$id,'active'=>$active ],'ok'); } } catch (\Throwable $__) {}
    echo json_encode(['status'=>'success']);
}



function eb_ph_catalog_product_get_stripe(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { echo json_encode(['status'=>'error','message'=>'msp']); return; }
    $acct = (string)($msp->stripe_connect_id ?? '');
    if ($acct === '') { echo json_encode(['status'=>'error','message'=>'not_connected']); return; }
    $stripeProductId = (string)($_GET['id'] ?? $_POST['id'] ?? '');
    if ($stripeProductId === '') { echo json_encode(['status'=>'error','message'=>'id']); return; }
    try {
        $svc = new CatalogService();
        $prod = $svc->retrieveProduct($stripeProductId, $acct);
        // Support active filter via GET: active=1 (default), active=0, active=all
        $activeParam = (string)($_GET['active'] ?? '1');
        $onlyActive = true;
        if ($activeParam === 'all') { $onlyActive = null; }
        else if ($activeParam === '0' || strtolower($activeParam) === 'false') { $onlyActive = false; }
        $prices = $svc->listPrices($stripeProductId, $acct, 100, $onlyActive);
        $items = [];
        $derivedBase = 'GENERIC';
        if (isset($prices['data']) && is_array($prices['data'])) {
            foreach ($prices['data'] as $pr) {
                $kind = 'recurring';
                if ((string)($pr['type'] ?? '') === 'one_time') { $kind = 'one_time'; }
                else if (isset($pr['recurring']['usage_type']) && $pr['recurring']['usage_type'] === 'metered') { $kind = 'metered'; $derivedBase = 'STORAGE_TB'; }
                $interval = ($kind === 'one_time') ? 'none' : (string)($pr['recurring']['interval'] ?? 'month');
                $items[] = [
                    'id' => (string)($pr['id'] ?? ''),
                    'label' => (string)($pr['nickname'] ?? ''),
                    'billingType' => $kind === 'metered' ? 'metered' : ($kind === 'one_time' ? 'one_time' : 'per_unit'),
                    'metric' => 'GENERIC',
                    'unitLabel' => (string)($pr['unit_label'] ?? ''),
                    'amount' => (float)((int)($pr['unit_amount'] ?? 0) / 100),
                    'interval' => $interval,
                    'active' => (bool)($pr['active'] ?? true),
                    'currency' => strtoupper((string)($pr['currency'] ?? ($msp->default_currency ?? 'CAD'))),
                ];
            }
        }
        // Attempt to load local product to surface features and base metric
        $features = [];
        $baseMetric = 'GENERIC';
        try {
            $local = Capsule::table('eb_catalog_products')->where('msp_id',(int)$msp->id)->where('stripe_product_id',$stripeProductId)->first();
            if ($local) {
                $rawf = (string)($local->features_json ?? ''); if ($rawf !== '') { $arr = json_decode($rawf, true); if (is_array($arr)) { $features = array_values(array_filter($arr, 'strlen')); } }
                $bm = (string)($local->base_metric_code ?? ''); if ($bm !== '') { $baseMetric = $bm; }
            }
        } catch (\Throwable $___) {}
        if ($baseMetric === 'GENERIC' && $derivedBase === 'STORAGE_TB') { $baseMetric = 'STORAGE_TB'; }
        // Apply base metric to outgoing items for UI consistency
        if (!empty($items)) {
            foreach ($items as &$it) {
                $it['metric'] = $baseMetric;
                if ($baseMetric === 'STORAGE_TB' && ($it['unitLabel'] ?? '') === '') { $it['unitLabel'] = 'GiB'; }
            }
            unset($it);
        }
        $out = [
            'status' => 'success',
            'product' => [
                'id' => (string)($prod['id'] ?? $stripeProductId),
                'name' => (string)($prod['name'] ?? ''),
                'description' => (string)($prod['description'] ?? ''),
                'active' => (bool)($prod['active'] ?? true),
                'features' => $features,
                'base_metric_code' => $baseMetric,
            ],
            'prices' => $items,
            'mixed_metrics' => false,
        ];
        echo json_encode($out); return;
    } catch (\Throwable $e) {
        echo json_encode(['status'=>'error','message'=>'exception']); return;
    }
}

function eb_ph_catalog_product_save_stripe(array $vars): void
{
    header('Content-Type: application/json');
    try { if (function_exists('logModuleCall')) { @logModuleCall('eazybackup','ph-catalog-product-save-stripe:enter',[ 'ctype'=>($_SERVER['CONTENT_TYPE'] ?? '') ],''); } } catch (\Throwable $__) {}
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    if (function_exists('check_token')) { try { check_token('WHMCS.default'); } catch (\Throwable $__) { echo json_encode(['status'=>'error','message'=>'csrf']); return; } }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { echo json_encode(['status'=>'error','message'=>'msp']); return; }
    $acct = (string)($msp->stripe_connect_id ?? '');
    if ($acct === '') { echo json_encode(['status'=>'error','message'=>'not_connected']); return; }

    $raw = file_get_contents('php://input');
    $json = json_decode((string)$raw, true);
    if (!is_array($json)) {
        // Robust fallback for form-encoded payload with potential extra quoting/escaping
        if (isset($_POST['payload'])) {
            $payloadRaw = (string)$_POST['payload'];
            $candidates = [];
            $candidates[] = $payloadRaw;
            // Trim surrounding single quotes if present (some stacks show quoted values)
            if (strlen($payloadRaw) > 2 && $payloadRaw[0] === '\'' && substr($payloadRaw, -1) === '\'') {
                $candidates[] = substr($payloadRaw, 1, -1);
            }
            // HTML entities decode candidate
            $candidates[] = html_entity_decode($payloadRaw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            // Strip slashes candidate
            $candidates[] = stripslashes($payloadRaw);
            // URL decode candidate
            $candidates[] = urldecode($payloadRaw);
            foreach ($candidates as $cand) {
                $try = json_decode((string)$cand, true);
                if (is_array($try)) { $json = $try; break; }
            }
        }
        // Accept top-level fields as a last resort (no payload wrapper)
        if (!is_array($json) && isset($_POST['stripe_product_id'])) {
            $json = [
                'stripe_product_id' => (string)($_POST['stripe_product_id'] ?? ''),
                'product' => [
                    'name' => (string)($_POST['name'] ?? ''),
                    'description' => (string)($_POST['description'] ?? ''),
                ],
                'items' => [],
                'currency' => (string)($_POST['currency'] ?? ''),
            ];
        }
    }
    if (!is_array($json)) { echo json_encode(['status'=>'error','message'=>'bad_json']); return; }

    $stripeProductId = (string)($json['stripe_product_id'] ?? '');
    $product = (array)($json['product'] ?? []);
    $items = (array)($json['items'] ?? []);
    $features = (array)($json['features'] ?? []);
    $currency = strtoupper((string)($json['currency'] ?? ($msp->default_currency ?? 'CAD')));
    if ($stripeProductId === '') { echo json_encode(['status'=>'error','message'=>'id']); return; }

    try {
        $svc = new CatalogService();
        // Update product fields when provided
        $fields = [];
        if (isset($product['name'])) { $fields['name'] = (string)$product['name']; }
        if (array_key_exists('description',$product)) { $fields['description'] = ($product['description'] === '' ? null : (string)$product['description']); }
        if (!empty($fields)) { $svc->updateProduct($stripeProductId, $fields, $acct); }
        // Persist features locally when a mapped product exists
        try {
            $sch = Capsule::schema(); if ($sch->hasTable('eb_catalog_products') && !$sch->hasColumn('eb_catalog_products','features_json')) { $sch->table('eb_catalog_products', function($table){ try { $table->text('features_json')->nullable(); } catch (\Throwable $___) {} }); }
            $local = Capsule::table('eb_catalog_products')->where('msp_id',(int)$msp->id)->where('stripe_product_id',$stripeProductId)->first();
            if ($local) {
                Capsule::table('eb_catalog_products')->where('id',(int)$local->id)->update([
                    'features_json' => (!empty($features) ? json_encode(array_values(array_filter($features, 'strlen'))) : null),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        } catch (\Throwable $___) {}

        // Fetch current prices to compare
        $current = $svc->listPrices($stripeProductId, $acct, 100);
        $byId = [];
        if (isset($current['data']) && is_array($current['data'])) {
            foreach ($current['data'] as $pr) { $byId[(string)$pr['id']] = $pr; }
        }

        $created = 0; $updated = 0; $deactivated = 0;
        $incomingIds = [];
        foreach ($items as $it) {
            $pid = (string)($it['id'] ?? '');
            if ($pid !== '') { $incomingIds[$pid] = true; }
            $label = trim((string)($it['label'] ?? ''));
            $amount = (float)($it['amount'] ?? 0);
            $interval = (string)($it['interval'] ?? 'month');
            $billingType = (string)($it['billingType'] ?? 'per_unit');
            $active = (bool)($it['active'] ?? true);
            if ($pid !== '' && isset($byId[$pid])) {
                // Update nickname if changed
                $cur = $byId[$pid];
                $toUpdate = [];
                $curNick = (string)($cur['nickname'] ?? '');
                if ($label !== '' && $label !== $curNick) { $toUpdate['nickname'] = $label; }
                // Toggle active if changed
                $curActive = (bool)($cur['active'] ?? true);
                if ($curActive !== $active) { $toUpdate['active'] = $active; }
                if (!empty($toUpdate)) { $svc->updatePrice($pid, $toUpdate, $acct); $updated++; }
                // If amount changed, create new price and deactivate old
                $curAmt = (int)($cur['unit_amount'] ?? 0);
                $newAmt = (int)round($amount * 100);
                if ($newAmt > 0 && $newAmt !== $curAmt) {
                    $params = [ 'product'=>$stripeProductId, 'currency'=>strtolower($currency), 'unit_amount'=>$newAmt, 'nickname'=>($label !== '' ? $label : ($curNick ?: '')) ];
                    if ($billingType === 'one_time') { /* leave as one-time */ }
                    else if ($billingType === 'metered') { $params['recurring[interval]']=$interval; $params['recurring[usage_type]']='metered'; $params['recurring[aggregate_usage]']='sum'; }
                    else { $params['recurring[interval]']=$interval; $params['billing_scheme']='per_unit'; }
                    $svc->createPrice($params, $acct);
                    // Deactivate old price
                    try { $svc->updatePrice($pid, ['active'=>false], $acct); } catch (\Throwable $__) {}
                    $created++;
                }
            } else if ($pid === '' && $amount > 0) {
                // New price
                $params = [ 'product'=>$stripeProductId, 'currency'=>strtolower($currency), 'unit_amount'=>(int)round($amount*100), 'nickname'=>($label ?: null) ];
                if ($billingType === 'one_time') { /* one-time */ }
                else if ($billingType === 'metered') { $params['recurring[interval]']=$interval; $params['recurring[usage_type]']='metered'; $params['recurring[aggregate_usage]']='sum'; }
                else { $params['recurring[interval]']=$interval; $params['billing_scheme']='per_unit'; }
                $svc->createPrice($params, $acct);
                $created++;
            }
        }

        // Deactivate any current prices that are NO LONGER present in incoming list (user removed them)
        foreach ($byId as $curId => $cur) {
            if (!isset($incomingIds[$curId])) {
                try { $svc->updatePrice($curId, ['active'=>false], $acct); $deactivated++; } catch (\Throwable $__) {}
            }
        }

        echo json_encode(['status'=>'success','updated'=>$updated,'created'=>$created,'deactivated'=>$deactivated]); return;
    } catch (\Throwable $e) {
        try { if (function_exists('logModuleCall')) { @logModuleCall('eazybackup','ph-catalog-product-save-stripe:error',[], $e->getMessage()); } } catch (\Throwable $__) {}
        echo json_encode(['status'=>'error','message'=>'save_fail']); return;
    }
}

function eb_ph_catalog_product_archive_stripe(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    if (function_exists('check_token')) { try { check_token('WHMCS.default'); } catch (\Throwable $__) { echo json_encode(['status'=>'error','message'=>'csrf']); return; } }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { echo json_encode(['status'=>'error','message'=>'msp']); return; }
    $acct = (string)($msp->stripe_connect_id ?? '');
    if ($acct === '') { echo json_encode(['status'=>'error','message'=>'not_connected']); return; }
    $id = (string)($_POST['id'] ?? '');
    if ($id === '') { echo json_encode(['status'=>'error','message'=>'id']); return; }
    try { (new CatalogService())->updateProduct($id, ['active'=>false], $acct); echo json_encode(['status'=>'success']); }
    catch (\Throwable $e) { echo json_encode(['status'=>'error','message'=>'archive_fail','detail'=>$e->getMessage()]); }
}

function eb_ph_catalog_product_delete_stripe(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    if (function_exists('check_token')) { try { check_token('WHMCS.default'); } catch (\Throwable $__) { echo json_encode(['status'=>'error','message'=>'csrf']); return; } }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { echo json_encode(['status'=>'error','message'=>'msp']); return; }
    $acct = (string)($msp->stripe_connect_id ?? '');
    if ($acct === '') { echo json_encode(['status'=>'error','message'=>'not_connected']); return; }
    $id = (string)($_POST['id'] ?? '');
    if ($id === '') { echo json_encode(['status'=>'error','message'=>'id']); return; }
    try { (new CatalogService())->deleteProduct($id, $acct); echo json_encode(['status'=>'success']); }
    catch (\Throwable $e) { echo json_encode(['status'=>'error','message'=>'delete_fail','detail'=>$e->getMessage()]); }
}

function eb_ph_catalog_product_unarchive_stripe(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    if (function_exists('check_token')) { try { check_token('WHMCS.default'); } catch (\Throwable $__) { echo json_encode(['status'=>'error','message'=>'csrf']); return; } }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { echo json_encode(['status'=>'error','message'=>'msp']); return; }
    $acct = (string)($msp->stripe_connect_id ?? '');
    if ($acct === '') { echo json_encode(['status'=>'error','message'=>'not_connected']); return; }
    $id = (string)($_POST['id'] ?? '');
    if ($id === '') { echo json_encode(['status'=>'error','message'=>'id']); return; }
    try { (new CatalogService())->updateProduct($id, ['active'=>true], $acct); echo json_encode(['status'=>'success']); }
    catch (\Throwable $e) { echo json_encode(['status'=>'error','message'=>'unarchive_fail','detail'=>$e->getMessage()]); }
}

function eb_ph_catalog_export_products(array $vars): void
{
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { header('HTTP/1.1 401 Unauthorized'); exit; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    $acct = (string)($msp->stripe_connect_id ?? '');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="products.csv"');
    $out = fopen('php://output','w');
    fprintf($out, "\xEF\xBB\xBF"); // UTF-8 BOM
    fputcsv($out, ['id','name','active','created']);
    if ($acct !== '') {
        try {
            $svc = new CatalogService(); $list = $svc->listProducts($acct, 100);
            foreach ((array)($list['data'] ?? []) as $p) {
                fputcsv($out, [(string)($p['id'] ?? ''),(string)($p['name'] ?? ''),(isset($p['active'])&&$p['active']?'1':'0'),(string)($p['created'] ?? 0)]);
            }
        } catch (\Throwable $__) {}
    }
    fclose($out); exit;
}

function eb_ph_catalog_export_prices(array $vars): void
{
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { header('HTTP/1.1 401 Unauthorized'); exit; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    $acct = (string)($msp->stripe_connect_id ?? '');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="prices.csv"');
    $out = fopen('php://output','w');
    fprintf($out, "\xEF\xBB\xBF");
    fputcsv($out, ['product_id','price_id','nickname','currency','unit_amount','interval','kind','active','created']);
    if ($acct !== '') {
        try {
            $svc = new CatalogService();
            $list = $svc->listProducts($acct, 100);
            foreach ((array)($list['data'] ?? []) as $p) {
                $pid = (string)($p['id'] ?? ''); if ($pid==='') continue;
                $prices = $svc->listPrices($pid, $acct, 100, null);
                foreach ((array)($prices['data'] ?? []) as $pr) {
                    $kind = 'recurring';
                    if ((string)($pr['type'] ?? '') === 'one_time') { $kind = 'one_time'; }
                    else if (isset($pr['recurring']['usage_type']) && $pr['recurring']['usage_type'] === 'metered') { $kind = 'metered'; }
                    fputcsv($out, [ $pid, (string)($pr['id'] ?? ''), (string)($pr['nickname'] ?? ''), strtoupper((string)($pr['currency'] ?? '')), (int)($pr['unit_amount'] ?? 0), ($kind==='one_time'?'none':(string)($pr['recurring']['interval'] ?? 'month')), $kind, ((bool)($pr['active'] ?? true)?'1':'0'), (string)($pr['created'] ?? 0) ]);
                }
            }
        } catch (\Throwable $__) {}
    }
    fclose($out); exit;
}
