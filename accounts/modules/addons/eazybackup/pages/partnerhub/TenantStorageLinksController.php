<?php

use WHMCS\Database\Capsule;

function eb_ph_tenant_storage_links_require_context(): array
{
    if (!isset($_SESSION['uid']) || (int) $_SESSION['uid'] <= 0) {
        return [0, null, 'auth'];
    }

    $clientId = (int) $_SESSION['uid'];
    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id', $clientId)->first();
    if (!$msp) {
        return [$clientId, null, 'msp'];
    }

    return [$clientId, $msp, null];
}

function eb_tenant_storage_links_is_assignable_tenant_status(string $status): bool
{
    $status = strtolower(trim($status));
    return in_array($status, ['queued', 'building', 'active', 'failed', 'suspended'], true);
}

function eb_tenant_storage_links_resolve_tenant_for_client(int $clientId, int $canonicalTenantId)
{
    if ($clientId <= 0 || $canonicalTenantId <= 0) {
        return null;
    }

    $tenant = Capsule::table('eb_whitelabel_tenants')->where('id', $canonicalTenantId)->where('client_id', $clientId)->first();
    if (!$tenant) {
        return null;
    }
    if (!eb_tenant_storage_links_is_assignable_tenant_status((string) ($tenant->status ?? ''))) {
        return null;
    }
    return $tenant;
}

function eb_tenant_storage_links_resolve_tenant_for_client_by_public_id(int $clientId, string $canonicalTenantPublicId)
{
    $canonicalTenantPublicId = trim($canonicalTenantPublicId);
    if ($clientId <= 0 || $canonicalTenantPublicId === '') {
        return null;
    }

    if (!Capsule::schema()->hasColumn('eb_whitelabel_tenants', 'public_id')) {
        return null;
    }

    $tenant = Capsule::table('eb_whitelabel_tenants')
        ->where('public_id', $canonicalTenantPublicId)
        ->where('client_id', $clientId)
        ->first();
    if (!$tenant) {
        return null;
    }
    if (!eb_tenant_storage_links_is_assignable_tenant_status((string) ($tenant->status ?? ''))) {
        return null;
    }

    return $tenant;
}

function eb_tenant_storage_links_ensure_canonical_tenant_public_id(object $canonicalTenant): string
{
    $publicId = trim((string) ($canonicalTenant->public_id ?? ''));
    if ($publicId !== '') {
        return $publicId;
    }

    if (
        empty($canonicalTenant->id)
        || !Capsule::schema()->hasTable('eb_whitelabel_tenants')
        || !Capsule::schema()->hasColumn('eb_whitelabel_tenants', 'public_id')
    ) {
        throw new \RuntimeException('canonical_tenant_public_id_missing');
    }

    $publicId = eazybackup_generate_ulid();
    Capsule::table('eb_whitelabel_tenants')
        ->where('id', (int) $canonicalTenant->id)
        ->where(function ($query) {
            $query->whereNull('public_id')
                ->orWhere('public_id', '');
        })
        ->update(['public_id' => $publicId]);

    return $publicId;
}

function eb_tenant_storage_links_storage_tenant_slug(string $canonicalTenantPublicId): string
{
    return 'eb-link-' . strtolower(trim($canonicalTenantPublicId));
}

function eb_tenant_storage_links_storage_tenant_name(object $canonicalTenant, string $canonicalTenantPublicId): string
{
    $name = trim((string) ($canonicalTenant->subdomain ?? ''));
    if ($name === '') {
        $name = trim((string) ($canonicalTenant->fqdn ?? ''));
    }
    if ($name === '') {
        $name = 'Tenant ' . substr($canonicalTenantPublicId, 0, 8);
    }

    return $name;
}

function eb_tenant_storage_links_resolve_tenant_public_id_for_client(int $clientId, string $canonicalTenantReference): ?string
{
    $canonicalTenantReference = trim($canonicalTenantReference);
    if ($clientId <= 0 || $canonicalTenantReference === '') {
        return null;
    }

    $tenant = eb_tenant_storage_links_resolve_tenant_for_client_by_public_id($clientId, $canonicalTenantReference);
    if ($tenant && trim((string) ($tenant->public_id ?? '')) !== '') {
        return trim((string) $tenant->public_id);
    }

    if ((int) $canonicalTenantReference <= 0) {
        return null;
    }

    $tenant = eb_tenant_storage_links_resolve_tenant_for_client($clientId, (int) $canonicalTenantReference);
    if (!$tenant) {
        return null;
    }

    $publicId = trim((string) ($tenant->public_id ?? ''));
    return $publicId !== '' ? $publicId : null;
}

function eb_tenant_storage_links_resolve_or_create_storage_tenant_id(int $clientId, int $canonicalTenantId): int
{
    if ($clientId <= 0 || $canonicalTenantId <= 0) {
        throw new \RuntimeException('invalid_tenant_mapping_input');
    }

    $canonicalTenant = eb_tenant_storage_links_resolve_tenant_for_client($clientId, $canonicalTenantId);
    if (!$canonicalTenant) {
        throw new \RuntimeException('canonical_tenant_not_found');
    }

    $canonicalTenantPublicId = eb_tenant_storage_links_ensure_canonical_tenant_public_id($canonicalTenant);
    $slug = eb_tenant_storage_links_storage_tenant_slug($canonicalTenantPublicId);
    $legacySlug = 'eb-canonical-' . $canonicalTenantId;
    $name = eb_tenant_storage_links_storage_tenant_name($canonicalTenant, $canonicalTenantPublicId);

    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id', $clientId)->first();
    if (!$msp) {
        throw new \RuntimeException('msp_not_found');
    }
    $mspId = (int) $msp->id;

    try {
        if (!Capsule::schema()->hasTable('eb_tenants')) {
            throw new \RuntimeException('storage_tenant_table_missing');
        }
    } catch (\Throwable $e) {
        throw new \RuntimeException('storage_tenant_table_missing');
    }

    $existing = Capsule::table('eb_tenants')
        ->where('msp_id', $mspId)
        ->where(function ($query) use ($slug, $legacySlug) {
            $query->where('slug', $slug)
                ->orWhere('slug', $legacySlug);
        })
        ->first();
    if ($existing) {
        $updates = ['updated_at' => Capsule::raw('NOW()')];
        if ((string) ($existing->status ?? '') === 'deleted') {
            $updates['status'] = 'active';
        }
        if (trim((string) ($existing->slug ?? '')) !== $slug) {
            $updates['slug'] = $slug;
        }
        if (
            trim((string) ($existing->name ?? '')) === ''
            || preg_match('/^Canonical Tenant #\d+$/', trim((string) ($existing->name ?? '')))
        ) {
            $updates['name'] = $name;
        }
        Capsule::table('eb_tenants')->where('id', (int) $existing->id)->update($updates);
        if (
            Capsule::schema()->hasTable('eb_tenants')
            && Capsule::schema()->hasColumn('eb_tenants', 'public_id')
            && trim((string) ($existing->public_id ?? '')) === ''
        ) {
            $publicId = eazybackup_generate_ulid();
            Capsule::table('eb_tenants')
                ->where('id', (int) $existing->id)
                ->where(function ($query) {
                    $query->whereNull('public_id')
                        ->orWhere('public_id', '');
                })
                ->update(['public_id' => $publicId]);
        }
        return (int) $existing->id;
    }

    $insert = [
        'msp_id' => $mspId,
        'name' => $name,
        'slug' => $slug,
        'status' => 'active',
        'created_at' => Capsule::raw('NOW()'),
        'updated_at' => Capsule::raw('NOW()'),
    ];
    if (Capsule::schema()->hasTable('eb_tenants') && Capsule::schema()->hasColumn('eb_tenants', 'public_id')) {
        $insert['public_id'] = eazybackup_generate_ulid();
    }

    try {
        return (int) Capsule::table('eb_tenants')->insertGetId($insert);
    } catch (\Throwable $e) {
        $raced = Capsule::table('eb_tenants')
            ->where('msp_id', $mspId)
            ->where('slug', $slug)
            ->first();
        if ($raced) {
            return (int) $raced->id;
        }
        throw new \RuntimeException('storage_tenant_create_failed');
    }
}

function eb_tenant_storage_identifier_for_user(int $userId): string
{
    return 's3_backup_user:' . max(0, $userId);
}

function eb_tenant_storage_links_storage_identifier_belongs_to_client(int $clientId, string $storageIdentifier): bool
{
    if ($clientId <= 0) {
        return false;
    }

    $storageIdentifier = trim($storageIdentifier);
    if ($storageIdentifier === '') {
        return false;
    }

    if (!preg_match('/^s3_backup_user:(\d+)$/', $storageIdentifier, $matches)) {
        // Fail closed: only s3_backup_user:<id> identifiers are supported in Task 6 path.
        return false;
    }

    $userId = (int) ($matches[1] ?? 0);
    if ($userId <= 0) {
        return false;
    }

    try {
        if (!Capsule::schema()->hasTable('s3_backup_users')) {
            return false;
        }
        return Capsule::table('s3_backup_users')->where('id', $userId)->where('client_id', $clientId)->exists();
    } catch (\Throwable $e) {
        return false;
    }
}

function eb_tenant_storage_links_get_current_link_for_identifier(int $clientId, string $storageIdentifier)
{
    if ($clientId <= 0) {
        return null;
    }

    $storageIdentifier = trim($storageIdentifier);
    if ($storageIdentifier === '') {
        return null;
    }

    try {
        if (!Capsule::schema()->hasTable('eb_tenant_storage_links') || !Capsule::schema()->hasTable('eb_whitelabel_tenants')) {
            return null;
        }
    } catch (\Throwable $e) {
        return null;
    }

    $link = Capsule::table('eb_tenant_storage_links as l')
        ->join('eb_whitelabel_tenants as t', 't.id', '=', 'l.tenant_id')
        ->where('t.client_id', $clientId)
        ->where('l.storage_identifier', $storageIdentifier)
        ->select([
            'l.tenant_id',
            'l.storage_identifier',
            't.status as tenant_status',
        ])
        ->orderBy('l.updated_at', 'desc')
        ->orderBy('l.id', 'desc')
        ->first();

    if (!$link) {
        return null;
    }
    if (!eb_tenant_storage_links_is_assignable_tenant_status((string) ($link->tenant_status ?? ''))) {
        return null;
    }

    return $link;
}

function eb_tenant_storage_links_infer_canonical_tenant_id_from_storage_tenant_id(int $clientId, ?int $storageTenantId): ?int
{
    if ($clientId <= 0 || $storageTenantId === null || $storageTenantId <= 0) {
        return null;
    }

    $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id', $clientId)->first();
    if (!$msp) {
        return null;
    }
    $mspId = (int) $msp->id;

    try {
        if (!Capsule::schema()->hasTable('eb_tenants')) {
            return null;
        }
    } catch (\Throwable $e) {
        return null;
    }

    $storageTenant = Capsule::table('eb_tenants')
        ->where('id', (int) $storageTenantId)
        ->where('msp_id', $mspId)
        ->first([
            'id',
            'slug',
            'status',
        ]);
    if (!$storageTenant) {
        return null;
    }

    $slug = trim((string) ($storageTenant->slug ?? ''));
    if ($slug === '') {
        return null;
    }

    $matches = [];
    if (preg_match('/^eb-link-([0-9a-z]{26})$/', $slug, $matches)) {
        $canonicalTenant = eb_tenant_storage_links_resolve_tenant_for_client_by_public_id($clientId, strtoupper((string) ($matches[1] ?? '')));
        return $canonicalTenant ? (int) $canonicalTenant->id : null;
    }

    if (!preg_match('/^eb-canonical-(\d+)$/', $slug, $matches)) {
        return null;
    }

    $canonicalTenantId = (int) ($matches[1] ?? 0);
    if ($canonicalTenantId <= 0) {
        return null;
    }

    $canonicalTenant = eb_tenant_storage_links_resolve_tenant_for_client($clientId, $canonicalTenantId);
    if (!$canonicalTenant) {
        return null;
    }

    return $canonicalTenantId;
}

function eb_tenant_storage_links_upsert_for_client(int $clientId, string $storageIdentifier, ?int $canonicalTenantId): array
{
    $storageIdentifier = trim($storageIdentifier);
    if ($clientId <= 0 || $storageIdentifier === '') {
        return ['ok' => false, 'message' => 'invalid'];
    }
    if (strlen($storageIdentifier) > 191) {
        return ['ok' => false, 'message' => 'storage_identifier_too_long'];
    }

    try {
        if (!Capsule::schema()->hasTable('eb_tenant_storage_links') || !Capsule::schema()->hasTable('eb_whitelabel_tenants')) {
            return ['ok' => false, 'message' => 'schema_missing'];
        }
    } catch (\Throwable $e) {
        return ['ok' => false, 'message' => 'schema_check_failed'];
    }

    if ($canonicalTenantId !== null) {
        $canonicalTenant = eb_tenant_storage_links_resolve_tenant_for_client($clientId, $canonicalTenantId);
        if (!$canonicalTenant) {
            return ['ok' => false, 'message' => 'canonical_tenant_not_found'];
        }
    }

    if (!eb_tenant_storage_links_storage_identifier_belongs_to_client($clientId, $storageIdentifier)) {
        return ['ok' => false, 'message' => 'storage_identifier_not_owned'];
    }

    try {
        Capsule::connection()->transaction(function () use ($storageIdentifier, $canonicalTenantId): void {
            if ($canonicalTenantId === null) {
                Capsule::table('eb_tenant_storage_links')
                    ->where('storage_identifier', $storageIdentifier)
                    ->delete();
                return;
            }

            $now = date('Y-m-d H:i:s');
            $existingLink = Capsule::table('eb_tenant_storage_links')
                ->where('storage_identifier', $storageIdentifier)
                ->first(['id']);
            if ($existingLink) {
                Capsule::table('eb_tenant_storage_links')
                    ->where('id', (int) $existingLink->id)
                    ->update([
                        'tenant_id' => $canonicalTenantId,
                        'link_status' => 'active',
                        'updated_at' => $now,
                    ]);
                return;
            }

            try {
                Capsule::table('eb_tenant_storage_links')->insert([
                    'tenant_id' => $canonicalTenantId,
                    'storage_identifier' => $storageIdentifier,
                    'link_status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } catch (\Throwable $e) {
                Capsule::table('eb_tenant_storage_links')
                    ->where('storage_identifier', $storageIdentifier)
                    ->update([
                        'tenant_id' => $canonicalTenantId,
                        'link_status' => 'active',
                        'updated_at' => $now,
                    ]);
            }
        });
    } catch (\Throwable $e) {
        return ['ok' => false, 'message' => 'write_failed'];
    }

    return ['ok' => true, 'tenant_id' => $canonicalTenantId];
}

function eb_ph_tenant_storage_links_list(array $vars): void
{
    header('Content-Type: application/json');

    [$clientId, $msp, $error] = eb_ph_tenant_storage_links_require_context();
    if ($error === 'auth') {
        echo json_encode(['status' => 'error', 'message' => 'auth']);
        return;
    }
    if ($error === 'msp') {
        echo json_encode(['status' => 'error', 'message' => 'msp']);
        return;
    }

    try {
        $rows = Capsule::table('eb_whitelabel_tenants')
            ->where('client_id', $clientId)
            ->whereNotIn('status', ['deleted', 'removing'])
            ->orderBy('subdomain', 'asc')
            ->orderBy('id', 'asc')
            ->get([
                'id',
                'public_id',
                'subdomain',
                'fqdn',
                'status',
            ]);
    } catch (\Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => 'query_failed']);
        return;
    }

    $tenants = [];
    foreach ($rows as $row) {
        if (!eb_tenant_storage_links_is_assignable_tenant_status((string) ($row->status ?? ''))) {
            continue;
        }
        $name = trim((string) ($row->subdomain ?? ''));
        if ($name === '') {
            $name = trim((string) ($row->fqdn ?? ''));
        }
        if ($name === '') {
            $name = 'Tenant';
        }
        $publicId = trim((string) ($row->public_id ?? ''));
        if ($publicId === '') {
            continue;
        }
        $tenants[] = [
            'id' => $publicId,
            'public_id' => $publicId,
            'name' => $name,
            'subdomain' => (string) ($row->subdomain ?? ''),
            'fqdn' => (string) ($row->fqdn ?? ''),
            'status' => (string) ($row->status ?? ''),
        ];
    }

    echo json_encode([
        'status' => 'success',
        'tenants' => $tenants,
    ]);
}

function eb_ph_tenant_storage_links_write(array $vars): void
{
    header('Content-Type: application/json');

    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        echo json_encode(['status' => 'error', 'message' => 'method']);
        return;
    }

    [$clientId, $msp, $error] = eb_ph_tenant_storage_links_require_context();
    if ($error === 'auth') {
        echo json_encode(['status' => 'error', 'message' => 'auth']);
        return;
    }
    if ($error === 'msp') {
        echo json_encode(['status' => 'error', 'message' => 'msp']);
        return;
    }

    $token = (string) ($_POST['token'] ?? '');
    if ($token === '' || !function_exists('check_token')) {
        echo json_encode(['status' => 'error', 'message' => 'csrf']);
        return;
    }
    try {
        if (!check_token('plain', $token)) {
            echo json_encode(['status' => 'error', 'message' => 'csrf']);
            return;
        }
    } catch (\Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => 'csrf']);
        return;
    }

    $storageIdentifier = trim((string) ($_POST['storage_identifier'] ?? ''));
    if ($storageIdentifier === '') {
        echo json_encode(['status' => 'error', 'message' => 'storage_identifier_required']);
        return;
    }

    if (!eb_tenant_storage_links_storage_identifier_belongs_to_client($clientId, $storageIdentifier)) {
        echo json_encode(['status' => 'error', 'message' => 'storage_identifier_not_owned']);
        return;
    }

    $canonicalTenantIdRaw = trim((string) ($_POST['canonical_tenant_id'] ?? ''));
    $canonicalTenantId = null;
    $canonicalTenantPublicId = null;
    if ($canonicalTenantIdRaw !== '' && $canonicalTenantIdRaw !== 'direct') {
        $canonicalTenant = eb_tenant_storage_links_resolve_tenant_for_client_by_public_id($clientId, $canonicalTenantIdRaw);
        if (!$canonicalTenant) {
            echo json_encode(['status' => 'error', 'message' => 'invalid_tenant']);
            return;
        }
        $canonicalTenantId = (int) $canonicalTenant->id;
        $canonicalTenantPublicId = trim((string) ($canonicalTenant->public_id ?? ''));
    }

    $result = eb_tenant_storage_links_upsert_for_client($clientId, $storageIdentifier, $canonicalTenantId);
    if (empty($result['ok'])) {
        echo json_encode(['status' => 'error', 'message' => (string) ($result['message'] ?? 'write_failed')]);
        return;
    }

    echo json_encode([
        'status' => 'success',
        'storage_identifier' => $storageIdentifier,
        'canonical_tenant_id' => $canonicalTenantPublicId,
    ]);
}
