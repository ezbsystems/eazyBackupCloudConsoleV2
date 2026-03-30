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
    $s3Users = [];
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

            foreach ($planRows as $planRow) {
                $planRow['assignment_mode'] = eb_ph_plan_assignment_mode((int)($planRow['id'] ?? 0));
                $planRow['requires_comet_user'] = (bool)($planRow['assignment_mode']['requires_comet_user'] ?? true);
                $assignablePlans[] = $planRow;
            }
        }

        $s3Users = eb_ph_discover_msp_s3_users((int)($msp->whmcs_client_id ?? 0));
        $s3UsersById = [];
        foreach ($s3Users as $s3User) {
            $s3UserId = (int)($s3User['id'] ?? 0);
            if ($s3UserId > 0) {
                $s3UsersById[$s3UserId] = $s3User;
            }
        }

        foreach ($planInstances as &$planInstance) {
            $cometUserId = trim((string)($planInstance['comet_user_id'] ?? ''));
            if (preg_match('/^e3:(\d+)$/', $cometUserId, $matches)) {
                $s3UserId = (int)($matches[1] ?? 0);
                $displayLabel = trim((string)($s3UsersById[$s3UserId]['display_label'] ?? ''));
                $planInstance['comet_user_display'] = $displayLabel !== '' ? 'S3: ' . $displayLabel : $cometUserId;
                continue;
            }

            if (strpos($cometUserId, 'storage:') === 0) {
                $planInstance['comet_user_display'] = 'Tenant-level (legacy)';
                continue;
            }

            $planInstance['comet_user_display'] = $cometUserId;
        }
        unset($planInstance);

        if (Capsule::schema()->hasTable('eb_tenant_comet_accounts')) {
            $tenantCometUsers = Capsule::table('eb_tenant_comet_accounts')
                ->where('tenant_id', $tenantId)
                ->orderBy('comet_user_id', 'asc')
                ->get(['comet_user_id'])
                ->map(fn($r) => (array)$r)
                ->toArray();
        }

        $mergedTenantCometUsers = [];
        foreach ($tenantCometUsers as $tenantCometUser) {
            $cometUserId = trim((string)($tenantCometUser['comet_user_id'] ?? ''));
            if ($cometUserId === '') {
                continue;
            }
            $mergedTenantCometUsers[strtolower($cometUserId)] = ['comet_user_id' => $cometUserId];
        }
        if (function_exists('eb_ph_discover_msp_comet_usernames')) {
            foreach (eb_ph_discover_msp_comet_usernames((int)($msp->whmcs_client_id ?? 0)) as $discoveredCometUser) {
                if (is_array($discoveredCometUser)) {
                    $cometUserId = trim((string)($discoveredCometUser['comet_user_id'] ?? $discoveredCometUser['comet_username'] ?? ''));
                } else {
                    $cometUserId = trim((string)$discoveredCometUser);
                }
                if ($cometUserId === '') {
                    continue;
                }
                $mergedTenantCometUsers[strtolower($cometUserId)] = ['comet_user_id' => $cometUserId];
            }
        }
        $tenantCometUsers = array_values($mergedTenantCometUsers);
        usort($tenantCometUsers, static function (array $left, array $right): int {
            return strcasecmp((string)($left['comet_user_id'] ?? ''), (string)($right['comet_user_id'] ?? ''));
        });

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
        'billing_s3_users' => $s3Users,
        'billing_payment_methods' => $paymentMethods,
    ]);
}
