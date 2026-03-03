<?php

use WHMCS\Database\Capsule;

require_once __DIR__ . '/TenantsController.php';

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

    return eb_ph_tenant_shell_response($vars, (array)$msp, (array)$tenant, 'white_label', [
        'whitelabel_error' => $wlError,
        'whitelabel_tenant' => $wlTenant ? (array)$wlTenant : null,
        'whitelabel_custom_domains' => $customDomains,
        'whitelabel_assets_by_type' => $assetsByType,
    ]);
}
