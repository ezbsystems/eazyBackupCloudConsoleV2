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
    return ['active', 'suspended'];
}

function eb_ph_tenants_normalize_status(string $status): string
{
    $status = strtolower(trim($status));
    if (!in_array($status, eb_ph_tenants_statuses(), true)) {
        return 'active';
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

function eb_ph_tenants_strip_infra_fields(array $input): array
{
    unset($input['product_id'], $input['server_id'], $input['servergroup_id']);
    return $input;
}

function eb_ph_tenants_slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-');
}

function eb_ph_tenants_is_valid_slug(string $value): bool
{
    return (bool)preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $value);
}

function eb_ph_tenants_existing_slug_owner(int $clientId, string $slug, ?int $ignoreTenantId = null): ?int
{
    if ($slug === '') {
        return null;
    }
    $query = Capsule::table('s3_backup_tenants')
        ->where('client_id', $clientId)
        ->where('slug', $slug)
        ->where('status', '!=', 'deleted');
    if ($ignoreTenantId !== null && $ignoreTenantId > 0) {
        $query->where('id', '!=', $ignoreTenantId);
    }
    $row = $query->first(['id']);
    if (!$row) {
        return null;
    }
    return (int)($row->id ?? 0);
}

function eb_ph_tenants_delete_blockers(int $tenantId): array
{
    $blockers = [];
    if ($tenantId <= 0) {
        return $blockers;
    }

    if (Capsule::schema()->hasTable('eb_customers')) {
        $linkedCustomers = (int)Capsule::table('eb_customers')->where('tenant_id', $tenantId)->count();
        if ($linkedCustomers > 0) {
            $blockers['customer_links'] = $linkedCustomers;
        }
    }
    if (Capsule::schema()->hasTable('s3_backup_tenant_users')) {
        $members = (int)Capsule::table('s3_backup_tenant_users')->where('tenant_id', $tenantId)->count();
        if ($members > 0) {
            $blockers['members'] = $members;
        }
    }
    if (Capsule::schema()->hasTable('eb_tenant_storage_links')) {
        $storageLinks = (int)Capsule::table('eb_tenant_storage_links')->where('tenant_id', $tenantId)->count();
        if ($storageLinks > 0) {
            $blockers['storage_links'] = $storageLinks;
        }
    }
    if (Capsule::schema()->hasTable('s3_backup_users')) {
        $storageUsers = (int)Capsule::table('s3_backup_users')->where('tenant_id', $tenantId)->count();
        if ($storageUsers > 0) {
            $blockers['storage_users'] = $storageUsers;
        }
    }
    if (Capsule::schema()->hasTable('s3_cloudbackup_agents')) {
        $agents = (int)Capsule::table('s3_cloudbackup_agents')->where('tenant_id', $tenantId)->count();
        if ($agents > 0) {
            $blockers['agents'] = $agents;
        }
    }
    if (Capsule::schema()->hasTable('s3_backup_usage_snapshots')) {
        $usageRows = (int)Capsule::table('s3_backup_usage_snapshots')
            ->where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->where('storage_bytes', '>', 0)
                    ->orWhere('agent_count', '>', 0)
                    ->orWhere('disk_image_agent_count', '>', 0)
                    ->orWhere('vm_count', '>', 0);
            })
            ->count();
        if ($usageRows > 0) {
            $blockers['usage'] = $usageRows;
        }
    }

    return $blockers;
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
        $post = eb_ph_tenants_strip_infra_fields((array)$_POST);
        $token = (string)($_POST['token'] ?? '');
        eb_ph_tenants_require_csrf_or_redirect($vars, $token);

        $name = trim((string)($post['name'] ?? ''));
        $slugRaw = trim((string)($post['slug'] ?? ''));
        $slug = eb_ph_tenants_slugify($slugRaw !== '' ? $slugRaw : $name);
        $contactEmail = strtolower(trim((string)($post['contact_email'] ?? '')));
        $status = eb_ph_tenants_normalize_status((string)($post['status'] ?? 'active'));
        if ($status === 'deleted') {
            $status = 'active';
        }

        if ($name === '' || $slug === '') {
            eb_ph_tenants_redirect($vars, 'error=missing_fields');
        }
        if (!eb_ph_tenants_is_valid_slug($slug)) {
            eb_ph_tenants_redirect($vars, 'error=invalid_slug');
        }
        if ($contactEmail !== '' && !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
            eb_ph_tenants_redirect($vars, 'error=invalid_email');
        }
        if (eb_ph_tenants_existing_slug_owner($clientId, $slug) !== null) {
            eb_ph_tenants_redirect($vars, 'error=slug_taken');
        }

        try {
            $tenantId = (int)Capsule::table('s3_backup_tenants')->insertGetId([
                'client_id' => $clientId,
                'name' => $name,
                'slug' => $slug,
                'contact_email' => $contactEmail !== '' ? $contactEmail : null,
                'status' => $status,
                'created_at' => Capsule::raw('NOW()'),
                'updated_at' => Capsule::raw('NOW()'),
            ]);
            header('Location: ' . eb_ph_tenants_base_link($vars) . '&a=ph-tenant&id=' . $tenantId . '&notice=created');
            exit;
        } catch (\Throwable $__) {
            eb_ph_tenants_redirect($vars, 'error=create_failed');
        }
    }

    $rowsCol = Capsule::table('s3_backup_tenants')->where('client_id', $clientId)
        ->where('status', '!=', 'deleted')
        ->orderBy('created_at', 'desc')
        ->get();
    $rows = [];
    foreach ($rowsCol as $row) {
        $rows[] = (array)$row;
    }

    return [
        'pagetitle' => 'Partner Hub - Customer Tenants',
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

    $tenant = Capsule::table('s3_backup_tenants')
        ->where('id', $tenantId)
        ->where('client_id', $clientId)
        ->where('status', '!=', 'deleted')
        ->first();
    if (!$tenant) {
        eb_ph_tenants_redirect($vars, 'error=not_found');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eb_save_tenant'])) {
        $post = eb_ph_tenants_strip_infra_fields((array)$_POST);
        $token = (string)($_POST['token'] ?? '');
        eb_ph_tenants_require_csrf_or_redirect($vars, $token, $tenantId);

        $name = trim((string)($post['name'] ?? ''));
        $slug = eb_ph_tenants_slugify((string)($post['slug'] ?? ''));
        $contactEmail = strtolower(trim((string)($post['contact_email'] ?? '')));
        if ($name === '' || $slug === '') {
            header('Location: ' . eb_ph_tenants_base_link($vars) . '&a=ph-tenant&id=' . $tenantId . '&error=missing_fields');
            exit;
        }
        if (!eb_ph_tenants_is_valid_slug($slug)) {
            header('Location: ' . eb_ph_tenants_base_link($vars) . '&a=ph-tenant&id=' . $tenantId . '&error=invalid_slug');
            exit;
        }
        if ($contactEmail !== '' && !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
            header('Location: ' . eb_ph_tenants_base_link($vars) . '&a=ph-tenant&id=' . $tenantId . '&error=invalid_email');
            exit;
        }
        if (eb_ph_tenants_existing_slug_owner($clientId, $slug, $tenantId) !== null) {
            header('Location: ' . eb_ph_tenants_base_link($vars) . '&a=ph-tenant&id=' . $tenantId . '&error=slug_taken');
            exit;
        }

        try {
            $status = eb_ph_tenants_normalize_status((string)($post['status'] ?? 'active'));
            if ($status === 'deleted') {
                $status = 'active';
            }
            Capsule::table('s3_backup_tenants')->where('id', $tenantId)->where('client_id', $clientId)->update([
                'name' => $name,
                'slug' => $slug,
                'contact_email' => $contactEmail !== '' ? $contactEmail : null,
                'status' => $status,
                'updated_at' => Capsule::raw('NOW()'),
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
            $blockers = eb_ph_tenants_delete_blockers($tenantId);
        } catch (\Throwable $__) {
            eb_ph_tenant_redirect($vars, $tenantId, 'error=tenant_ref_check_failed');
        }
        if (!empty($blockers)) {
            eb_ph_tenant_redirect($vars, $tenantId, 'error=tenant_in_use');
        }

        try {
            Capsule::table('s3_backup_tenants')->where('id', $tenantId)->where('client_id', $clientId)->update([
                'status' => 'deleted',
                'updated_at' => Capsule::raw('NOW()'),
            ]);
            eb_ph_tenants_redirect($vars, 'notice=deleted');
        } catch (\Throwable $__) {
            header('Location: ' . eb_ph_tenants_base_link($vars) . '&a=ph-tenant&id=' . $tenantId . '&error=delete_failed');
            exit;
        }
    }

    $tenant = Capsule::table('s3_backup_tenants')
        ->where('id', $tenantId)
        ->where('client_id', $clientId)
        ->where('status', '!=', 'deleted')
        ->first();
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

