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

function eb_tenant_storage_links_resolve_tenant_for_client(int $clientId, int $canonicalTenantId)
{
    if ($clientId <= 0 || $canonicalTenantId <= 0) {
        return null;
    }

    return Capsule::table('eb_whitelabel_tenants')->where('id', $canonicalTenantId)->where('client_id', $clientId)->first();
}

function eb_tenant_storage_identifier_for_user(int $userId): string
{
    return 's3_backup_user:' . max(0, $userId);
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

    try {
        Capsule::connection()->transaction(function () use ($clientId, $storageIdentifier, $canonicalTenantId): void {
            $ownedTenantIds = Capsule::table('eb_whitelabel_tenants')
                ->where('client_id', $clientId)
                ->pluck('id')
                ->map(static function ($id): int {
                    return (int) $id;
                })
                ->all();

            if ($ownedTenantIds !== []) {
                Capsule::table('eb_tenant_storage_links as l')
                    ->whereIn('l.tenant_id', $ownedTenantIds)
                    ->where('l.storage_identifier', $storageIdentifier)
                    ->delete();
            }

            if ($canonicalTenantId !== null) {
                Capsule::table('eb_tenant_storage_links')->insert([
                    'tenant_id' => $canonicalTenantId,
                    'storage_identifier' => $storageIdentifier,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
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
            ->where('status', '!=', 'deleted')
            ->orderBy('subdomain', 'asc')
            ->orderBy('id', 'asc')
            ->get([
                'id',
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
        $name = trim((string) ($row->subdomain ?? ''));
        if ($name === '') {
            $name = trim((string) ($row->fqdn ?? ''));
        }
        if ($name === '') {
            $name = 'Tenant #' . (int) $row->id;
        }
        $tenants[] = [
            'id' => (int) $row->id,
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
    if (function_exists('check_token')) {
        try {
            if (!check_token('plain', $token)) {
                echo json_encode(['status' => 'error', 'message' => 'csrf']);
                return;
            }
        } catch (\Throwable $e) {
            echo json_encode(['status' => 'error', 'message' => 'csrf']);
            return;
        }
    }

    $storageIdentifier = trim((string) ($_POST['storage_identifier'] ?? ''));
    if ($storageIdentifier === '') {
        echo json_encode(['status' => 'error', 'message' => 'storage_identifier_required']);
        return;
    }

    $canonicalTenantIdRaw = $_POST['canonical_tenant_id'] ?? null;
    $canonicalTenantId = null;
    if ($canonicalTenantIdRaw !== null && $canonicalTenantIdRaw !== '' && $canonicalTenantIdRaw !== 'direct') {
        if ((int) $canonicalTenantIdRaw > 0) {
            $canonicalTenantId = (int) $canonicalTenantIdRaw;
        } else {
            echo json_encode(['status' => 'error', 'message' => 'invalid_tenant']);
            return;
        }
    }

    $result = eb_tenant_storage_links_upsert_for_client($clientId, $storageIdentifier, $canonicalTenantId);
    if (empty($result['ok'])) {
        echo json_encode(['status' => 'error', 'message' => (string) ($result['message'] ?? 'write_failed')]);
        return;
    }

    echo json_encode([
        'status' => 'success',
        'storage_identifier' => $storageIdentifier,
        'canonical_tenant_id' => $canonicalTenantId,
    ]);
}
