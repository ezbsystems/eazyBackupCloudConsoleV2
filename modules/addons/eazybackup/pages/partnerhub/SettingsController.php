<?php

use WHMCS\Database\Capsule;
use PartnerHub\SettingsService;
use PartnerHub\StripeService;

function eb_ph_settings_checkout_show(array $vars)
{
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { header('Location: clientarea.php'); exit; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { header('Location: index.php?m=eazybackup&a=ph-clients'); exit; }

    $mspId = (int)$msp->id;

    // Settings (defaults merged)
    $settings = SettingsService::getCheckoutSettings($mspId);

    // Capabilities snapshot for warnings
    $capabilities = [ 'cards' => true, 'bank_debits' => false ];
    try {
        $caps = json_decode((string)($msp->connect_capabilities ?? ''), true);
        if (is_array($caps)) {
            $capabilities['cards'] = (isset($caps['card_payments']) && (string)$caps['card_payments'] === 'active');
            $bankActive = false;
            foreach ($caps as $k=>$v) {
                if (!is_string($k)) continue;
                if (stripos($k,'bank') !== false || stripos($k,'debit') !== false) {
                    if ((string)$v === 'active') { $bankActive = true; break; }
                }
            }
            $capabilities['bank_debits'] = $bankActive;
        }
    } catch (\Throwable $__) { /* ignore */ }

    return [
        'pagetitle' => 'Settings — Checkout & Dunning',
        'templatefile' => 'whitelabel/settings-checkout',
        'breadcrumb' => [ 'index.php?m=eazybackup' => 'eazyBackup', 'index.php?m=eazybackup&a=ph-clients' => 'Partner Hub' ],
        'requirelogin' => true,
        'forcessl' => true,
        'vars' => [
            'modulelink' => $vars['modulelink'] ?? ('index.php?m=eazybackup'),
            'token' => function_exists('generate_token') ? generate_token('plain') : '',
            'settings' => $settings,
            'capabilities' => $capabilities,
            'msp' => (array)$msp,
        ],
    ];
}

function eb_ph_settings_checkout_save(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { echo json_encode(['status'=>'error','message'=>'method']); return; }

    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { echo json_encode(['status'=>'error','message'=>'msp']); return; }
    $mspId = (int)$msp->id;

    $token = (string)($_POST['token'] ?? '');
    if (function_exists('check_token')) { try { if (!check_token('plain', $token)) { echo json_encode(['status'=>'error','message'=>'csrf']); return; } } catch (\Throwable $__) {} }

    // Expect payload either as JSON string in 'payload' or flat POST fields (not used here)
    $payload = [];
    $diag = [
        'content_type' => (string)($_SERVER['CONTENT_TYPE'] ?? ''),
        'post_keys' => implode(',', array_keys($_POST ?? [])),
        'had_payload_param' => isset($_POST['payload']) ? 1 : 0,
        'attempts' => [],
    ];
    if (isset($_POST['payload'])) {
        $raw = (string)$_POST['payload'];
        // Tolerate stray wrapping quotes and slashes
        $rawTrim = trim($raw);
        if ((substr($rawTrim,0,1)==="'" && substr($rawTrim,-1)==="'") || (substr($rawTrim,0,1)=='"' && substr($rawTrim,-1)=='"')) {
            $rawTrim = substr($rawTrim,1,strlen($rawTrim)-2);
        }
        $rawTrim = stripslashes($rawTrim);
        $rawTrim = rawurldecode($rawTrim);
        $rawTrim = html_entity_decode($rawTrim, ENT_QUOTES, 'UTF-8');
        // Also try plus-to-space decode edge cases
        $rawTrimPlus = str_replace('+',' ', $rawTrim);
        $decoded = json_decode($rawTrim, true);
        if (!is_array($decoded)) { $diag['attempts'][] = 'try:trim+stripslashes+urldecode err='.json_last_error_msg(); }
        if (!is_array($decoded)) {
            // Fallback: try decoding original
            $decoded = json_decode(html_entity_decode($raw, ENT_QUOTES, 'UTF-8'), true);
            if (!is_array($decoded)) { $diag['attempts'][] = 'try:raw err='.json_last_error_msg(); }
            if (!is_array($decoded)) {
                $decoded = json_decode(html_entity_decode(rawurldecode($raw), ENT_QUOTES, 'UTF-8'), true);
                if (!is_array($decoded)) { $diag['attempts'][] = 'try:raw urldecode err='.json_last_error_msg(); }
                if (!is_array($decoded)) {
                    $decoded = json_decode($rawTrimPlus, true);
                    if (!is_array($decoded)) { $diag['attempts'][] = 'try:plus-space err='.json_last_error_msg(); }
                }
            }
        }
        if (is_array($decoded)) { $payload = $decoded; }
    } else {
        // Fallback: attempt to read raw body (for application/json clients)
        try {
            $rawBody = (string)file_get_contents('php://input');
            if ($rawBody !== '') {
                parse_str($rawBody, $form);
                if (isset($form['payload'])) {
                    $decoded = json_decode(html_entity_decode((string)$form['payload'], ENT_QUOTES, 'UTF-8'), true);
                    if (!is_array($decoded)) { $diag['attempts'][] = 'try:parse_str payload err='.json_last_error_msg(); }
                    if (is_array($decoded)) { $payload = $decoded; }
                } else {
                    $maybe = json_decode(html_entity_decode($rawBody, ENT_QUOTES, 'UTF-8'), true);
                    if (!is_array($maybe)) { $diag['attempts'][] = 'try:rawBody json err='.json_last_error_msg(); }
                    if (is_array($maybe)) { $payload = $maybe; }
                }
            }
        } catch (\Throwable $__) { /* ignore */ }
    }
    if (!is_array($payload) || empty($payload)) {
        // Include safe diagnostics for troubleshooting
        $rawPreview = '';
        try {
            $rawPreview = isset($_POST['payload']) ? (string)$_POST['payload'] : '';
            $diag['raw_len'] = strlen($rawPreview);
            $diag['raw_head'] = substr($rawPreview, 0, 64);
            $diag['raw_tail'] = substr($rawPreview, -64);
            $diag['raw_b64_120'] = base64_encode(substr($rawPreview, 0, 120));
        } catch (\Throwable $__) {}
        try { logModuleCall('eazybackup','ph-settings-checkout-save-invalid', ['meta'=>$diag], ['ok'=>false]); } catch (\Throwable $__) {}
        echo json_encode(['status'=>'error','message'=>'invalid','code'=>'payload_parse_failed','details'=>$diag]);
        return;
    }

    // Normalize and validate statement descriptor
    $descriptor = (string)($payload['checkout_experience']['statement_descriptor'] ?? '');
    $descriptor = strtoupper(trim($descriptor));
    $payload['checkout_experience']['statement_descriptor'] = $descriptor;
    if (strlen($descriptor) > 22) { echo json_encode(['status'=>'error','message'=>'descriptor_len']); return; }
    if ($descriptor !== '' && !preg_match('/^[A-Z0-9 ]{1,22}$/', $descriptor)) { echo json_encode(['status'=>'error','message'=>'descriptor_chars']); return; }

    // Currency guard: confirm if changing to a new default and published prices exist in other currencies
    $newCurrency = strtoupper((string)($payload['checkout_experience']['default_currency'] ?? ''));
    if ($newCurrency === '') { $newCurrency = 'USD'; $payload['checkout_experience']['default_currency'] = 'USD'; }
    $force = (int)($_POST['force'] ?? 0) === 1;
    try {
        $currentCur = (string)(Capsule::table('eb_msp_accounts')->where('id',$mspId)->value('default_currency') ?? '');
        if ($currentCur === '') { $currentCur = 'USD'; }
        if ($newCurrency !== '' && strtoupper($newCurrency) !== strtoupper($currentCur)) {
            if (SettingsService::hasPublishedPricesInOtherCurrencies($mspId, $newCurrency) && !$force) {
                echo json_encode(['status'=>'confirm_required','code'=>'currency_conflict','message'=>'Existing published prices use a different currency. Changing default currency does not affect existing prices.']);
                return;
            }
        }
    } catch (\Throwable $__) { /* ignore */ }

    // Persist
    try {
        SettingsService::saveCheckoutSettings($mspId, $payload);
    } catch (\Throwable $e) {
        try { logActivity('eazybackup: ph-settings-checkout-save EX='.$e->getMessage()); } catch (\Throwable $__) {}
        echo json_encode(['status'=>'error','message'=>'persist']);
        return;
    }

    // Update Stripe business profile if available
    try {
        $svc = new StripeService();
        $svc->updateConnectedAccountProfile($mspId, [
            'statement_descriptor' => $descriptor,
            'support_url' => (string)($payload['checkout_experience']['support_url'] ?? ''),
        ]);
    } catch (\Throwable $__) { /* ignore */ }

    try { logModuleCall('eazybackup','ph-settings-checkout-save',$payload,['ok'=>true]); } catch (\Throwable $__) {}
    echo json_encode(['status'=>'success']);
}


