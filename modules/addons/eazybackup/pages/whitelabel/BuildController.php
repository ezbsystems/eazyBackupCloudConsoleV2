<?php

use WHMCS\Database\Capsule;
use Illuminate\Database\Capsule\Manager as DB;

require_once __DIR__ . '/../../lib/Whitelabel/Builder.php';

/**
 * Client: White-Label Intake (GET/POST)
 * - GET renders existing intake form template for branding fields
 * - POST runs the builder immediately (no cron) and redirects to Branding page
 */
function eazybackup_whitelabel_intake(array $vars)
{
    if (!((int)($_SESSION['uid'] ?? 0) > 0)) {
        return [
            'pagetitle' => 'White Label Signup',
            'templatefile' => 'templates/error',
            'vars' => ['error' => 'Please sign in to continue.']
        ];
    }

    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        // Render intake with an auto-generated suggested subdomain and custom domain
        $baseDomain = (string)($vars['whitelabel_base_domain'] ?? 'obcbackup.com');
        // 8-hex slug similar to legacy flow
        try { $slug = substr(bin2hex(random_bytes(4)), 0, 8); } catch (\Throwable $_) { $slug = substr(sha1(uniqid('', true)), 0, 8); }
        $suggestedFqdn = strtolower($slug . '.' . $baseDomain);
        return [
            'pagetitle' => 'White Label Signup',
            'breadcrumb' => ['index.php?m=eazybackup' => 'eazyBackup'],
            'templatefile' => 'templates/whitelabel-signup',
            'requirelogin' => true,
            'forcessl' => true,
            'vars' => [
                'modulelink' => $vars['modulelink'],
                // New-intake specific: post back to this route
                'form_action' => $vars['modulelink'] . '&a=whitelabel',
                // Provide generated values for display + hidden field
                'generated_subdomain' => $slug,
                'custom_domain' => $suggestedFqdn,
            ],
        ];
    }

    $clientId = (int)$_SESSION['uid'];
    $subdomain = trim($_POST['subdomain'] ?? '');
    $baseDomain = (string)($vars['whitelabel_base_domain'] ?? '');
    if ($subdomain === '' || $baseDomain === '') {
        return [
            'pagetitle' => 'White Label Signup',
            'templatefile' => 'templates/error',
            'vars' => ['error' => 'Missing subdomain or base domain.']
        ];
    }

    $fqdn = strtolower($subdomain . '.' . $baseDomain);

    // Create tenant row (idempotent by fqdn)
    $tenantId = null;
    $now = date('Y-m-d H:i:s');
    $existing = Capsule::table('eb_whitelabel_tenants')->where('fqdn', $fqdn)->first();
    if ($existing) {
        $tenantId = (int)$existing->id;
    } else {
        $tenantId = Capsule::table('eb_whitelabel_tenants')->insertGetId([
            'client_id' => $clientId,
            'status' => 'queued',
            'org_id' => null,
            'subdomain' => $subdomain,
            'fqdn' => $fqdn,
            'custom_domain' => trim($_POST['custom_domain'] ?? ''),
            'product_id' => null,
            'server_id' => null,
            'servergroup_id' => null,
            'comet_admin_user' => null,
            'comet_admin_pass_enc' => null,
            'brand_json' => json_encode([]), // placeholder; update below after file handling
            'email_json' => json_encode([]), // placeholder; update below after file handling
            'policy_ids_json' => json_encode([]),
            'storage_template_json' => json_encode([]),
            'idempotency_key' => sha1($clientId . ':' . $fqdn),
            'last_build_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    // Handle branding + email mapping (and file uploads)
    try {
        $uploadBase = realpath(__DIR__ . '/../../'); // module root
        if ($uploadBase === false) { $uploadBase = __DIR__ . '/../../'; }
        $tenantDir = rtrim($uploadBase, DIRECTORY_SEPARATOR) . '/uploads/whitelabel/' . (int)$tenantId;
        if (!is_dir($tenantDir)) { @mkdir($tenantDir, 0775, true); }

        $saveUpload = function(string $key, string $namePrefix) use ($tenantDir): string {
            if (!isset($_FILES[$key]) || !is_array($_FILES[$key]) || (int)($_FILES[$key]['error'] ?? 4) !== 0) { return ''; }
            $tmp = (string)($_FILES[$key]['tmp_name'] ?? ''); if ($tmp === '' || !is_uploaded_file($tmp)) { return ''; }
            $ext = strtolower(pathinfo((string)($_FILES[$key]['name'] ?? ''), PATHINFO_EXTENSION));
            if ($ext === '') { $ext = 'bin'; }
            $dest = $tenantDir . '/' . $namePrefix . '.' . preg_replace('/[^a-z0-9]+/i', '', $ext);
            if (@move_uploaded_file($tmp, $dest)) { @chmod($dest, 0664); return $dest; }
            return '';
        };

        // EULA: textarea content -> write to file for PathEulaRtf
        $eulaPath = '';
        $eulaTxt = (string)($_POST['eula'] ?? '');
        if ($eulaTxt !== '') {
            $eulaPath = $tenantDir . '/eula.rtf';
            @file_put_contents($eulaPath, $eulaTxt);
            @chmod($eulaPath, 0664);
        }

        // Uploads → local file paths
        $pathIcoFile       = $saveUpload('icon_windows', 'icon_windows');
        $pathIcnsFile      = $saveUpload('icon_macos', 'icon_macos');
        $pathMenuBarIcns   = $saveUpload('menu_bar_icon_macos', 'menu_bar_icon_macos');
        $logoImage         = $saveUpload('logo_image', 'logo_image');
        $pathTilePng       = $saveUpload('tile_image', 'tile_image');
        $favicon           = $saveUpload('tab_icon', 'tab_icon');
        $pathHeaderImage   = $saveUpload('header', 'header');
        $pathAppIconImage  = $saveUpload('app_icon_image', 'app_icon_image');

        // Build brand mapping for Comet Organization BrandingOptions
        $brand = [
            'BrandName' => (string)($_POST['product_name'] ?? ''),
            'ProductName' => (string)($_POST['product_name'] ?? ''),
            'CompanyName' => (string)($_POST['company_name'] ?? ''),
            'HelpURL' => (string)($_POST['help_url'] ?? ''),
            'DefaultLoginServerURL' => 'https://' . $fqdn . '/',
            'TopColor' => (string)($_POST['header_color'] ?? ''),
            'AccentColor' => (string)($_POST['accent_color'] ?? ''),
            'TileBackgroundColor' => (string)($_POST['tile_background'] ?? ''),
            // Assets (local paths)
            'PathIcoFile' => $pathIcoFile,
            'PathIcnsFile' => $pathIcnsFile,
            'PathMenuBarIcnsFile' => $pathMenuBarIcns,
            'LogoImage' => $logoImage,
            'PathTilePng' => $pathTilePng,
            'Favicon' => $favicon,
            'PathHeaderImage' => $pathHeaderImage,
            'PathAppIconImage' => $pathAppIconImage,
            'PathEulaRtf' => $eulaPath,
        ];

        // Email mapping to Comet EmailOptions
        // Email delivery mapping per Comet constants: "" inherit; "builtin" MX Direct; "smtp" STARTTLS/plain; "smtp-ssl" SSL/TLS
        $rawSec = (string)($_POST['smtp_security'] ?? '');
        $smtpHost = (string)($_POST['smtp_server'] ?? '');
        $mode = '';
        $allowUnenc = false;
        if ($smtpHost === '') {
            $mode = 'builtin';
        } else if (strcasecmp($rawSec, 'SSL/TLS') === 0) {
            $mode = 'smtp-ssl';
        } else if (strcasecmp($rawSec, 'STARTTLS') === 0) {
            $mode = 'smtp';
        } else if (strcasecmp($rawSec, 'Plain') === 0) {
            $mode = 'smtp'; $allowUnenc = true;
        } else {
            $mode = 'smtp';
        }
        $email = [
            'inherit' => ($smtpHost === '' ? 1 : 0),
            'FromName' => (string)($_POST['smtp_sendas_name'] ?? ''),
            'FromEmail' => (string)($_POST['smtp_sendas_email'] ?? ''),
            'SMTPHost' => $smtpHost,
            'SMTPPort' => (int)($_POST['smtp_port'] ?? 0),
            'SMTPUsername' => (string)($_POST['smtp_username'] ?? ''),
            'SMTPPassword' => (string)($_POST['smtp_password'] ?? ''),
            'SMTPAllowUnencrypted' => $allowUnenc ? true : false,
            'Mode' => $mode,
        ];

        Capsule::table('eb_whitelabel_tenants')->where('id', $tenantId)->update([
            'brand_json' => json_encode($brand),
            'email_json' => json_encode($email),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    } catch (\Throwable $_) { /* non-fatal; continue */ }

    // Initialize step rows as queued; actual execution will be driven by loader polling / dev panel
    try {
        $builder = new \EazyBackup\Whitelabel\Builder($vars);
        foreach (['dns','nginx','cert','org','admin','branding','email','storage','whmcs','verify'] as $st) {
            // ensureStep is idempotent
            $ref = new \ReflectionMethod($builder, 'ensureStep');
            $ref->setAccessible(true);
            $ref->invoke($builder, (int)$tenantId, $st, 'queued');
        }
        Capsule::table('eb_whitelabel_tenants')->where('id', $tenantId)->update([
            'status' => 'building',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    } catch (\Throwable $_) { /* safe to ignore; loader can still runImmediate */ }

    // Redirect to Loader page for this tenant immediately so user sees progress
    header('Location: ' . $vars['modulelink'] . '&a=whitelabel-loader&id=' . urlencode((string)$tenantId));
    exit;
}

/** Branding & Hostname page (GET/POST) */
function eazybackup_whitelabel_branding(array $vars)
{
    if (!((int)($_SESSION['uid'] ?? 0) > 0)) {
        return [
            'pagetitle' => 'Branding & Hostname',
            'templatefile' => 'templates/error',
            'vars' => ['error' => 'Please sign in to continue.']
        ];
    }
    $tenantId = (int)($_GET['id'] ?? 0);
    $clientId = (int)$_SESSION['uid'];
    $tenantObj = $tenantId ? Capsule::table('eb_whitelabel_tenants')->where('id', $tenantId)->first() : null;
    if (!$tenantObj || (int)$tenantObj->client_id !== $clientId) {
        // Fallback: show tenant list so users can pick a tenant
        $tenants = [];
        try {
            $rows = Capsule::table('eb_whitelabel_tenants')
                ->where('client_id', $clientId)
                ->orderBy('created_at', 'desc')
                ->get();
            foreach ($rows as $r) { $tenants[] = (array)$r; }
        } catch (\Throwable $_) { /* ignore */ }
        return [
            'pagetitle' => 'Branding & Hostname',
            'breadcrumb' => ['index.php?m=eazybackup' => 'eazyBackup'],
            'templatefile' => 'templates/whitelabel/branding-list',
            'requirelogin' => true,
            'forcessl' => true,
            'vars' => [
                'modulelink' => $vars['modulelink'],
                'tenants' => $tenants,
            ],
        ];
    }

    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        // Update branding/email; apply via builder (partial)
        $brand = [
            'ProductName' => (string)($_POST['product_name'] ?? ''),
            'CompanyName' => (string)($_POST['company_name'] ?? ''),
            'CloudStorageName' => (string)($_POST['product_name'] ?? ''),
            'HelpURL' => (string)($_POST['help_url'] ?? ''),
            // Comet keys
            'TopColor' => (string)($_POST['header_color'] ?? ''),
            'AccentColor' => (string)($_POST['accent_color'] ?? ''),
            'TileBackgroundColor' => (string)($_POST['tile_background'] ?? ''),
        ];
        // Email mapping (flattened for Comet EmailOptions)
        $rawSec = (string)($_POST['smtp_security'] ?? '');
        $smtpHost = (string)($_POST['smtp_server'] ?? '');
        $mode = '';
        $allowUnenc = false;
        if ($smtpHost === '') {
            $mode = 'builtin';
        } else if (strcasecmp($rawSec, 'SSL/TLS') === 0) {
            $mode = 'smtp-ssl';
        } else if (strcasecmp($rawSec, 'STARTTLS') === 0) {
            $mode = 'smtp';
        } else if (strcasecmp($rawSec, 'Plain') === 0) {
            $mode = 'smtp'; $allowUnenc = true;
        } else {
            $mode = 'smtp';
        }
        $email = [
            'inherit' => (int)($_POST['use_parent_mail'] ?? 1) ? 1 : 0,
            'FromName' => (string)($_POST['smtp_sendas_name'] ?? ''),
            'FromEmail' => (string)($_POST['smtp_sendas_email'] ?? ''),
            'SMTPHost' => $smtpHost,
            'SMTPPort' => (int)($_POST['smtp_port'] ?? 0),
            'SMTPUsername' => (string)($_POST['smtp_username'] ?? ''),
            'SMTPPassword' => (string)($_POST['smtp_password'] ?? ''),
            'SMTPAllowUnencrypted' => $allowUnenc ? true : false,
            'Mode' => $mode,
        ];
        Capsule::table('eb_whitelabel_tenants')->where('id', $tenantId)->update([
            'brand_json' => json_encode($brand),
            'email_json' => json_encode($email),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        try {
            $builder = new \EazyBackup\Whitelabel\Builder($vars);
            $builder->applyBranding((int)$tenantId);
        } catch (\Throwable $e) {
            // soft-fail
        }
        // reload tenant
        $tenantObj = Capsule::table('eb_whitelabel_tenants')->where('id', $tenantId)->first();
    }

    $tenant = (array)$tenantObj;
    $brandJson  = (string)($tenantObj->brand_json ?? '{}');
    $emailJson  = (string)($tenantObj->email_json ?? '{}');

    $brandArr = json_decode($brandJson, true) ?: [];
    // Provide template-friendly aliases for legacy keys
    if (!isset($brandArr['HeaderColor']) && isset($brandArr['TopColor'])) { $brandArr['HeaderColor'] = $brandArr['TopColor']; }
    if (!isset($brandArr['TileBackground']) && isset($brandArr['TileBackgroundColor'])) { $brandArr['TileBackground'] = $brandArr['TileBackgroundColor']; }

    return [
        'pagetitle' => 'Branding & Hostname',
        'breadcrumb' => ['index.php?m=eazybackup' => 'eazyBackup'],
        'templatefile' => 'templates/whitelabel/branding',
        'requirelogin' => true,
        'forcessl' => true,
        'vars' => [
            'modulelink' => $vars['modulelink'],
            'tenant' => $tenant,
            'brand' => $brandArr,
            'email' => json_decode($emailJson, true),
        ],
    ];
}

/** Status endpoint (JSON) */
function eazybackup_whitelabel_status(array $vars = []): void
{
    header('Content-Type: application/json');
    $tenantId = (int)($_GET['id'] ?? 0);
    if ($tenantId <= 0) { echo json_encode(['ok' => false, 'message' => 'Invalid id']); exit; }
    $tenant = Capsule::table('eb_whitelabel_tenants')->where('id', $tenantId)->first();
    if (!$tenant) { echo json_encode(['ok' => false, 'message' => 'Not found']); exit; }
    $steps = Capsule::table('eb_whitelabel_builds')->where('tenant_id', $tenantId)->orderBy('id', 'asc')->get();
    $publicMap = [
        'dns' => 'Reserving your service address…',
        'nginx' => 'Checking that your address is live on the Internet…',
        'cert' => 'Making everything shiny and secure…',
        'org' => 'Creating your private management space…',
        'admin' => 'Setting up your admin access…',
        'branding' => 'Applying your branding…',
        'email' => 'Configuring email options…',
        'storage' => 'Preparing storage templates…',
        'whmcs' => 'Finalizing your product…',
        'verify' => 'All set!'
    ];
    $timeline = [];
    foreach ($steps as $s) {
        $timeline[] = [
            'step' => (string)$s->step,
            'label' => $publicMap[(string)$s->step] ?? (string)$s->step,
            'status' => (string)$s->status,
        ];
    }
    // Evaluate overall state: mark tenant active/failed when appropriate (no blocking)
    try {
        $hasRunning = false; $hasFailed = false; $hasQueued = false; $allSuccess = (count($steps) > 0);
        foreach ($steps as $s) {
            $st = (string)$s->status;
            if ($st === 'running') { $hasRunning = true; }
            if ($st === 'failed') { $hasFailed = true; $allSuccess = false; }
            if ($st === 'queued') { $hasQueued = true; $allSuccess = false; }
            if ($st !== 'success' && $st !== 'running' && $st !== 'queued') { $allSuccess = false; }
        }
        if ($hasFailed && (string)$tenant->status !== 'failed') {
            Capsule::table('eb_whitelabel_tenants')->where('id', $tenantId)->update([
                'status' => 'failed', 'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $tenant = Capsule::table('eb_whitelabel_tenants')->where('id', $tenantId)->first();
        } else if ($allSuccess && (string)$tenant->status !== 'active') {
            Capsule::table('eb_whitelabel_tenants')->where('id', $tenantId)->update([
                'status' => 'active', 'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $tenant = Capsule::table('eb_whitelabel_tenants')->where('id', $tenantId)->first();
        }
    } catch (\Throwable $_) { /* non-fatal */ }

    echo json_encode(['ok' => true, 'status' => (string)$tenant->status, 'timeline' => $timeline]);
    exit;
}

/** Loader page */
function eazybackup_whitelabel_loader(array $vars)
{
    if (!((int)($_SESSION['uid'] ?? 0) > 0)) {
        return [
            'pagetitle' => 'Setting up your tenant…',
            'templatefile' => 'templates/error',
            'vars' => ['error' => 'Please sign in to continue.']
        ];
    }
    $tenantId = (int)($_GET['id'] ?? 0);
    $tenantObj = $tenantId ? Capsule::table('eb_whitelabel_tenants')->where('id', $tenantId)->first() : null;
    if (!$tenantObj || (int)$tenantObj->client_id !== (int)$_SESSION['uid']) {
        return [
            'pagetitle' => 'Setting up your tenant…',
            'templatefile' => 'templates/error',
            'vars' => ['error' => 'Tenant not found.']
        ];
    }
    // DEV: handle step runner
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['dev_step'])) {
        try {
            // Release the PHP session lock so polling requests can proceed concurrently
            try { if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); } } catch (\Throwable $_) {}
            try { @ignore_user_abort(true); } catch (\Throwable $_) {}
            try { @set_time_limit(0); } catch (\Throwable $_) {}
            $builder = new \EazyBackup\Whitelabel\Builder($vars);
            $step = preg_replace('/[^a-z]/','', (string)($_POST['dev_step'] ?? ''));
            if ($step === '') { $step = 'verify'; }
            // Special kickoff value will execute the whole pipeline
            if ($step === 'kickoff') {
                $builder->runImmediate((int)$tenantObj->id);
            } else if (method_exists($builder, 'runStep')) {
                $builder->runStep((int)$tenantObj->id, $step);
            } else {
                $builder->runImmediate((int)$tenantObj->id);
            }
        } catch (\Throwable $_) {}
    }

    return [
        'pagetitle' => 'Setting up your tenant…',
        'templatefile' => 'templates/whitelabel/loader',
        'requirelogin' => true,
        'forcessl' => true,
        'vars' => [
            'modulelink' => $vars['modulelink'],
            'tenant' => (array)$tenantObj,
            'devMode' => (int)($vars['whitelabel_dev_mode'] ?? 0),
        ],
    ];
}


