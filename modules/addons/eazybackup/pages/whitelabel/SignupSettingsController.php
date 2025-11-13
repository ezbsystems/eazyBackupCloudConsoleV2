<?php

use WHMCS\Database\Capsule;

function eazybackup_whitelabel_signup_settings(array $vars)
{
    if (!((int)($_SESSION['uid'] ?? 0) > 0)) {
        return [
            'pagetitle' => 'Signup Settings',
            'templatefile' => 'templates/error',
            'vars' => ['error' => 'Please sign in to continue.']
        ];
    }
    // Resolve by public_id (tid) or fallback to numeric id
    $tenantId = 0; $clientId = (int)$_SESSION['uid']; $tenantObj = null;
    $tid = strtoupper(trim((string)($_GET['tid'] ?? '')));
    if ($tid !== '' && preg_match('/^[0-9A-HJ-NP-TV-Z]{26}$/', $tid)) {
        try { $tenantObj = Capsule::table('eb_whitelabel_tenants')->where('public_id', $tid)->first(); } catch (\Throwable $__) { $tenantObj = null; }
        if ($tenantObj) { $tenantId = (int)$tenantObj->id; }
    }
    if ($tenantId <= 0) {
        $tenantId = (int)($_GET['id'] ?? 0);
        $tenantObj = $tenantId ? Capsule::table('eb_whitelabel_tenants')->where('id', $tenantId)->first() : null;
    }
    if (!$tenantObj || (int)$tenantObj->client_id !== $clientId) {
        return [
            'pagetitle' => 'Signup Settings',
            'templatefile' => 'templates/error',
            'vars' => ['error' => 'Tenant not found.']
        ];
    }
    // Canonicalize to tid on GET when available
    if ((string)($tenantObj->public_id ?? '') !== '' && isset($_GET['id']) && !isset($_GET['tid'])) {
        $dest = $vars['modulelink'] . '&a=whitelabel-signup-settings&tid=' . urlencode((string)$tenantObj->public_id);
        header('Location: ' . $dest, true, 302);
        exit;
    }

    // Ensure Partner Hub tables exist (runtime safe-guard for environments where SQL hasn't been applied)
    try {
        $schema = Capsule::schema();
        if (method_exists($schema, 'hasTable')) {
            if (!$schema->hasTable('eb_whitelabel_signup_domains')) {
                $schema->create('eb_whitelabel_signup_domains', function($t){
                    $t->bigIncrements('id');
                    $t->unsignedBigInteger('tenant_id');
                    $t->string('hostname', 255);
                    $t->enum('status', ['pending_dns','dns_ok','cert_ok','verified','disabled','failed'])->default('pending_dns');
                    $t->text('last_error')->nullable();
                    $t->dateTime('cert_expires_at')->nullable();
                    $t->dateTime('created_at')->default(Capsule::raw('CURRENT_TIMESTAMP'));
                    $t->dateTime('updated_at')->default(Capsule::raw('CURRENT_TIMESTAMP'));
                });
            }
            if (!$schema->hasTable('eb_whitelabel_signup_flows')) {
                $schema->create('eb_whitelabel_signup_flows', function($t){
                    $t->bigIncrements('id');
                    $t->unsignedBigInteger('tenant_id');
                    $t->integer('product_pid');
                    $t->string('promo_code', 64)->nullable();
                    $t->string('payment_method', 64)->nullable();
                    $t->tinyInteger('require_email_verify')->default(0);
                    $t->tinyInteger('send_customer_welcome')->default(1);
                    $t->tinyInteger('send_msp_notice')->default(1);
                    $t->dateTime('created_at')->default(Capsule::raw('CURRENT_TIMESTAMP'));
                    $t->dateTime('updated_at')->default(Capsule::raw('CURRENT_TIMESTAMP'));
                });
            }
        }
    } catch (\Throwable $__) { /* ignore; next queries may still work if tables exist */ }

    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        try {
            // Ensure new columns exist (idempotent runtime migration)
            try {
                $schema = Capsule::schema();
                if (method_exists($schema, 'hasColumn')) {
                    $targets = [
                        'hero_title' => 'string', 'hero_subtitle'=>'string', 'feature_bullets'=>'text',
                        'tos_url'=>'string','privacy_url'=>'string','support_url'=>'string','accent_override'=>'string',
                        'allow_domains'=>'text','deny_domains'=>'text','rate_ip'=>'integer','rate_email'=>'integer','turnstile_sitekey_override'=>'string',
                        'is_enabled'=>'integer','plan_price_id'=>'integer','require_card'=>'integer',
                    ];
                    foreach ($targets as $col=>$type) {
                        if (!$schema->hasColumn('eb_whitelabel_signup_flows', $col)) {
                            $schema->table('eb_whitelabel_signup_flows', function($table) use ($col,$type) {
                                if ($type === 'string') { $table->string($col, 255)->nullable(); }
                                else if ($type === 'integer') { $table->integer($col)->nullable(); }
                                else { $table->text($col)->nullable(); }
                            });
                        }
                    }
                }
            } catch (\Throwable $__) {}

            $pid  = (int)($_POST['product_pid'] ?? 0);
            $promo = trim((string)($_POST['promo_code'] ?? ''));
            $pm    = trim((string)($_POST['payment_method'] ?? ''));
            $requireVerify = isset($_POST['require_email_verify']) ? 1 : 0;
            $sendWelcome   = isset($_POST['send_customer_welcome']) ? 1 : 0;
            $sendMsp       = isset($_POST['send_msp_notice']) ? 1 : 0;
            $isEnabled     = isset($_POST['is_enabled']) ? 1 : 0;
            $planPriceId   = (int)($_POST['plan_price_id'] ?? 0);
            $requireCard   = isset($_POST['require_card']) ? 1 : 0;
            // Content & abuse
            $heroTitle = trim((string)($_POST['hero_title'] ?? ''));
            $heroSubtitle = trim((string)($_POST['hero_subtitle'] ?? ''));
            $featureBullets = trim((string)($_POST['feature_bullets'] ?? ''));
            $tosUrl = trim((string)($_POST['tos_url'] ?? ''));
            $privacyUrl = trim((string)($_POST['privacy_url'] ?? ''));
            $supportUrl = trim((string)($_POST['support_url'] ?? ''));
            $accentOverride = trim((string)($_POST['accent_override'] ?? ''));
            $allowDomains = trim((string)($_POST['allow_domains'] ?? ''));
            $denyDomains = trim((string)($_POST['deny_domains'] ?? ''));
            $rateIp = (int)($_POST['rate_ip'] ?? 0);
            $rateEmail = (int)($_POST['rate_email'] ?? 0);
            $turnstileOverride = trim((string)($_POST['turnstile_sitekey_override'] ?? ''));
            $now = date('Y-m-d H:i:s');

            $exists = Capsule::table('eb_whitelabel_signup_flows')->where('tenant_id',$tenantId)->first();
            if ($exists) {
                Capsule::table('eb_whitelabel_signup_flows')->where('tenant_id',$tenantId)->update([
                    'product_pid' => $pid,
                    'promo_code' => $promo,
                    'payment_method' => $pm,
                    'require_email_verify' => $requireVerify,
                    'send_customer_welcome' => $sendWelcome,
                    'send_msp_notice' => $sendMsp,
                    'hero_title' => $heroTitle,
                    'hero_subtitle' => $heroSubtitle,
                    'feature_bullets' => $featureBullets,
                    'tos_url' => $tosUrl,
                    'privacy_url' => $privacyUrl,
                    'support_url' => $supportUrl,
                    'accent_override' => $accentOverride,
                    'allow_domains' => $allowDomains,
                    'deny_domains' => $denyDomains,
                    'rate_ip' => $rateIp,
                    'rate_email' => $rateEmail,
                    'turnstile_sitekey_override' => $turnstileOverride,
                    'is_enabled' => $isEnabled,
                    'plan_price_id' => $planPriceId,
                    'require_card' => $requireCard,
                    'updated_at' => $now,
                ]);
            } else {
                Capsule::table('eb_whitelabel_signup_flows')->insert([
                    'tenant_id' => $tenantId,
                    'product_pid' => $pid,
                    'promo_code' => $promo,
                    'payment_method' => $pm,
                    'require_email_verify' => $requireVerify,
                    'send_customer_welcome' => $sendWelcome,
                    'send_msp_notice' => $sendMsp,
                    'hero_title' => $heroTitle,
                    'hero_subtitle' => $heroSubtitle,
                    'feature_bullets' => $featureBullets,
                    'tos_url' => $tosUrl,
                    'privacy_url' => $privacyUrl,
                    'support_url' => $supportUrl,
                    'accent_override' => $accentOverride,
                    'allow_domains' => $allowDomains,
                    'deny_domains' => $denyDomains,
                    'rate_ip' => $rateIp,
                    'rate_email' => $rateEmail,
                    'turnstile_sitekey_override' => $turnstileOverride,
                    'is_enabled' => $isEnabled,
                    'plan_price_id' => $planPriceId,
                    'require_card' => $requireCard,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        } catch (\Throwable $__) {}

        $redirTid = (string)($tenantObj->public_id ?? '');
        $dest = $vars['modulelink'] . '&a=whitelabel-signup-settings&' . ($redirTid !== '' ? ('tid=' . urlencode($redirTid)) : ('id=' . urlencode((string)$tenantId)));
        header('Location: ' . $dest . '&saved=1');
        exit;
    }

    // GET: load flow + latest signup domain
    $flow = null; $domainRow = null;
    try { $flow = Capsule::table('eb_whitelabel_signup_flows')->where('tenant_id',$tenantId)->first(); } catch (\Throwable $__) { $flow = null; }
    try { $domainRow = Capsule::table('eb_whitelabel_signup_domains')->where('tenant_id',$tenantId)->orderBy('updated_at','desc')->first(); } catch (\Throwable $__) { $domainRow = null; }
    $csrf = (function(){ try { if (function_exists('generate_token')) { return (string)generate_token('plain'); } } catch (\Throwable $_) {} return ''; })();

    // Build list of available backup products: Only the tenant's white-label product
    $products = [];
    try {
        $tenantProductId = (int)($tenantObj->product_id ?? 0);
        if ($tenantProductId > 0) {
            $row = Capsule::table('tblproducts')->select('id','name')->where('id',$tenantProductId)->first();
            if ($row) { $products[] = ['id' => (int)$row->id, 'name' => (string)$row->name]; }
        }
    } catch (\Throwable $__) { /* ignore */ }

    // Load MSP plans/prices
    $plans = []; $prices = [];
    try {
        $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',(int)$tenantObj->client_id)->first();
        if ($msp) {
            $plans = Capsule::table('eb_plans')->where('msp_id',(int)$msp->id)->orderBy('name','asc')->get();
            $prices = Capsule::table('eb_plan_prices')->join('eb_plans','eb_plans.id','=','eb_plan_prices.plan_id')->where('eb_plans.msp_id',(int)$msp->id)->get(['eb_plan_prices.*']);
        }
    } catch (\Throwable $__) { $plans = []; $prices = []; }

    return [
        'pagetitle' => 'Signup Settings',
        'templatefile' => 'templates/whitelabel/signup-settings',
        'requirelogin' => true,
        'forcessl' => true,
        'vars' => [
            'modulelink' => $vars['modulelink'],
            'tenant' => (array)$tenantObj,
            'flow' => $flow ? (array)$flow : [],
            'signup_domain_row' => $domainRow ? (array)$domainRow : [],
            'csrf_token' => $csrf,
            'products' => $products,
            'plans' => $plans,
            'prices' => $prices,
        ],
    ];
}

function eazybackup_whitelabel_signup_checkdns(array $vars)
{
    header('Content-Type: application/json');
    try {
        if (!((int)($_SESSION['uid'] ?? 0) > 0)) { echo json_encode(['ok'=>false,'error'=>'Not authenticated']); return; }
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { echo json_encode(['ok'=>false,'error'=>'Invalid method']); return; }
        $token = (string)($_POST['token'] ?? '');
        if (function_exists('check_token')) { try { if (!check_token('plain', $token)) { echo json_encode(['ok'=>false,'error'=>'Invalid token']); return; } } catch (\Throwable $_) {} }
        // Resolve by tid or id
        $tenantId = 0; $tid = strtoupper(trim((string)($_POST['tenant_tid'] ?? '')));
        if ($tid !== '' && preg_match('/^[0-9A-HJ-NP-TV-Z]{26}$/', $tid)) {
            $trow = Capsule::table('eb_whitelabel_tenants')->where('public_id',$tid)->first();
            if ($trow) { $tenantId = (int)$trow->id; }
        }
        if ($tenantId <= 0) { $tenantId = (int)($_POST['tenant_id'] ?? 0); }
        $hostname = strtolower(trim((string)($_POST['hostname'] ?? '')));
        if ($tenantId <= 0 || $hostname === '') { echo json_encode(['ok'=>false,'error'=>'Missing tenant or hostname']); return; }
        $tenant = Capsule::table('eb_whitelabel_tenants')->where('id',$tenantId)->first();
        if (!$tenant || (int)$tenant->client_id !== (int)$_SESSION['uid']) { echo json_encode(['ok'=>false,'error'=>'Tenant not found']); return; }
        $expectedTarget = (string)$tenant->fqdn;
        $answers = @dns_get_record($hostname, DNS_CNAME) ?: [];
        $seen = [];
        foreach ($answers as $a) { if (isset($a['target'])) { $seen[] = rtrim(strtolower((string)$a['target']), '.'); } }
        $okExact = in_array($expectedTarget, $seen, true);
        $now = date('Y-m-d H:i:s');
        $row = [ 'tenant_id'=>$tenantId, 'hostname'=>$hostname, 'status'=>$okExact?'dns_ok':'pending_dns', 'last_error'=>$okExact?null:('Expected CNAME '.$hostname.' → '.$expectedTarget.'; got: '.implode(',', $seen)), 'updated_at'=>$now ];
        try {
            $ex = Capsule::table('eb_whitelabel_signup_domains')->where('tenant_id',$tenantId)->where('hostname',$hostname)->first();
            if ($ex) { Capsule::table('eb_whitelabel_signup_domains')->where('id',$ex->id)->update($row); }
            else { $row['created_at']=$now; Capsule::table('eb_whitelabel_signup_domains')->insert($row); }
        } catch (\Throwable $__) {}
        echo json_encode(['ok'=>true,'status'=>$row['status']]);
    } catch (\Throwable $e) { echo json_encode(['ok'=>false,'error'=>'Server error']); }
}

function eazybackup_whitelabel_signup_attachdomain(array $vars)
{
    header('Content-Type: application/json');
    try {
        if (!((int)($_SESSION['uid'] ?? 0) > 0)) { echo json_encode(['ok'=>false,'error'=>'Not authenticated']); return; }
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { echo json_encode(['ok'=>false,'error'=>'Invalid method']); return; }
        $token = (string)($_POST['token'] ?? '');
        if (function_exists('check_token')) { try { if (!check_token('plain', $token)) { echo json_encode(['ok'=>false,'error'=>'Invalid token']); return; } } catch (\Throwable $_) {} }
        // Resolve by tid or id
        $tenantId = 0; $tid = strtoupper(trim((string)($_POST['tenant_tid'] ?? '')));
        if ($tid !== '' && preg_match('/^[0-9A-HJ-NP-TV-Z]{26}$/', $tid)) {
            $trow = Capsule::table('eb_whitelabel_tenants')->where('public_id',$tid)->first();
            if ($trow) { $tenantId = (int)$trow->id; }
        }
        if ($tenantId <= 0) { $tenantId = (int)($_POST['tenant_id'] ?? 0); }
        $hostname = strtolower(trim((string)($_POST['hostname'] ?? '')));
        if ($tenantId <= 0 || $hostname === '') { echo json_encode(['ok'=>false,'error'=>'Missing tenant or hostname']); return; }
        $tenant = Capsule::table('eb_whitelabel_tenants')->where('id',$tenantId)->first();
        if (!$tenant || (int)$tenant->client_id !== (int)$_SESSION['uid']) { echo json_encode(['ok'=>false,'error'=>'Tenant not found']); return; }
        $now = date('Y-m-d H:i:s');

        // HostOps steps
        $ops = new \EazyBackup\Whitelabel\HostOps($vars);
        if (!$ops->writeHttpStub($hostname)) { echo json_encode(['ok'=>false,'error'=>'Failed to write HTTP stub']); return; }
        if (!$ops->issueCert($hostname)) { try { $ops->deleteHost($hostname); } catch (\Throwable $__) {} echo json_encode(['ok'=>false,'error'=>'Certificate issuance failed']); return; }
        if (!$ops->writeSignupHttps($hostname)) { try { $ops->deleteHost($hostname); } catch (\Throwable $__) {} echo json_encode(['ok'=>false,'error'=>'Failed to write HTTPS vhost']); return; }

        // Optional cert expiry probe
        $expAt = null;
        try {
            $ctx = stream_context_create(['ssl'=>['SNI_enabled'=>true,'verify_peer'=>false,'verify_peer_name'=>false,'capture_peer_cert'=>true]]);
            $soc = @stream_socket_client('ssl://'.$hostname.':443', $errno, $errstr, 6, STREAM_CLIENT_CONNECT, $ctx);
            if ($soc) {
                $params = stream_context_get_params($soc);
                if (isset($params['options']['ssl']['peer_certificate'])) {
                    $cert = $params['options']['ssl']['peer_certificate'];
                    if ($cert) { $info = @openssl_x509_parse($cert); if (is_array($info) && isset($info['validTo_time_t'])) { $expAt = date('Y-m-d H:i:s', (int)$info['validTo_time_t']); } }
                }
                @fclose($soc);
            }
        } catch (\Throwable $__) { $expAt = null; }

        $upd = [ 'status'=>'verified', 'updated_at'=>$now ]; if ($expAt) { $upd['cert_expires_at'] = $expAt; }
        try {
            $ex = Capsule::table('eb_whitelabel_signup_domains')->where('tenant_id',$tenantId)->where('hostname',$hostname)->first();
            if ($ex) { Capsule::table('eb_whitelabel_signup_domains')->where('id',$ex->id)->update($upd); }
            else { Capsule::table('eb_whitelabel_signup_domains')->insert(['tenant_id'=>$tenantId,'hostname'=>$hostname] + $upd + ['created_at'=>$now]); }
        } catch (\Throwable $__) {}

        echo json_encode(['ok'=>true,'status'=>'verified','message'=>'Signup domain attached and secured.']);
    } catch (\Throwable $e) { echo json_encode(['ok'=>false,'error'=>'Server error']); }
}


