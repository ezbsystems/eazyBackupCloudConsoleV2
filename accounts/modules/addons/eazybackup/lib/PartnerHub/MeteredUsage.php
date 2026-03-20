<?php

namespace PartnerHub;

use WHMCS\Database\Capsule;

function computeBillableMeteredUsage(int $actualUsage, int $defaultQty, string $overageMode): int
{
    $actualUsage = max(0, $actualUsage);
    $defaultQty = max(0, $defaultQty);
    $normalizedMode = strtolower(trim($overageMode));

    if ($normalizedMode === 'cap_at_default') {
        return 0;
    }

    if ($normalizedMode === 'bill_all') {
        return max(0, $actualUsage - $defaultQty);
    }

    return max(0, $actualUsage - $defaultQty);
}

function resolveActivePlanInstanceMeteredItem(int $tenantId, string $metricCode): ?array
{
    $tenantId = (int) $tenantId;
    $metricCode = trim($metricCode);
    if ($tenantId <= 0 || $metricCode === '') {
        return null;
    }

    $row = Capsule::table('eb_plan_instances as pi')
        ->join('eb_plan_instance_items as pii', 'pii.plan_instance_id', '=', 'pi.id')
        ->join('eb_plan_components as pc', 'pc.id', '=', 'pii.plan_component_id')
        ->leftJoin('eb_plan_instance_usage_map as pium', 'pium.plan_instance_item_id', '=', 'pii.id')
        ->where('pi.tenant_id', $tenantId)
        ->whereIn('pi.status', ['active', 'trialing', 'past_due', 'paused'])
        ->where('pii.metric_code', $metricCode)
        ->orderByDesc('pi.created_at')
        ->orderByDesc('pi.id')
        ->first([
            'pi.id as plan_instance_id',
            'pi.plan_id',
            'pi.stripe_account_id',
            'pi.stripe_subscription_id',
            'pii.id as plan_instance_item_id',
            'pii.plan_component_id',
            'pii.metric_code',
            'pii.last_qty',
            'pc.default_qty',
            'pc.overage_mode',
            'pii.stripe_subscription_item_id as instance_subscription_item_id',
            'pium.stripe_subscription_item_id as usage_map_subscription_item_id',
        ]);

    if (!$row) {
        return null;
    }

    $resolved = (array) $row;
    $subscriptionItemId = trim((string) ($resolved['usage_map_subscription_item_id'] ?? ''));
    if ($subscriptionItemId === '') {
        $subscriptionItemId = trim((string) ($resolved['instance_subscription_item_id'] ?? ''));
    }
    if ($subscriptionItemId === '') {
        return null;
    }

    return [
        'plan_instance_id' => (int) ($resolved['plan_instance_id'] ?? 0),
        'plan_id' => (int) ($resolved['plan_id'] ?? 0),
        'stripe_account_id' => (string) ($resolved['stripe_account_id'] ?? ''),
        'stripe_subscription_id' => (string) ($resolved['stripe_subscription_id'] ?? ''),
        'plan_instance_item_id' => (int) ($resolved['plan_instance_item_id'] ?? 0),
        'plan_component_id' => (int) ($resolved['plan_component_id'] ?? 0),
        'metric_code' => (string) ($resolved['metric_code'] ?? $metricCode),
        'default_qty' => max(0, (int) ($resolved['default_qty'] ?? 0)),
        'overage_mode' => (string) ($resolved['overage_mode'] ?? 'bill_all'),
        'stripe_subscription_item_id' => $subscriptionItemId,
        'last_qty' => isset($resolved['last_qty']) ? (int) $resolved['last_qty'] : null,
    ];
}
