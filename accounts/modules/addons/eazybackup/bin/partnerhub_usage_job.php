<?php

use WHMCS\Database\Capsule;
use PartnerHub\StripeService;
use PartnerHub\CatalogService;
use function PartnerHub\computeBillableMeteredUsage;

require_once __DIR__ . '/../eazybackup.php';
require_once __DIR__ . '/../lib/PartnerHub/MeteredUsage.php';

// Legacy nightly usage job. Keep behavior aligned with stripe_tenant_usage_rollup.php
// so storage allowance handling does not diverge between operational paths.
// This script is intended to be run by PHP CLI with WHMCS bootstrap environment.

function partnerhub_usage_job_resolve_plan_instance_metered_item(int $planInstanceId, string $metricCode): ?array
{
    $planInstanceId = (int) $planInstanceId;
    $metricCode = trim($metricCode);
    if ($planInstanceId <= 0 || $metricCode === '') {
        return null;
    }

    $row = Capsule::table('eb_plan_instance_items as pii')
        ->join('eb_plan_instances as pi', 'pi.id', '=', 'pii.plan_instance_id')
        ->join('eb_plan_components as pc', 'pc.id', '=', 'pii.plan_component_id')
        ->leftJoin('eb_plan_instance_usage_map as pium', 'pium.plan_instance_item_id', '=', 'pii.id')
        ->where('pii.plan_instance_id', $planInstanceId)
        ->whereIn('pi.status', ['active', 'trialing', 'past_due', 'paused'])
        ->where('pii.metric_code', $metricCode)
        ->orderByDesc('pii.id')
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

try {
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $periodStart = (new DateTimeImmutable('first day of this month 00:00:00', new DateTimeZone('UTC')));
    $periodEnd = (new DateTimeImmutable('first day of next month 00:00:00', new DateTimeZone('UTC')));

    $instances = Capsule::table('eb_plan_instances')->where('status','!=','canceled')->get();
    $svc = new StripeService();
    $cSvc = new CatalogService();

    foreach ($instances as $pi) {
        $acct = (string)$pi->stripe_account_id;
        $items = Capsule::table('eb_plan_instance_items')->where('plan_instance_id',(int)$pi->id)->get();
        // Build metric => subscription_item_id map
        $map = [];
        foreach ($items as $it) { $map[(string)$it->metric_code] = (string)$it->stripe_subscription_item_id; }
        $tenantId = (int)($pi->tenant_id ?? $pi->customer_id ?? 0);
        $cometUserId = (string)$pi->comet_user_id;

        // STORAGE (metered): sum GB for period from eb_storage_daily (bytes_total)
        if (isset($map['STORAGE_TB']) && !str_starts_with($cometUserId, 'storage:')) {
            try {
                $rows = Capsule::table('eb_storage_daily')
                    ->where('username', $cometUserId)
                    ->where('d','>=',$periodStart->format('Y-m-d'))
                    ->where('d','<',$periodEnd->format('Y-m-d'))
                    ->get(['bytes_total']);
                $sumBytes = 0; foreach ($rows as $r) { $sumBytes += (int)$r->bytes_total; }
                $gb = (int)floor($sumBytes / (1024*1024*1024));
                $meteredItem = partnerhub_usage_job_resolve_plan_instance_metered_item((int) $pi->id, 'STORAGE_TB');
                if (!$meteredItem) {
                    continue;
                }
                $resolvedItemId = (string) $meteredItem['stripe_subscription_item_id'];
                $billableGb = computeBillableMeteredUsage($gb, (int) ($meteredItem['default_qty'] ?? 0), (string) ($meteredItem['overage_mode'] ?? 'bill_all'));
                $idemp = sha1('pi:'.$pi->id.'|item:'.$resolvedItemId.'|metric:STORAGE_TB|'.$periodStart->format('Y-m-d').'|'.$periodEnd->format('Y-m-d'));
                $ledger = Capsule::table('eb_usage_ledger')->where('idempotency_key',$idemp)->first(['pushed_to_stripe_at']);
                if (!$ledger && $gb >= 0) {
                    // Record locally and attempt to push
                    Capsule::table('eb_usage_ledger')->insert([
                        'tenant_id' => $tenantId,
                        'metric' => 'STORAGE_TB',
                        'qty' => $billableGb,
                        'period_start' => $periodStart->format('Y-m-d H:i:s'),
                        'period_end' => $periodEnd->format('Y-m-d H:i:s'),
                        'source' => 'cron',
                        'idempotency_key' => $idemp,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                    $ledger = (object)['pushed_to_stripe_at' => null];
                }
                if ($ledger && empty($ledger->pushed_to_stripe_at) && $gb >= 0) {
                    try {
                        $cSvc->createUsageRecordConnected($resolvedItemId, $billableGb, time(), $acct);
                        Capsule::table('eb_usage_ledger')->where('idempotency_key',$idemp)->update(['pushed_to_stripe_at'=>date('Y-m-d H:i:s')]);
                    } catch (\Throwable $e) {
                        try { if (function_exists('logActivity')) { @logActivity('eazybackup: usage push failed for item '.$resolvedItemId.' — '.$e->getMessage()); } } catch (\Throwable $__) {}
                    }
                }
            } catch (\Throwable $e) { /* ignore */ }
        }

        if (isset($map['E3_STORAGE_GIB']) && str_starts_with($cometUserId, 'e3:')) {
            try {
                $s3UserId = (int)substr($cometUserId, 3);
                if ($s3UserId <= 0) {
                    continue;
                }

                $maxBytes = (int) Capsule::table('s3_historical_stats')
                    ->where('user_id', $s3UserId)
                    ->where('date', '>=', $periodStart->format('Y-m-d'))
                    ->where('date', '<', $periodEnd->format('Y-m-d'))
                    ->max('total_storage');
                $gib = (int)floor($maxBytes / (1024*1024*1024));
                $meteredItem = partnerhub_usage_job_resolve_plan_instance_metered_item((int) $pi->id, 'E3_STORAGE_GIB');
                if (!$meteredItem) {
                    continue;
                }
                $resolvedItemId = (string) $meteredItem['stripe_subscription_item_id'];
                $billableGib = computeBillableMeteredUsage($gib, (int) ($meteredItem['default_qty'] ?? 0), (string) ($meteredItem['overage_mode'] ?? 'bill_all'));
                $idemp = sha1('pi:'.$pi->id.'|item:'.$resolvedItemId.'|metric:E3_STORAGE_GIB|'.$periodStart->format('Y-m-d').'|'.$periodEnd->format('Y-m-d'));
                $ledger = Capsule::table('eb_usage_ledger')->where('idempotency_key',$idemp)->first(['pushed_to_stripe_at']);
                if (!$ledger && $gib >= 0) {
                    Capsule::table('eb_usage_ledger')->insert([
                        'tenant_id' => $tenantId,
                        'metric' => 'E3_STORAGE_GIB',
                        'qty' => $billableGib,
                        'period_start' => $periodStart->format('Y-m-d H:i:s'),
                        'period_end' => $periodEnd->format('Y-m-d H:i:s'),
                        'source' => 'cron',
                        'idempotency_key' => $idemp,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                    $ledger = (object)['pushed_to_stripe_at' => null];
                }
                if ($ledger && empty($ledger->pushed_to_stripe_at) && $gib >= 0) {
                    try {
                        $cSvc->createUsageRecordConnected($resolvedItemId, $billableGib, time(), $acct);
                        Capsule::table('eb_usage_ledger')->where('idempotency_key',$idemp)->update(['pushed_to_stripe_at'=>date('Y-m-d H:i:s')]);
                    } catch (\Throwable $e) {
                        try { if (function_exists('logActivity')) { @logActivity('eazybackup: usage push failed for item '.$resolvedItemId.' — '.$e->getMessage()); } } catch (\Throwable $__) {}
                    }
                }
            } catch (\Throwable $e) { /* ignore */ }
        }

        // PER-UNIT (example: DEVICE_COUNT) — recompute count at anchor and update if changed
        if (isset($map['DEVICE_COUNT'])) {
            try {
                // Simple count of active devices by username; refine as needed
                $deviceQty = (int)Capsule::table('comet_devices')->where('username',(string)$pi->comet_user_id)->where('is_active',1)->count();
                $current = Capsule::table('eb_plan_instance_items')->where('plan_instance_id',(int)$pi->id)->where('metric_code','DEVICE_COUNT')->first();
                if ($current && ((int)$current->last_qty !== $deviceQty)) {
                    // Update Stripe quantity (proration)
                    try {
                        $cSvc->updateSubscriptionItemQuantity((string)$current->stripe_subscription_item_id, $deviceQty, $acct, 'create_prorations');
                    } catch (\Throwable $e) { /* ignore */ }
                    Capsule::table('eb_plan_instance_items')->where('id',(int)$current->id)->update([
                        'last_qty' => $deviceQty,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            } catch (\Throwable $e) { /* ignore */ }
        }
    }
} catch (\Throwable $e) {
    try { if (function_exists('logActivity')) { @logActivity('eazybackup: partnerhub_usage_job exception: '.$e->getMessage()); } } catch (\Throwable $__) {}
}


