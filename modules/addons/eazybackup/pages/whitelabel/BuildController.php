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
        // Default color if blank
        $def = '#1B2C50';
        $top = (string)($_POST['header_color'] ?? ''); if ($top === '') { $top = $def; }
        $acc = (string)($_POST['accent_color'] ?? ''); if ($acc === '') { $acc = $def; }
        $tile = (string)($_POST['tile_background'] ?? ''); if ($tile === '') { $tile = $def; }

        $brand = [
            'BrandName' => (string)($_POST['product_name'] ?? ''),
            'ProductName' => (string)($_POST['product_name'] ?? ''),
            'CompanyName' => (string)($_POST['company_name'] ?? ''),
            'HelpURL' => (string)($_POST['help_url'] ?? ''),
            'DefaultLoginServerURL' => 'https://' . $fqdn . '/',
            'TopColor' => $top,
            'AccentColor' => $acc,
            'TileBackgroundColor' => $tile,
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
            'inherit' => isset($_POST['use_parent_mail']) ? 1 : 0,
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
            'BrandName' => trim((string)($_POST['brand_name'] ?? '')),
            'ProductName' => trim((string)($_POST['product_name'] ?? '')),
            'CompanyName' => trim((string)($_POST['company_name'] ?? '')),
            'CloudStorageName' => trim((string)($_POST['product_name'] ?? '')),
            'HelpURL' => trim((string)($_POST['help_url'] ?? '')),
            // Comet color keys
            'TopColor' => trim((string)($_POST['header_color'] ?? '')),
            'AccentColor' => trim((string)($_POST['accent_color'] ?? '')),
            'TileBackgroundColor' => trim((string)($_POST['tile_background'] ?? '')),
        ];

        // Handle asset uploads (mirror intake approach) + EULA
        try {
            $uploadBase = realpath(__DIR__ . '/../../');
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

            // Initialize keys expected by Comet branding
            foreach (['Favicon','LogoImage','PathHeaderImage','PathAppIconImage','PathTilePng','PathIcoFile','PathIcnsFile','PathMenuBarIcnsFile','PathEulaRtf'] as $k) {
                if (!isset($brand[$k])) { $brand[$k] = ''; }
            }

            // Map uploads from form → brand keys
            $map = [
                'favicon_file' => ['Favicon','tab_icon'],
                'logo_file' => ['LogoImage','logo'],
                'header_image_file' => ['PathHeaderImage','header'],
                'app_icon_file' => ['PathAppIconImage','app_icon_image'],
                'tile_image_file' => ['PathTilePng','tile_image'],
                'win_ico_file' => ['PathIcoFile','icon_windows'],
                'mac_icns_file' => ['PathIcnsFile','icon_macos'],
                'mac_menubar_icns_file' => ['PathMenuBarIcnsFile','menu_bar_icon_macos'],
                'eula_file' => ['PathEulaRtf','eula'],
                // Non-Comet extra asset preserved in our JSON for UI completeness
                'background_logo_file' => ['BackgroundLogo','background_logo'],
            ];
            foreach ($map as $formKey => $pair) {
                $brandKey = (string)$pair[0]; $prefix = (string)$pair[1];
                $p = $saveUpload($formKey, $prefix);
                if ($p !== '') { $brand[$brandKey] = $p; }
            }

            // Optional: EULA text → save to file if no file upload provided
            if ((string)($brand['PathEulaRtf'] ?? '') === '' && isset($_POST['eula_text']) && trim((string)$_POST['eula_text']) !== '') {
                $eulaPath = $tenantDir . '/eula.rtf';
                @file_put_contents($eulaPath, (string)$_POST['eula_text']);
                @chmod($eulaPath, 0664);
                if (is_file($eulaPath)) { $brand['PathEulaRtf'] = $eulaPath; }
                try { logModuleCall('eazybackup','branding_post_eula_source',['tenant'=>$tenantId,'source'=>'text'], ''); } catch (\Throwable $_) {}
            } else if ((string)($brand['PathEulaRtf'] ?? '') !== '') {
                try { logModuleCall('eazybackup','branding_post_eula_source',['tenant'=>$tenantId,'source'=>'file'], ''); } catch (\Throwable $_) {}
            } else {
                try { logModuleCall('eazybackup','branding_post_eula_source',['tenant'=>$tenantId,'source'=>'none'], ''); } catch (\Throwable $_) {}
            }
        } catch (\Throwable $_) { /* non-fatal; proceed */ }
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
            // Checkbox posts only when checked; default to 0 when missing
            'inherit' => isset($_POST['use_parent_mail']) ? 1 : 0,
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
        try { logModuleCall('eazybackup','branding_post_draft_saved',['tenant'=>$tenantId], 'ok'); } catch (\Throwable $_) {}

        // Guard: org must exist before applying branding
        $orgIdNow = (string)($tenantObj->org_id ?? '');
        if ($orgIdNow === '') {
            try { logModuleCall('eazybackup','branding_post_apply',['tenant'=>$tenantId], 'missing_org'); } catch (\Throwable $_) {}
            header('Location: ' . $vars['modulelink'] . '&a=whitelabel-branding&id=' . urlencode((string)$tenantId) . '&error=missing_org');
            exit;
        }

        $applyOk = false;
        try {
            $builder = new \EazyBackup\Whitelabel\Builder($vars);
            $applyOk = (bool)$builder->applyBranding((int)$tenantId);
            try { logModuleCall('eazybackup','branding_post_apply',['tenant'=>$tenantId], $applyOk ? 'ok' : 'failed'); } catch (\Throwable $_) {}
        } catch (\Throwable $e) {
            $applyOk = false;
            try { logModuleCall('eazybackup','branding_post_apply',['tenant'=>$tenantId], 'error: '.$e->getMessage()); } catch (\Throwable $_) {}
        }

        // After apply, re-read from Comet and overwrite DB cache if available, then redirect
        if ($applyOk) {
            // Only on success do we re-pull Comet and refresh cache for this tenant
            try {
                $rowAfter = Capsule::table('eb_whitelabel_tenants')->where('id', $tenantId)->first();
                $orgIdAfter = $rowAfter ? (string)($rowAfter->org_id ?? '') : '';
                $ct = new \EazyBackup\Whitelabel\CometTenant($vars);
                $live = $orgIdAfter !== '' ? (array)$ct->getOrgBranding($orgIdAfter) : [];
                if (empty($live)) { throw new \RuntimeException('empty_live_branding'); }

                // Optional mini-diff logging on keystone keys
                $keys = ['ProductName','TopColor','AccentColor','TileBackgroundColor','LogoImage'];
                $diff = [];
                foreach ($keys as $k) {
                    $want = isset($brand[$k]) ? (string)$brand[$k] : '';
                    $got  = isset($live[$k]) ? (string)$live[$k] : '';
                    if ($k === 'TopColor' || $k === 'AccentColor' || $k === 'TileBackgroundColor') { $want = strtoupper($want); $got = strtoupper($got); }
                    if ($k === 'LogoImage') {
                        // Loosen check: only record mismatch if want was set but got is empty
                        if ($want !== '' && $got === '') { $diff[$k] = ['want'=>$want,'got'=>$got]; }
                    } else if ($want !== '' && $got !== '' && $want !== $got) {
                        $diff[$k] = ['want'=>$want,'got'=>$got];
                    }
                }
                if (!empty($diff)) { try { logModuleCall('eazybackup','branding_post_diff',['tenant'=>$tenantId,'diff'=>$diff], 'mismatch'); } catch (\Throwable $_) {} }

                // Rename WHMCS product to match current ProductName (fallback BrandName → "<fqdn> Plan")
                try {
                    $pid = (int)($rowAfter->product_id ?? 0);
                    if ($pid > 0) {
                        $newName = trim((string)($live['ProductName'] ?? ''));
                        if ($newName === '') { $newName = trim((string)($live['BrandName'] ?? '')); }
                        if ($newName === '') {
                            $fqdnAfter = (string)($rowAfter->fqdn ?? '');
                            if ($fqdnAfter !== '') { $newName = $fqdnAfter . ' Plan'; }
                        }
                        if ($newName !== '') {
                            Capsule::table('tblproducts')->where('id', $pid)->update(['name' => $newName]);
                            try { logModuleCall('eazybackup','branding_post_product_rename',['tenant'=>$tenantId,'pid'=>$pid], $newName); } catch (\Throwable $_) {}
                        }
                    }
                } catch (\Throwable $__) { /* non-fatal rename */ }

                Capsule::table('eb_whitelabel_tenants')->where('id', $tenantId)->update([
                    'brand_json' => json_encode($live),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                try { logModuleCall('eazybackup','branding_post_cache_update',['tenant'=>$tenantId], 'ok'); } catch (\Throwable $_) {}
                header('Location: ' . $vars['modulelink'] . '&a=whitelabel-branding&id=' . urlencode((string)$tenantId) . '&saved=1');
                exit;
            } catch (\Throwable $e) {
                try { logModuleCall('eazybackup','branding_reload_after_apply',['tenant'=>$tenantId], 'error: ' . $e->getMessage()); } catch (\Throwable $_) {}
                header('Location: ' . $vars['modulelink'] . '&a=whitelabel-branding&id=' . urlencode((string)$tenantId) . '&error=reload_failed');
                exit;
            }
        }

        // Failure path
        header('Location: ' . $vars['modulelink'] . '&a=whitelabel-branding&id=' . urlencode((string)$tenantId) . '&error=apply_failed');
        exit;
    }

    // Comet is canonical: fetch live branding, refresh DB cache, render live
    $row = Capsule::table('eb_whitelabel_tenants')->find($tenantId);
    $tenant = $row ? (array)$row : (array)$tenantObj;
    $emailJson  = (string)($row->email_json ?? '{}');
    $orgId = (string)($row->org_id ?? '');

    $ct = new \EazyBackup\Whitelabel\CometTenant($vars);
    $brand = []; $syncNotice = '';
    try {
        $live = $orgId ? $ct->getOrgBranding($orgId) : null; // assoc array
        if (is_array($live) && !empty($live)) {
            $brand = $live;
            $affected = Capsule::table('eb_whitelabel_tenants')
                ->where('id', $tenantId)
                ->update([
                    'brand_json' => json_encode($brand, JSON_UNESCAPED_SLASHES),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            try { logModuleCall('eazybackup','branding_get_cache_refreshed',['tenant'=>$tenantId,'rows'=>$affected], 'ok'); } catch (\Throwable $_) {}
            $syncNotice = 'Branding synced from Comet.';
        } else {
            $brand = json_decode((string)($row->brand_json ?? '[]'), true) ?: [];
            $syncNotice = 'Live Comet unavailable; showing cached branding.';
            try { logModuleCall('eazybackup','branding_get_comet_empty_fallback',['tenant'=>$tenantId], ''); } catch (\Throwable $_) {}
        }
    } catch (\Throwable $e) {
        $brand = json_decode((string)($row->brand_json ?? '[]'), true) ?: [];
        $syncNotice = 'Live Comet unavailable; showing cached branding.';
        try { logModuleCall('eazybackup','branding_get_comet_error',['tenant'=>$tenantId,'err'=>$e->getMessage()], ''); } catch (\Throwable $_) {}
    }

    // Legacy aliases for template only
    if (!isset($brand['HeaderColor']) && isset($brand['TopColor'])) { $brand['HeaderColor'] = $brand['TopColor']; }
    if (!isset($brand['TileBackground']) && isset($brand['TileBackgroundColor'])) { $brand['TileBackground'] = $brand['TileBackgroundColor']; }
    try { logModuleCall('eazybackup','branding_get_render_summary',['tenant'=>$tenantId,'brand_keys'=>array_keys((array)$brand)], ''); } catch (\Throwable $_) {}
    // Asset status + EULA prefill
    $assetStatus = [];
    try { $assetStatus = (new \EazyBackup\Whitelabel\CometTenant($vars))->getBrandingAssetStatus($brand); } catch (\Throwable $_) {}
    try {
        $counts = ['uploaded'=>0,'local'=>0,'missing'=>0];
        foreach ($assetStatus as $st) { $s = (string)($st['state'] ?? ''); if (isset($counts[$s])) { $counts[$s]++; } }
        logModuleCall('eazybackup','branding_get_asset_status_ready',['tenant'=>$tenantId,'counts'=>$counts], '');
    } catch (\Throwable $_) {}
    $eulaText = '';
    if (!empty($brand['PathEulaRtf']) && strpos((string)$brand['PathEulaRtf'], 'resource://') === 0) {
        try {
            $eulaText = (string)((new \EazyBackup\Whitelabel\CometTenant($vars))->getEulaTextFromResource((string)$brand['PathEulaRtf']) ?: '');
            logModuleCall('eazybackup','branding_get_eula_prefill',['tenant'=>$tenantId,'len'=>strlen($eulaText)], '');
        } catch (\Throwable $_) {}
    }

    // Load custom domain row if present
    $customDomainRow = null;
    try {
        if (!empty($tenant['custom_domain'])) {
            $cdr = Capsule::table('eb_whitelabel_custom_domains')
                ->where('tenant_id', $tenantId)
                ->where('hostname', (string)$tenant['custom_domain'])
                ->orderBy('updated_at','desc')
                ->first();
            if ($cdr) { $customDomainRow = (array)$cdr; }
        }
    } catch (\Throwable $__) {}

    return [
        'pagetitle' => 'Branding & Hostname',
        'breadcrumb' => ['index.php?m=eazybackup' => 'eazyBackup'],
        'templatefile' => 'templates/whitelabel/branding',
        'requirelogin' => true,
        'forcessl' => true,
        'vars' => [
            'modulelink' => $vars['modulelink'],
            'tenant' => $tenant,
            'brand' => $brand,
        'email' => (function() use ($ct, $orgId, $emailJson){
            $liveEmail = $orgId ? $ct->getOrgEmailOptions($orgId) : [];
            if (!empty($liveEmail)) { return $liveEmail; }
            return json_decode($emailJson, true) ?: [];
        })(),
            'assetStatus' => $assetStatus,
            'eula_text' => $eulaText,
            'sync_notice' => $syncNotice,
            'flash_saved' => (int)(($_GET['saved'] ?? 0)) === 1 ? 1 : 0,
            'flash_error' => (string)($_GET['error'] ?? '') === 'apply_failed' ? 1 : 0,
            'csrf_token' => (function(){ try { if (function_exists('generate_token')) { return (string)generate_token('plain'); } } catch (\Throwable $_) {} return ''; })(),
            'custom_domain_row' => $customDomainRow,
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

/** AJAX: Check DNS for custom domain */
function eazybackup_whitelabel_branding_checkdns(array $vars)
{
    header('Content-Type: application/json');
    try {
        if (!((int)($_SESSION['uid'] ?? 0) > 0)) { echo json_encode(['ok'=>false,'error'=>'Not authenticated']); return; }
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { echo json_encode(['ok'=>false,'error'=>'Invalid method']); return; }
        $token = (string)($_POST['token'] ?? '');
        if (function_exists('check_token')) { try { if (!check_token('plain', $token)) { echo json_encode(['ok'=>false,'error'=>'Invalid token']); return; } } catch (\Throwable $_) {} }
        $devMode = (int)($vars['whitelabel_dev_mode'] ?? 0) === 1;
        // Ensure custom domains table exists; attempt migration if missing
        $hasCustomTable = false;
        try { $hasCustomTable = Capsule::schema()->hasTable('eb_whitelabel_custom_domains'); } catch (\Throwable $__) { $hasCustomTable = false; }
        if (!$hasCustomTable && function_exists('eazybackup_migrate_schema')) {
            try { eazybackup_migrate_schema(); $hasCustomTable = Capsule::schema()->hasTable('eb_whitelabel_custom_domains'); } catch (\Throwable $__) { $hasCustomTable = false; }
        }
        $tenantId = (int)($_POST['tenant_id'] ?? 0);
        $hostname = strtolower(trim((string)($_POST['hostname'] ?? '')));
        if ($tenantId <= 0 || $hostname === '') { echo json_encode(['ok'=>false,'error'=>'Missing tenant or hostname']); return; }
        // Basic hostname validation (no apex-only unless later supported)
        if (!preg_match('/^(?=.{1,255}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/', $hostname) || substr_count($hostname, '.') < 2) {
            echo json_encode(['ok'=>false,'error'=>'Invalid hostname']); return; }
        // Duplicate guard across tenants (skip if table missing)
        if ($hasCustomTable) {
            try {
                $dupe = Capsule::table('eb_whitelabel_custom_domains')->where('hostname',$hostname)->where('tenant_id','<>',$tenantId)->first();
                if ($dupe) { echo json_encode(['ok'=>false,'error'=>'Hostname already in use by another tenant']); return; }
            } catch (\Throwable $__) { /* ignore duplicate check on environments without table */ }
        }
        $tenant = Capsule::table('eb_whitelabel_tenants')->where('id',$tenantId)->first();
        if (!$tenant || (int)$tenant->client_id !== (int)$_SESSION['uid']) { echo json_encode(['ok'=>false,'error'=>'Tenant not found']); return; }
        $expected = (string)$tenant->fqdn;
        $expectedTarget = $expected;
        // dns_get_record check
        $answers = @dns_get_record($hostname, DNS_CNAME) ?: [];
        $seen = [];
        foreach ($answers as $a) { if (isset($a['target'])) { $seen[] = rtrim(strtolower((string)$a['target']), '.'); } }
        $okExact = in_array($expectedTarget, $seen, true);
        // Detect Cloudflare proxy (A instead of CNAME)
        $aRecords = [];
        if (!$okExact) {
            $aRows = @dns_get_record($hostname, DNS_A) ?: [];
            foreach ($aRows as $ar) { if (isset($ar['ip'])) { $aRecords[] = (string)$ar['ip']; } }
        }
        $digAnswers = [];
        if (!$okExact) {
            try {
                $hop = new \EazyBackup\Whitelabel\HostOps($vars);
                foreach (['1.1.1.1','8.8.8.8'] as $res) {
                    $res1 = $hop->dig($hostname, $res, 'CNAME');
                    if (is_array($res1) && !empty($res1['answer'])) { $digAnswers[] = rtrim(strtolower((string)$res1['answer']), '.'); }
                }
                $okExact = in_array($expectedTarget, $digAnswers, true);
            } catch (\Throwable $__) { /* ignore */ }
        }
        $now = date('Y-m-d H:i:s');
        $row = [
            'tenant_id' => $tenantId,
            'hostname' => $hostname,
            'status' => $okExact ? 'dns_ok' : 'pending_dns',
            'last_error' => $okExact ? null : ((empty($aRecords) ? '' : 'A record detected; Cloudflare proxy likely. ') . 'Expected CNAME ' . $hostname . ' → ' . $expectedTarget . '; got: ' . implode(',', array_unique(array_merge($seen,$digAnswers)))) ,
            'checked_at' => $now,
            'updated_at' => $now,
        ];
        // Upsert (only if table exists); always update tenant shortcut
        if ($hasCustomTable) {
            try {
                $exists = Capsule::table('eb_whitelabel_custom_domains')->where('tenant_id',$tenantId)->where('hostname',$hostname)->first();
                if ($exists) {
                    Capsule::table('eb_whitelabel_custom_domains')->where('id', $exists->id)->update($row);
                } else {
                    $row['created_at'] = $now; Capsule::table('eb_whitelabel_custom_domains')->insert($row);
                }
            } catch (\Throwable $__) { /* ignore upsert errors */ }
        }
        try { Capsule::table('eb_whitelabel_tenants')->where('id',$tenantId)->update(['custom_domain'=>$hostname,'custom_domain_status'=>$row['status'],'updated_at'=>$now]); } catch (\Throwable $__) {}
        try { logModuleCall('eazybackup','custom_domain_checkdns',[ 'tenant'=>$tenantId,'host'=>$hostname,'expected'=>$expectedTarget ], json_encode(['status'=>$row['status'],'answers'=>$seen,'dig'=>$digAnswers,'aRecords'=>$aRecords,'table'=>$hasCustomTable?'present':'missing'])); } catch (\Throwable $_) {}
        $payload = ['ok'=>true,'status'=>$row['status']];
        if ($devMode) { $payload['debug'] = ['answers'=>$seen,'dig'=>$digAnswers,'aRecords'=>$aRecords,'table'=>$hasCustomTable?'present':'missing']; }
        echo json_encode($payload);
    } catch (\Throwable $e) {
        try { logModuleCall('eazybackup','custom_domain_checkdns_error', ['post_keys'=>array_keys($_POST ?? [])], (string)$e->getMessage()); } catch (\Throwable $_) {}
        $devMode = (int)($vars['whitelabel_dev_mode'] ?? 0) === 1;
        $msg = 'Server error';
        if ($devMode) { $msg .= ': ' . $e->getMessage(); }
        echo json_encode(['ok'=>false,'error'=>$msg]);
    }
}

/** POST: Attach custom domain (create vhost, cert, update Comet, verify) */
function eazybackup_whitelabel_branding_attachdomain(array $vars)
{
    header('Content-Type: application/json');
    try {
        if (!((int)($_SESSION['uid'] ?? 0) > 0)) { echo json_encode(['ok'=>false,'error'=>'Not authenticated']); return; }
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { echo json_encode(['ok'=>false,'error'=>'Invalid method']); return; }
        $token = (string)($_POST['token'] ?? '');
        if (function_exists('check_token')) { try { if (!check_token('plain', $token)) { echo json_encode(['ok'=>false,'error'=>'Invalid token']); return; } } catch (\Throwable $_) {} }
        $devMode = (int)($vars['whitelabel_dev_mode'] ?? 0) === 1;
        // Ensure custom domains table exists; attempt migration if missing
        $hasCustomTable = false;
        try { $hasCustomTable = Capsule::schema()->hasTable('eb_whitelabel_custom_domains'); } catch (\Throwable $__) { $hasCustomTable = false; }
        if (!$hasCustomTable && function_exists('eazybackup_migrate_schema')) {
            try { eazybackup_migrate_schema(); $hasCustomTable = Capsule::schema()->hasTable('eb_whitelabel_custom_domains'); } catch (\Throwable $__) { $hasCustomTable = false; }
        }
        $tenantId = (int)($_POST['tenant_id'] ?? 0);
        $hostname = strtolower(trim((string)($_POST['hostname'] ?? '')));
        if ($tenantId <= 0 || $hostname === '') { echo json_encode(['ok'=>false,'error'=>'Missing tenant or hostname']); return; }
        if (!preg_match('/^(?=.{1,255}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/', $hostname) || substr_count($hostname, '.') < 2) { echo json_encode(['ok'=>false,'error'=>'Invalid hostname']); return; }

        $tenant = Capsule::table('eb_whitelabel_tenants')->where('id',$tenantId)->first();
        if (!$tenant || (int)$tenant->client_id !== (int)$_SESSION['uid']) { echo json_encode(['ok'=>false,'error'=>'Tenant not found']); return; }
        // Duplicate guard
        if ($hasCustomTable) {
            try { $dupe = Capsule::table('eb_whitelabel_custom_domains')->where('hostname',$hostname)->where('tenant_id','<>',$tenantId)->first(); if ($dupe) { echo json_encode(['ok'=>false,'error'=>'Hostname already in use by another tenant']); return; } } catch (\Throwable $__) {}
        }
        $expectedTarget = (string)$tenant->fqdn;

        // Ensure DNS OK or re-run quick check
        $cd = Capsule::table('eb_whitelabel_custom_domains')->where('tenant_id',$tenantId)->where('hostname',$hostname)->first();
        $dnsOk = $cd && (string)$cd->status === 'dns_ok';
        if (!$dnsOk) {
            $answers = @dns_get_record($hostname, DNS_CNAME) ?: [];
            $seen = [];
            foreach ($answers as $a) { if (isset($a['target'])) { $seen[] = rtrim(strtolower((string)$a['target']), '.'); } }
            $dnsOk = in_array($expectedTarget, $seen, true);
        }
        if (!$dnsOk) { echo json_encode(['ok'=>false,'error'=>'DNS not ready. Expected CNAME ' . $hostname . ' → ' . $expectedTarget]); return; }

        $now = date('Y-m-d H:i:s');
        // HostOps steps
        $ops = new \EazyBackup\Whitelabel\HostOps($vars);
        if (!$ops->writeHttpStub($hostname)) { Capsule::table('eb_whitelabel_custom_domains')->updateOrInsert(['tenant_id'=>$tenantId,'hostname'=>$hostname],[ 'status'=>'failed','last_error'=>'http_stub_failed','updated_at'=>$now ]); echo json_encode(['ok'=>false,'error'=>'Failed to write HTTP stub']); return; }
        if (!$ops->issueCert($hostname)) {
            try { $ops->deleteHost($hostname); } catch (\Throwable $__) {}
            Capsule::table('eb_whitelabel_custom_domains')->updateOrInsert(['tenant_id'=>$tenantId,'hostname'=>$hostname],[ 'status'=>'failed','last_error'=>'cert_issuance_failed','updated_at'=>$now ]);
            echo json_encode(['ok'=>false,'error'=>'Certificate issuance failed']); return; }
        if (!$ops->writeHttps($hostname)) {
            try { $ops->deleteHost($hostname); } catch (\Throwable $__) {}
            Capsule::table('eb_whitelabel_custom_domains')->updateOrInsert(['tenant_id'=>$tenantId,'hostname'=>$hostname],[ 'status'=>'failed','last_error'=>'https_write_failed','updated_at'=>$now ]);
            echo json_encode(['ok'=>false,'error'=>'Failed to write HTTPS vhost']); return; }
        // Try to probe certificate expiry via TLS handshake
        $expAt = null;
        try {
            $ctx = stream_context_create(['ssl'=>['SNI_enabled'=>true,'verify_peer'=>false,'verify_peer_name'=>false,'capture_peer_cert'=>true]]);
            $soc = @stream_socket_client('ssl://'.$hostname.':443', $errno, $errstr, 6, STREAM_CLIENT_CONNECT, $ctx);
            if ($soc) {
                $params = stream_context_get_params($soc);
                if (isset($params['options']['ssl']['peer_certificate'])) {
                    $cert = $params['options']['ssl']['peer_certificate'];
                    if ($cert) { $info = @openssl_x509_parse($cert); if (is_array($info) && isset($info['validTo_time_t'])) { $expAt = date('Y-m-d H:i:s', (int)$info['validTo_time_t']); } }
                }
                @fclose($soc);
            }
        } catch (\Throwable $__) { $expAt = null; }
        $upd = [ 'status'=>'cert_ok','checked_at'=>$now,'updated_at'=>$now ]; if ($expAt) { $upd['cert_expires_at'] = $expAt; }
        if ($hasCustomTable) { try { Capsule::table('eb_whitelabel_custom_domains')->updateOrInsert(['tenant_id'=>$tenantId,'hostname'=>$hostname], $upd); } catch (\Throwable $__) {} }

        // Comet: add host and set default URL
        $orgId = (string)($tenant->org_id ?? '');
        if ($orgId === '') { if ($hasCustomTable) { try { Capsule::table('eb_whitelabel_custom_domains')->where('tenant_id',$tenantId)->where('hostname',$hostname)->update(['status'=>'failed','last_error'=>'org_missing','updated_at'=>$now]); } catch (\Throwable $__) {} } echo json_encode(['ok'=>false,'error'=>'Comet organization not ready']); return; }
        $ct = new \EazyBackup\Whitelabel\CometTenant($vars);
        if (!$ct->addHostAndSetDefaultURL($orgId, $hostname)) { if ($hasCustomTable) { try { Capsule::table('eb_whitelabel_custom_domains')->where('tenant_id',$tenantId)->where('hostname',$hostname)->update(['status'=>'failed','last_error'=>'org_update_failed','updated_at'=>$now]); } catch (\Throwable $__) {} } echo json_encode(['ok'=>false,'error'=>'Comet update failed']); return; }
        if ($hasCustomTable) { try { Capsule::table('eb_whitelabel_custom_domains')->where('tenant_id',$tenantId)->where('hostname',$hostname)->update(['status'=>'org_updated','updated_at'=>$now]); } catch (\Throwable $__) {} }
        Capsule::table('eb_whitelabel_tenants')->where('id',$tenantId)->update(['custom_domain'=>$hostname,'custom_domain_status'=>'org_updated','updated_at'=>$now]);

        // Verify reachability
        $url = 'https://' . $hostname . '/';
        $ok = false;
        // Tolerate protected endpoints: accept 2xx/3xx/401/403
        $ok = $ct->verifyOrgReachable($url);
        if (!$ok) { $ok = $ct->verifyOrgReachable($url . 'api/v1/'); }
        if (!$ok) {
            if ($hasCustomTable) { try { Capsule::table('eb_whitelabel_custom_domains')->where('tenant_id',$tenantId)->where('hostname',$hostname)->update(['status'=>'failed','last_error'=>'https_probe_failed','updated_at'=>$now]); } catch (\Throwable $__) {} }
            echo json_encode(['ok'=>false,'error'=>'Host not reachable over HTTPS']); return;
        }

        if ($hasCustomTable) { try { Capsule::table('eb_whitelabel_custom_domains')->where('tenant_id',$tenantId)->where('hostname',$hostname)->update(['status'=>'verified','updated_at'=>$now]); } catch (\Throwable $__) {} }
        Capsule::table('eb_whitelabel_tenants')->where('id',$tenantId)->update(['custom_domain'=>$hostname,'custom_domain_status'=>'verified','updated_at'=>$now]);
        try { logModuleCall('eazybackup','custom_domain_attach', ['tenant'=>$tenantId,'host'=>$hostname], 'verified'); } catch (\Throwable $_) {}
        echo json_encode(['ok'=>true,'status'=>'verified','message'=>'Custom domain attached and secured.']);
    } catch (\Throwable $e) {
        try { logModuleCall('eazybackup','custom_domain_attach_error', ['post_keys'=>array_keys($_POST ?? [])], (string)$e->getMessage()); } catch (\Throwable $_) {}
        $devMode = (int)($vars['whitelabel_dev_mode'] ?? 0) === 1;
        $msg = 'Server error'; if ($devMode) { $msg .= ': ' . $e->getMessage(); }
        echo json_encode(['ok'=>false,'error'=>$msg]);
    }
}


