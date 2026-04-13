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

function portal_tenant_table(): string
{
    return 'eb_tenants';
}

function portal_tenant_users_table(): string
{
    return 'eb_tenant_users';
}

function portal_resolve_msp_id_for_client(int $clientId): ?int
{
    try {
        $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id', $clientId)->first(['id']);
        return $msp ? (int) $msp->id : null;
    } catch (\Throwable $e) {
        return null;
    }
}

function portal_detect_branding(): array
{
    $tenantContextId = portal_resolve_tenant_context();
    if ($tenantContextId !== null && $tenantContextId > 0) {
        $tenantBrandingJson = Capsule::table('eb_tenants')
            ->where('id', $tenantContextId)
            ->whereNotNull('branding_json')
            ->value('branding_json');
        $tenantBranding = $tenantBrandingJson ? json_decode((string) $tenantBrandingJson, true) : [];
        if (!is_array($tenantBranding)) {
            $tenantBranding = [];
        }
        return array_merge(portal_default_branding(), $tenantBranding);
    }

    $clientId = portal_resolve_client_context();
    if ($clientId !== null && $clientId > 0) {
        return portal_load_branding($clientId);
    }

    return portal_default_branding();
}

function portal_resolve_tenant_context(): ?int
{
    $mspParam = $_GET['msp'] ?? null;
    if (!$mspParam) {
        return null;
    }

    $domainClientId = portal_resolve_client_context_from_domain();
    $query = Capsule::table('eb_tenants')
        ->where('slug', $mspParam)
        ->where('status', 'active');

    if ($domainClientId !== null && $domainClientId > 0) {
        $mspId = portal_resolve_msp_id_for_client($domainClientId);
        if ($mspId !== null) {
            $query->where('msp_id', $mspId);
        }
    }

    $matches = $query->orderBy('id', 'asc')->limit(2)->get(['id']);
    if (!$matches || count($matches) !== 1) {
        return null;
    }

    return (int) ($matches[0]->id ?? 0);
}

function portal_has_msp_context_param(): bool
{
    return isset($_GET['msp']) && trim((string) $_GET['msp']) !== '';
}

function portal_msp_context_is_invalid(): bool
{
    if (!portal_has_msp_context_param()) {
        return false;
    }
    return portal_resolve_tenant_context() === null;
}

function portal_resolve_client_context_from_domain(): ?int
{
    $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
    $host = preg_replace('/:\d+$/', '', $host);
    $host = rtrim((string) $host, '.');
    if (!$host) {
        return null;
    }

    $domain = Capsule::table('s3_msp_portal_domains')
        ->where('domain', $host)
        ->where('is_verified', 1)
        ->first();
    if ($domain && !empty($domain->client_id)) {
        return (int) $domain->client_id;
    }

    return null;
}

function portal_resolve_client_context(): ?int
{
    $domainClientId = portal_resolve_client_context_from_domain();
    if ($domainClientId !== null && $domainClientId > 0) {
        return $domainClientId;
    }

    $tenantContextId = portal_resolve_tenant_context();
    if ($tenantContextId !== null && $tenantContextId > 0) {
        $tenant = Capsule::table('eb_tenants')
            ->where('id', $tenantContextId)
            ->first();
        if (!$tenant) return null;

        $mspId = (int) ($tenant->msp_id ?? 0);
        if ($mspId > 0) {
            $msp = Capsule::table('eb_msp_accounts')->where('id', $mspId)->first(['whmcs_client_id']);
            return $msp ? (int) $msp->whmcs_client_id : null;
        }
    }

    return null;
}

function portal_load_branding(int $clientId): array
{
    $mspId = portal_resolve_msp_id_for_client($clientId);
    if ($mspId !== null) {
        $portalJson = null;
        try {
            $portalJson = Capsule::table('eb_msp_settings')
                ->where('msp_id', $mspId)
                ->value('portal_branding_json');
        } catch (\Throwable $e) {}
        if ($portalJson) {
            $stored = json_decode((string)$portalJson, true);
            if (is_array($stored) && isset($stored['identity'])) {
                $merged = portal_default_branding();
                $identity = (array)($stored['identity'] ?? []);
                foreach ($identity as $k => $v) {
                    if ($v !== '' && $v !== null) { $merged[$k] = $v; }
                }
                if (!empty($stored['portal_pages'])) { $merged['portal_pages'] = $stored['portal_pages']; }
                if (!empty($stored['footer']['text'])) { $merged['footer_text'] = $stored['footer']['text']; }
                return $merged;
            }
        }
    }

    if ($mspId !== null) {
        $brandingJson = Capsule::table('eb_tenants')
            ->where('msp_id', $mspId)
            ->whereNotNull('branding_json')
            ->value('branding_json');
    } else {
        $brandingJson = null;
    }

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
    if (portal_msp_context_is_invalid()) {
        $_SESSION['portal_user'] = null;
        header('Location: /portal/index.php?page=login&msp=' . rawurlencode((string) ($_GET['msp'] ?? '')));
        exit;
    }

    $sess = portal_session();
    if (!$sess) {
        $url = '/portal/index.php?page=login';
        if (!empty($_GET['msp'])) {
            $url .= '&msp=' . rawurlencode((string) $_GET['msp']);
        }
        header('Location: ' . $url);
        exit;
    }

    $tenantId = (int) ($sess['tenant_id'] ?? 0);
    $userId = (int) ($sess['user_id'] ?? 0);
    if ($tenantId <= 0 || $userId <= 0) {
        $_SESSION['portal_user'] = null;
        $url = '/portal/index.php?page=login';
        if (!empty($_GET['msp'])) {
            $url .= '&msp=' . rawurlencode((string) $_GET['msp']);
        }
        header('Location: ' . $url);
        exit;
    }

    $row = Capsule::table('eb_tenant_users as u')
        ->join('eb_tenants as t', 'u.tenant_id', '=', 't.id')
        ->where('u.id', $userId)
        ->where('u.tenant_id', $tenantId)
        ->where('u.status', 'active')
        ->where('t.status', 'active')
        ->first([
            'u.id as user_id',
            'u.tenant_id',
            'u.email',
            'u.name',
            'u.role',
            't.msp_id as owner_id',
            't.name as tenant_name',
        ]);
    if (!$row) {
        $_SESSION['portal_user'] = null;
        $url = '/portal/index.php?page=login';
        if (!empty($_GET['msp'])) {
            $url .= '&msp=' . rawurlencode((string) $_GET['msp']);
        }
        header('Location: ' . $url);
        exit;
    }
    $requestedTenantId = portal_resolve_tenant_context();
    if ($requestedTenantId !== null && $requestedTenantId > 0 && $requestedTenantId !== (int) $row->tenant_id) {
        $_SESSION['portal_user'] = null;
        $url = '/portal/index.php?page=login&msp=' . rawurlencode((string) ($_GET['msp'] ?? ''));
        header('Location: ' . $url);
        exit;
    }

    $clientId = (int) ($row->owner_id ?? 0);
    if ($clientId > 0) {
        $msp = Capsule::table('eb_msp_accounts')->where('id', $clientId)->first(['whmcs_client_id']);
        $clientId = $msp ? (int) $msp->whmcs_client_id : 0;
    }

    $sess['user_id'] = (int) $row->user_id;
    $sess['tenant_id'] = (int) $row->tenant_id;
    $sess['client_id'] = $clientId;
    $sess['email'] = (string) ($row->email ?? '');
    $sess['name'] = (string) ($row->name ?? '');
    $sess['role'] = (string) ($row->role ?? '');
    $sess['tenant_name'] = (string) ($row->tenant_name ?? '');
    $_SESSION['portal_user'] = $sess;

    return $sess;
}

function portal_require_auth_json(): array
{
    if (portal_msp_context_is_invalid()) {
        $_SESSION['portal_user'] = null;
        portal_json(['status' => 'fail', 'message' => 'auth'], 401);
    }

    $sess = portal_session();
    if (!$sess) {
        portal_json(['status' => 'fail', 'message' => 'auth'], 401);
    }

    $tenantId = (int) ($sess['tenant_id'] ?? 0);
    $userId = (int) ($sess['user_id'] ?? 0);
    if ($tenantId <= 0 || $userId <= 0) {
        $_SESSION['portal_user'] = null;
        portal_json(['status' => 'fail', 'message' => 'auth'], 401);
    }

    $row = Capsule::table('eb_tenant_users as u')
        ->join('eb_tenants as t', 'u.tenant_id', '=', 't.id')
        ->where('u.id', $userId)
        ->where('u.tenant_id', $tenantId)
        ->where('u.status', 'active')
        ->where('t.status', 'active')
        ->first([
            'u.id as user_id',
            'u.tenant_id',
            'u.email',
            'u.name',
            'u.role',
            't.msp_id as owner_id',
            't.name as tenant_name',
        ]);
    if (!$row) {
        $_SESSION['portal_user'] = null;
        portal_json(['status' => 'fail', 'message' => 'auth'], 401);
    }
    $requestedTenantId = portal_resolve_tenant_context();
    if ($requestedTenantId !== null && $requestedTenantId > 0 && $requestedTenantId !== (int) $row->tenant_id) {
        $_SESSION['portal_user'] = null;
        portal_json(['status' => 'fail', 'message' => 'auth'], 401);
    }

    $clientId = (int) ($row->owner_id ?? 0);
    if ($clientId > 0) {
        $msp = Capsule::table('eb_msp_accounts')->where('id', $clientId)->first(['whmcs_client_id']);
        $clientId = $msp ? (int) $msp->whmcs_client_id : 0;
    }

    $sess['user_id'] = (int) $row->user_id;
    $sess['tenant_id'] = (int) $row->tenant_id;
    $sess['client_id'] = $clientId;
    $sess['email'] = (string) ($row->email ?? '');
    $sess['name'] = (string) ($row->name ?? '');
    $sess['role'] = (string) ($row->role ?? '');
    $sess['tenant_name'] = (string) ($row->tenant_name ?? '');
    $_SESSION['portal_user'] = $sess;

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
