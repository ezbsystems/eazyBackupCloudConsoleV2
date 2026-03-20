<?php

use WHMCS\Database\Capsule;
use PartnerHub\StripeService;
use PartnerHub\CatalogService;
use function PartnerHub\computeBillableMeteredUsage;
use function PartnerHub\resolveActivePlanInstanceMeteredItem;

require_once __DIR__ . '/../eazybackup.php';
require_once __DIR__ . '/../lib/PartnerHub/MeteredUsage.php';

// Legacy nightly usage job. Keep behavior aligned with stripe_tenant_usage_rollup.php
// so storage allowance handling does not diverge between operational paths.
// This script is intended to be run by PHP CLI with WHMCS bootstrap environment.

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

        // STORAGE (metered): sum GB for period from eb_storage_daily (bytes_total)
        if (isset($map['STORAGE_TB'])) {
            try {
                $rows = Capsule::table('eb_storage_daily')
                    ->where('username', (string)$pi->comet_user_id)
                    ->where('d','>=',$periodStart->format('Y-m-d'))
                    ->where('d','<',$periodEnd->format('Y-m-d'))
                    ->get(['bytes_total']);
                $sumBytes = 0; foreach ($rows as $r) { $sumBytes += (int)$r->bytes_total; }
                $gb = (int)floor($sumBytes / (1024*1024*1024));
                $tenantId = (int)($pi->tenant_id ?? $pi->customer_id ?? 0);
                $meteredItem = resolveActivePlanInstanceMeteredItem($tenantId, 'STORAGE_TB');
                if (!$meteredItem) {
                    continue;
                }
                if ((int) ($meteredItem['plan_instance_id'] ?? 0) !== (int) $pi->id) {
                    continue;
                }
                $resolvedItemId = (string) $meteredItem['stripe_subscription_item_id'];
                $billableGb = computeBillableMeteredUsage($gb, (int) ($meteredItem['default_qty'] ?? 0), (string) ($meteredItem['overage_mode'] ?? 'bill_all'));
                $idemp = sha1('pi:'.$pi->id.'|item:'.$resolvedItemId.'|metric:STORAGE_TB|'.$periodStart->format('Y-m-d').'|'.$periodEnd->format('Y-m-d'));
                $exists = Capsule::table('eb_usage_ledger')->where('idempotency_key',$idemp)->first();
                if (!$exists && $gb >= 0) {
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
                    try {
                        $cSvc->createUsageRecordConnected($resolvedItemId, $billableGb, time(), $acct);
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


