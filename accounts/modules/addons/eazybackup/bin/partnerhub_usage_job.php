<?php

use WHMCS\Database\Capsule;
use PartnerHub\StripeService;
use PartnerHub\CatalogService;

require_once __DIR__ . '/../eazybackup.php';

// Nightly usage job skeleton: iterate plan instances, push metered GB usage and update per-unit quantities
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
                $idemp = sha1('pi:'.$pi->id.'|item:'.$map['STORAGE_TB'].'|metric:STORAGE_TB|'.$periodStart->format('Y-m-d').'|'.$periodEnd->format('Y-m-d'));
                $exists = Capsule::table('eb_usage_ledger')->where('idempotency_key',$idemp)->first();
                if (!$exists && $gb >= 0) {
                    // Record locally and attempt to push
                    Capsule::table('eb_usage_ledger')->insert([
                        'customer_id' => (int)$pi->customer_id,
                        'metric' => 'STORAGE_TB',
                        'qty' => $gb,
                        'period_start' => $periodStart->format('Y-m-d H:i:s'),
                        'period_end' => $periodEnd->format('Y-m-d H:i:s'),
                        'source' => 'cron',
                        'idempotency_key' => $idemp,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                    try {
                        // Push usage record via REST (StripeService::createUsageRecord posts to platform; here we use direct header in retrieveSubscription path)
                        // Reuse Subscription retrieval to get connected items if needed
                        $cSvc->createUsageRecordConnected($map['STORAGE_TB'], $gb, time(), $acct);
                        Capsule::table('eb_usage_ledger')->where('idempotency_key',$idemp)->update(['pushed_to_stripe_at'=>date('Y-m-d H:i:s')]);
                    } catch (\Throwable $e) {
                        try { if (function_exists('logActivity')) { @logActivity('eazybackup: usage push failed for item '.$map['STORAGE_TB'].' — '.$e->getMessage()); } } catch (\Throwable $__) {}
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


