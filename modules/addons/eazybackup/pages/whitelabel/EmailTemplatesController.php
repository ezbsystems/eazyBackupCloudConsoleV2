<?php

use WHMCS\Database\Capsule;

require_once __DIR__ . '/../../lib/Whitelabel/MailService.php';

/** List templates and handle enable/disable toggles */
function eazybackup_whitelabel_email_templates(array $vars)
{
    if (!((int)($_SESSION['uid'] ?? 0) > 0)) {
        return [ 'pagetitle'=>'Email Templates', 'templatefile'=>'templates/error', 'vars'=>['error'=>'Please sign in to continue.'] ];
    }
    // Resolve tenant by tid or id; verify ownership
    $tenantId = 0; $tenantObj = null; $tid = strtoupper(trim((string)($_GET['tid'] ?? '')));
    if ($tid !== '' && preg_match('/^[0-9A-HJ-NP-TV-Z]{26}$/', $tid)) {
        $tenantObj = Capsule::table('eb_whitelabel_tenants')->where('public_id', $tid)->first();
        if ($tenantObj) { $tenantId = (int)$tenantObj->id; }
    }
    if ($tenantId <= 0) {
        $tenantId = (int)($_GET['id'] ?? 0);
        $tenantObj = $tenantId ? Capsule::table('eb_whitelabel_tenants')->where('id', $tenantId)->first() : null;
    }
    if (!$tenantObj || (int)$tenantObj->client_id !== (int)$_SESSION['uid']) {
        return [ 'pagetitle'=>'Email Templates', 'templatefile'=>'templates/error', 'vars'=>['error'=>'Tenant not found.'] ];
    }

    // Seed defaults
    try { $ms = new \EazyBackup\Whitelabel\MailService($vars); $ms->seedSmtpIfMissing($tenantId); $ms->seedDefaultTemplatesIfMissing($tenantId); } catch (\Throwable $__) {}

    // Handle toggle POST
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $token = (string)($_POST['token'] ?? '');
        if (function_exists('check_token')) { try { if (!check_token('plain', $token)) { header('Location: '.$vars['modulelink'].'&a=whitelabel-email-templates&tid='.urlencode((string)$tenantObj->public_id).'&error=csrf'); exit; } } catch (\Throwable $__) {} }
        $k = preg_replace('/[^a-z0-9_-]/i','', (string)($_POST['key'] ?? ''));
        $active = isset($_POST['is_active']) ? 1 : 0;
        if ($k !== '') {
            try { Capsule::table('eb_whitelabel_email_templates')->where('tenant_id',$tenantId)->where('key',$k)->update(['is_active'=>$active,'updated_at'=>date('Y-m-d H:i:s')]); } catch (\Throwable $__) {}
        }
        header('Location: '.$vars['modulelink'].'&a=whitelabel-email-templates&tid='.urlencode((string)$tenantObj->public_id).'&saved=1');
        exit;
    }

    // Load templates and smtp state
    $templates = [];
    try { $templates = Capsule::table('eb_whitelabel_email_templates')->where('tenant_id', $tenantId)->orderBy('name','asc')->get()->toArray(); } catch (\Throwable $__) {}
    $smtpConfigured = false;
    try { $smtpConfigured = (new \EazyBackup\Whitelabel\MailService($vars))->isSmtpConfigured($tenantId); } catch (\Throwable $__) {}

    return [
        'pagetitle' => 'Email Templates',
        'breadcrumb' => ['index.php?m=eazybackup' => 'eazyBackup'],
        'templatefile' => 'templates/whitelabel/email-templates',
        'requirelogin' => true,
        'forcessl' => true,
        'vars' => [
            'modulelink' => $vars['modulelink'],
            'tenant' => (array)$tenantObj,
            'templates' => array_map(function($r){ return (array)$r; }, $templates),
            'smtp_configured' => $smtpConfigured ? 1 : 0,
            'csrf_token' => (function(){ try { if (function_exists('generate_token')) { return (string)generate_token('plain'); } } catch (\Throwable $__) {} return ''; })(),
            'flash_saved' => (int)(($_GET['saved'] ?? 0)) === 1 ? 1 : 0,
            'flash_error' => (string)($_GET['error'] ?? ''),
        ],
    ];
}

/** Edit a single template (save / test send) */
function eazybackup_whitelabel_email_template_edit(array $vars)
{
    if (!((int)($_SESSION['uid'] ?? 0) > 0)) {
        return [ 'pagetitle'=>'Edit Email Template', 'templatefile'=>'templates/error', 'vars'=>['error'=>'Please sign in to continue.'] ];
    }
    $tenantId = 0; $tenantObj = null; $tid = strtoupper(trim((string)($_GET['tid'] ?? '')));
    if ($tid !== '' && preg_match('/^[0-9A-HJ-NP-TV-Z]{26}$/', $tid)) { $tenantObj = Capsule::table('eb_whitelabel_tenants')->where('public_id',$tid)->first(); if ($tenantObj) { $tenantId = (int)$tenantObj->id; } }
    if ($tenantId <= 0) { $tenantId = (int)($_GET['id'] ?? 0); $tenantObj = $tenantId ? Capsule::table('eb_whitelabel_tenants')->where('id',$tenantId)->first() : null; }
    if (!$tenantObj || (int)$tenantObj->client_id !== (int)$_SESSION['uid']) { return [ 'pagetitle'=>'Edit Email Template', 'templatefile'=>'templates/error', 'vars'=>['error'=>'Tenant not found.'] ]; }

    $key = preg_replace('/[^a-z0-9_-]/i','', (string)($_GET['tpl'] ?? 'welcome'));
    $tpl = Capsule::table('eb_whitelabel_email_templates')->where('tenant_id',$tenantId)->where('key',$key)->first();
    if (!$tpl) {
        // Seed if somehow missing
        try { (new \EazyBackup\Whitelabel\MailService($vars))->seedDefaultTemplatesIfMissing($tenantId); } catch (\Throwable $__) {}
        $tpl = Capsule::table('eb_whitelabel_email_templates')->where('tenant_id',$tenantId)->where('key',$key)->first();
    }

    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $token = (string)($_POST['token'] ?? '');
        if (function_exists('check_token')) { try { if (!check_token('plain', $token)) { header('Location: '.$vars['modulelink'].'&a=whitelabel-email-template-edit&tid='.urlencode((string)$tenantObj->public_id).'&tpl='.urlencode($key).'&error=csrf'); exit; } } catch (\Throwable $__) {} }

        if (isset($_POST['action']) && $_POST['action'] === 'save') {
            $subject = trim((string)($_POST['subject'] ?? ''));
            $bodyHtml = (string)($_POST['body_html'] ?? '');
            $bodyText = (string)($_POST['body_text'] ?? '');
            // Basic HTML sanitization: keep a safe subset of tags, drop scripts and event handlers
            $allow = '<p><a><strong><em><u><ul><ol><li><br><span><div><h1><h2><h3><h4><h5><h6><blockquote>'; 
            $bodyHtml = strip_tags($bodyHtml, $allow);
            // Remove on* attributes and javascript: URLs
            $bodyHtml = preg_replace('/\son[a-z]+\s*=\s*"[^"]*"/i', '', $bodyHtml);
            $bodyHtml = preg_replace("/\son[a-z]+\s*=\s*'[^']*'/i", '', $bodyHtml);
            $bodyHtml = preg_replace('/javascript\s*:/i', '', $bodyHtml);
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            try {
                Capsule::table('eb_whitelabel_email_templates')->updateOrInsert(
                    ['tenant_id'=>$tenantId,'key'=>$key],
                    ['name'=>ucfirst($key).' Email','subject'=>$subject,'body_html'=>$bodyHtml,'body_text'=>$bodyText,'is_active'=>$isActive,'updated_at'=>date('Y-m-d H:i:s'),'created_at'=>date('Y-m-d H:i:s')]
                );
                try { logModuleCall('eazybackup','email_tpl_save',['tenant'=>$tenantId,'key'=>$key],'ok'); } catch (\Throwable $__) {}
            } catch (\Throwable $__) {}
            header('Location: '.$vars['modulelink'].'&a=whitelabel-email-template-edit&tid='.urlencode((string)$tenantObj->public_id).'&tpl='.urlencode($key).'&saved=1');
            exit;
        } else if (isset($_POST['action']) && $_POST['action'] === 'test') {
            $to = trim((string)($_POST['test_to'] ?? ''));
            if ($to !== '' && filter_var($to, FILTER_VALIDATE_EMAIL)) {
                $ms = new \EazyBackup\Whitelabel\MailService($vars);
                $brandName = (string)($tenantObj->product_name ?? 'eazyBackup');
                $portalUrl = rtrim((string)($vars['systemurl'] ?? ''), '/') . '/index.php?m=eazybackup&a=public-download';
                $helpUrl = rtrim((string)($vars['systemurl'] ?? ''), '/') . '/index.php?m=eazybackup&a=knowledgebase';
                $varsSend = [ 'customer_name'=>'Test User', 'brand_name'=>$brandName, 'portal_url'=>$portalUrl, 'help_url'=>$helpUrl ];
                $ms->testSend($tenantId, $key, $to, $varsSend);
                try { logModuleCall('eazybackup','email_tpl_test',['tenant'=>$tenantId,'key'=>$key,'to'=>$to],'ok'); } catch (\Throwable $__) {}
                header('Location: '.$vars['modulelink'].'&a=whitelabel-email-template-edit&tid='.urlencode((string)$tenantObj->public_id).'&tpl='.urlencode($key).'&tested=1');
                exit;
            } else {
                header('Location: '.$vars['modulelink'].'&a=whitelabel-email-template-edit&tid='.urlencode((string)$tenantObj->public_id).'&tpl='.urlencode($key).'&error=invalid_to');
                exit;
            }
        }
    }

    $smtpConfigured = (new \EazyBackup\Whitelabel\MailService($vars))->isSmtpConfigured($tenantId);
    // Prepare template payload with decoded HTML if entities were stored
    $tplArr = $tpl ? (array)$tpl : ['key'=>$key,'name'=>ucfirst($key).' Email','subject'=>'','body_html'=>'','body_text'=>'','is_active'=>0];
    try {
        if (isset($tplArr['body_html']) && (strpos((string)$tplArr['body_html'], '&lt;') !== false || strpos((string)$tplArr['body_html'], '&gt;') !== false)) {
            $tplArr['body_html'] = html_entity_decode((string)$tplArr['body_html'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    } catch (\Throwable $__) {}
    return [
        'pagetitle' => 'Edit Email Template',
        'breadcrumb' => ['index.php?m=eazybackup' => 'eazyBackup'],
        'templatefile' => 'templates/whitelabel/email-template-edit',
        'requirelogin' => true,
        'forcessl' => true,
        'vars' => [
            'modulelink' => $vars['modulelink'],
            'tenant' => (array)$tenantObj,
            'emailTemplate' => $tplArr,
            'smtp_configured' => $smtpConfigured ? 1 : 0,
            'csrf_token' => (function(){ try { if (function_exists('generate_token')) { return (string)generate_token('plain'); } } catch (\Throwable $__) {} return ''; })(),
            'flash_saved' => (int)(($_GET['saved'] ?? 0)) === 1 ? 1 : 0,
            'flash_tested' => (int)(($_GET['tested'] ?? 0)) === 1 ? 1 : 0,
            'flash_error' => (string)($_GET['error'] ?? ''),
        ],
    ];
}


