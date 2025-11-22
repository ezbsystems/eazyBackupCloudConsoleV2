<?php

use WHMCS\Database\Capsule;
use PartnerHub\StripeService;

/**
 * Public Signup Controller (GET/POST)
 * - Host-guarded; no login required
 */
function eazybackup_public_signup(array $vars)
{
    // Feature flag
    if (!(int)($vars['PARTNER_HUB_SIGNUP_ENABLED'] ?? 0)) {
        return [ 'pagetitle'=>'Signup Unavailable', 'templatefile'=>'templates/whitelabel/public-invalid-host', 'vars'=>['reason'=>'disabled'] ];
    }

    // Resolve tenant by Host header (must be verified signup domain)
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $tenant = null;
    try {
        $row = Capsule::table('eb_whitelabel_signup_domains')->where('hostname',$host)->where('status','verified')->first();
        if ($row) { $tenant = Capsule::table('eb_whitelabel_tenants')->where('id',(int)$row->tenant_id)->first(); }
    } catch (\Throwable $__) {}
    if (!$tenant) {
        return [ 'pagetitle'=>'Invalid Signup URL', 'templatefile'=>'templates/whitelabel/public-invalid-host', 'vars'=>['reason'=>'invalid_host'] ];
    }

    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        // GET: render form (themed). Load flow config and branding for header/theming
        $flow = null; $brand = [];
        try { $flow = Capsule::table('eb_whitelabel_signup_flows')->where('tenant_id',(int)$tenant->id)->first(); } catch (\Throwable $__) {}
        try {
            $orgId = (string)($tenant->org_id ?? '');
            if ($orgId !== '') {
                $ct = new \EazyBackup\Whitelabel\CometTenant($vars);
                $b = $ct->getOrgBranding($orgId);
                if (is_array($b)) { $brand = $b; }
            }
        } catch (\Throwable $__) {}
        $turnstileSiteKey = (string)($vars['turnstilesitekey'] ?? '');
        // Fetch plan/price selection for display (optional)
        $plans = []; $prices = [];
        try {
            $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',(int)$tenant->client_id)->first();
            if ($msp) {
                $plans = Capsule::table('eb_plans')->where('msp_id',(int)$msp->id)->orderBy('name','asc')->get();
                $prices = Capsule::table('eb_plan_prices')->join('eb_plans','eb_plans.id','=','eb_plan_prices.plan_id')->where('eb_plans.msp_id',(int)$msp->id)->get(['eb_plan_prices.*']);
            }
        } catch (\Throwable $__) { $plans = []; $prices = []; }

        return [
            'pagetitle' => 'Start your trial',
            'templatefile' => 'templates/whitelabel/public-signup',
            'forcessl' => true,
            'vars' => [
                'tenant' => (array)$tenant,
                'host' => $host,
                'flow' => $flow ? (array)$flow : [],
                'brand' => $brand,
                'turnstile_site_key' => $turnstileSiteKey,
                'plans' => $plans,
                'prices' => $prices,
            ],
        ];
    }

    // POST: Validate, rate-limit, write event, create client + order, accept/provision, redirect to download
    $email = trim((string)($_POST['email'] ?? ''));
    $first = trim((string)($_POST['first_name'] ?? ''));
    $last  = trim((string)($_POST['last_name'] ?? ''));
    $company = trim((string)($_POST['company'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm  = (string)($_POST['confirm_password'] ?? '');
    $agree    = isset($_POST['agree']);
    $promo    = trim((string)($_POST['promo_code'] ?? ''));
    $flow = Capsule::table('eb_whitelabel_signup_flows')->where('tenant_id',(int)$tenant->id)->first();
    $pid  = (int)($flow->product_pid ?? 0);
    $paymentMethod = (string)($flow->payment_method ?? '');
    $planPriceId = (int)($flow->plan_price_id ?? 0);
    $requireCard  = (int)($flow->require_card ?? 0);

    // Basic validation
    $errs = [];
    if ($first === '' || $last === '') { $errs[] = 'name'; }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errs[] = 'email'; }
    if ($username === '') { $errs[] = 'username'; }
    if ($password === '' || $password !== $confirm) { $errs[] = 'password'; }
    if (!$agree) { $errs[] = 'agree'; }
    if ($pid <= 0) { $errs[] = 'product'; }
    if (!empty($errs)) {
        return [ 'pagetitle'=>'Start your trial', 'templatefile'=>'templates/whitelabel/public-signup', 'vars'=>['errors'=>$errs, 'tenant'=>(array)$tenant, 'host'=>$host] ];
    }

    // Observability: log received
    try { logModuleCall('eazybackup','signup_post',['tenant_id'=>(int)$tenant->id,'email'=>$email,'ip'=>$ip??($_SERVER['REMOTE_ADDR']??'')],'received'); } catch (\Throwable $__) {}

    // Turnstile verification if configured
    $turnstileSecret = (string)($vars['turnstilesecret'] ?? '');
    if ($turnstileSecret !== '') {
        $token = (string)($_POST['cf-turnstile-response'] ?? '');
        if ($token === '') { return [ 'pagetitle'=>'Start your trial', 'templatefile'=>'templates/whitelabel/public-signup', 'vars'=>['errors'=>['turnstile'], 'tenant'=>(array)$tenant, 'host'=>$host] ]; }
        try {
            $verifyPayload = http_build_query(['secret'=>$turnstileSecret,'response'=>$token,'remoteip'=>$_SERVER['REMOTE_ADDR'] ?? '']);
            $ctx = stream_context_create(['http'=>['method'=>'POST','header'=>'Content-Type: application/x-www-form-urlencoded','content'=>$verifyPayload,'timeout'=>6]]);
            $resp = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $ctx);
            $ok = false; if ($resp) { $jr = json_decode($resp, true); $ok = (bool)($jr['success'] ?? false); }
            if (!$ok) { try { logModuleCall('eazybackup','signup_post',['tenant_id'=>(int)$tenant->id,'email'=>$email],'turnstile_failed'); } catch (\Throwable $__) {} return [ 'pagetitle'=>'Start your trial', 'templatefile'=>'templates/whitelabel/public-signup', 'vars'=>['errors'=>['turnstile'], 'tenant'=>(array)$tenant, 'host'=>$host] ]; }
        } catch (\Throwable $__) {
            try { logModuleCall('eazybackup','signup_post',['tenant_id'=>(int)$tenant->id,'email'=>$email],'turnstile_exception'); } catch (\Throwable $___) {}
            return [ 'pagetitle'=>'Start your trial', 'templatefile'=>'templates/whitelabel/public-signup', 'vars'=>['errors'=>['turnstile'], 'tenant'=>(array)$tenant, 'host'=>$host] ];
        }
    }

    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');

    // Abuse controls: domain allow/deny
    try {
        $domain = strtolower(substr(strrchr($email, '@') ?: '', 1));
        $allow = array_filter(array_map('trim', explode(',', (string)($flow->allow_domains ?? ''))));
        $deny  = array_filter(array_map('trim', explode(',', (string)($flow->deny_domains ?? ''))));
        if (!empty($allow) && $domain !== '' && !in_array($domain, $allow, true)) {
            try { logModuleCall('eazybackup','signup_post',['tenant_id'=>(int)$tenant->id,'email'=>$email,'domain'=>$domain],'blocked_not_in_allow'); } catch (\Throwable $__) {}
            return [ 'pagetitle'=>'Start your trial', 'templatefile'=>'templates/whitelabel/public-signup', 'vars'=>['errors'=>['email_domain'], 'tenant'=>(array)$tenant, 'host'=>$host] ];
        }
        if (!empty($deny) && $domain !== '' && in_array($domain, $deny, true)) {
            try { logModuleCall('eazybackup','signup_post',['tenant_id'=>(int)$tenant->id,'email'=>$email,'domain'=>$domain],'blocked_in_deny'); } catch (\Throwable $__) {}
            return [ 'pagetitle'=>'Start your trial', 'templatefile'=>'templates/whitelabel/public-signup', 'vars'=>['errors'=>['email_domain'], 'tenant'=>(array)$tenant, 'host'=>$host] ];
        }
    } catch (\Throwable $__) {}

    // Rate limiting: per-IP and per-email in last hour
    try {
        $cutoff = date('Y-m-d H:i:s', time() - 3600);
        $rateIp    = (int)($flow->rate_ip ?? 0);
        $rateEmail = (int)($flow->rate_email ?? 0);
        if ($rateIp > 0) {
            $cntIp = (int)Capsule::table('eb_whitelabel_signup_events')->where('tenant_id',(int)$tenant->id)->where('ip',$ip)->where('created_at','>',$cutoff)->count();
            if ($cntIp >= $rateIp) {
                try { logModuleCall('eazybackup','signup_post',['tenant_id'=>(int)$tenant->id,'ip'=>$ip,'count'=>$cntIp],'rate_limited_ip'); } catch (\Throwable $__) {}
                return [ 'pagetitle'=>'Start your trial', 'templatefile'=>'templates/whitelabel/public-signup', 'vars'=>['errors'=>['rate_ip'], 'tenant'=>(array)$tenant, 'host'=>$host] ];
            }
        }
        if ($rateEmail > 0) {
            $cntEmail = (int)Capsule::table('eb_whitelabel_signup_events')->where('tenant_id',(int)$tenant->id)->where('email',$email)->where('created_at','>',$cutoff)->count();
            if ($cntEmail >= $rateEmail) {
                try { logModuleCall('eazybackup','signup_post',['tenant_id'=>(int)$tenant->id,'email'=>$email,'count'=>$cntEmail],'rate_limited_email'); } catch (\Throwable $__) {}
                return [ 'pagetitle'=>'Start your trial', 'templatefile'=>'templates/whitelabel/public-signup', 'vars'=>['errors'=>['rate_email'], 'tenant'=>(array)$tenant, 'host'=>$host] ];
            }
        }
    } catch (\Throwable $__) {}

    // Idempotency: if an event already exists for this tenant+email, avoid duplicate order
    $existing = Capsule::table('eb_whitelabel_signup_events')->where('tenant_id',(int)$tenant->id)->where('email',$email)->first();
    if ($existing) {
        // If terminal, redirect to download; otherwise indicate processing
        $st = (string)($existing->status ?? '');
        if (in_array($st, ['emailed','completed','provisioned','accepted'], true)) {
            header('Location: index.php?m=eazybackup&a=public-download&existing=1'); exit;
        }
    }

    $now = date('Y-m-d H:i:s');
    $eventId = null;
    try {
        $eventId = Capsule::table('eb_whitelabel_signup_events')->insertGetId([
            'tenant_id' => (int)$tenant->id,
            'host_header' => $host,
            'email' => $email,
            'status' => 'received',
            'ip' => $ip,
            'user_agent' => $ua,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    } catch (\Throwable $__) { /* unique dup ok */ }

    // LocalAPI pipeline
    $adminUser = 'API';
    try {
        // Create or find client
        $addClientPayload = [
            'firstname' => $first,
            'lastname' => $last,
            'companyname' => $company,
            'email' => $email,
            'address1' => 'N/A',
            'city' => 'N/A',
            'state' => 'N/A',
            'postcode' => '00000',
            'country' => 'US',
            'phonenumber' => $phone,
            'password2' => $password,
        ];
        try { logModuleCall('eazybackup','signup_post',['tenant_id'=>(int)$tenant->id,'event_id'=>$eventId,'email'=>$email],'validate_ok'); } catch (\Throwable $__) {}
        $resClient = localAPI('AddClient', $addClientPayload, $adminUser);
        $clientId = 0;
        if (($resClient['result'] ?? '') === 'success') { $clientId = (int)($resClient['clientid'] ?? 0); }
        else {
            // If duplicate email, try to resolve client id
            $row = Capsule::table('tblclients')->where('email',$email)->first();
            if ($row) { $clientId = (int)$row->id; }
        }
        if ($clientId <= 0) { throw new \RuntimeException('client_create_failed'); }

        try { logModuleCall('eazybackup','signup_post',['tenant_id'=>(int)$tenant->id,'event_id'=>$eventId,'clientId'=>$clientId],'client_ok'); } catch (\Throwable $__) {}

        // If card is required, attach payment method to platform Stripe Customer and set default
        $requireCard = (int)($flow->require_card ?? 0);
        $pmId = trim((string)($_POST['payment_method_id'] ?? ''));
        if ($requireCard) {
            if ($pmId === '') { throw new \RuntimeException('payment_method_missing'); }
            $svc = new StripeService();
            $cust = $svc->createCustomerBasic(trim($first.' '.$last), $email);
            $scus = (string)($cust['id'] ?? '');
            if ($scus === '') { throw new \RuntimeException('stripe_customer_create_failed'); }
            $svc->attachPaymentMethod($pmId, $scus);
            $svc->updateCustomerDefaultPaymentMethod($scus, $pmId);
        }

        // Create order
        $orderPayload = [
            'clientid' => $clientId,
            'pid' => [$pid],
            'billingcycle' => 'monthly',
            'noemail' => true,
        ];
        if ($paymentMethod !== '') { $orderPayload['paymentmethod'] = $paymentMethod; }
        if ($promo !== '') { $orderPayload['promocode'] = $promo; }
        $resOrder = localAPI('AddOrder', $orderPayload, $adminUser);
        if (($resOrder['result'] ?? '') !== 'success') { throw new \RuntimeException('order_create_failed'); }
        $orderId = (int)($resOrder['orderid'] ?? 0);
        // Accept order (provision)
        try { logModuleCall('eazybackup','signup_post',['tenant_id'=>(int)$tenant->id,'event_id'=>$eventId,'orderId'=>$orderId],'order_ok'); } catch (\Throwable $__) {}
        $resAccept = localAPI('AcceptOrder', ['orderid'=>$orderId,'sendemail'=>false], $adminUser);
        if (($resAccept['result'] ?? '') !== 'success') { throw new \RuntimeException('order_accept_failed'); }

        // Update event status
        Capsule::table('eb_whitelabel_signup_events')->where('tenant_id',(int)$tenant->id)->where('email',$email)->update([
            'status' => 'accepted',
            'whmcs_client_id' => $clientId,
            'whmcs_order_id' => $orderId,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Try to read back Comet username from latest service
        $svc = Capsule::table('tblhosting')->where('userid',$clientId)->orderBy('id','desc')->first();
        if ($svc) {
            Capsule::table('eb_whitelabel_signup_events')->where('tenant_id',(int)$tenant->id)->where('email',$email)->update([
                'status' => 'provisioned',
                'comet_username' => (string)($svc->username ?? ''),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            try { logModuleCall('eazybackup','signup_post',['tenant_id'=>(int)$tenant->id,'event_id'=>$eventId,'username'=>(string)($svc->username ?? '')],'provisioned'); } catch (\Throwable $__) {}
        }

        // Send emails if configured
        try {
            $downloadsUrl = rtrim((string)($vars['systemurl'] ?? ''), '/') . '/index.php?m=eazybackup&a=public-download&new=1';
            // Customer Welcome
            if ((int)($flow->send_customer_welcome ?? 1) === 1) {
                $msgVars = [
                    'Brand_ProductName' => (string)($tenant->product_name ?? ''),
                    'Account_Username' => (string)($svc->username ?? ''),
                    'Downloads_Url' => $downloadsUrl,
                    'Brand_ServerAddress' => 'https://' . (string)($tenant->fqdn ?? $host) . '/',
                ];
                // Attempt MSP custom welcome via template; fall back to WHMCS email if not configured
                try {
                    require_once __DIR__ . '/EmailTriggers.php';
                    $brandName = (string)($tenant->product_name ?? 'eazyBackup');
                    $varsSend = [
                        'customer_name' => $first . ' ' . $last,
                        'brand_name' => $brandName,
                        'portal_url' => $downloadsUrl,
                        'help_url' => rtrim((string)($vars['systemurl'] ?? ''), '/') . '/index.php?m=eazybackup&a=knowledgebase',
                    ];
                    EmailTriggers::trigger((int)$tenant->id, 'welcome', (string)$email, $varsSend);
                } catch (\Throwable $___) {
                    @localAPI('SendEmail', ['messagename'=>'eB Partner Hub — Customer Welcome','id'=>$clientId,'customvars'=>$msgVars], $adminUser);
                }
            }
            // MSP Notice (route to module setting notify_test_recipient as a simple default or admin email)
            if ((int)($flow->send_msp_notice ?? 1) === 1) {
                $mspEmail = (string)($vars['notify_test_recipient'] ?? ($vars['trialsignupemail'] ?? ''));
                if ($mspEmail !== '') {
                    $msgVars2 = [
                        'Customer_Name' => $first . ' ' . $last,
                        'Customer_Email' => $email,
                        'Account_Username' => (string)($svc->username ?? ''),
                        'Downloads_Url' => $downloadsUrl,
                        'Brand_ProductName' => (string)($tenant->product_name ?? ''),
                    ];
                    @localAPI('SendEmail', ['customtype'=>'general','customsubject'=>'New signup: ' . $email,'custommessage'=>'New signup: '.$email.' Username: '.((string)($svc->username ?? '')).' Downloads: '.$downloadsUrl,'customvars'=>$msgVars2,'to'=>$mspEmail], $adminUser);
                }
            }
        } catch (\Throwable $__) {}

        // Redirect to download page
        header('Location: index.php?m=eazybackup&a=public-download&new=1');
        exit;
    } catch (\Throwable $e) {
        try {
            Capsule::table('eb_whitelabel_signup_events')->where('tenant_id',(int)$tenant->id)->where('email',$email)->update([
                'status' => 'failed',
                'error' => substr($e->getMessage(),0,1024),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $__) {}
        try { logModuleCall('eazybackup','signup_post',['tenant_id'=>(int)$tenant->id,'event_id'=>$eventId,'email'=>$email],'failed: '.$e->getMessage()); } catch (\Throwable $___) {}
        return [ 'pagetitle'=>'Start your trial', 'templatefile'=>'templates/whitelabel/public-signup', 'vars'=>['errors'=>['server'], 'tenant'=>(array)$tenant, 'host'=>$host] ];
    }
}

function eazybackup_public_setupintent(array $vars): void
{
    header('Content-Type: application/json');
    try {
        $svc = new StripeService();
        $si = $svc->createSetupIntentAdhoc();
        echo json_encode(['status'=>'success','client_secret'=>$si['client_secret'] ?? null,'publishable'=>$svc->getPublishable()]);
    } catch (\Throwable $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
}


