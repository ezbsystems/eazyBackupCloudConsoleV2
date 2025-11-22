<?php

// Nightly backfill for Stripe invoice/payment caches

require_once __DIR__ . '/../bin/bootstrap.php';

use WHMCS\Database\Capsule;
use PartnerHub\StripeService;

function main(): void {
    $svc = new StripeService();
    $now = time();
    $gte = $now - 86400 * 7; // last 7 days
    $rows = Capsule::table('eb_customers')->whereNotNull('stripe_customer_id')->get(['id','stripe_customer_id']);
    foreach ($rows as $r) {
        $cid = (int)$r->id;
        $scus = (string)$r->stripe_customer_id;
        try {
            $invs = $svc->listInvoices($scus, $gte, 100);
            foreach (($invs['data'] ?? []) as $iv) {
                $invId = (string)($iv['id'] ?? ''); if ($invId === '') continue;
                Capsule::table('eb_invoice_cache')->updateOrInsert(
                    ['stripe_invoice_id' => $invId],
                    [
                        'customer_id' => $cid,
                        'amount_total' => (int)($iv['amount_total'] ?? 0),
                        'amount_tax' => (int)($iv['tax'] ?? 0),
                        'status' => (string)($iv['status'] ?? ''),
                        'hosted_invoice_url' => (string)($iv['hosted_invoice_url'] ?? ''),
                        'created' => (int)($iv['created'] ?? 0),
                        'currency' => (string)($iv['currency'] ?? 'usd'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]
                );
            }
        } catch (\Throwable $__) { /* ignore */ }
        try {
            $chs = $svc->listCharges($scus, $gte, 100);
            foreach (($chs['data'] ?? []) as $ch) {
                $pi = (string)($ch['payment_intent'] ?? ''); if ($pi === '') continue;
                Capsule::table('eb_payment_cache')->updateOrInsert(
                    ['stripe_payment_intent_id' => $pi],
                    [
                        'customer_id' => $cid,
                        'amount' => (int)($ch['amount'] ?? 0),
                        'currency' => (string)($ch['currency'] ?? 'usd'),
                        'status' => (string)($ch['status'] ?? ''),
                        'created' => (int)($ch['created'] ?? 0),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]
                );
            }
        } catch (\Throwable $__) { /* ignore */ }
    }
}

main();


