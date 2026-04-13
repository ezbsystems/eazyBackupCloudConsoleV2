<?php

use WHMCS\Database\Capsule;
use PartnerHub\SettingsService;
use PartnerHub\StripeService;

/** @internal Stripe Tax Registration types referenced from Stripe API (Create a registration). */
function eb_ph_stripe_tax_registration_types_allowed(): array
{
    return ['standard', 'simplified', 'province_standard', 'state_sales_tax', 'ioss', 'oss_union', 'oss_non_union'];
}

/**
 * EU member states where Stripe documents a domestic `standard` registration with required
 * `country_options.{cc}.standard.place_of_supply_scheme` (see Stripe Tax Registrations API).
 */
function eb_ph_stripe_tax_eu_member_countries(): array
{
    return [
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT',
        'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
    ];
}

function eb_ph_default_stripe_registration_type(string $country, string $region): string
{
    $c = strtoupper($country);
    if ($c === 'US') {
        return 'state_sales_tax';
    }
    if ($c === 'CA') {
        return 'standard';
    }
    return 'standard';
}

/**
 * @return array<string, mixed>|null Stripe POST body, or null to skip remote registration (local-only).
 */
function eb_ph_build_stripe_tax_registration_create_params(array $reg, string $stripeType): ?array
{
    $country = strtoupper((string)($reg['country'] ?? ''));
    $region = strtoupper((string)($reg['region'] ?? ''));
    $cc = strtolower($country);

    $allowed = eb_ph_stripe_tax_registration_types_allowed();
    if (!in_array($stripeType, $allowed, true)) {
        return null;
    }

    if ($stripeType === 'state_sales_tax') {
        if ($country !== 'US' || $region === '') {
            return null;
        }
        $opts = [
            'type' => 'state_sales_tax',
            'state' => $region,
        ];
    } elseif ($stripeType === 'province_standard') {
        if ($country !== 'CA' || $region === '') {
            return null;
        }
        $opts = [
            'type' => 'province_standard',
            'province_standard' => ['province' => $region],
        ];
    } else {
        $opts = ['type' => $stripeType];
        if ($stripeType === 'standard' && in_array($country, eb_ph_stripe_tax_eu_member_countries(), true)) {
            $opts['standard'] = ['place_of_supply_scheme' => 'standard'];
        }
    }

    return [
        'country' => $country,
        'active_from' => 'now',
        'country_options' => [$cc => $opts],
    ];
}

function eb_ph_stripe_registration_type_label(string $stripeType): string
{
    $type = trim($stripeType);
    if ($type === '') {
        return 'Auto';
    }
    $labels = [
        'standard' => 'Standard',
        'simplified' => 'Simplified',
        'province_standard' => 'Canada provincial',
        'state_sales_tax' => 'US state sales tax',
        'ioss' => 'EU IOSS',
        'oss_union' => 'EU OSS (union)',
        'oss_non_union' => 'EU OSS (non-union)',
    ];
    return $labels[$type] ?? $type;
}

function eb_ph_settings_tax_show(array $vars)
{
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { header('Location: clientarea.php'); exit; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { header('Location: index.php?m=eazybackup&a=ph-tenants-manage'); exit; }
    $mspId = (int)$msp->id;

    $settings = SettingsService::getTaxSettings($mspId);
    $regs = SettingsService::listRegistrations($mspId);
    foreach ($regs as &$reg) {
        $reg['stripe_registration_type_label'] = eb_ph_stripe_registration_type_label((string)($reg['stripe_registration_type'] ?? ''));
    }
    unset($reg);

    return [
        'pagetitle' => 'Settings — Tax & Invoicing',
        'templatefile' => 'whitelabel/settings-tax',
        'breadcrumb' => [ 'index.php?m=eazybackup' => 'eazyBackup', 'index.php?m=eazybackup&a=ph-tenants-manage' => 'Partner Hub' ],
        'requirelogin' => true,
        'forcessl' => true,
        'vars' => [
            'modulelink' => $vars['modulelink'] ?? ('index.php?m=eazybackup'),
            'token' => function_exists('generate_token') ? generate_token('plain') : '',
            'settings' => $settings,
            'registrations' => $regs,
            'msp' => (array)$msp,
        ],
    ];
}

function eb_ph_settings_tax_save(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { echo json_encode(['status'=>'error','message'=>'method']); return; }

    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { echo json_encode(['status'=>'error','message'=>'msp']); return; }
    $mspId = (int)$msp->id;

    $token = (string)($_POST['token'] ?? '');
    if (function_exists('check_token')) { try { if (!check_token('plain', $token)) { echo json_encode(['status'=>'error','message'=>'csrf']); return; } } catch (\Throwable $__) { echo json_encode(['status'=>'error','message'=>'csrf']); return; } }

    // Payload parse (same robustness as checkout)
    $payload = [];
    $raw = (string)($_POST['payload'] ?? '');
    $rawTrim = trim($raw);
    if ((substr($rawTrim,0,1)==="'" && substr($rawTrim,-1)==="'") || (substr($rawTrim,0,1)=='"' && substr($rawTrim,-1)=='"')) { $rawTrim = substr($rawTrim,1,strlen($rawTrim)-2); }
    $rawTrim = stripslashes($rawTrim);
    $rawTrim = rawurldecode($rawTrim);
    $rawTrim = html_entity_decode($rawTrim, ENT_QUOTES, 'UTF-8');
    $decoded = json_decode($rawTrim, true);
    if (!is_array($decoded)) { $decoded = json_decode(html_entity_decode($raw, ENT_QUOTES, 'UTF-8'), true); }
    if (is_array($decoded)) { $payload = $decoded; }
    if (!is_array($payload) || empty($payload)) { echo json_encode(['status'=>'error','message'=>'invalid']); return; }

    // Validation
    $iprefix = (string)($payload['invoice_presentation']['invoice_prefix'] ?? '');
    if ($iprefix !== '' && !preg_match('/^[A-Z0-9\-_.]{0,16}$/i', $iprefix)) { echo json_encode(['status'=>'error','message'=>'prefix']); return; }
    $taxBehavior = (string)($payload['tax_mode']['default_tax_behavior'] ?? 'exclusive');
    if (!in_array($taxBehavior, ['exclusive','inclusive'], true)) { echo json_encode(['status'=>'error','message'=>'tax_behavior']); return; }
    $terms = (string)($payload['invoice_presentation']['payment_terms'] ?? 'due_immediately');
    if (!in_array($terms, ['due_immediately','net_7','net_15','net_30'], true)) { echo json_encode(['status'=>'error','message'=>'terms']); return; }
    $rounding = (string)($payload['rounding']['rounding_mode'] ?? 'bankers_rounding');
    if (!in_array($rounding, ['bankers_rounding','round_half_up'], true)) { echo json_encode(['status'=>'error','message'=>'rounding']); return; }

    // Persist
    $before = SettingsService::getTaxSettings($mspId);
    SettingsService::saveTaxSettings($mspId, $payload);
    SettingsService::auditTax($mspId, 'update', $before, $payload, ['actor'=>'client-area','ip'=>($_SERVER['REMOTE_ADDR'] ?? '')], (int)($_SESSION['uid'] ?? 0));

    // Stripe updates (best-effort)
    try {
        $footer = (string)($payload['invoice_presentation']['footer_md'] ?? '');
        $days = null;
        if ($terms === 'net_7') $days = 7; else if ($terms === 'net_15') $days = 15; else if ($terms === 'net_30') $days = 30; else $days = null;
        $svc = new StripeService();
        $svc->updateInvoiceSettings($mspId, [ 'footer' => strip_tags($footer), 'days_until_due' => $days ]);
    } catch (\Throwable $__) { /* ignore */ }

    try { logModuleCall('eazybackup','ph-settings-tax-save',$payload,['ok'=>true]); } catch (\Throwable $__) {}
    echo json_encode(['status'=>'success']);
}

function eb_ph_tax_registrations(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { echo json_encode(['status'=>'error','message'=>'msp']); return; }
    $rows = SettingsService::listRegistrations((int)$msp->id);
    foreach ($rows as &$row) {
        $row['stripe_registration_type_label'] = eb_ph_stripe_registration_type_label((string)($row['stripe_registration_type'] ?? ''));
    }
    unset($row);
    echo json_encode(['status'=>'success','data'=>$rows]);
}

function eb_ph_tax_registration_upsert(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { echo json_encode(['status'=>'error','message'=>'method']); return; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { echo json_encode(['status'=>'error','message'=>'msp']); return; }
    $mspId = (int)$msp->id;
    $token = (string)($_POST['token'] ?? '');
    if (function_exists('check_token')) { try { if (!check_token('plain', $token)) { echo json_encode(['status'=>'error','message'=>'csrf']); return; } } catch (\Throwable $__) { echo json_encode(['status'=>'error','message'=>'csrf']); return; } }

    $reg = [
        'id' => (int)($_POST['id'] ?? 0),
        'country' => strtoupper((string)($_POST['country'] ?? '')),
        'region' => strtoupper((string)($_POST['region'] ?? '')),
        'registration_number' => trim((string)($_POST['registration_number'] ?? '')),
        'legal_name' => trim((string)($_POST['legal_name'] ?? '')),
        'is_active' => (int)($_POST['is_active'] ?? 1),
    ];
    if ($reg['registration_number'] === '' || strlen($reg['country']) !== 2) { echo json_encode(['status'=>'error','message'=>'invalid']); return; }

    $reqStripeType = trim((string)($_POST['stripe_registration_type'] ?? ''));
    if ($reqStripeType !== '' && !in_array($reqStripeType, eb_ph_stripe_tax_registration_types_allowed(), true)) {
        echo json_encode(['status'=>'error','message'=>'Select a valid Stripe registration type.']);
        return;
    }
    $stripeType = $reqStripeType !== '' ? $reqStripeType : eb_ph_default_stripe_registration_type($reg['country'], $reg['region']);
    if ($stripeType === 'state_sales_tax' && $reg['country'] === 'US' && $reg['region'] === '') {
        echo json_encode(['status'=>'error','message'=>'State/region is required for United States tax registrations.']);
        return;
    }
    if ($stripeType === 'province_standard' && $reg['country'] === 'CA' && $reg['region'] === '') {
        echo json_encode(['status'=>'error','message'=>'Province/region is required for Canadian provincial registrations.']);
        return;
    }
    $reg['stripe_registration_type'] = $stripeType;

    // Try Stripe first
    try {
        $acct = (string)($msp->stripe_connect_id ?? '');
        if ($acct !== '') {
            $stripeParams = eb_ph_build_stripe_tax_registration_create_params($reg, $stripeType);
            if (is_array($stripeParams)) {
                $svc = new StripeService();
                $created = $svc->createTaxRegistration($acct, $stripeParams);
                $reg['stripe_registration_id'] = (string)($created['id'] ?? '');
                $reg['source'] = 'stripe';
            }
        }
    } catch (\Throwable $__) { /* fall back to local */ }

    $out = SettingsService::upsertRegistration($mspId, $reg);
    SettingsService::auditTax($mspId, ($reg['id'] ?? 0) ? 'update' : 'create', null, $out, ['endpoint'=>'upsert'], (int)$clientId);
    echo json_encode(['status'=>'success','data'=>$out]);
}

function eb_ph_tax_registration_delete(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { echo json_encode(['status'=>'error','message'=>'method']); return; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { echo json_encode(['status'=>'error','message'=>'msp']); return; }
    $mspId = (int)$msp->id;
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { echo json_encode(['status'=>'error','message'=>'invalid']); return; }
    // Try Stripe delete if mapped
    try {
        $row = Capsule::table('eb_msp_tax_regs')->where('id',$id)->where('msp_id',$mspId)->first();
        if ($row && (string)($row->stripe_registration_id ?? '') !== '' && (string)($msp->stripe_connect_id ?? '') !== '') {
            $svc = new StripeService();
            $svc->deleteTaxRegistration((string)$msp->stripe_connect_id, (string)$row->stripe_registration_id);
        }
    } catch (\Throwable $__) { /* ignore */ }
    $ok = SettingsService::deleteRegistration($mspId, $id);
    SettingsService::auditTax($mspId, 'delete', null, ['id'=>$id], ['endpoint'=>'delete'], (int)$clientId);
    echo json_encode(['status'=>$ok?'success':'error']);
}


