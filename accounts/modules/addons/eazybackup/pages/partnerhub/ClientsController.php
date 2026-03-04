<?php

use WHMCS\Database\Capsule;
use PartnerHub\StripeService;

require_once __DIR__ . '/TenantsController.php';

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

    // Handle create tenant POST (creates eb_tenants row; no WHMCS client)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eb_create_client'])) {
        try {
            try { logActivity('eazybackup: ph-clients POST create-tenant'); } catch (\Throwable $_) { /* ignore */ }
            $ebDebug[] = 'post-branch';
            $companyName = trim((string)($_POST['companyname'] ?? ''));
            $first = trim((string)($_POST['firstname'] ?? ''));
            $last = trim((string)($_POST['lastname'] ?? ''));
            $name = $companyName !== '' ? $companyName : trim($first . ' ' . $last);
            if ($name === '') {
                $name = trim((string)($_POST['email'] ?? ''));
            }
            if ($name === '') {
                $name = 'Tenant';
            }
            $slugRaw = trim((string)($_POST['slug'] ?? ''));
            $slug = eb_ph_tenants_slugify($slugRaw !== '' ? $slugRaw : $name);
            $contactEmail = strtolower(trim((string)($_POST['email'] ?? '')));
            $contactName = trim($first . ' ' . $last);
            if ($contactName === '') {
                $contactName = $companyName;
            }
            $countryRaw = trim((string)($_POST['country'] ?? ''));
            $country = strlen($countryRaw) === 2 ? strtoupper($countryRaw) : null;
            if ($country === null && $countryRaw !== '') {
                try {
                    $code = (string)(Capsule::table('tblcountries')->whereRaw('LOWER(name)=LOWER(?)', [$countryRaw])->value('country') ?? '');
                    if ($code !== '') { $country = strtoupper($code); }
                } catch (\Throwable $__) { /* ignore */ }
            }
            if (!eb_ph_tenants_is_valid_slug($slug)) {
                $createError = 'Invalid slug.';
            } elseif (eb_ph_tenants_existing_slug_owner((int)$msp->id, $slug) !== null) {
                $createError = 'Slug already in use.';
            } else {
                $insert = [
                    'msp_id' => (int)$msp->id,
                    'name' => $name,
                    'slug' => $slug,
                    'contact_email' => $contactEmail !== '' ? $contactEmail : null,
                    'contact_name' => $contactName !== '' ? $contactName : null,
                    'contact_phone' => trim((string)($_POST['phonenumber'] ?? '')) ?: null,
                    'address_line1' => trim((string)($_POST['address1'] ?? '')) ?: null,
                    'address_line2' => trim((string)($_POST['address2'] ?? '')) ?: null,
                    'city' => trim((string)($_POST['city'] ?? '')) ?: null,
                    'state' => trim((string)($_POST['state'] ?? '')) ?: null,
                    'postal_code' => trim((string)($_POST['postcode'] ?? '')) ?: null,
                    'country' => $country,
                    'status' => 'active',
                    'created_at' => Capsule::raw('NOW()'),
                    'updated_at' => Capsule::raw('NOW()'),
                ];
                $tenantId = (int)Capsule::table('eb_tenants')->insertGetId($insert);
                $cometUser = trim((string)($_POST['comet_username'] ?? ''));
                if ($cometUser !== '' && Capsule::schema()->hasTable('eb_tenant_comet_accounts')) {
                    try {
                        Capsule::table('eb_tenant_comet_accounts')->updateOrInsert(
                            ['tenant_id' => $tenantId, 'comet_user_id' => $cometUser],
                            []
                        );
                        if (Capsule::schema()->hasTable('comet_users') && Capsule::schema()->hasColumn('comet_users', 'tenant_id')) {
                            Capsule::table('comet_users')->where('username', $cometUser)->update([
                                'msp_id' => (int)$msp->id,
                                'tenant_id' => $tenantId,
                            ]);
                        }
                    } catch (\Throwable $__) { /* ignore */ }
                }
                try { logActivity('eazybackup: ph-clients created tenant id=' . $tenantId); } catch (\Throwable $_) { /* ignore */ }
                header('Location: ' . ($vars['modulelink'] ?? 'index.php?m=eazybackup') . '&a=ph-tenant&id=' . $tenantId . '&notice=created');
                exit;
            }
        } catch (\Throwable $e) {
            try { logActivity('eazybackup: ph-clients EX=' . $e->getMessage()); } catch (\Throwable $_) { /* ignore */ }
            $createError = 'Error: ' . $e->getMessage();
            $ebDebug[] = 'ex=' . $e->getMessage();
        }
    }

    // List tenants (eb_tenants) for this MSP
    $q = trim((string)($_GET['q'] ?? ''));
    $page = max(1, (int)($_GET['p'] ?? 1));
    $per = min(100, max(10, (int)($_GET['per'] ?? 25)));

    $base = Capsule::table('eb_tenants as t')->where('t.msp_id', (int)($msp->id ?? 0));
    if ($q !== '') {
        $base->where(function ($w) use ($q) {
            $w->where('t.name', 'like', '%' . $q . '%')
              ->orWhere('t.contact_email', 'like', '%' . $q . '%')
              ->orWhere('t.contact_name', 'like', '%' . $q . '%')
              ->orWhere('t.external_ref', 'like', '%' . $q . '%');
        });
    }
    $total = (int)$base->count();
    $rowsCol = $base->orderBy('t.created_at', 'desc')->forPage($page, $per)->get(['t.*']);
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


