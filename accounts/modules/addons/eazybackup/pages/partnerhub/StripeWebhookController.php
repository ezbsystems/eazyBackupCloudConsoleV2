<?php

use WHMCS\Database\Capsule;
use PartnerHub\StripeService;

function eb_ph_webhook_account_id(array $event, array $obj): string
{
    $acctId = (string)($event['account'] ?? '');
    if ($acctId !== '') {
        return $acctId;
    }

    $candidate = $obj['account'] ?? '';
    if (is_string($candidate) && $candidate !== '') {
        return $candidate;
    }

    return '';
}

function eb_ph_webhook_msp_id_for_account(string $acctId): int
{
    if ($acctId === '') {
        return 0;
    }

    return (int)(Capsule::table('eb_msp_accounts')->where('stripe_connect_id', $acctId)->value('id') ?? 0);
}

function eb_ph_webhook_tenant_for_customer(string $stripeCustomerId): ?object
{
    if ($stripeCustomerId === '') {
        return null;
    }

    $tenant = Capsule::table('eb_tenants')->where('stripe_customer_id', $stripeCustomerId)->first();
    return $tenant ?: null;
}

function eb_ph_store_trial_notice(int $mspId, ?int $tenantId, string $stripeCustomerId, string $subscriptionId, string $tenantName, int $trialEnd): void
{
    if ($mspId <= 0 || $subscriptionId === '') {
        return;
    }

    $noticeKey = 'trial_will_end:' . $subscriptionId . ':' . max(0, $trialEnd);
    $now = date('Y-m-d H:i:s');
    $effectiveAt = $trialEnd > 0 ? date('Y-m-d H:i:s', $trialEnd) : null;
    $tenantLabel = trim($tenantName) !== '' ? trim($tenantName) : 'A customer';
    $message = $tenantLabel . "'s trial subscription ends soon.";
    if ($trialEnd > 0) {
        $message .= ' Review the subscription before ' . date('M j, Y', $trialEnd) . '.';
    }

    $existing = Capsule::table('eb_partnerhub_notices')
        ->where('msp_id', $mspId)
        ->where('notice_key', $noticeKey)
        ->first();

    $payload = [
        'tenant_id' => $tenantId ?: null,
        'notice_type' => 'trial_will_end',
        'title' => 'Trial ending soon',
        'message' => $message,
        'stripe_customer_id' => $stripeCustomerId !== '' ? $stripeCustomerId : null,
        'stripe_subscription_id' => $subscriptionId,
        'action_url' => 'index.php?m=eazybackup&a=ph-billing-subscriptions',
        'action_label' => 'Review subscription',
        'effective_at' => $effectiveAt,
        'resolved_at' => null,
        'updated_at' => $now,
    ];

    if ($existing) {
        Capsule::table('eb_partnerhub_notices')->where('id', (int)$existing->id)->update($payload);
        return;
    }

    $payload['msp_id'] = $mspId;
    $payload['notice_key'] = $noticeKey;
    $payload['dismissed_at'] = null;
    $payload['created_at'] = $now;
    Capsule::table('eb_partnerhub_notices')->insert($payload);
}

function eb_ph_resolve_trial_notice(int $mspId, string $subscriptionId): void
{
    if ($mspId <= 0 || $subscriptionId === '') {
        return;
    }

    Capsule::table('eb_partnerhub_notices')
        ->where('msp_id', $mspId)
        ->where('notice_type', 'trial_will_end')
        ->where('stripe_subscription_id', $subscriptionId)
        ->whereNull('resolved_at')
        ->update([
            'resolved_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
}

function eb_ph_store_billing_notice(int $mspId, ?int $tenantId, string $noticeType, string $tenantName, string $referenceId): void
{
    $noticeType = trim($noticeType);
    $referenceId = trim($referenceId);
    if ($mspId <= 0 || $noticeType === '' || $referenceId === '') {
        return;
    }

    $noticeKey = 'billing_' . $noticeType . '_' . $referenceId;
    $tenantLabel = trim($tenantName) !== '' ? trim($tenantName) : 'A customer';
    $now = date('Y-m-d H:i:s');
    $title = 'Billing notice';
    $message = $tenantLabel . ' has a billing event that needs review.';
    $actionUrl = 'index.php?m=eazybackup&a=ph-billing-payments';
    $actionLabel = 'Review billing';

    if ($noticeType === 'payment_failed') {
        $title = 'Payment failed';
        $message = $tenantLabel . ' has a failed payment that needs follow-up.';
        $actionUrl = 'index.php?m=eazybackup&a=ph-billing-invoices';
        $actionLabel = 'Review invoice';
    } elseif ($noticeType === 'dispute_opened') {
        $title = 'Dispute opened';
        $message = 'A Stripe dispute was opened and needs review.';
        $actionUrl = 'index.php?m=eazybackup&a=ph-money-disputes';
        $actionLabel = 'Review dispute';
    }

    $payload = [
        'tenant_id' => $tenantId ?: null,
        'notice_type' => $noticeType,
        'title' => $title,
        'message' => $message,
        'action_url' => $actionUrl,
        'action_label' => $actionLabel,
        'resolved_at' => null,
        'updated_at' => $now,
    ];

    $existing = Capsule::table('eb_partnerhub_notices')
        ->where('msp_id', $mspId)
        ->where('notice_key', $noticeKey)
        ->first();

    if ($existing) {
        Capsule::table('eb_partnerhub_notices')->where('id', (int)$existing->id)->update($payload);
        return;
    }

    $payload['msp_id'] = $mspId;
    $payload['notice_key'] = $noticeKey;
    $payload['dismissed_at'] = null;
    $payload['created_at'] = $now;
    Capsule::table('eb_partnerhub_notices')->insert($payload);
}

function eb_ph_resolve_billing_notice(int $mspId, string $noticeType, string $referenceId): void
{
    $noticeType = trim($noticeType);
    $referenceId = trim($referenceId);
    if ($mspId <= 0 || $noticeType === '' || $referenceId === '') {
        return;
    }

    Capsule::table('eb_partnerhub_notices')
        ->where('msp_id', $mspId)
        ->where('notice_key', 'billing_' . $noticeType . '_' . $referenceId)
        ->whereNull('resolved_at')
        ->update([
            'resolved_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
}

function eb_ph_touch_payment_method_state(string $stripeCustomerId): void
{
    if ($stripeCustomerId === '') {
        return;
    }

    $now = date('Y-m-d H:i:s');
    Capsule::table('eb_tenants')
        ->where('stripe_customer_id', $stripeCustomerId)
        ->update(['updated_at' => $now]);

    Capsule::table('eb_customers')
        ->where('stripe_customer_id', $stripeCustomerId)
        ->update(['updated_at' => $now]);
}

function eb_ph_clear_deleted_customer(string $stripeCustomerId): void
{
    if ($stripeCustomerId === '') {
        return;
    }

    $now = date('Y-m-d H:i:s');

    Capsule::table('eb_tenants')
        ->where('stripe_customer_id', $stripeCustomerId)
        ->update([
            'stripe_customer_id' => null,
            'updated_at' => $now,
        ]);

    Capsule::table('eb_customers')
        ->where('stripe_customer_id', $stripeCustomerId)
        ->update([
            'stripe_customer_id' => null,
            'updated_at' => $now,
        ]);

    Capsule::table('eb_partnerhub_notices')
        ->where('stripe_customer_id', $stripeCustomerId)
        ->whereNull('resolved_at')
        ->update([
            'resolved_at' => $now,
            'updated_at' => $now,
        ]);
}

function eb_ph_mark_account_deauthorized(string $acctId): void
{
    if ($acctId === '') {
        return;
    }

    Capsule::table('eb_msp_accounts')
        ->where('stripe_connect_id', $acctId)
        ->update([
            'stripe_connect_id' => null,
            'charges_enabled' => 0,
            'payouts_enabled' => 0,
            'connect_capabilities' => null,
            'connect_requirements' => json_encode([
                'deauthorized' => true,
                'account_id' => $acctId,
            ]),
            'last_verification_check' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
}

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
    $acctId = eb_ph_webhook_account_id($event, is_array($obj) ? $obj : []);

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
            case 'account.application.deauthorized':
                eb_ph_mark_account_deauthorized($acctId);
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
                $customerId = (string)($obj['customer'] ?? '');
                $mspId = eb_ph_webhook_msp_id_for_account($acctId);
                if ($subId !== '') {
                    Capsule::table('eb_subscriptions')->where('stripe_subscription_id',$subId)->update([
                        'stripe_status' => $status,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    if (Capsule::schema()->hasTable('eb_plan_instances')) {
                        $instanceUpdate = ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')];
                        if ($type === 'customer.subscription.deleted') {
                            $instanceUpdate['status'] = 'canceled';
                            $instanceUpdate['cancelled_at'] = date('Y-m-d H:i:s');
                        }
                        Capsule::table('eb_plan_instances')
                            ->where('stripe_subscription_id', $subId)
                            ->update($instanceUpdate);
                    }
                    if ($mspId > 0 && $status !== 'trialing') {
                        eb_ph_resolve_trial_notice($mspId, $subId);
                    }
                }
                if ($type === 'customer.subscription.deleted' && $mspId > 0 && $subId !== '') {
                    eb_ph_resolve_trial_notice($mspId, $subId);
                }
                break;
            case 'customer.subscription.trial_will_end':
                $subId = (string)($obj['id'] ?? '');
                $trialEnd = (int)($obj['trial_end'] ?? 0);
                $customerId = (string)($obj['customer'] ?? '');
                $tenant = eb_ph_webhook_tenant_for_customer($customerId);
                $mspId = eb_ph_webhook_msp_id_for_account($acctId);
                if ($mspId <= 0 && $tenant) {
                    $mspId = (int)($tenant->msp_id ?? 0);
                }
                eb_ph_store_trial_notice(
                    $mspId,
                    $tenant ? (int)($tenant->id ?? 0) : null,
                    $customerId,
                    $subId,
                    $tenant ? (string)($tenant->name ?? '') : '',
                    $trialEnd
                );
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
                            if ($type === 'charge.dispute.created' && $mspId > 0) {
                                eb_ph_store_billing_notice((int)$mspId, null, 'dispute_opened', '', $did);
                            }
                            if ($type === 'charge.dispute.closed' && $mspId > 0) {
                                eb_ph_resolve_billing_notice((int)$mspId, 'dispute_opened', $did);
                            }
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
            case 'invoice.voided':
                $invId = (string)($obj['id'] ?? '');
                if ($invId !== '') {
                    $stripeCustomer = (string)($obj['customer'] ?? '');
                    $tenantId = null;
                    $mspId = eb_ph_webhook_msp_id_for_account($acctId);
                    if ($stripeCustomer !== '') {
                        $tenant = Capsule::table('eb_tenants')->where('stripe_customer_id',$stripeCustomer)->first(['id', 'msp_id', 'name']);
                        $tenantId = (int)($tenant->id ?? 0);
                        if ($mspId <= 0) {
                            $mspId = (int)($tenant->msp_id ?? 0);
                        }
                    }
                    Capsule::table('eb_invoice_cache')->updateOrInsert(
                        ['stripe_invoice_id' => $invId],
                        [
                            'tenant_id' => (int)($tenantId ?? 0),
                            'amount_total' => (int)($obj['amount_total'] ?? 0),
                            'amount_tax' => (int)($obj['tax'] ?? 0),
                            'status' => (string)($obj['status'] ?? ''),
                            'hosted_invoice_url' => (string)($obj['hosted_invoice_url'] ?? ''),
                            'created' => (int)($obj['created'] ?? 0),
                            'currency' => (string)($obj['currency'] ?? 'usd'),
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]
                    );
                    if ($type === 'invoice.payment_failed' && $mspId > 0) {
                        $tenantName = isset($tenant) ? (string)($tenant->name ?? '') : 'A customer';
                        eb_ph_store_billing_notice((int)$mspId, $tenantId ?: null, 'payment_failed', $tenantName, $invId);
                    }
                }
                break;
            case 'charge.succeeded':
            case 'charge.failed':
            case 'charge.refunded':
                $pi = (string)($obj['payment_intent'] ?? '');
                $stripeCustomer = (string)($obj['customer'] ?? '');
                $tenantId = null;
                if ($stripeCustomer !== '') {
                    $tenantId = Capsule::table('eb_tenants')->where('stripe_customer_id',$stripeCustomer)->value('id');
                }
                if ($pi !== '') {
                    Capsule::table('eb_payment_cache')->updateOrInsert(
                        ['stripe_payment_intent_id' => $pi],
                        [
                            'tenant_id' => (int)($tenantId ?? 0),
                            'amount' => (int)($obj['amount'] ?? 0),
                            'currency' => (string)($obj['currency'] ?? 'usd'),
                            'status' => (string)($obj['status'] ?? ''),
                            'created' => (int)($obj['created'] ?? 0),
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]
                    );
                }
                break;
            case 'payment_method.attached':
            case 'payment_method.detached':
                eb_ph_touch_payment_method_state((string)($obj['customer'] ?? ''));
                break;
            case 'payment_intent.succeeded':
            case 'payment_intent.payment_failed':
                $pi = (string)($obj['id'] ?? '');
                $stripeCustomer = (string)($obj['customer'] ?? '');
                $tenantId = null;
                if ($stripeCustomer !== '') {
                    $tenantId = Capsule::table('eb_tenants')->where('stripe_customer_id',$stripeCustomer)->value('id');
                }
                if ($pi !== '') {
                    Capsule::table('eb_payment_cache')->updateOrInsert(
                        ['stripe_payment_intent_id' => $pi],
                        [
                            'tenant_id' => (int)($tenantId ?? 0),
                            'amount' => (int)($obj['amount'] ?? 0),
                            'currency' => (string)($obj['currency'] ?? 'usd'),
                            'status' => (string)($obj['status'] ?? ''),
                            'created' => (int)($obj['created'] ?? 0),
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]
                    );
                }
                break;
            case 'customer.deleted':
                eb_ph_clear_deleted_customer((string)($obj['id'] ?? ''));
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


