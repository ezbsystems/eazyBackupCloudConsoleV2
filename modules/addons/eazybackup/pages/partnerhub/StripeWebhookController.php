<?php

use WHMCS\Database\Capsule;
use PartnerHub\StripeService;

function eb_ph_stripe_webhook(): void
{
    $payload = file_get_contents('php://input');
    $sig = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
    // Secret from addon settings
    $secret = (string)(Capsule::table('tbladdonmodules')->where('module','eazybackup')->where('setting','stripe_webhook_secret')->value('value') ?? '');
    if ($secret === '') {
        // Log once per hit to aid ops; avoid leaking details to client area
        try { if (function_exists('logActivity')) { @logActivity('eazybackup: stripe webhook secret not configured'); } } catch (\Throwable $__) { /* ignore */ }
        http_response_code(200); echo 'ok'; return;
    }

    // Verify signature (prefer stripe-php if available)
    $event = null;
    $tolerance = 300; // seconds
    if (class_exists('Stripe\\Webhook')) {
        try {
            // Lazy init API version handling left to Stripe\Webhook
            $event = \Stripe\Webhook::constructEvent($payload, $sig, $secret, $tolerance);
            $event = json_decode(json_encode($event), true); // normalize to array
        } catch (\Throwable $e) {
            http_response_code(400); echo 'invalid-sig'; return;
        }
    } else {
        // Lightweight verifier
        if ($sig === '') { http_response_code(400); echo 'missing-sig'; return; }
        $parts = [];
        foreach (explode(',', (string)$sig) as $p) {
            $kv = explode('=', trim($p), 2);
            if (count($kv) === 2) { $parts[$kv[0]] = $kv[1]; }
        }
        $ts = isset($parts['t']) ? (int)$parts['t'] : 0;
        $v1 = $parts['v1'] ?? '';
        if ($ts <= 0 || $v1 === '') { http_response_code(400); echo 'invalid-sig'; return; }
        if (abs(time() - $ts) > $tolerance) { http_response_code(400); echo 'sig-timeout'; return; }
        $signedPayload = $ts . '.' . $payload;
        $expected = hash_hmac('sha256', $signedPayload, $secret);
        // Constant-time compare
        if (!hash_equals($expected, $v1)) { http_response_code(400); echo 'invalid-sig'; return; }
        $event = json_decode($payload, true);
        if (!is_array($event)) { http_response_code(400); echo 'invalid'; return; }
    }
    $type = (string)($event['type'] ?? '');
    $obj  = $event['data']['object'] ?? [];
    $acctId = (string)($event['account'] ?? ''); // present for Connect events

    // Idempotency: skip if event already processed
    $eid = (string)($event['id'] ?? '');
    if ($eid !== '') {
        try {
            Capsule::table('eb_stripe_events')->insert(['event_id'=>$eid, 'created_at'=>date('Y-m-d H:i:s')]);
        } catch (\Throwable $__dup) {
            http_response_code(200);
            echo 'duplicate';
            return;
        }
    }

    try {
        switch ($type) {
            case 'account.updated':
                if ($acctId !== '') {
                    $charges = (int)($obj['charges_enabled'] ?? 0) ? 1 : 0;
                    // Fallback to transfers_enabled when payouts_enabled is absent
                    $payoutFlag = $obj['payouts_enabled'] ?? ($obj['transfers_enabled'] ?? 0);
                    $payouts = (int)((bool)$payoutFlag) ? 1 : 0;
                    $caps = isset($obj['capabilities']) ? json_encode($obj['capabilities']) : null;
                    $reqs = isset($obj['requirements']) ? json_encode($obj['requirements']) : null;
                    Capsule::table('eb_msp_accounts')->where('stripe_connect_id',$acctId)->update([
                        'charges_enabled' => $charges,
                        'payouts_enabled' => $payouts,
                        'connect_capabilities' => $caps,
                        'connect_requirements' => $reqs,
                        'last_verification_check' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }
                break;
            case 'capability.updated':
                if ($acctId !== '') {
                    // Pull fresh account snapshot to sync flags, capabilities, and requirements
                    try {
                        $svc = new StripeService();
                        $acct = $svc->retrieveAccount($acctId);
                        $charges = (int)($acct['charges_enabled'] ?? 0) ? 1 : 0;
                        $payoutFlag = $acct['payouts_enabled'] ?? ($acct['transfers_enabled'] ?? 0);
                        $payouts = (int)((bool)$payoutFlag) ? 1 : 0;
                        $caps = isset($acct['capabilities']) ? json_encode($acct['capabilities']) : null;
                        $reqs = isset($acct['requirements']) ? json_encode($acct['requirements']) : null;
                        Capsule::table('eb_msp_accounts')->where('stripe_connect_id',$acctId)->update([
                            'charges_enabled' => $charges,
                            'payouts_enabled' => $payouts,
                            'connect_capabilities' => $caps,
                            'connect_requirements' => $reqs,
                            'last_verification_check' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]);
                    } catch (\Throwable $__) {
                        // Fallback: still stamp verification check time
                        Capsule::table('eb_msp_accounts')->where('stripe_connect_id',$acctId)->update([
                            'last_verification_check' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]);
                    }
                }
                break;
            case 'customer.subscription.created':
            case 'customer.subscription.updated':
            case 'customer.subscription.deleted':
                $subId = (string)($obj['id'] ?? '');
                $status = (string)($obj['status'] ?? '');
                if ($subId !== '') {
                    Capsule::table('eb_subscriptions')->where('stripe_subscription_id',$subId)->update([
                        'stripe_status' => $status,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }
                break;
            case 'payout.created':
            case 'payout.updated':
            case 'payout.paid':
            case 'payout.failed':
                if ($acctId !== '') {
                    $mspId = Capsule::table('eb_msp_accounts')->where('stripe_connect_id',$acctId)->value('id');
                    if ($mspId) {
                        $pid = (string)($obj['id'] ?? '');
                        if ($pid !== '') {
                            Capsule::table('eb_payouts')->updateOrInsert(
                                ['stripe_payout_id'=>$pid],
                                [
                                    'msp_id' => (int)$mspId,
                                    'amount' => (int)($obj['amount'] ?? 0),
                                    'currency' => (string)($obj['currency'] ?? 'usd'),
                                    'status' => (string)($obj['status'] ?? ''),
                                    'arrival_date' => (int)($obj['arrival_date'] ?? 0),
                                    'created' => (int)($obj['created'] ?? 0),
                                    'updated_at' => date('Y-m-d H:i:s'),
                                ]
                            );
                        }
                    }
                }
                break;
            case 'charge.dispute.created':
            case 'charge.dispute.updated':
            case 'charge.dispute.closed':
                if ($acctId !== '') {
                    $mspId = Capsule::table('eb_msp_accounts')->where('stripe_connect_id',$acctId)->value('id');
                    if ($mspId) {
                        $did = (string)($obj['id'] ?? '');
                        if ($did !== '') {
                            Capsule::table('eb_disputes')->updateOrInsert(
                                ['stripe_dispute_id'=>$did],
                                [
                                    'msp_id' => (int)$mspId,
                                    'amount' => (int)($obj['amount'] ?? 0),
                                    'currency' => (string)($obj['currency'] ?? 'usd'),
                                    'reason' => (string)($obj['reason'] ?? ''),
                                    'status' => (string)($obj['status'] ?? ''),
                                    'evidence_due_by' => (int)($obj['evidence_details']['due_by'] ?? 0),
                                    'charge_id' => (string)($obj['charge'] ?? ''),
                                    'created' => (int)($obj['created'] ?? 0),
                                    'updated_at' => date('Y-m-d H:i:s'),
                                ]
                            );
                        }
                    }
                }
                break;
            case 'application_fee.created':
            case 'application_fee.refunded':
                // No local table yet; ignore or log
                break;
            case 'invoice.created':
            case 'invoice.updated':
            case 'invoice.paid':
            case 'invoice.payment_failed':
                $invId = (string)($obj['id'] ?? '');
                if ($invId !== '') {
                    // Resolve customer row
                    $stripeCustomer = (string)($obj['customer'] ?? '');
                    $custId = null;
                    if ($stripeCustomer !== '') {
                        $custId = Capsule::table('eb_customers')->where('stripe_customer_id',$stripeCustomer)->value('id');
                    }
                    Capsule::table('eb_invoice_cache')->updateOrInsert(
                        ['stripe_invoice_id' => $invId],
                        [
                            'customer_id' => (int)($custId ?? 0),
                            'amount_total' => (int)($obj['amount_total'] ?? 0),
                            'amount_tax' => (int)($obj['tax'] ?? 0),
                            'status' => (string)($obj['status'] ?? ''),
                            'hosted_invoice_url' => (string)($obj['hosted_invoice_url'] ?? ''),
                            'created' => (int)($obj['created'] ?? 0),
                            'currency' => (string)($obj['currency'] ?? 'usd'),
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]
                    );
                }
                break;
            case 'charge.succeeded':
            case 'charge.failed':
            case 'charge.refunded':
                $pi = (string)($obj['payment_intent'] ?? '');
                $stripeCustomer = (string)($obj['customer'] ?? '');
                $custId = null;
                if ($stripeCustomer !== '') {
                    $custId = Capsule::table('eb_customers')->where('stripe_customer_id',$stripeCustomer)->value('id');
                }
                if ($pi !== '') {
                    Capsule::table('eb_payment_cache')->updateOrInsert(
                        ['stripe_payment_intent_id' => $pi],
                        [
                            'customer_id' => (int)($custId ?? 0),
                            'amount' => (int)($obj['amount'] ?? 0),
                            'currency' => (string)($obj['currency'] ?? 'usd'),
                            'status' => (string)($obj['status'] ?? ''),
                            'created' => (int)($obj['created'] ?? 0),
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]
                    );
                }
                break;
            case 'payment_intent.succeeded':
            case 'payment_intent.payment_failed':
                $pi = (string)($obj['id'] ?? '');
                $stripeCustomer = (string)($obj['customer'] ?? '');
                $custId = null;
                if ($stripeCustomer !== '') {
                    $custId = Capsule::table('eb_customers')->where('stripe_customer_id',$stripeCustomer)->value('id');
                }
                if ($pi !== '') {
                    Capsule::table('eb_payment_cache')->updateOrInsert(
                        ['stripe_payment_intent_id' => $pi],
                        [
                            'customer_id' => (int)($custId ?? 0),
                            'amount' => (int)($obj['amount'] ?? 0),
                            'currency' => (string)($obj['currency'] ?? 'usd'),
                            'status' => (string)($obj['status'] ?? ''),
                            'created' => (int)($obj['created'] ?? 0),
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]
                    );
                }
                break;
            default:
                break;
        }
    } catch (\Throwable $e) {
        // Avoid noisy failures; log if available
        if (function_exists('logActivity')) {
            @logActivity('eazybackup: stripe webhook error: '.$e->getMessage());
        }
    }

    http_response_code(200);
    echo 'ok';
}


