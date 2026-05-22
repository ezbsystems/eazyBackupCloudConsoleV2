<?php

use WHMCS\Database\Capsule;
use PartnerHub\StripeService;
use PartnerHub\TenantCustomerService;

/**
 * Validate the basic POST input for the public signup form.
 *
 * Pure function: no DB, no $_POST, no $_SERVER. Tests pass an array; production
 * passes the trimmed/normalised $_POST values.
 *
 * @return array<int,string> List of error codes ('name', 'email', 'username',
 *   'password', 'agree', 'product'). Empty array means "OK".
 */
function eb_signup_validate_basic_input(array $input): array
{
    $errs = [];
    $first = trim((string)($input['first_name'] ?? ''));
    $last = trim((string)($input['last_name'] ?? ''));
    $email = trim((string)($input['email'] ?? ''));
    $username = trim((string)($input['username'] ?? ''));
    $password = (string)($input['password'] ?? '');
    $confirm = (string)($input['confirm_password'] ?? '');
    $agree = !empty($input['agree']);
    $pid = (int)($input['product_pid'] ?? 0);

    if ($first === '' || $last === '') { $errs[] = 'name'; }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errs[] = 'email'; }
    if ($username === '') { $errs[] = 'username'; }
    if ($password === '' || $password !== $confirm) { $errs[] = 'password'; }
    if (!$agree) { $errs[] = 'agree'; }
    if ($pid <= 0) { $errs[] = 'product'; }
    return $errs;
}

/**
 * Apply per-tenant allow / deny domain filters to the submitted email.
 *
 * Returns null when the email passes (or no filters are configured), or one of
 * 'blocked_not_in_allow' / 'blocked_in_deny' on a violation.
 *
 * Pure function: pass the parsed allow/deny lists rather than the raw flow row
 * so tests can drive every branch without seeding eb_whitelabel_signup_flows.
 */
function eb_signup_check_domain_filters(string $email, string $allowCsv, string $denyCsv): ?string
{
    $domain = strtolower(substr(strrchr($email, '@') ?: '', 1));
    $allow = array_values(array_filter(array_map('trim', explode(',', $allowCsv))));
    $deny = array_values(array_filter(array_map('trim', explode(',', $denyCsv))));

    if (!empty($allow) && $domain !== '' && !in_array($domain, $allow, true)) {
        return 'blocked_not_in_allow';
    }
    if (!empty($deny) && $domain !== '' && in_array($domain, $deny, true)) {
        return 'blocked_in_deny';
    }
    return null;
}

/**
 * Apply 1-hour-window per-IP and per-email rate limits against
 * eb_whitelabel_signup_events.
 *
 * Returns null when within limits, or one of 'rate_ip' / 'rate_email' on
 * exceed. Limits of 0 (or less) mean "no limit".
 */
function eb_signup_check_rate_limits(int $tenantId, string $ip, string $email, int $rateIp, int $rateEmail): ?string
{
    if ($tenantId <= 0) { return null; }
    $cutoff = date('Y-m-d H:i:s', time() - 3600);

    if ($rateIp > 0 && $ip !== '') {
        $cnt = (int) Capsule::table('eb_whitelabel_signup_events')
            ->where('tenant_id', $tenantId)
            ->where('ip', $ip)
            ->where('created_at', '>', $cutoff)
            ->count();
        if ($cnt >= $rateIp) { return 'rate_ip'; }
    }
    if ($rateEmail > 0 && $email !== '') {
        $cnt = (int) Capsule::table('eb_whitelabel_signup_events')
            ->where('tenant_id', $tenantId)
            ->where('email', $email)
            ->where('created_at', '>', $cutoff)
            ->count();
        if ($cnt >= $rateEmail) { return 'rate_email'; }
    }
    return null;
}

/**
 * Look up an existing eb_whitelabel_signup_events row for (tenant, email) and
 * classify the resulting state as one of:
 *   - null              — no prior submission, proceed.
 *   - 'pending_approval' — show "we received it" UI.
 *   - 'completed'        — terminal success states; 302 to download page.
 *   - 'in_progress'      — non-terminal, non-pending state (rare; treat as proceed).
 *   - 'failed'           — prior attempt failed (treat as proceed; UI may surface).
 */
function eb_signup_existing_event_state(int $tenantId, string $email): ?string
{
    if ($tenantId <= 0 || $email === '') { return null; }
    $row = Capsule::table('eb_whitelabel_signup_events')
        ->where('tenant_id', $tenantId)
        ->where('email', $email)
        ->first(['status']);
    if (!$row) { return null; }
    $status = (string) ($row->status ?? '');
    if ($status === 'pending_approval') { return 'pending_approval'; }
    if (in_array($status, ['emailed', 'completed', 'provisioned', 'accepted'], true)) {
        return 'completed';
    }
    if ($status === 'failed') { return 'failed'; }
    return 'in_progress';
}

/**
 * Notify the MSP (the WHMCS client that owns this white-label tenant) that a
 * new public signup has landed in `pending_approval` and is waiting for them
 * to approve or reject from the Partner Hub queue.
 *
 * Uses the seeded WHMCS email template "EazyBackup Pending Signup Notice"
 * via localAPI('SendEmail', ...) so the message uses the platform's outbound
 * mail and is logged to tblemails. Failures are swallowed at the call site —
 * this must never break the customer-facing signup flow.
 */
function eb_signup_send_msp_pending_notice($tenant, string $customerEmail, int $orderId): void
{
    $tenantId = (int)($tenant->id ?? 0);
    $mspClientId = (int)($tenant->client_id ?? 0);
    if ($tenantId <= 0 || $mspClientId <= 0) { return; }

    $tenantFqdn = (string)($tenant->fqdn ?? '');
    $tenantPublicId = (string)($tenant->public_id ?? '');

    // Resolve admin user for localAPI calls (mirrors SignupApprovalsController).
    $adminUser = 'API';
    try {
        $configuredAdmin = (string)(Capsule::table('tbladdonmodules')
            ->where('module', 'eazybackup')->where('setting', 'adminuser')
            ->value('value') ?? '');
        if ($configuredAdmin !== '') { $adminUser = $configuredAdmin; }
        else {
            $firstAdmin = Capsule::table('tbladmins')->where('disabled', 0)
                ->orderBy('id', 'asc')->value('username');
            if ($firstAdmin) { $adminUser = (string)$firstAdmin; }
        }
    } catch (\Throwable $__) {}

    // Build the deep-link to the per-tenant approvals queue. SystemURL falls
    // back to the request host so dev environments still get a clickable link.
    $base = '';
    try {
        $sysUrl = (string)(Capsule::table('tblconfiguration')->where('setting','SystemURL')->value('value') ?? '');
        if ($sysUrl !== '') { $base = rtrim($sysUrl, '/') . '/'; }
    } catch (\Throwable $__) {}
    if ($base === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        $base = $scheme . '://' . $host . '/';
    }
    $approvalsUrl = $base . 'index.php?m=eazybackup&a=ph-signup-approvals'
        . ($tenantPublicId !== '' ? ('&tid=' . urlencode($tenantPublicId)) : '');

    $customVars = [
        'tenant_fqdn' => $tenantFqdn,
        'tenant_name' => (string)($tenant->name ?? $tenantFqdn),
        'customer_email' => $customerEmail,
        'signup_received_at' => date('Y-m-d H:i:s'),
        'approvals_url' => $approvalsUrl,
        'whmcs_order_id' => (string)$orderId,
    ];

    try {
        $res = localAPI('SendEmail', [
            'messagename' => 'EazyBackup Pending Signup Notice',
            'id' => $mspClientId,
            'customvars' => base64_encode(serialize($customVars)),
        ], $adminUser);
        $result = (string)($res['result'] ?? '');
        try { logModuleCall('eazybackup','signup_msp_notice', [
            'tenant_id' => $tenantId,
            'msp_client_id' => $mspClientId,
            'order_id' => $orderId,
        ], $result === 'success' ? 'sent' : ('failed: ' . (string)($res['message'] ?? ''))); } catch (\Throwable $__) {}
    } catch (\Throwable $e) {
        try { logModuleCall('eazybackup','signup_msp_notice', [
            'tenant_id' => $tenantId,
            'msp_client_id' => $mspClientId,
        ], 'exception: ' . $e->getMessage()); } catch (\Throwable $__) {}
    }
}

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

    // POST: Validate, rate-limit, write event, create client + order, queue for approval
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

    // Basic validation (delegated to pure helper for testability — see eb_signup_validate_basic_input)
    $errs = eb_signup_validate_basic_input([
        'first_name' => $first,
        'last_name' => $last,
        'email' => $email,
        'username' => $username,
        'password' => $password,
        'confirm_password' => $confirm,
        'agree' => $agree,
        'product_pid' => $pid,
    ]);
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

    // Abuse controls: domain allow/deny (delegated to pure helper)
    $domainViolation = eb_signup_check_domain_filters(
        $email,
        (string)($flow->allow_domains ?? ''),
        (string)($flow->deny_domains ?? '')
    );
    if ($domainViolation !== null) {
        try { logModuleCall('eazybackup','signup_post',['tenant_id'=>(int)$tenant->id,'email'=>$email],$domainViolation); } catch (\Throwable $__) {}
        return [ 'pagetitle'=>'Start your trial', 'templatefile'=>'templates/whitelabel/public-signup', 'vars'=>['errors'=>['email_domain'], 'tenant'=>(array)$tenant, 'host'=>$host] ];
    }

    // Idempotency: if an event already exists for this tenant+email, avoid duplicate order
    $existingState = eb_signup_existing_event_state((int)$tenant->id, $email);
    if ($existingState === 'pending_approval') {
        return [
            'pagetitle' => 'Signup received',
            'templatefile' => 'templates/whitelabel/public-signup',
            'forcessl' => true,
            'vars' => [
                'signup_state' => 'pending_approval',
                'tenant' => (array)$tenant,
                'host' => $host,
                'flow' => $flow ? (array)$flow : [],
            ],
        ];
    }
    if ($existingState === 'completed') {
        header('Location: index.php?m=eazybackup&a=public-download&existing=1'); exit;
    }

    // Rate limiting: per-IP and per-email in last hour (delegated to pure helper)
    $rateViolation = eb_signup_check_rate_limits(
        (int)$tenant->id,
        $ip,
        $email,
        (int)($flow->rate_ip ?? 0),
        (int)($flow->rate_email ?? 0)
    );
    if ($rateViolation !== null) {
        try { logModuleCall('eazybackup','signup_post',['tenant_id'=>(int)$tenant->id,'ip'=>$ip,'email'=>$email],'rate_limited:'.$rateViolation); } catch (\Throwable $__) {}
        return [ 'pagetitle'=>'Start your trial', 'templatefile'=>'templates/whitelabel/public-signup', 'vars'=>['errors'=>[$rateViolation], 'tenant'=>(array)$tenant, 'host'=>$host] ];
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

    // Ensure canonical tenant -> customer linkage exists (idempotent, fail-closed).
    try {
        if (!class_exists(\PartnerHub\TenantCustomerService::class)) {
            @require_once __DIR__ . '/../../lib/PartnerHub/TenantCustomerService.php';
        }
        (new TenantCustomerService())->ensureCustomerForTenant((int)$tenant->id);
    } catch (\Throwable $tenantEnsureError) {
        $tenantEnsureMessage = (string)$tenantEnsureError->getMessage();
        $isConflict = in_array($tenantEnsureMessage, ['tenant_customer_owner_conflict', 'tenant_customer_conflict'], true);
        $errorCode = $isConflict ? 'tenant_customer_conflict' : 'tenant_customer_enforcement_failed';
        $auditCode = $isConflict ? 'tenant_customer_conflict_hard_fail' : 'tenant_customer_ensure_hard_fail';
        try {
            Capsule::table('eb_whitelabel_signup_events')->where('tenant_id',(int)$tenant->id)->where('email',$email)->update([
                'status' => 'failed',
                'error' => substr($errorCode . ':' . $tenantEnsureMessage, 0, 1024),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $__) {}
        try { logModuleCall('eazybackup','signup_post',['tenant_id'=>(int)$tenant->id,'event_id'=>$eventId,'email'=>$email,'ensure_error'=>$tenantEnsureMessage],$auditCode); } catch (\Throwable $__) {}
        return [ 'pagetitle'=>'Start your trial', 'templatefile'=>'templates/whitelabel/public-signup', 'vars'=>['errors'=>['server'], 'tenant'=>(array)$tenant, 'host'=>$host] ];
    }

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
        // Order is intentionally left pending for MSP approval before provisioning.
        try { logModuleCall('eazybackup','signup_post',['tenant_id'=>(int)$tenant->id,'event_id'=>$eventId,'orderId'=>$orderId],'order_ok'); } catch (\Throwable $__) {}

        // Update event status
        Capsule::table('eb_whitelabel_signup_events')->where('tenant_id',(int)$tenant->id)->where('email',$email)->update([
            'status' => 'pending_approval',
            'whmcs_client_id' => $clientId,
            'whmcs_order_id' => $orderId,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        try { logModuleCall('eazybackup','signup_post',['tenant_id'=>(int)$tenant->id,'event_id'=>$eventId,'orderId'=>$orderId],'pending_approval'); } catch (\Throwable $__) {}

        // Notify the MSP that a new signup is awaiting approval. Best-effort:
        // a failure here must never break the customer-facing signup flow.
        try {
            $noticeEnabled = function_exists('eazybackup_addon_bool_setting')
                ? eazybackup_addon_bool_setting('partnerhub_signup_notice_enabled', true)
                : true;
            if ($noticeEnabled) {
                eb_signup_send_msp_pending_notice((object)$tenant, $email, $orderId);
            }
        } catch (\Throwable $__) {}

        try {
            require_once __DIR__ . '/EmailTriggers.php';
            \EmailTriggers::trigger((int)$tenant->id, \EmailTriggers::WELCOME_ON_SIGNUP, $email, [
                'customer_name' => trim($first . ' ' . $last),
                'brand_name' => (string)($tenant->fqdn ?? $tenant->name ?? ''),
            ]);
        } catch (\Throwable $__) {}

        return [
            'pagetitle' => 'Signup received',
            'templatefile' => 'templates/whitelabel/public-signup',
            'forcessl' => true,
            'vars' => [
                'signup_state' => 'pending_approval',
                'tenant' => (array)$tenant,
                'host' => $host,
                'flow' => $flow ? (array)$flow : [],
            ],
        ];
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


