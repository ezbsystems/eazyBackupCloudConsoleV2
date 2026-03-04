<?php

use WHMCS\Database\Capsule;

require_once __DIR__ . '/TenantsController.php';

function eb_ph_tenant_billing(array $vars)
{
    [$clientId, $msp, $tenantId, $tenant] = eb_ph_tenant_require_owned($vars);

    $mspId = (int)($msp->id ?? 0);
    $billingError = '';
    $subscriptionsCount = 0;
    $usageMetricsCount = 0;
    $invoicesCount = 0;

    try {
        if ($mspId > 0 && Capsule::schema()->hasTable('eb_subscriptions')) {
            $subscriptionsCount = (int)Capsule::table('eb_subscriptions')
                ->where('tenant_id', $tenantId)
                ->count();
        }

        if (Capsule::schema()->hasTable('eb_usage_ledger')) {
            $usageMetricsCount = (int)(Capsule::table('eb_usage_ledger')
                ->where('tenant_id', $tenantId)
                ->selectRaw('COUNT(DISTINCT metric) as cnt')
                ->value('cnt') ?? 0);
        }

        if (Capsule::schema()->hasTable('eb_invoice_cache')) {
            $invoicesCount = (int)Capsule::table('eb_invoice_cache')
                ->where('tenant_id', $tenantId)
                ->count();
        }
    } catch (\Throwable $__) {
        $billingError = 'tenant_billing_query_failed';
    }

    return eb_ph_tenant_shell_response($vars, (array)$msp, (array)$tenant, 'billing', [
        'billing_error' => $billingError,
        'billing_tenant' => (array)$tenant,
        'billing_subscriptions_count' => $subscriptionsCount,
        'billing_usage_metrics_count' => $usageMetricsCount,
        'billing_invoices_count' => $invoicesCount,
    ]);
}
