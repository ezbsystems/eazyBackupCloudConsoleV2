<?php

use WHMCS\Database\Capsule;
use PartnerHub\SettingsService;
use PartnerHub\MailService;

function eb_ph_settings_email_show(array $vars)
{
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { header('Location: clientarea.php'); exit; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { header('Location: index.php?m=eazybackup&a=ph-clients'); exit; }
    $mspId = (int)$msp->id;

    $settings = SettingsService::getEmailSettings($mspId);
    // Normalize CC list for display as a comma-separated string
    try {
        $cc = $settings['sender']['cc_finance'] ?? [];
        if (is_array($cc)) {
            $settings['sender']['cc_finance_display'] = implode(', ', array_filter(array_map('strval', $cc), function($s){ return $s !== ''; }));
        } else if (is_string($cc)) {
            $settings['sender']['cc_finance_display'] = $cc;
        } else {
            $settings['sender']['cc_finance_display'] = '';
        }
    } catch (\Throwable $__) { $settings['sender']['cc_finance_display'] = ''; }

    return [
        'pagetitle' => 'Settings — Email Templates',
        'templatefile' => 'whitelabel/settings-email',
        'breadcrumb' => [ 'index.php?m=eazybackup' => 'eazyBackup', 'index.php?m=eazybackup&a=ph-clients' => 'Partner Hub' ],
        'requirelogin' => true,
        'forcessl' => true,
        'vars' => [
            'modulelink' => $vars['modulelink'] ?? ('index.php?m=eazybackup'),
            'token' => function_exists('generate_token') ? generate_token('plain') : '',
            'settings' => $settings,
            'msp' => (array)$msp,
        ],
    ];
}

function eb_ph_settings_email_save(array $vars): void
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

    $raw = (string)($_POST['payload'] ?? '');
    $rawTrim = trim($raw);
    if ((substr($rawTrim,0,1)==="'" && substr($rawTrim,-1)==="'") || (substr($rawTrim,0,1)=='"' && substr($rawTrim,-1)=='"')) { $rawTrim = substr($rawTrim,1,strlen($rawTrim)-2); }
    $rawTrim = stripslashes($rawTrim);
    $rawTrim = rawurldecode($rawTrim);
    $rawTrim = html_entity_decode($rawTrim, ENT_QUOTES, 'UTF-8');
    $payload = json_decode($rawTrim, true);
    if (!is_array($payload)) { echo json_encode(['status'=>'error','message'=>'invalid']); return; }

    // Basic validations
    $sender = (array)($payload['sender'] ?? []);
    $fromAddr = (string)($sender['from_address'] ?? '');
    if ($fromAddr !== '' && !preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $fromAddr)) { echo json_encode(['status'=>'error','message'=>'from_address']); return; }
    $replyTo = (string)($sender['reply_to'] ?? '');
    if ($replyTo !== '' && !preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $replyTo)) { echo json_encode(['status'=>'error','message'=>'reply_to']); return; }
    $smtp = (array)($payload['smtp'] ?? []);
    if ((string)($smtp['mode'] ?? 'builtin') !== 'builtin') {
        if ((string)($smtp['host'] ?? '') === '') { echo json_encode(['status'=>'error','message'=>'smtp_host']); return; }
        if ((int)($smtp['port'] ?? 0) <= 0) { echo json_encode(['status'=>'error','message'=>'smtp_port']); return; }
    }

    SettingsService::saveEmailSettings($mspId, $payload);
    try { logModuleCall('eazybackup','ph-settings-email-save',$payload,['ok'=>true]); } catch (\Throwable $__) {}
    echo json_encode(['status'=>'success']);
}

function eb_ph_email_test(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { echo json_encode(['status'=>'error','message'=>'method']); return; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { echo json_encode(['status'=>'error','message'=>'msp']); return; }
    $token = (string)($_POST['token'] ?? '');
    if (function_exists('check_token')) { try { if (!check_token('plain', $token)) { echo json_encode(['status'=>'error','message'=>'csrf']); return; } } catch (\Throwable $__) {} }

    $tplKey = preg_replace('/[^a-z_]/','', (string)($_POST['template'] ?? 'welcome'));
    $to = (string)($_POST['to'] ?? '');
    if ($to === '' || !preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $to)) { echo json_encode(['status'=>'error','message'=>'to']); return; }

    $sample = [
        'customer' => [ 'name' => 'Jane Doe', 'company' => 'Acme', 'email' => 'jane@example.com' ],
        'invoice' => [ 'number' => 'INV-1001', 'amount_due' => '29.00', 'due_date' => '2025-12-31' ],
        'subscription' => [ 'plan_name' => 'Pro' ],
        'portal_url' => 'https://portal.example.com',
        'pay_link_url' => 'https://pay.example.com/abcdef',
        'msp' => [ 'brand' => [ 'name' => 'Your MSP' ], 'support' => [ 'email' => 'support@example.com' ] ],
    ];
    $ret = MailService::sendTemplate((int)$msp->id, $tplKey, $to, $sample, true);
    if (!($ret['ok'] ?? false)) { echo json_encode(['status'=>'error','message'=>(string)($ret['error'] ?? 'send_failed')]); return; }
    try { logModuleCall('eazybackup','ph-email-test', ['key'=>$tplKey,'to'=>$to], ['ok'=>true]); } catch (\Throwable $__) {}
    echo json_encode(['status'=>'success']);
}

function eb_ph_email_restore_default(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status'=>'error','message'=>'auth']); return; }
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { echo json_encode(['status'=>'error','message'=>'method']); return; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id',$clientId)->first();
    if (!$msp) { echo json_encode(['status'=>'error','message'=>'msp']); return; }
    $token = (string)($_POST['token'] ?? '');
    if (function_exists('check_token')) { try { if (!check_token('plain', $token)) { echo json_encode(['status'=>'error','message'=>'csrf']); return; } } catch (\Throwable $__) {} }
    $key = preg_replace('/[^a-z_]/','', (string)($_POST['template'] ?? 'welcome'));
    $settings = SettingsService::getEmailSettings((int)$msp->id);
    $defaults = (new \ReflectionClass(\PartnerHub\SettingsService::class)); // not directly accessible; recompose
    $base = SettingsService::getEmailSettings((int)$msp->id); // already merged; reset just this key to hard defaults below
    $hard = [
        'welcome' => [ 'subject' => 'Welcome to {{ msp.brand.name }} Backup', 'body_md' => 'Hi {{ customer.name }}, welcome!' ],
        'trial_ending' => [ 'subject' => 'Your trial ends soon', 'body_md' => 'Hi {{ customer.name }}, your trial ends soon.' ],
        'payment_failed' => [ 'subject' => 'Payment failed on {{ invoice.number }}', 'body_md' => 'We could not process your payment.' ],
        'card_expiring' => [ 'subject' => 'Your card is expiring soon', 'body_md' => 'Please update your payment method.' ],
        'subscription_changed' => [ 'subject' => 'Subscription updated', 'body_md' => 'Your subscription has changed.' ],
        'new_invoice' => [ 'subject' => 'New invoice {{ invoice.number }}', 'body_md' => 'A new invoice is available.' ],
        'pay_link' => [ 'subject' => 'Complete your payment', 'body_md' => '{{ pay_link_url }}' ],
    ];
    if (!isset($hard[$key])) { echo json_encode(['status'=>'error','message'=>'invalid']); return; }
    $settings['templates'][$key] = $hard[$key];
    SettingsService::saveEmailSettings((int)$msp->id, $settings);
    echo json_encode(['status'=>'success']);
}


