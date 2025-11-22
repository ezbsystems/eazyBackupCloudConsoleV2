<?php

use WHMCS\Database\Capsule;
use PartnerHub\StripeService;

function eb_ph_stripe_onboard(array $vars)
{
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { header('Location: clientarea.php'); exit; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { header('Location: index.php?m=eazybackup&a=ph-clients'); exit; }
    // Generate an onboarding link (Express account)
    $svc = new StripeService();
    try {
		// Validate platform keys before attempting API calls
		$pub = $svc->getPublishable();
		$sec = $svc->getSecret();
		if ($pub === '' || $sec === '') {
			try { if (function_exists('logActivity')) { @logActivity('eazybackup: ph-stripe-onboard missing platform keys (publishable/secret)'); } } catch (\Throwable $___) { /* ignore */ }
			header('Location: '.$vars['modulelink'].'&a=ph-clients&onboard_error=1');
			return '';
		}
        $acct = $svc->ensureConnectedAccount((int)$msp->id);
        if ($acct !== '') {
            // Build absolute HTTPS base URL from WHMCS config or request env
            $candidates = [];
            $ssl = (string)($vars['systemsslurl'] ?? ''); if ($ssl !== '') { $candidates[] = $ssl; }
            $sys = (string)($vars['systemurl'] ?? '');    if ($sys !== '') { $candidates[] = $sys; }
            if (!empty($_SERVER['HTTP_HOST'])) {
                $host = (string)$_SERVER['HTTP_HOST'];
                $scheme = 'https';
                if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === 1)) { $scheme = 'https'; }
                elseif (!empty($_SERVER['REQUEST_SCHEME'])) { $scheme = (string)$_SERVER['REQUEST_SCHEME']; }
                $candidates[] = $scheme.'://'.$host;
            }
            $base = '';
            foreach ($candidates as $cand) {
                $cand = rtrim((string)$cand, '/');
                if ($cand === '') { continue; }
                // Normalize to https
                if (stripos($cand, 'http://') === 0) {
                    $cand = 'https://'.substr($cand, 7);
                }
                if (stripos($cand, 'https://') === 0) { $base = $cand; break; }
            }
            if ($base === '') { throw new \RuntimeException('base_url_unresolved'); }
            $refresh = $base.'/index.php?m=eazybackup&a=ph-clients&onboard_refresh=1';
            $return  = $base.'/index.php?m=eazybackup&a=ph-clients&onboard_success=1';
            // Log URLs for diagnostics
            try { if (function_exists('logActivity')) { @logActivity('eazybackup: ph-stripe-onboard urls base='.$base); } } catch (\Throwable $___) { /* ignore */ }
            $url = $svc->createAccountLink($acct, $refresh, $return);
            if ($url !== '') { header('Location: '.$url); exit; }
        }
	} catch (\Throwable $__) {
		// Log and fallthrough to graceful client notice
		try { if (function_exists('logActivity')) { @logActivity('eazybackup: ph-stripe-onboard error: '.$__->getMessage()); } } catch (\Throwable $___) { /* ignore */ }
	}
	// Graceful fallback with a query flag consumed by ClientsController/clients.tpl
    header('Location: '.$vars['modulelink'].'&a=ph-clients&onboard_error=1');
    exit;
}

function eb_ph_stripe_setup_intent(array $vars): void
{
    header('Content-Type: application/json');
    try {
        $customerId = (int)($_POST['customer_id'] ?? 0);
        $clientId = (int)($_SESSION['uid'] ?? 0);
        $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
        $svc = new StripeService();
        // Ensure Stripe customer exists
        $acct = (string)($msp->stripe_connect_id ?? '');
        $scus = $svc->ensureStripeCustomerFor($customerId, $acct ?: null);
        $si = $svc->createSetupIntent($scus, $acct ?: null);
        echo json_encode(['status'=>'success','client_secret'=>$si['client_secret'] ?? null,'publishable'=>$svc->getPublishable()]);
        return;
    } catch (\Throwable $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
        return;
    }
}

function eb_ph_stripe_connect_status(array $vars)
{
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { header('Location: clientarea.php'); exit; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { header('Location: '.$vars['modulelink'].'&a=ph-clients'); exit; }

    $acctId = (string)($msp->stripe_connect_id ?? '');
    $status = [ 'hasAccount' => false, 'chargesEnabled' => false, 'payoutsEnabled' => false, 'currentlyDue' => [] ];
    if ($acctId !== '') {
        $svc = new StripeService();
        try {
            $acct = $svc->retrieveAccount($acctId);
            $status['hasAccount'] = true;
            $status['chargesEnabled'] = (bool)($acct['charges_enabled'] ?? false);
            // Stripe Accounts API may omit payouts_enabled; fall back to transfers_enabled
            $status['payoutsEnabled'] = (bool)($acct['payouts_enabled'] ?? ($acct['transfers_enabled'] ?? false));
            $reqs = $acct['requirements'] ?? [];
            if (is_array($reqs) && isset($reqs['currently_due']) && is_array($reqs['currently_due'])) {
                $status['currentlyDue'] = $reqs['currently_due'];
            }
        } catch (\Throwable $__) { /* ignore */ }
    }

    return [
        'pagetitle' => 'Stripe Connect — Status',
        'templatefile' => 'whitelabel/stripe-connect',
        'breadcrumb' => [ 'index.php?m=eazybackup' => 'eazyBackup' ],
        'requirelogin' => true,
        'forcessl' => true,
        'vars' => [ 'msp' => $msp, 'modulelink' => $vars['modulelink'], 'status' => $status ],
    ];
}


function eb_ph_stripe_account_session(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    try {
        $clientId = (int)$_SESSION['uid'];
        $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
        if (!$msp || (string)($msp->stripe_connect_id ?? '') === '') { echo json_encode(['status'=>'error','message'=>'not_connected']); return; }
        $svc = new StripeService();
        // Ensure publishable key exists (generic error for client area to avoid leaking config details)
        $pub = $svc->getPublishable();
        if ($pub === '') { echo json_encode(['status'=>'error','message'=>'config_missing']); return; }
        $sess = $svc->createAccountSession((string)$msp->stripe_connect_id, [ 'components[account_management][enabled]' => 'true' ]);
        if (!is_array($sess) || !isset($sess['client_secret'])) { throw new \RuntimeException('missing_client_secret'); }
        echo json_encode(['status'=>'success','client_secret'=>$sess['client_secret'] ?? null,'publishable'=>$pub]);
        return;
    } catch (\Throwable $e) {
        try { if (function_exists('logActivity')) { @logActivity('eazybackup: ph-stripe-account-session error: '.$e->getMessage()); } } catch (\Throwable $___) { /* ignore */ }
        echo json_encode(['status'=>'error','message'=>'session_failed']);
        return;
    }
}

function eb_ph_stripe_manage(array $vars)
{
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { header('Location: clientarea.php'); exit; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { header('Location: '.$vars['modulelink'].'&a=ph-clients'); exit; }
    // Minimal shell page; frontend should fetch ph-stripe-account-session JSON and mount embedded component
    return [
        'pagetitle' => 'Manage Stripe Account',
        'templatefile' => 'whitelabel/stripe-manage',
        'breadcrumb' => [ 'index.php?m=eazybackup' => 'eazyBackup' ],
        'requirelogin' => true,
        'forcessl' => true,
        'vars' => [ 'msp' => $msp, 'modulelink' => $vars['modulelink'] ],
    ];
}


function eb_ph_stripe_manage_redirect(array $vars): void
{
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { header('Location: clientarea.php'); exit; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp || (string)($msp->stripe_connect_id ?? '') === '') { header('Location: '.$vars['modulelink'].'&a=ph-stripe-connect'); exit; }
    try {
        $svc = new StripeService();
        $link = $svc->createAccountLoginLink((string)$msp->stripe_connect_id);
        $url = (string)($link['url'] ?? '');
        if ($url !== '') { header('Location: '.$url); exit; }
    } catch (\Throwable $__) {
        try { if (function_exists('logActivity')) { @logActivity('eazybackup: ph-stripe-manage-redirect error: '.$__->getMessage()); } } catch (\Throwable $___) { /* ignore */ }
    }
    header('Location: '.$vars['modulelink'].'&a=ph-stripe-connect');
    exit;
}

