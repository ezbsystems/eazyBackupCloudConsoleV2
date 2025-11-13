<?php

use WHMCS\Database\Capsule;
use PartnerHub\StripeService;
use PartnerHub\WhmcsBridge;

function eb_require_login_and_reseller(array $vars): void {
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) {
        header('Location: clientarea.php'); exit;
    }
    $clientId = (int)$_SESSION['uid'];
    // Gate by reseller groups setting (same logic as hook)
    try {
        $resellerGroupsSetting = (string)(Capsule::table('tbladdonmodules')
            ->where('module','eazybackup')->where('setting','resellergroups')->value('value') ?? '');
        if ($resellerGroupsSetting !== '') {
            $gid = (int)(Capsule::table('tblclients')->where('id',$clientId)->value('groupid') ?? 0);
            if ($gid > 0) {
                $ids = array_map('intval', array_filter(array_map('trim', explode(',', $resellerGroupsSetting))));
                if (!in_array($gid, $ids, true)) { header('HTTP/1.1 403 Forbidden'); exit; }
            }
        }
    } catch (\Throwable $__) { /* allow */ }
}

function eb_msp_account_for_client(int $clientId): ?object {
    $row = Capsule::table('eb_msp_accounts')->where('whmcs_client_id', $clientId)->first();
    if ($row) return $row;
    // Create lazily on first access
    $name = (string)(Capsule::table('tblclients')->where('id',$clientId)->value('companyname') ?? '');
    try {
        $id = Capsule::table('eb_msp_accounts')->insertGetId([
            'whmcs_client_id' => $clientId,
            'name' => $name,
            'status' => 'active',
            'billing_mode' => 'stripe_connect',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return Capsule::table('eb_msp_accounts')->where('id',$id)->first();
    } catch (\Throwable $__) { return null; }
}

function eb_ph_clients_index(array $vars)
{
    eb_require_login_and_reseller($vars);
    $clientId = (int)$_SESSION['uid'];
    $ebDebug = [];
    $ebDebug[] = 'enter';
    // Trace entry for diagnostics
    try {
        $method = (string)($_SERVER['REQUEST_METHOD'] ?? '');
        $hasCreate = isset($_POST['eb_create_client']) ? '1' : '0';
        $keys = $method === 'POST' ? implode(',', array_keys($_POST ?? [])) : '';
        $ebDebug[] = 'method=' . $method;
        $ebDebug[] = 'hasCreate=' . $hasCreate;
        $ebDebug[] = 'keys=' . $keys;
        logActivity("eazybackup: ph-clients enter method={$method} hasCreate={$hasCreate} keys={$keys}");
    } catch (\Throwable $_) { /* ignore */ }
    $msp = eb_msp_account_for_client($clientId);
    $connect = [ 'hasAccount' => false, 'chargesEnabled' => false, 'payoutsEnabled' => false, 'detailsSubmitted' => false ];
    $connectDue = [];
    try {
        if ($msp && (string)($msp->stripe_connect_id ?? '') !== '') {
            $svc = new StripeService();
            $acct = $svc->retrieveAccount((string)$msp->stripe_connect_id);
            $connect = [
                'hasAccount' => true,
                'chargesEnabled' => (bool)($acct['charges_enabled'] ?? false),
                'payoutsEnabled' => (bool)($acct['payouts_enabled'] ?? false),
                'detailsSubmitted' => (bool)($acct['details_submitted'] ?? false),
            ];
            $reqs = $acct['requirements'] ?? [];
            if (is_array($reqs) && isset($reqs['currently_due']) && is_array($reqs['currently_due'])) {
                $connectDue = $reqs['currently_due'];
            }
        }
    } catch (\Throwable $__) { /* ignore */ }

    // Handle create client POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eb_create_client'])) {
        try {
            // Activity log: POST received
            try {
                $keys = implode(',', array_keys($_POST ?? []));
                logActivity("eazybackup: ph-clients POST received (keys={$keys})");
            } catch (\Throwable $_) { /* ignore logging errors */ }
            $ebDebug[] = 'post-branch';
            // Normalize country to ISO 3166-1 alpha-2 if a full name was provided
            $countryRaw = trim((string)($_POST['country'] ?? ''));
            $country = strtoupper($countryRaw);
            if (strlen($country) !== 2) {
                try {
                    $code = (string)(Capsule::table('tblcountries')
                        ->whereRaw('LOWER(name)=LOWER(?)', [$countryRaw])
                        ->value('country') ?? '');
                    if ($code !== '') { $country = strtoupper($code); }
                } catch (\Throwable $__) { /* ignore country lookup errors */ }
            }
            $payload = [
                'firstname'    => (string)($_POST['firstname'] ?? ''),
                'lastname'     => (string)($_POST['lastname'] ?? ''),
                'companyname'  => (string)($_POST['companyname'] ?? ''),
                'email'        => (string)($_POST['email'] ?? ''),
                'password2'    => (string)($_POST['password'] ?? ''),
                'address1'     => (string)($_POST['address1'] ?? ''),
                'address2'     => (string)($_POST['address2'] ?? ''),
                'city'         => (string)($_POST['city'] ?? ''),
                'state'        => (string)($_POST['state'] ?? ''),
                'postcode'     => (string)($_POST['postcode'] ?? ''),
                'country'      => $country,
                'phonenumber'  => (string)($_POST['phonenumber'] ?? ''),
                // Best-effort to reduce API validation friction
                'skipvalidation' => true,
            ];
            // Optional fields if provided
            $currency = (int)($_POST['currency'] ?? 0);
            if ($currency > 0) { $payload['currency'] = $currency; }
            $pm = trim((string)($_POST['payment_method'] ?? ''));
            if ($pm !== '') { $payload['paymentmethod'] = $pm; }
            if ($payload['password2'] === '') { $payload['password2'] = bin2hex(random_bytes(8)); }
            // Resolve admin username for LocalAPI: module setting 'adminuser' → first active admin → 'API'
            $adminUser = 'API';
            try {
                $cfgAdmin = (string)(Capsule::table('tbladdonmodules')->where('module','eazybackup')->where('setting','adminuser')->value('value') ?? '');
                if ($cfgAdmin !== '') { $adminUser = $cfgAdmin; }
                else {
                    $firstAdmin = Capsule::table('tbladmins')->where('disabled', 0)->orderBy('id','asc')->value('username');
                    if ($firstAdmin) { $adminUser = (string)$firstAdmin; }
                }
            } catch (\Throwable $__) { /* use default */ }
            try { logActivity("eazybackup: ph-clients AddClient admin={$adminUser}"); } catch (\Throwable $_) { /* ignore */ }
            $ebDebug[] = 'admin=' . $adminUser;
            // Sanity ping to LocalAPI to verify it runs under this admin user
            try {
                $ping = localAPI('GetConfigurationValue', ['setting' => 'CompanyName'], $adminUser);
                $ebDebug[] = 'ping=' . ($ping['result'] ?? '');
            } catch (\Throwable $__) {
                $ebDebug[] = 'ping=ex';
            }
            // Fallback include if autoload mapping missed
            if (!class_exists(\PartnerHub\WhmcsBridge::class)) {
                @require_once __DIR__ . '/../../lib/PartnerHub/WhmcsBridge.php';
            }
            $res = WhmcsBridge::addClient($payload, $adminUser);
            try {
                $brief = [ 'result' => ($res['result'] ?? ''), 'message' => ($res['message'] ?? ''), 'clientid' => ($res['clientid'] ?? null) ];
                logActivity('eazybackup: ph-clients AddClient resp=' . json_encode($brief));
            } catch (\Throwable $_) { /* ignore */ }
            // If LocalAPI is blocked by token CSRF, abort with message
            if (isset($res['result']) && $res['result'] === 'error' && stripos((string)($res['message'] ?? ''), 'invalid token') !== false) {
                $createError = 'WHMCS token invalid; please refresh and try again.';
            }
            $ebDebug[] = 'resp=' . ($res['result'] ?? '');
            // Also write a file log for absolute certainty (module logs can be filtered by settings)
            try { if (function_exists('customFileLog')) { customFileLog('ph-clients AddClient payload', $payload); customFileLog('ph-clients AddClient response', $res); } } catch (\Throwable $_) {}
            if (($res['result'] ?? '') === 'success') {
                $newId = (int)($res['clientid'] ?? 0);
                if ($newId > 0) {
                    $ebDebug[] = 'clientid=' . $newId;
                    $displayName = trim(($payload['companyname'] !== '' ? $payload['companyname'] : ($payload['firstname'].' '.$payload['lastname'])));
                    $ecid = Capsule::table('eb_customers')->insertGetId([
                        'msp_id' => (int)($msp->id ?? 0),
                        'whmcs_client_id' => $newId,
                        'name' => $displayName,
                        'status' => 'active',
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    // Optional pre-link Comet user
                    $cometUser = trim((string)($_POST['comet_username'] ?? ''));
                    if ($cometUser !== '') {
                        try {
                            Capsule::table('eb_customer_comet_accounts')->updateOrInsert(
                                ['customer_id'=>$ecid,'comet_user_id'=>$cometUser],
                                []
                            );
                            // Tag mirrors
                            Capsule::table('comet_users')->where('username',$cometUser)->update([
                                'msp_id' => (int)($msp->id ?? 0),
                                'customer_id' => $ecid,
                            ]);
                        } catch (\Throwable $__) { /* ignore */ }
                    }
                    try { logActivity('eazybackup: ph-clients redirect to ph-client id=' . $ecid); } catch (\Throwable $_) { /* ignore */ }
                    header('Location: '.$vars['modulelink'].'&a=ph-client&id='.$ecid);
                    exit;
                } else {
                    $ebDebug[] = 'empty-clientid';
                    $createError = 'AddClient returned success but clientid was empty.';
                }
            } else {
                $ebDebug[] = 'failed:' . ($res['message'] ?? '');
                try { logModuleCall('eazybackup', 'ph-clients:addClient', $payload, $res); } catch (\Throwable $_) { /* ignore logging errors */ }
                $createError = (string)($res['message'] ?? 'AddClient failed');
            }
        } catch (\Throwable $e) {
            // Surface unexpected exceptions
            try { logActivity('eazybackup: ph-clients EX=' . $e->getMessage()); } catch (\Throwable $_) { /* ignore */ }
            try { if (function_exists('customFileLog')) { customFileLog('ph-clients exception', $e->getMessage()); } } catch (\Throwable $_) {}
            $createError = 'Error: ' . $e->getMessage();
            try { $ebDebug[] = 'ex=' . $e->getMessage(); } catch (\Throwable $_) {}
        }
    }

    // Basic server-side pagination & filtering
    $q = trim((string)($_GET['q'] ?? ''));
    $page = max(1, (int)($_GET['p'] ?? 1));
    $per = min(100, max(10, (int)($_GET['per'] ?? 25)));

    $base = Capsule::table('eb_customers as c')
        ->leftJoin('tblclients as wc','wc.id','=','c.whmcs_client_id')
        ->where('c.msp_id', (int)($msp->id ?? 0));
    if ($q !== '') {
        $base->where(function($w) use ($q){
            $w->where('c.name','like','%'.$q.'%')
              ->orWhere('c.external_ref','like','%'.$q.'%')
              ->orWhere('wc.firstname','like','%'.$q.'%')
              ->orWhere('wc.lastname','like','%'.$q.'%')
              ->orWhere('wc.companyname','like','%'.$q.'%')
              ->orWhere('wc.email','like','%'.$q.'%');
        });
    }
    $total = (int)($base->count());
    $rowsCol = $base->orderBy('c.created_at','desc')
        ->forPage($page, $per)
        ->get(['c.*','wc.firstname','wc.lastname','wc.companyname','wc.email']);
    // Convert Collection<stdClass> to array of arrays for Smarty
    $rows = [];
    foreach ($rowsCol as $r) { $rows[] = (array)$r; }

    // Optional onboarding error notice from redirect
    $onboardError = isset($_GET['onboard_error']) && $_GET['onboard_error'] !== '';
    $onboardSuccess = isset($_GET['onboard_success']) && $_GET['onboard_success'] !== '';
    $onboardRefresh = isset($_GET['onboard_refresh']) && $_GET['onboard_refresh'] !== '';

    return [
        'pagetitle' => 'Partner Hub — Clients',
        'templatefile' => 'whitelabel/clients',
        'breadcrumb' => [ 'index.php?m=eazybackup' => 'eazyBackup' ],
        'requirelogin' => true,
        'forcessl' => true,
        'vars' => [
            'modulelink' => $vars['modulelink'],
            'msp' => $msp,
            'customers' => $rows,
            'q' => $q,
            'page' => $page,
            'per' => $per,
            'total' => $total,
            'connect' => $connect,
            'connect_due' => $connectDue,
            'eb_debug' => implode('|', $ebDebug),
            'createError' => $createError ?? '',
            'onboardError' => $onboardError,
            'onboardSuccess' => $onboardSuccess,
            'onboardRefresh' => $onboardRefresh,
        ],
    ];
}


