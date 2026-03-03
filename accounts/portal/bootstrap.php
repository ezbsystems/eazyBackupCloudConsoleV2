<?php

require_once __DIR__ . '/../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function portal_json(array $payload, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function portal_detect_branding(): array
{
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $mspParam = $_GET['msp'] ?? null;

    if ($host) {
        $domain = Capsule::table('s3_msp_portal_domains')
            ->where('domain', $host)
            ->where('is_verified', 1)
            ->first();
        if ($domain) {
            return portal_load_branding((int) $domain->client_id);
        }
    }

    if ($mspParam) {
        $tenant = Capsule::table('s3_backup_tenants')
            ->where('slug', $mspParam)
            ->first();
        if ($tenant) {
            return portal_load_branding((int) $tenant->client_id);
        }
    }

    return portal_default_branding();
}

function portal_load_branding(int $clientId): array
{
    $brandingJson = Capsule::table('s3_backup_tenants')
        ->where('client_id', $clientId)
        ->whereNotNull('branding_json')
        ->value('branding_json');

    $branding = $brandingJson ? json_decode($brandingJson, true) : [];
    if (!is_array($branding)) {
        $branding = [];
    }

    return array_merge(portal_default_branding(), $branding);
}

function portal_default_branding(): array
{
    return [
        'company_name' => 'e3 Cloud Backup',
        'logo_url' => '/templates/eazyBackup/assets/img/logo.svg',
        'logo_dark_url' => '/templates/eazyBackup/assets/img/logo.svg',
        'primary_color' => '#FE5000',
        'support_email' => 'support@eazybackup.ca',
        'support_url' => 'https://support.eazybackup.ca',
    ];
}

function portal_session(): ?array
{
    return $_SESSION['portal_user'] ?? null;
}

function portal_require_auth(): array
{
    $sess = portal_session();
    if (!$sess) {
        header('Location: /portal/index.php?page=login');
        exit;
    }
    return $sess;
}

function portal_logout(): void
{
    $_SESSION['portal_user'] = null;
    session_destroy();
}

function portal_render(string $template, array $vars = []): void
{
    extract($vars);
    include __DIR__ . '/templates/layout.tpl';
}
