<?php

use WHMCS\Database\Capsule;

require_once __DIR__ . '/TenantsController.php';
require_once __DIR__ . '/../whitelabel/BuildController.php';

function eb_ph_tenant_whitelabel_redirect(array $vars, string $tenantPublicId, string $query = ''): void
{
    $url = eb_ph_tenants_base_link($vars) . '&a=ph-tenant-whitelabel&id=' . rawurlencode($tenantPublicId);
    if ($query !== '') {
        $url .= '&' . $query;
    }
    header('Location: ' . $url);
    exit;
}

function eb_ph_tenant_whitelabel_enable(array $vars): void
{
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        eb_ph_tenants_redirect($vars, 'error=invalid_method');
    }

    [$clientId, $msp, $tenantId, $tenant] = eb_ph_tenant_require_owned($vars);
    $tenantPublicId = trim((string)($tenant->public_id ?? ''));
    $token = (string)($_POST['token'] ?? '');
    eb_ph_tenants_require_csrf_or_redirect($vars, $token, $tenantPublicId);

    try {
        $enabled = eazybackup_whitelabel_enable_for_canonical_tenant($vars, (int)$clientId, (int)$tenantId, (array)$tenant);
    } catch (\Throwable $_) {
        eb_ph_tenant_whitelabel_redirect($vars, $tenantPublicId, 'error=whitelabel_enable_failed');
    }

    if (!is_array($enabled) || empty($enabled['ok'])) {
        $err = is_array($enabled) ? (string)($enabled['error'] ?? 'whitelabel_enable_failed') : 'whitelabel_enable_failed';
        eb_ph_tenant_whitelabel_redirect($vars, $tenantPublicId, 'error=' . urlencode($err));
    }

    if (!empty($enabled['already_enabled'])) {
        eb_ph_tenant_whitelabel_redirect($vars, $tenantPublicId, 'notice=whitelabel_already_enabled');
    }

    eb_ph_tenant_whitelabel_redirect($vars, $tenantPublicId, 'notice=whitelabel_enabled');
}

function eb_ph_tenant_whitelabel(array $vars)
{
    [$clientId, $msp, $tenantId, $tenant] = eb_ph_tenant_require_owned($vars);

    $wlError = '';
    $wlTenant = null;
    $customDomains = [];
    $assetsByType = [];

    try {
        if (!Capsule::schema()->hasTable('eb_whitelabel_tenants')) {
            $wlError = 'whitelabel_table_missing';
        } else {
            $wlTenant = Capsule::table('eb_whitelabel_tenants')
                ->where('client_id', $clientId)
                ->where('canonical_tenant_id', $tenantId)
                ->first([
                    'id',
                    'status',
                    'canonical_tenant_id',
                    'subdomain',
                    'fqdn',
                    'custom_domain',
                    'custom_domain_status',
                    'org_id',
                    'product_id',
                    'server_id',
                    'servergroup_id',
                    'created_at',
                    'updated_at',
                ]);
        }

        if ($wlTenant && Capsule::schema()->hasTable('eb_whitelabel_custom_domains')) {
            $rows = Capsule::table('eb_whitelabel_custom_domains')
                ->where('tenant_id', (int)$wlTenant->id)
                ->orderBy('hostname', 'asc')
                ->get([
                    'hostname',
                    'status',
                    'created_at',
                    'updated_at',
                ]);
            foreach ($rows as $row) {
                $customDomains[] = (array)$row;
            }
        }

        if ($wlTenant && Capsule::schema()->hasTable('eb_whitelabel_assets')) {
            $rows = Capsule::table('eb_whitelabel_assets')
                ->where('tenant_id', (int)$wlTenant->id)
                ->groupBy('asset_type')
                ->orderBy('asset_type', 'asc')
                ->get([
                    'asset_type',
                    Capsule::raw('COUNT(*) as asset_count'),
                ]);
            foreach ($rows as $row) {
                $assetsByType[] = (array)$row;
            }
        }
    } catch (\Throwable $__) {
        $wlError = 'tenant_whitelabel_query_failed';
    }

    $mappingState = 'not_mapped';
    if ($wlTenant) {
        $mappingState = ((int)($wlTenant->canonical_tenant_id ?? 0) === $tenantId) ? 'mapped' : 'mismatch';
    }

    return eb_ph_tenant_shell_response($vars, (array)$msp, (array)$tenant, 'white_label', [
        'whitelabel_error' => $wlError,
        'whitelabel_tenant' => $wlTenant ? (array)$wlTenant : null,
        'whitelabel_custom_domains' => $customDomains,
        'whitelabel_assets_by_type' => $assetsByType,
        'whitelabel_mapping_state' => $mappingState,
        'whitelabel_enabled' => $wlTenant ? 1 : 0,
        'whitelabel_enable_action' => eb_ph_tenants_base_link($vars) . '&a=ph-tenant-whitelabel-enable',
    ]);
}
