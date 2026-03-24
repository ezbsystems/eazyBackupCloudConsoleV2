<?php

use WHMCS\Database\Capsule;
use PartnerHub\StripeService;

function eb_ph_tenants_base_link(array $vars): string
{
    return (string)($vars['modulelink'] ?? 'index.php?m=eazybackup');
}

function eb_ph_tenants_redirect(array $vars, string $query = ''): void
{
    $url = eb_ph_tenants_base_link($vars) . '&a=ph-tenants-manage';
    if ($query !== '') {
        $url .= '&' . $query;
    }
    header('Location: ' . $url);
    exit;
}

function eb_ph_tenants_legacy_clients_redirect(array $vars): void
{
    $query = $_GET;
    unset($query['m'], $query['a']);
    eb_ph_tenants_redirect($vars, http_build_query($query));
}

function eb_ph_tenant_redirect(array $vars, string $tenantPublicId, string $query = ''): void
{
    $url = eb_ph_tenants_base_link($vars) . '&a=ph-tenant&id=' . rawurlencode($tenantPublicId);
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

function eb_ph_tenants_existing_slug_owner(int $mspId, string $slug, ?int $ignoreTenantId = null): ?int
{
    if ($slug === '') {
        return null;
    }
    $query = Capsule::table('eb_tenants')
        ->where('msp_id', $mspId)
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

    if (Capsule::schema()->hasTable('eb_tenant_users')) {
        $members = (int)Capsule::table('eb_tenant_users')->where('tenant_id', $tenantId)->count();
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

    if (Capsule::schema()->hasTable('eb_plan_instances')) {
        $activeSubs = (int)Capsule::table('eb_plan_instances')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['active', 'trialing', 'past_due', 'paused'])
            ->count();
        if ($activeSubs > 0) {
            $blockers['plan_instances'] = $activeSubs;
        }
    }
    if (Capsule::schema()->hasTable('eb_subscriptions')) {
        $legacySubs = (int)Capsule::table('eb_subscriptions')
            ->where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->whereNull('stripe_status')
                    ->orWhereNotIn('stripe_status', ['canceled', 'cancelled']);
            })
            ->count();
        if ($legacySubs > 0) {
            $blockers['subscriptions'] = $legacySubs;
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
        header('Location: ' . eb_ph_tenants_base_link($vars) . '&a=ph-tenants-manage');
        exit;
    }

    return [$clientId, $msp];
}

function eb_ph_tenants_find_owned_tenant_by_reference(int $mspId, string $tenantReference): ?object
{
    $tenantReference = trim($tenantReference);
    if ($mspId <= 0 || $tenantReference === '') {
        return null;
    }

    $query = Capsule::table('eb_tenants')
        ->where('msp_id', $mspId)
        ->where('status', '!=', 'deleted');

    if (preg_match('/^\d+$/', $tenantReference)) {
        return $query->where('id', (int)$tenantReference)->first();
    }

    return $query->where('public_id', $tenantReference)->first();
}

function eb_ph_tenants_find_owned_tenant_by_public_id(int $mspId, string $tenantPublicId): ?object
{
    $tenantPublicId = trim($tenantPublicId);
    if ($mspId <= 0 || $tenantPublicId === '') {
        return null;
    }

    if (preg_match('/^\d+$/', $tenantPublicId)) {
        return null;
    }

    return Capsule::table('eb_tenants')
        ->where('msp_id', $mspId)
        ->where('status', '!=', 'deleted')
        ->where('public_id', $tenantPublicId)
        ->first();
}

function eb_ph_tenants_require_csrf_or_redirect(array $vars, string $token, ?string $tenantPublicId = null): void
{
    $reject = function () use ($vars, $tenantPublicId): void {
        if ($tenantPublicId !== null && $tenantPublicId !== '') {
            eb_ph_tenant_redirect($vars, $tenantPublicId, 'error=csrf');
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

function eb_ph_tenants_require_csrf_or_json_error(string $token): bool
{
    if ($token === '' || !function_exists('check_token')) {
        echo json_encode(['status' => 'error', 'message' => 'csrf']);
        return false;
    }

    try {
        $valid = (bool)check_token('plain', $token);
    } catch (\Throwable $__) {
        echo json_encode(['status' => 'error', 'message' => 'csrf']);
        return false;
    }

    if (!$valid) {
        echo json_encode(['status' => 'error', 'message' => 'csrf']);
        return false;
    }

    return true;
}

function eb_ph_tenants_management_entry(array $vars)
{
    return eb_ph_tenants_index($vars);
}

function eb_ph_tenant_tab_links(array $vars, string $tenantPublicId): array
{
    $base = eb_ph_tenants_base_link($vars);
    $tenantPublicId = rawurlencode($tenantPublicId);
    return [
        'profile' => $base . '&a=ph-tenant&id=' . $tenantPublicId,
        'members' => $base . '&a=ph-tenant-members&id=' . $tenantPublicId,
        'storage_users' => $base . '&a=ph-tenant-storage-users&id=' . $tenantPublicId,
        'billing' => $base . '&a=ph-tenant-billing&id=' . $tenantPublicId,
        'white_label' => $base . '&a=ph-tenant-whitelabel&id=' . $tenantPublicId,
    ];
}

function eb_ph_tenant_require_owned(array $vars): array
{
    [$clientId, $msp] = eb_ph_tenants_require_context($vars);
    $tenantPublicId = (string)($_GET['id'] ?? $_POST['tenant_id'] ?? '');
    $tenantPublicId = trim($tenantPublicId);
    if ($tenantPublicId === '') {
        eb_ph_tenants_redirect($vars, 'error=not_found');
    }

    $tenant = Capsule::table('eb_tenants')
        ->where('public_id', $tenantPublicId)
        ->where('msp_id', (int)$msp->id)
        ->where('status', '!=', 'deleted')
        ->first();
    if (!$tenant) {
        eb_ph_tenants_redirect($vars, 'error=not_found');
    }

    $tenantId = (int)($tenant->id ?? 0);

    return [$clientId, $msp, $tenantId, $tenant];
}

function eb_ph_tenant_primary_admin(int $tenantId): array
{
    $empty = [
        'available' => false,
        'exists' => false,
        'id' => 0,
        'email' => '',
        'name' => '',
        'status' => 'active',
        'last_login_at' => '',
        'updated_at' => '',
    ];
    if ($tenantId <= 0 || !Capsule::schema()->hasTable('eb_tenant_users')) {
        return $empty;
    }

    try {
        $row = Capsule::table('eb_tenant_users')
            ->where('tenant_id', $tenantId)
            ->where('role', 'admin')
            ->orderBy('id', 'asc')
            ->first([
                'id',
                'email',
                'name',
                'status',
                'last_login_at',
                'updated_at',
            ]);
        if (!$row) {
            $empty['available'] = true;
            return $empty;
        }

        return [
            'available' => true,
            'exists' => true,
            'id' => (int)($row->id ?? 0),
            'email' => (string)($row->email ?? ''),
            'name' => (string)($row->name ?? ''),
            'status' => (string)($row->status ?? 'active'),
            'last_login_at' => (string)($row->last_login_at ?? ''),
            'updated_at' => (string)($row->updated_at ?? ''),
        ];
    } catch (\Throwable $__) {
        return $empty;
    }
}

function eb_ph_tenant_shell_response(array $vars, array $msp, array $tenant, string $activeTab, array $tabVars = []): array
{
    $tenantPublicId = trim((string)($tenant['public_id'] ?? ''));
    $tabVars['tab_links'] = eb_ph_tenant_tab_links($vars, $tenantPublicId);
    $tabVars['active_tab'] = $activeTab;

    return [
        'pagetitle' => 'Tenant Detail',
        'templatefile' => 'whitelabel/tenant-detail',
        'breadcrumb' => ['index.php?m=eazybackup' => 'eazyBackup'],
        'requirelogin' => true,
        'forcessl' => true,
        'vars' => array_merge([
            'modulelink' => eb_ph_tenants_base_link($vars),
            'msp' => $msp,
            'tenant' => $tenant,
            'statuses' => eb_ph_tenants_statuses(),
            'token' => function_exists('generate_token') ? generate_token('plain') : '',
            'notice' => (string)($_GET['notice'] ?? ''),
            'legacy_notice' => (string)($_GET['legacy'] ?? ''),
            'error' => (string)($_GET['error'] ?? ''),
        ], $tabVars),
    ];
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
        $contactName = trim((string)($post['contact_name'] ?? ''));
        $contactPhone = trim((string)($post['contact_phone'] ?? ''));
        $addressLine1 = trim((string)($post['address_line1'] ?? ''));
        $addressLine2 = trim((string)($post['address_line2'] ?? ''));
        $city = trim((string)($post['city'] ?? ''));
        $state = trim((string)($post['state'] ?? ''));
        $postalCode = trim((string)($post['postal_code'] ?? ''));
        $countryRaw = (string)($post['country'] ?? '');
        $countryRaw = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $countryRaw) ?? $countryRaw;
        if (function_exists('mb_convert_kana')) {
            $countryRaw = mb_convert_kana($countryRaw, 'as', 'UTF-8');
        }
        $countryRaw = preg_replace('/\s+/u', '', trim($countryRaw)) ?? trim($countryRaw);
        $countryRaw = preg_replace('/[^A-Za-z]/', '', $countryRaw) ?? $countryRaw;
        $country = $countryRaw !== '' ? strtoupper($countryRaw) : null;
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
        if ($country !== null && $country !== '' && !preg_match('/^[A-Z]{2}$/', $country)) {
            eb_ph_tenants_redirect($vars, 'error=invalid_country');
        }
        if (eb_ph_tenants_existing_slug_owner((int)$msp->id, $slug) !== null) {
            eb_ph_tenants_redirect($vars, 'error=slug_taken');
        }

        $insert = [
            'msp_id' => (int)$msp->id,
            'name' => $name,
            'slug' => $slug,
            'contact_email' => $contactEmail !== '' ? $contactEmail : null,
            'status' => $status,
            'created_at' => Capsule::raw('NOW()'),
            'updated_at' => Capsule::raw('NOW()'),
        ];
        $schema = Capsule::schema();
        if ($schema->hasTable('eb_tenants') && $schema->hasColumn('eb_tenants', 'public_id')) {
            $insert['public_id'] = eazybackup_generate_ulid();
        }
        if ($schema->hasTable('eb_tenants') && $schema->hasColumn('eb_tenants', 'contact_name')) {
            $insert['contact_name'] = $contactName !== '' ? $contactName : null;
        }
        if ($schema->hasTable('eb_tenants') && $schema->hasColumn('eb_tenants', 'contact_phone')) {
            $insert['contact_phone'] = $contactPhone !== '' ? $contactPhone : null;
        }
        if ($schema->hasTable('eb_tenants') && $schema->hasColumn('eb_tenants', 'address_line1')) {
            $insert['address_line1'] = $addressLine1 !== '' ? $addressLine1 : null;
        }
        if ($schema->hasTable('eb_tenants') && $schema->hasColumn('eb_tenants', 'address_line2')) {
            $insert['address_line2'] = $addressLine2 !== '' ? $addressLine2 : null;
        }
        if ($schema->hasTable('eb_tenants') && $schema->hasColumn('eb_tenants', 'city')) {
            $insert['city'] = $city !== '' ? $city : null;
        }
        if ($schema->hasTable('eb_tenants') && $schema->hasColumn('eb_tenants', 'state')) {
            $insert['state'] = $state !== '' ? $state : null;
        }
        if ($schema->hasTable('eb_tenants') && $schema->hasColumn('eb_tenants', 'postal_code')) {
            $insert['postal_code'] = $postalCode !== '' ? $postalCode : null;
        }
        if ($schema->hasTable('eb_tenants') && $schema->hasColumn('eb_tenants', 'country')) {
            $insert['country'] = $country;
        }

        try {
            $tenantId = (int)Capsule::table('eb_tenants')->insertGetId($insert);
            $tenantPublicId = trim((string)($insert['public_id'] ?? ''));
            if ($tenantPublicId === '') {
                $tenantPublicId = (string)(Capsule::table('eb_tenants')->where('id', $tenantId)->value('public_id') ?? '');
                $tenantPublicId = trim($tenantPublicId);
            }

            try {
                $createAdminRequested = isset($post['create_admin']) && (string)$post['create_admin'] === '1';
                $adminEmail = strtolower(trim((string)($post['admin_email'] ?? '')));
                $adminName = trim((string)($post['admin_name'] ?? ''));
                $autoPasswordFlag = (string)($post['auto_password'] ?? '1');
                $adminPassword = (string)($post['admin_password'] ?? '');
                $adminPasswordShort = false;

                if (
                    $createAdminRequested
                    && $adminEmail !== ''
                    && $adminName !== ''
                    && Capsule::schema()->hasTable('eb_tenant_users')
                    && filter_var($adminEmail, FILTER_VALIDATE_EMAIL)
                ) {
                    $hasAdmin = (bool)Capsule::table('eb_tenant_users')
                        ->where('tenant_id', $tenantId)
                        ->where('role', 'admin')
                        ->exists();
                    if (!$hasAdmin) {
                        $plainPassword = null;
                        if ($autoPasswordFlag === '0') {
                            if (strlen($adminPassword) >= 8) {
                                $plainPassword = $adminPassword;
                            } else {
                                $adminPasswordShort = true;
                            }
                        } else {
                            try {
                                $plainPassword = bin2hex(random_bytes(16));
                            } catch (\Throwable $___) {
                                $plainPassword = sha1((string)microtime(true) . '-' . mt_rand());
                            }
                        }
                        if ($plainPassword !== null) {
                            Capsule::table('eb_tenant_users')->insertGetId([
                                'tenant_id' => $tenantId,
                                'email' => $adminEmail,
                                'password_hash' => password_hash($plainPassword, PASSWORD_DEFAULT),
                                'name' => $adminName,
                                'role' => 'admin',
                                'status' => 'active',
                                'created_at' => Capsule::raw('NOW()'),
                                'updated_at' => Capsule::raw('NOW()'),
                            ]);
                        }
                    }
                }
            } catch (\Throwable $___) {
                // Best-effort: tenant row already exists.
            }

            $acct = trim((string)($msp->stripe_connect_id ?? ''));
            if ($acct !== '') {
                try {
                    (new StripeService())->ensureStripeCustomerFor($tenantId, $acct);
                } catch (\Throwable $stripeEx) {
                    if (function_exists('logModuleCall')) {
                        try {
                            logModuleCall(
                                'eazybackup',
                                'ph-tenant-create-stripe-customer-warning',
                                ['tenant_id' => $tenantId],
                                $stripeEx->getMessage()
                            );
                        } catch (\Throwable $__) {
                            // ignore
                        }
                    }
                }
            }

            if ($tenantPublicId !== '') {
                $redirectQuery = 'notice=created';
                if (!empty($adminPasswordShort)) {
                    $redirectQuery .= '&error=portal_admin_password_short';
                }
                eb_ph_tenant_redirect($vars, $tenantPublicId, $redirectQuery);
            }
            $redirectQuery = 'notice=created';
            if (!empty($adminPasswordShort)) {
                $redirectQuery .= '&error=portal_admin_password_short';
            }
            eb_ph_tenants_redirect($vars, $redirectQuery);
        } catch (\Throwable $__) {
            eb_ph_tenants_redirect($vars, 'error=create_failed');
        }
    }

    $rowsCol = Capsule::table('eb_tenants')->where('msp_id', (int)$msp->id)
        ->where('status', '!=', 'deleted')
        ->orderBy('created_at', 'desc')
        ->get();
    $rows = [];
    foreach ($rowsCol as $row) {
        $rows[] = (array)$row;
    }

    // Stripe Connect status for onboarding CTA/alerts on tenant list page.
    $connect = [ 'hasAccount' => false, 'chargesEnabled' => false, 'payoutsEnabled' => false, 'detailsSubmitted' => false ];
    $connectDue = [];
    try {
        if ($msp && (string)($msp->stripe_connect_id ?? '') !== '') {
            $svc = new StripeService();
            $acct = $svc->retrieveAccount((string)$msp->stripe_connect_id);
            $connect = [
                'hasAccount' => true,
                'chargesEnabled' => (bool)($acct['charges_enabled'] ?? false),
                'payoutsEnabled' => (bool)($acct['payouts_enabled'] ?? false),
                'detailsSubmitted' => (bool)($acct['details_submitted'] ?? false),
            ];
            $reqs = $acct['requirements'] ?? [];
            if (is_array($reqs) && isset($reqs['currently_due']) && is_array($reqs['currently_due'])) {
                $connectDue = $reqs['currently_due'];
            }
        }
    } catch (\Throwable $__) { /* ignore */ }

    $onboardError = isset($_GET['onboard_error']) && $_GET['onboard_error'] !== '';
    $onboardSuccess = isset($_GET['onboard_success']) && $_GET['onboard_success'] !== '';
    $onboardRefresh = isset($_GET['onboard_refresh']) && $_GET['onboard_refresh'] !== '';

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
            'legacy_notice' => (string)($_GET['legacy'] ?? ''),
            'error' => (string)($_GET['error'] ?? ''),
            'connect' => $connect,
            'connect_due' => $connectDue,
            'onboardError' => $onboardError,
            'onboardSuccess' => $onboardSuccess,
            'onboardRefresh' => $onboardRefresh,
        ],
    ];
}

function eb_ph_tenant_detail(array $vars)
{
    [$clientId, $msp, $tenantId, $tenant] = eb_ph_tenant_require_owned($vars);
    $tenantPublicId = trim((string)($tenant->public_id ?? ''));

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eb_save_tenant'])) {
        $post = eb_ph_tenants_strip_infra_fields((array)$_POST);
        $token = (string)($_POST['token'] ?? '');
        eb_ph_tenants_require_csrf_or_redirect($vars, $token, $tenantPublicId);

        $name = trim((string)($post['name'] ?? ''));
        $slug = eb_ph_tenants_slugify((string)($post['slug'] ?? ''));
        $contactEmail = strtolower(trim((string)($post['contact_email'] ?? '')));
        $contactName = trim((string)($post['contact_name'] ?? ''));
        $contactPhone = trim((string)($post['contact_phone'] ?? ''));
        $addressLine1 = trim((string)($post['address_line1'] ?? ''));
        $addressLine2 = trim((string)($post['address_line2'] ?? ''));
        $city = trim((string)($post['city'] ?? ''));
        $state = trim((string)($post['state'] ?? ''));
        $postalCode = trim((string)($post['postal_code'] ?? ''));
        $countryRaw = (string)($post['country'] ?? '');
        $countryRaw = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $countryRaw) ?? $countryRaw;
        if (function_exists('mb_convert_kana')) {
            $countryRaw = mb_convert_kana($countryRaw, 'as', 'UTF-8');
        }
        $countryRaw = preg_replace('/\s+/u', '', trim($countryRaw)) ?? trim($countryRaw);
        $countryRaw = preg_replace('/[^A-Za-z]/', '', $countryRaw) ?? $countryRaw;
        $country = $countryRaw !== '' ? strtoupper($countryRaw) : null;
        $portalAdmin = eb_ph_tenant_primary_admin($tenantId);
        $portalAdminEmail = strtolower(trim((string)($post['portal_admin_email'] ?? '')));
        $portalAdminName = trim((string)($post['portal_admin_name'] ?? ''));
        $portalAdminStatus = strtolower(trim((string)($post['portal_admin_status'] ?? 'active')));
        $portalAdminPasswordMode = trim((string)($post['portal_admin_password_mode'] ?? 'keep'));
        $portalAdminPassword = (string)($post['portal_admin_password'] ?? '');
        $portalAdminRequested = $portalAdminEmail !== ''
            || $portalAdminName !== ''
            || $portalAdminPassword !== ''
            || isset($post['portal_admin_status'])
            || isset($post['portal_admin_password_mode']);
        if ($name === '' || $slug === '') {
            eb_ph_tenant_redirect($vars, $tenantPublicId, 'error=missing_fields');
        }
        if (!eb_ph_tenants_is_valid_slug($slug)) {
            eb_ph_tenant_redirect($vars, $tenantPublicId, 'error=invalid_slug');
        }
        if ($contactEmail !== '' && !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
            eb_ph_tenant_redirect($vars, $tenantPublicId, 'error=invalid_email');
        }
        if ($country !== null && $country !== '' && !preg_match('/^[A-Z]{2}$/', $country)) {
            eb_ph_tenant_redirect($vars, $tenantPublicId, 'error=invalid_country');
        }
        if (eb_ph_tenants_existing_slug_owner((int)$msp->id, $slug, $tenantId) !== null) {
            eb_ph_tenant_redirect($vars, $tenantPublicId, 'error=slug_taken');
        }
        if ($portalAdminEmail !== '' && !filter_var($portalAdminEmail, FILTER_VALIDATE_EMAIL)) {
            eb_ph_tenant_redirect($vars, $tenantPublicId, 'error=invalid_portal_admin_email');
        }
        if (!in_array($portalAdminStatus, ['active', 'disabled'], true)) {
            $portalAdminStatus = 'active';
        }
        if (!in_array($portalAdminPasswordMode, ['keep', 'manual'], true)) {
            $portalAdminPasswordMode = $portalAdmin['exists'] ? 'keep' : 'manual';
        }

        $savePortalAdmin = $portalAdmin['available'] && (
            $portalAdmin['exists']
            || $portalAdminEmail !== ''
            || $portalAdminName !== ''
            || $portalAdminPassword !== ''
        );
        if (!$portalAdmin['available'] && $portalAdminRequested) {
            eb_ph_tenant_redirect($vars, $tenantPublicId, 'error=portal_admin_unavailable');
        }
        if ($savePortalAdmin && ($portalAdminEmail === '' || $portalAdminName === '')) {
            eb_ph_tenant_redirect($vars, $tenantPublicId, 'error=portal_admin_missing_fields');
        }
        if ($savePortalAdmin && !$portalAdmin['exists'] && $portalAdminPasswordMode !== 'manual') {
            eb_ph_tenant_redirect($vars, $tenantPublicId, 'error=portal_admin_password_required');
        }
        if ($portalAdminPasswordMode === 'manual' && strlen($portalAdminPassword) < 8) {
            eb_ph_tenant_redirect($vars, $tenantPublicId, 'error=portal_admin_password_short');
        }

        try {
            $status = eb_ph_tenants_normalize_status((string)($post['status'] ?? 'active'));
            if ($status === 'deleted') {
                $status = 'active';
            }
            $update = [
                'name' => $name,
                'slug' => $slug,
                'contact_email' => $contactEmail !== '' ? $contactEmail : null,
                'status' => $status,
                'updated_at' => Capsule::raw('NOW()'),
            ];
            $schema = Capsule::schema();
            if ($schema->hasTable('eb_tenants') && $schema->hasColumn('eb_tenants', 'contact_name')) {
                $update['contact_name'] = $contactName !== '' ? $contactName : null;
            }
            if ($schema->hasTable('eb_tenants') && $schema->hasColumn('eb_tenants', 'contact_phone')) {
                $update['contact_phone'] = $contactPhone !== '' ? $contactPhone : null;
            }
            if ($schema->hasTable('eb_tenants') && $schema->hasColumn('eb_tenants', 'address_line1')) {
                $update['address_line1'] = $addressLine1 !== '' ? $addressLine1 : null;
            }
            if ($schema->hasTable('eb_tenants') && $schema->hasColumn('eb_tenants', 'address_line2')) {
                $update['address_line2'] = $addressLine2 !== '' ? $addressLine2 : null;
            }
            if ($schema->hasTable('eb_tenants') && $schema->hasColumn('eb_tenants', 'city')) {
                $update['city'] = $city !== '' ? $city : null;
            }
            if ($schema->hasTable('eb_tenants') && $schema->hasColumn('eb_tenants', 'state')) {
                $update['state'] = $state !== '' ? $state : null;
            }
            if ($schema->hasTable('eb_tenants') && $schema->hasColumn('eb_tenants', 'postal_code')) {
                $update['postal_code'] = $postalCode !== '' ? $postalCode : null;
            }
            if ($schema->hasTable('eb_tenants') && $schema->hasColumn('eb_tenants', 'country')) {
                $update['country'] = $country;
            }
            Capsule::table('eb_tenants')->where('id', $tenantId)->where('msp_id', (int)$msp->id)->update($update);

            $stripeCustomerSyncWarning = false;
            $stripeCustomerId = trim((string)($tenant->stripe_customer_id ?? ''));
            $stripeConnectId = trim((string)($msp->stripe_connect_id ?? ''));
            if ($stripeCustomerId !== '' && $stripeConnectId !== '') {
                try {
                    (new StripeService())->updateCustomer(
                        $stripeCustomerId,
                        [
                            'name' => $name,
                            'email' => $contactEmail,
                            'phone' => $contactPhone,
                            'address' => [
                                'line1' => $addressLine1,
                                'line2' => $addressLine2,
                                'city' => $city,
                                'state' => $state,
                                'postal_code' => $postalCode,
                                'country' => $country !== null && $country !== '' ? $country : '',
                            ],
                        ],
                        $stripeConnectId
                    );
                } catch (\Throwable $stripeEx) {
                    if (function_exists('logModuleCall')) {
                        try {
                            logModuleCall(
                                'eazybackup',
                                'ph-tenant-profile-stripe-sync-warning',
                                ['tenant_id' => $tenantId, 'stripe_customer_id' => $stripeCustomerId],
                                $stripeEx->getMessage()
                            );
                        } catch (\Throwable $__) {
                            // ignore
                        }
                    }
                    $stripeCustomerSyncWarning = true;
                }
            }

            if ($savePortalAdmin) {
                $portalAdminPayload = [
                    'email' => $portalAdminEmail,
                    'name' => $portalAdminName,
                    'status' => $portalAdminStatus,
                    'updated_at' => Capsule::raw('NOW()'),
                ];
                if ($portalAdminPasswordMode === 'manual') {
                    $portalAdminPayload['password_hash'] = password_hash($portalAdminPassword, PASSWORD_DEFAULT);
                }

                if ($portalAdmin['exists']) {
                    Capsule::table('eb_tenant_users')
                        ->where('id', (int)$portalAdmin['id'])
                        ->where('tenant_id', $tenantId)
                        ->update($portalAdminPayload);
                } else {
                    Capsule::table('eb_tenant_users')->insertGetId([
                        'tenant_id' => $tenantId,
                        'email' => $portalAdminEmail,
                        'password_hash' => password_hash($portalAdminPassword, PASSWORD_DEFAULT),
                        'name' => $portalAdminName,
                        'role' => 'admin',
                        'status' => $portalAdminStatus,
                        'created_at' => Capsule::raw('NOW()'),
                        'updated_at' => Capsule::raw('NOW()'),
                    ]);
                }
            }

            $redirectQuery = 'notice=saved';
            if ($stripeCustomerSyncWarning) {
                $redirectQuery .= '&error=stripe_sync_warning';
            }
            eb_ph_tenant_redirect($vars, $tenantPublicId, $redirectQuery);
        } catch (\Throwable $__) {
            eb_ph_tenant_redirect($vars, $tenantPublicId, 'error=save_failed');
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eb_delete_tenant'])) {
        $token = (string)($_POST['token'] ?? '');
        eb_ph_tenants_require_csrf_or_redirect($vars, $token, $tenantPublicId);

        try {
            $blockers = eb_ph_tenants_delete_blockers($tenantId);
        } catch (\Throwable $__) {
            eb_ph_tenant_redirect($vars, $tenantPublicId, 'error=tenant_ref_check_failed');
        }
        if (!empty($blockers)) {
            eb_ph_tenant_redirect($vars, $tenantPublicId, 'error=tenant_in_use');
        }

        try {
            Capsule::table('eb_tenants')->where('id', $tenantId)->where('msp_id', (int)$msp->id)->update([
                'status' => 'deleted',
                'updated_at' => Capsule::raw('NOW()'),
            ]);
            eb_ph_tenants_redirect($vars, 'notice=deleted');
        } catch (\Throwable $__) {
            eb_ph_tenant_redirect($vars, $tenantPublicId, 'error=delete_failed');
        }
    }

    $tenant = Capsule::table('eb_tenants')
        ->where('id', $tenantId)
        ->where('msp_id', (int)$msp->id)
        ->where('status', '!=', 'deleted')
        ->first();
    if (!$tenant) {
        eb_ph_tenants_redirect($vars, 'notice=deleted');
    }

    $portalAdmin = eb_ph_tenant_primary_admin($tenantId);

    return eb_ph_tenant_shell_response($vars, (array)$msp, (array)$tenant, 'profile', [
        'portal_admin' => $portalAdmin,
    ]);
}

function eb_ph_tenant_storage_users(array $vars)
{
    [$clientId, $msp, $tenantId, $tenant] = eb_ph_tenant_require_owned($vars);

    $rows = [];
    $error = '';
    try {
        if (!Capsule::schema()->hasTable('s3_backup_users')) {
            $error = 'storage_users_table_missing';
        } else {
            $result = Capsule::table('s3_backup_users')
                ->where('client_id', $clientId)
                ->where('tenant_id', $tenantId)
                ->orderBy('username', 'asc')
                ->orderBy('id', 'asc')
                ->get([
                    'id',
                    'username',
                    'email',
                    'status',
                    'created_at',
                    'updated_at',
                ]);
            foreach ($result as $row) {
                $rows[] = (array)$row;
            }
        }
    } catch (\Throwable $__) {
        $error = 'storage_users_query_failed';
    }

    return eb_ph_tenant_shell_response($vars, (array)$msp, (array)$tenant, 'storage_users', [
        'storage_users' => $rows,
        'storage_users_error' => $error,
    ]);
}

