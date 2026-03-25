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
    $planInstances = [];
    $assignablePlans = [];
    $tenantCometUsers = [];
    $paymentMethods = [];

    try {
        if (Capsule::schema()->hasTable('eb_plan_instances')) {
            $subscriptionsCount = (int)Capsule::table('eb_plan_instances')
                ->where('tenant_id', $tenantId)
                ->whereNotIn('status', ['canceled'])
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

        if (Capsule::schema()->hasTable('eb_plan_instances') && Capsule::schema()->hasTable('eb_plan_templates')) {
            $planInstances = Capsule::table('eb_plan_instances as pi')
                ->leftJoin('eb_plan_templates as pt', 'pt.id', '=', 'pi.plan_id')
                ->where('pi.tenant_id', $tenantId)
                ->orderByRaw("FIELD(pi.status, 'active', 'trialing', 'past_due', 'paused', 'canceled')")
                ->orderBy('pi.created_at', 'desc')
                ->get([
                    'pi.id', 'pi.comet_user_id', 'pi.status', 'pi.created_at',
                    'pi.stripe_subscription_id', 'pi.cancelled_at',
                    'pt.name as plan_name',
                ])
                ->map(fn($r) => (array)$r)
                ->toArray();
        }

        if (Capsule::schema()->hasTable('eb_plan_templates')) {
            $planRows = Capsule::table('eb_plan_templates')
                ->where('msp_id', $mspId)
                ->where('status', 'active')
                ->orderBy('name', 'asc')
                ->get(['id', 'name', 'description', 'billing_interval', 'currency'])
                ->map(fn($r) => (array)$r)
                ->toArray();

            $planComponentRows = [];
            if ($planRows !== [] && Capsule::schema()->hasTable('eb_plan_components')) {
                $planComponentRows = Capsule::table('eb_plan_components as pc')
                    ->leftJoin('eb_catalog_prices as pr', 'pr.id', '=', 'pc.price_id')
                    ->leftJoin('eb_catalog_products as p', 'p.id', '=', 'pr.product_id')
                    ->whereIn('pc.plan_id', array_map(static fn(array $row): int => (int)($row['id'] ?? 0), $planRows))
                    ->get([
                        'pc.plan_id',
                        'pc.metric_code',
                        'pr.metric_code as price_metric',
                        'p.base_metric_code as product_base_metric',
                    ])
                    ->map(fn($r) => (array)$r)
                    ->toArray();
            }

            $metricsByPlan = [];
            foreach ($planComponentRows as $component) {
                $planId = (int)($component['plan_id'] ?? 0);
                $metric = strtoupper(trim((string)($component['price_metric'] ?? $component['metric_code'] ?? $component['product_base_metric'] ?? '')));
                if ($planId <= 0 || $metric === '') {
                    continue;
                }
                if (!isset($metricsByPlan[$planId])) {
                    $metricsByPlan[$planId] = [];
                }
                $metricsByPlan[$planId][$metric] = true;
            }

            foreach ($planRows as $planRow) {
                $planId = (int)($planRow['id'] ?? 0);
                $metrics = array_keys($metricsByPlan[$planId] ?? []);
                $nonStorageMetrics = array_filter($metrics, static fn(string $metric): bool => $metric !== 'STORAGE_TB');
                $planRow['requires_comet_user'] = count($metrics) === 0 ? true : count($nonStorageMetrics) > 0;
                $assignablePlans[] = $planRow;
            }
        }

        if (Capsule::schema()->hasTable('eb_tenant_comet_accounts')) {
            $tenantCometUsers = Capsule::table('eb_tenant_comet_accounts')
                ->where('tenant_id', $tenantId)
                ->orderBy('comet_user_id', 'asc')
                ->get(['comet_user_id'])
                ->map(fn($r) => (array)$r)
                ->toArray();
        }

        $whmcsCometUsernames = eb_ph_discover_msp_comet_usernames((int)($msp->whmcs_client_id ?? 0));
        if ($whmcsCometUsernames !== []) {
            $existingIds = array_map(
                static fn(array $r): string => (string)($r['comet_user_id'] ?? ''),
                $tenantCometUsers
            );
            foreach ($whmcsCometUsernames as $username) {
                if (!in_array($username, $existingIds, true)) {
                    $tenantCometUsers[] = ['comet_user_id' => $username];
                }
            }
            usort($tenantCometUsers, static fn(array $a, array $b): int =>
                strcasecmp((string)($a['comet_user_id'] ?? ''), (string)($b['comet_user_id'] ?? ''))
            );
        }

        $stripeCustomerId = trim((string)($tenant->stripe_customer_id ?? ''));
        $stripeConnectId = trim((string)($msp->stripe_connect_id ?? ''));
        if ($stripeCustomerId !== '' && $stripeConnectId !== '') {
            $svc = new \PartnerHub\StripeService();
            $customer = $svc->retrieveCustomer($stripeCustomerId, $stripeConnectId);
            $defaultPaymentMethodId = (string)($customer['invoice_settings']['default_payment_method'] ?? '');
            $paymentMethodRows = $svc->listCustomerPaymentMethods($stripeCustomerId, 'card', $stripeConnectId);
            foreach (($paymentMethodRows['data'] ?? []) as $paymentMethod) {
                if (!is_array($paymentMethod)) {
                    continue;
                }
                $card = is_array($paymentMethod['card'] ?? null) ? $paymentMethod['card'] : [];
                $paymentMethods[] = [
                    'id' => (string)($paymentMethod['id'] ?? ''),
                    'brand' => (string)($card['brand'] ?? 'card'),
                    'last4' => (string)($card['last4'] ?? ''),
                    'exp_month' => (int)($card['exp_month'] ?? 0),
                    'exp_year' => (int)($card['exp_year'] ?? 0),
                    'is_default' => ((string)($paymentMethod['id'] ?? '') !== '' && (string)($paymentMethod['id'] ?? '') === $defaultPaymentMethodId),
                ];
            }
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
        'billing_plan_instances' => $planInstances,
        'billing_assignable_plans' => $assignablePlans,
        'billing_tenant_comet_users' => $tenantCometUsers,
        'billing_payment_methods' => $paymentMethods,
    ]);
}
