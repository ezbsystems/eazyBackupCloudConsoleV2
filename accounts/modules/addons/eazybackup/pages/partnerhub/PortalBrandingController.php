<?php

use WHMCS\Database\Capsule;
use PartnerHub\SettingsService;

require_once __DIR__ . '/TenantsController.php';
require_once __DIR__ . '/../../lib/PartnerHub/SettingsService.php';

function eb_ph_portal_branding_defaults(): array
{
    return [
        'identity' => [
            'company_name' => '',
            'logo_url' => '',
            'logo_dark_url' => '',
            'favicon_url' => '',
            'primary_color' => '#FE5000',
            'accent_color' => '#1B2C50',
            'support_email' => '',
            'support_url' => '',
        ],
        'portal_pages' => [
            'show_billing' => true,
            'show_services' => true,
            'show_cloud_storage' => true,
            'show_devices' => true,
            'show_jobs' => true,
            'show_restore' => true,
        ],
        'footer' => [
            'text' => '',
            'links' => [],
        ],
        'domain' => [
            'hostname' => '',
            'status' => '',
            'cert_expires_at' => null,
        ],
        'smtp' => [
            'mode' => 'builtin',
            'host' => '',
            'port' => 587,
            'username' => '',
            'password_enc' => '',
            'from_name' => '',
            'from_email' => '',
        ],
    ];
}

function eb_ph_settings_portal_branding_show(array $vars)
{
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { header('Location: clientarea.php'); exit; }
    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id', $clientId)->first();
    if (!$msp) { header('Location: ' . $vars['modulelink'] . '&a=ph-tenants-manage'); exit; }

    $settings = eb_ph_get_portal_branding((int)$msp->id);
    $token = function_exists('generate_token') ? generate_token('plain') : '';

    $domains = [];
    try {
        $domains = Capsule::table('s3_msp_portal_domains')
            ->where('client_id', $clientId)
            ->orderBy('is_primary', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($d) { return (array)$d; })
            ->toArray();
    } catch (\Throwable $__) {}

    return [
        'pagetitle' => 'Portal Branding',
        'templatefile' => 'templates/whitelabel/settings-portal-branding',
        'breadcrumb' => ['index.php?m=eazybackup' => 'eazyBackup'],
        'requirelogin' => true,
        'forcessl' => true,
        'vars' => [
            'msp' => $msp,
            'modulelink' => $vars['modulelink'],
            'settings' => $settings,
            'domains' => $domains,
            'token' => $token,
        ],
    ];
}

function eb_ph_settings_portal_branding_save(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status' => 'error', 'message' => 'auth']); return; }

    $token = (string)($_POST['token'] ?? '');
    if (function_exists('check_token')) {
        try {
            if (!check_token('plain', $token)) { echo json_encode(['status' => 'error', 'message' => 'csrf']); return; }
        } catch (\Throwable $__) {
            echo json_encode(['status' => 'error', 'message' => 'csrf']); return;
        }
    }

    $clientId = (int)$_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id', $clientId)->first();
    if (!$msp) { echo json_encode(['status' => 'error', 'message' => 'no-msp']); return; }
    $mspId = (int)$msp->id;

    $raw = (string)($_POST['payload'] ?? '');
    $data = json_decode($raw, true);
    if (!is_array($data)) { $data = $_POST; }

    $defaults = eb_ph_portal_branding_defaults();
    $current = eb_ph_get_portal_branding($mspId);
    $merged = array_replace_recursive($defaults, $current, $data);

    $merged['identity']['company_name'] = substr(strip_tags((string)($merged['identity']['company_name'] ?? '')), 0, 191);
    $merged['identity']['primary_color'] = preg_match('/^#[0-9a-fA-F]{3,8}$/', (string)($merged['identity']['primary_color'] ?? '')) ? (string)$merged['identity']['primary_color'] : '#FE5000';
    $merged['identity']['accent_color'] = preg_match('/^#[0-9a-fA-F]{3,8}$/', (string)($merged['identity']['accent_color'] ?? '')) ? (string)$merged['identity']['accent_color'] : '#1B2C50';

    if (isset($merged['smtp']['password_enc']) && $merged['smtp']['password_enc'] !== '' && function_exists('encrypt')) {
        $merged['smtp']['password_enc'] = encrypt($merged['smtp']['password_enc']);
    }

    $uploadBase = realpath(__DIR__ . '/../../');
    if ($uploadBase === false) { $uploadBase = __DIR__ . '/../../'; }
    $uploadDir = rtrim($uploadBase, DIRECTORY_SEPARATOR) . '/uploads/portal_branding/' . $mspId;
    if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0775, true); }

    $saveUpload = function (string $key, string $prefix) use ($uploadDir): string {
        if (!isset($_FILES[$key]) || !is_array($_FILES[$key]) || (int)($_FILES[$key]['error'] ?? 4) !== 0) { return ''; }
        $tmp = (string)($_FILES[$key]['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) { return ''; }
        $ext = strtolower(pathinfo((string)($_FILES[$key]['name'] ?? ''), PATHINFO_EXTENSION));
        if ($ext === '') { $ext = 'bin'; }
        $allowed = ['jpg','jpeg','png','gif','svg','ico','webp'];
        if (!in_array($ext, $allowed, true)) { return ''; }
        $dest = $uploadDir . '/' . $prefix . '.' . preg_replace('/[^a-z0-9]+/i', '', $ext);
        if (@move_uploaded_file($tmp, $dest)) { @chmod($dest, 0664); return $dest; }
        return '';
    };

    $logoLight = $saveUpload('logo_light_file', 'logo_light');
    if ($logoLight !== '') { $merged['identity']['logo_url'] = '/modules/addons/eazybackup/uploads/portal_branding/' . $mspId . '/' . basename($logoLight); }
    $logoDark = $saveUpload('logo_dark_file', 'logo_dark');
    if ($logoDark !== '') { $merged['identity']['logo_dark_url'] = '/modules/addons/eazybackup/uploads/portal_branding/' . $mspId . '/' . basename($logoDark); }
    $favicon = $saveUpload('favicon_file', 'favicon');
    if ($favicon !== '') { $merged['identity']['favicon_url'] = '/modules/addons/eazybackup/uploads/portal_branding/' . $mspId . '/' . basename($favicon); }

    try {
        $row = Capsule::table('eb_msp_settings')->where('msp_id', $mspId)->first();
        $json = json_encode($merged);
        if ($row) {
            Capsule::table('eb_msp_settings')->where('msp_id', $mspId)->update(['portal_branding_json' => $json, 'updated_at' => date('Y-m-d H:i:s')]);
        } else {
            Capsule::table('eb_msp_settings')->insert(['msp_id' => $mspId, 'portal_branding_json' => $json, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        }
        echo json_encode(['status' => 'success']);
    } catch (\Throwable $e) {
        try { if (function_exists('logModuleCall')) { @logModuleCall('eazybackup', 'ph-settings-portal-branding-save', $raw, $e->getMessage()); } } catch (\Throwable $__) {}
        echo json_encode(['status' => 'error', 'message' => 'save_failed']);
    }
}

function eb_ph_portal_branding_check_dns(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status' => 'error', 'message' => 'auth']); return; }

    $token = (string)($_POST['token'] ?? '');
    if (function_exists('check_token')) {
        try { if (!check_token('plain', $token)) { echo json_encode(['status' => 'error', 'message' => 'csrf']); return; } } catch (\Throwable $__) { echo json_encode(['status' => 'error', 'message' => 'csrf']); return; }
    }

    $clientId = (int)$_SESSION['uid'];
    $hostname = strtolower(trim((string)($_POST['hostname'] ?? '')));
    if ($hostname === '' || !preg_match('/^[a-z0-9][a-z0-9.-]+\.[a-z]{2,}$/', $hostname)) {
        echo json_encode(['status' => 'error', 'message' => 'invalid_hostname']); return;
    }

    $target = (string)(Capsule::table('tbladdonmodules')->where('module', 'eazybackup')->where('setting', 'whitelabel_dns_target')->value('value') ?? '');
    $cname = @dns_get_record($hostname, DNS_CNAME);
    $resolved = false;
    if (is_array($cname)) {
        foreach ($cname as $r) {
            if (isset($r['target']) && rtrim(strtolower($r['target']), '.') === rtrim(strtolower($target), '.')) {
                $resolved = true;
                break;
            }
        }
    }

    echo json_encode(['status' => 'success', 'dns_ok' => $resolved, 'expected_target' => $target, 'hostname' => $hostname]);
}

function eb_ph_portal_branding_attach_domain(array $vars): void
{
    header('Content-Type: application/json');
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) { echo json_encode(['status' => 'error', 'message' => 'auth']); return; }

    $token = (string)($_POST['token'] ?? '');
    if (function_exists('check_token')) {
        try { if (!check_token('plain', $token)) { echo json_encode(['status' => 'error', 'message' => 'csrf']); return; } } catch (\Throwable $__) { echo json_encode(['status' => 'error', 'message' => 'csrf']); return; }
    }

    $clientId = (int)$_SESSION['uid'];
    $hostname = strtolower(trim((string)($_POST['hostname'] ?? '')));
    if ($hostname === '' || !preg_match('/^[a-z0-9][a-z0-9.-]+\.[a-z]{2,}$/', $hostname)) {
        echo json_encode(['status' => 'error', 'message' => 'invalid_hostname']); return;
    }

    try {
        require_once __DIR__ . '/../../lib/Whitelabel/HostOps.php';
        $ops = new \EazyBackup\Whitelabel\HostOps([]);

        $upstream = (string)(Capsule::table('tbladdonmodules')->where('module', 'eazybackup')->where('setting', 'ops_whmcs_upstream')->value('value') ?? '');
        if ($upstream === '') { $upstream = 'http://obc_servers'; }

        $httpOk = $ops->writeHttpStub($hostname);
        if (!$httpOk) { echo json_encode(['status' => 'error', 'message' => 'http_stub_failed']); return; }

        $certOk = $ops->issueCert($hostname);
        if (!$certOk) { echo json_encode(['status' => 'error', 'message' => 'cert_failed']); return; }

        $httpsOk = $ops->writeHttpsWithUpstream($hostname, $upstream);
        if (!$httpsOk) { echo json_encode(['status' => 'error', 'message' => 'https_failed']); return; }

        Capsule::table('s3_msp_portal_domains')->updateOrInsert(
            ['domain' => $hostname],
            [
                'client_id' => $clientId,
                'is_verified' => 1,
                'is_primary' => 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        );

        echo json_encode(['status' => 'success', 'hostname' => $hostname]);
    } catch (\Throwable $e) {
        try { if (function_exists('logModuleCall')) { @logModuleCall('eazybackup', 'ph-portal-branding-attach', ['hostname' => $hostname], $e->getMessage()); } } catch (\Throwable $__) {}
        echo json_encode(['status' => 'error', 'message' => 'attach_failed']);
    }
}

function eb_ph_get_portal_branding(int $mspId): array
{
    $defaults = eb_ph_portal_branding_defaults();
    try {
        $json = Capsule::table('eb_msp_settings')->where('msp_id', $mspId)->value('portal_branding_json');
        if ($json) {
            $stored = json_decode((string)$json, true);
            if (is_array($stored)) {
                return array_replace_recursive($defaults, $stored);
            }
        }
    } catch (\Throwable $__) {}
    return $defaults;
}
