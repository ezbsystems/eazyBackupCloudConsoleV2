<?php

use WHMCS\Database\Capsule;

function eb_ph_tenants_base_link(array $vars): string
{
    return (string)($vars['modulelink'] ?? 'index.php?m=eazybackup');
}

function eb_ph_tenants_redirect(array $vars, string $query = ''): void
{
    $url = eb_ph_tenants_base_link($vars) . '&a=ph-tenants';
    if ($query !== '') {
        $url .= '&' . $query;
    }
    header('Location: ' . $url);
    exit;
}

function eb_ph_tenant_redirect(array $vars, int $tenantId, string $query = ''): void
{
    $url = eb_ph_tenants_base_link($vars) . '&a=ph-tenant&id=' . $tenantId;
    if ($query !== '') {
        $url .= '&' . $query;
    }
    header('Location: ' . $url);
    exit;
}

function eb_ph_tenants_statuses(): array
{
    return ['queued', 'building', 'active', 'failed', 'suspended', 'removing'];
}

function eb_ph_tenants_normalize_status(string $status): string
{
    $status = strtolower(trim($status));
    if (!in_array($status, eb_ph_tenants_statuses(), true)) {
        return 'queued';
    }
    return $status;
}

function eb_ph_tenants_generate_idempotency_key(): string
{
    try {
        return bin2hex(random_bytes(16));
    } catch (\Throwable $__) {
        return sha1((string)microtime(true) . '-' . mt_rand());
    }
}

function eb_ph_tenants_require_context(array $vars): array
{
    if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) {
        header('Location: clientarea.php');
        exit;
    }

    $clientId = (int)$_SESSION['uid'];

    // Preserve reseller-group gating used across Partner Hub pages.
    try {
        $resellerGroupsSetting = (string)(Capsule::table('tbladdonmodules')
            ->where('module', 'eazybackup')
            ->where('setting', 'resellergroups')
            ->value('value') ?? '');
        if ($resellerGroupsSetting !== '') {
            $gid = (int)(Capsule::table('tblclients')->where('id', $clientId)->value('groupid') ?? 0);
            if ($gid > 0) {
                $ids = array_map('intval', array_filter(array_map('trim', explode(',', $resellerGroupsSetting))));
                if (!in_array($gid, $ids, true)) {
                    header('HTTP/1.1 403 Forbidden');
                    exit;
                }
            }
        }
    } catch (\Throwable $__) {
        // Keep existing fail-open behavior for reseller lookup issues.
    }

    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id', $clientId)->first();
    if (!$msp) {
        header('Location: ' . eb_ph_tenants_base_link($vars) . '&a=ph-clients');
        exit;
    }

    return [$clientId, $msp];
}

function eb_ph_tenants_require_csrf_or_redirect(array $vars, string $token, ?int $tenantId = null): void
{
    $reject = function () use ($vars, $tenantId): void {
        if ($tenantId !== null && $tenantId > 0) {
            eb_ph_tenant_redirect($vars, $tenantId, 'error=csrf');
        }
        eb_ph_tenants_redirect($vars, 'error=csrf');
    };

    if ($token === '' || !function_exists('check_token')) {
        $reject();
    }

    try {
        $valid = (bool)check_token('plain', $token);
    } catch (\Throwable $__) {
        $reject();
    }

    if (!$valid) {
        $reject();
    }
}

function eb_ph_tenants_management_entry(array $vars)
{
    return eb_ph_tenants_index($vars);
}

function eb_ph_tenants_index(array $vars)
{
    [$clientId, $msp] = eb_ph_tenants_require_context($vars);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eb_create_tenant'])) {
        $token = (string)($_POST['token'] ?? '');
        eb_ph_tenants_require_csrf_or_redirect($vars, $token);

        $subdomain = trim((string)($_POST['subdomain'] ?? ''));
        $fqdn = trim((string)($_POST['fqdn'] ?? ''));
        $status = eb_ph_tenants_normalize_status((string)($_POST['status'] ?? 'queued'));

        if ($subdomain === '' || $fqdn === '') {
            eb_ph_tenants_redirect($vars, 'error=missing_fields');
        }

        $insert = [
            'client_id' => $clientId,
            'status' => $status,
            'org_id' => trim((string)($_POST['org_id'] ?? '')) ?: null,
            'subdomain' => $subdomain,
            'fqdn' => $fqdn,
            'custom_domain' => trim((string)($_POST['custom_domain'] ?? '')) ?: null,
            'product_id' => (int)($_POST['product_id'] ?? 0) > 0 ? (int)$_POST['product_id'] : null,
            'server_id' => (int)($_POST['server_id'] ?? 0) > 0 ? (int)$_POST['server_id'] : null,
            'servergroup_id' => (int)($_POST['servergroup_id'] ?? 0) > 0 ? (int)$_POST['servergroup_id'] : null,
            'idempotency_key' => eb_ph_tenants_generate_idempotency_key(),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        try {
            $tenantId = (int)Capsule::table('eb_whitelabel_tenants')->insertGetId([
                'client_id' => $insert['client_id'],
                'status' => $insert['status'],
                'org_id' => $insert['org_id'],
                'subdomain' => $insert['subdomain'],
                'fqdn' => $insert['fqdn'],
                'custom_domain' => $insert['custom_domain'],
                'product_id' => $insert['product_id'],
                'server_id' => $insert['server_id'],
                'servergroup_id' => $insert['servergroup_id'],
                'idempotency_key' => $insert['idempotency_key'],
                'created_at' => $insert['created_at'],
                'updated_at' => $insert['updated_at'],
            ]);
            header('Location: ' . eb_ph_tenants_base_link($vars) . '&a=ph-tenant&id=' . $tenantId . '&notice=created');
            exit;
        } catch (\Throwable $__) {
            eb_ph_tenants_redirect($vars, 'error=create_failed');
        }
    }

    $rowsCol = Capsule::table('eb_whitelabel_tenants')->where('client_id', $clientId)
        ->orderBy('created_at', 'desc')
        ->get();
    $rows = [];
    foreach ($rowsCol as $row) {
        $rows[] = (array)$row;
    }

    return [
        'pagetitle' => 'Partner Hub - Tenant Management',
        'templatefile' => 'whitelabel/tenants',
        'breadcrumb' => ['index.php?m=eazybackup' => 'eazyBackup'],
        'requirelogin' => true,
        'forcessl' => true,
        'vars' => [
            'modulelink' => eb_ph_tenants_base_link($vars),
            'msp' => (array)$msp,
            'tenants' => $rows,
            'statuses' => eb_ph_tenants_statuses(),
            'token' => function_exists('generate_token') ? generate_token('plain') : '',
            'notice' => (string)($_GET['notice'] ?? ''),
            'error' => (string)($_GET['error'] ?? ''),
        ],
    ];
}

function eb_ph_tenant_detail(array $vars)
{
    [$clientId, $msp] = eb_ph_tenants_require_context($vars);
    $tenantId = (int)($_GET['id'] ?? $_POST['tenant_id'] ?? 0);
    if ($tenantId <= 0) {
        eb_ph_tenants_redirect($vars, 'error=not_found');
    }

    $tenant = Capsule::table('eb_whitelabel_tenants')->where('id', $tenantId)->where('client_id', $clientId)->first();
    if (!$tenant) {
        eb_ph_tenants_redirect($vars, 'error=not_found');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eb_save_tenant'])) {
        $token = (string)($_POST['token'] ?? '');
        eb_ph_tenants_require_csrf_or_redirect($vars, $token, $tenantId);

        $subdomain = trim((string)($_POST['subdomain'] ?? ''));
        $fqdn = trim((string)($_POST['fqdn'] ?? ''));
        if ($subdomain === '' || $fqdn === '') {
            header('Location: ' . eb_ph_tenants_base_link($vars) . '&a=ph-tenant&id=' . $tenantId . '&error=missing_fields');
            exit;
        }

        try {
            Capsule::table('eb_whitelabel_tenants')->where('id', $tenantId)->where('client_id', $clientId)->update([
                'status' => eb_ph_tenants_normalize_status((string)($_POST['status'] ?? 'queued')),
                'org_id' => trim((string)($_POST['org_id'] ?? '')) ?: null,
                'subdomain' => $subdomain,
                'fqdn' => $fqdn,
                'custom_domain' => trim((string)($_POST['custom_domain'] ?? '')) ?: null,
                'product_id' => (int)($_POST['product_id'] ?? 0) > 0 ? (int)$_POST['product_id'] : null,
                'server_id' => (int)($_POST['server_id'] ?? 0) > 0 ? (int)$_POST['server_id'] : null,
                'servergroup_id' => (int)($_POST['servergroup_id'] ?? 0) > 0 ? (int)$_POST['servergroup_id'] : null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            header('Location: ' . eb_ph_tenants_base_link($vars) . '&a=ph-tenant&id=' . $tenantId . '&notice=saved');
            exit;
        } catch (\Throwable $__) {
            header('Location: ' . eb_ph_tenants_base_link($vars) . '&a=ph-tenant&id=' . $tenantId . '&error=save_failed');
            exit;
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eb_delete_tenant'])) {
        $token = (string)($_POST['token'] ?? '');
        eb_ph_tenants_require_csrf_or_redirect($vars, $token, $tenantId);

        try {
            $hasCanonicalLinks = Capsule::table('eb_customers')->where('tenant_id', $tenantId)->exists();
        } catch (\Throwable $__) {
            eb_ph_tenant_redirect($vars, $tenantId, 'error=tenant_ref_check_failed');
        }
        if ($hasCanonicalLinks) {
            eb_ph_tenant_redirect($vars, $tenantId, 'error=tenant_in_use');
        }

        try {
            Capsule::table('eb_whitelabel_tenants')->where('id', $tenantId)->where('client_id', $clientId)->delete();
            eb_ph_tenants_redirect($vars, 'notice=deleted');
        } catch (\Throwable $__) {
            header('Location: ' . eb_ph_tenants_base_link($vars) . '&a=ph-tenant&id=' . $tenantId . '&error=delete_failed');
            exit;
        }
    }

    $tenant = Capsule::table('eb_whitelabel_tenants')->where('id', $tenantId)->where('client_id', $clientId)->first();
    if (!$tenant) {
        eb_ph_tenants_redirect($vars, 'notice=deleted');
    }

    return [
        'pagetitle' => 'Tenant Detail',
        'templatefile' => 'whitelabel/tenant-detail',
        'breadcrumb' => ['index.php?m=eazybackup' => 'eazyBackup'],
        'requirelogin' => true,
        'forcessl' => true,
        'vars' => [
            'modulelink' => eb_ph_tenants_base_link($vars),
            'msp' => (array)$msp,
            'tenant' => (array)$tenant,
            'statuses' => eb_ph_tenants_statuses(),
            'token' => function_exists('generate_token') ? generate_token('plain') : '',
            'notice' => (string)($_GET['notice'] ?? ''),
            'error' => (string)($_GET['error'] ?? ''),
        ],
    ];
}

