<?php

declare(strict_types=1);

namespace EazyBackup\Tests\Integration;

use EazyBackup\Tests\Support\DatabaseTestCase;
use EazyBackup\Tests\Support\Seeder;
use EazyBackup\Tests\Support\TestableStripeService;
use WHMCS\Database\Capsule;

/**
 * Coverage for `eb_ph_plan_subscription_cancel_for_msp`.
 *
 * Source: pages/partnerhub/CatalogPlansController.php
 *
 * Risks this catches:
 *   - Cross-MSP cancel (one MSP cancelling another MSP's plan instance) —
 *     the scope check is the only thing standing between this and a refund war.
 *   - Local cancel mark applied without the Stripe call succeeding (would
 *     leave Stripe still billing while Partner Hub shows "Canceled").
 *   - Already-canceled instances re-firing the Stripe call (idempotent
 *     business rule: do nothing).
 *   - cancel_reason not persisted (used by the audit trail and by reporting).
 */
final class PlanSubscriptionCancelTest extends DatabaseTestCase
{
    public function test_cancel_marks_instance_canceled_and_calls_stripe(): void
    {
        $accountId = 'acct_phg_can_' . bin2hex(random_bytes(4));
        $mspId = Seeder::seedMsp(['stripe_connect_id' => $accountId]);
        $tenantId = Seeder::seedTenant($mspId);
        $stripeSubId = 'sub_phg_can_' . bin2hex(random_bytes(4));
        $instanceId = $this->seedActivePlanInstance($mspId, $tenantId, $accountId, $stripeSubId);

        $stripe = new TestableStripeService();
        $stripe->queueResponse(['id' => $stripeSubId, 'status' => 'canceled']);

        $result = eb_ph_plan_subscription_cancel_for_msp($mspId, $instanceId, 'customer requested', $stripe);

        self::assertSame('success', $result['status']);

        // Stripe DELETE call was made with the right account header.
        $call = $stripe->lastCall();
        self::assertNotNull($call);
        self::assertSame('DELETE', $call->method);
        self::assertSame('/v1/subscriptions/' . $stripeSubId, $call->path);
        self::assertSame($accountId, $call->stripeAccount);

        $row = Capsule::table('eb_plan_instances')->where('id', $instanceId)->first();
        self::assertSame('canceled', $row->status);
        self::assertNotNull($row->cancelled_at);
        self::assertSame('customer requested', $row->cancel_reason);
    }

    public function test_cancel_blocks_when_instance_belongs_to_other_msp(): void
    {
        $accountId = 'acct_phg_can_' . bin2hex(random_bytes(4));
        $myMspId = Seeder::seedMsp();
        $otherMspId = Seeder::seedMsp(['whmcs_client_id' => random_int(900_000_000, 999_999_999) - 1, 'stripe_connect_id' => $accountId]);
        $tenantId = Seeder::seedTenant($otherMspId);
        $instanceId = $this->seedActivePlanInstance($otherMspId, $tenantId, $accountId, 'sub_other');

        $stripe = new TestableStripeService();
        $result = eb_ph_plan_subscription_cancel_for_msp($myMspId, $instanceId, '', $stripe);

        self::assertSame('error', $result['status']);
        self::assertSame('scope', $result['message']);
        self::assertSame([], $stripe->calls, 'Scope failure must not call Stripe.');

        // Other MSP's instance untouched.
        $row = Capsule::table('eb_plan_instances')->where('id', $instanceId)->first();
        self::assertSame('active', $row->status);
    }

    public function test_cancel_returns_already_canceled_without_calling_stripe(): void
    {
        $accountId = 'acct_phg_can_' . bin2hex(random_bytes(4));
        $mspId = Seeder::seedMsp(['stripe_connect_id' => $accountId]);
        $tenantId = Seeder::seedTenant($mspId);
        $instanceId = $this->seedActivePlanInstance($mspId, $tenantId, $accountId, 'sub_canceled', 'canceled');

        $stripe = new TestableStripeService();
        $result = eb_ph_plan_subscription_cancel_for_msp($mspId, $instanceId, '', $stripe);

        self::assertSame('error', $result['status']);
        self::assertSame('already_canceled', $result['message']);
        self::assertSame([], $stripe->calls);
    }

    public function test_stripe_failure_does_not_mark_instance_canceled_locally(): void
    {
        $accountId = 'acct_phg_can_' . bin2hex(random_bytes(4));
        $mspId = Seeder::seedMsp(['stripe_connect_id' => $accountId]);
        $tenantId = Seeder::seedTenant($mspId);
        $instanceId = $this->seedActivePlanInstance($mspId, $tenantId, $accountId, 'sub_will_fail');

        $stripe = new TestableStripeService();
        $stripe->throwOnNext(new \RuntimeException('Stripe error (HTTP 500): Internal'));

        $result = eb_ph_plan_subscription_cancel_for_msp($mspId, $instanceId, '', $stripe);

        self::assertSame('error', $result['status']);
        self::assertSame('stripe_cancel_failed', $result['message']);

        // Local row remains active — operator can retry.
        $row = Capsule::table('eb_plan_instances')->where('id', $instanceId)->first();
        self::assertSame('active', $row->status);
        self::assertNull($row->cancelled_at);
    }

    public function test_invalid_args_short_circuit(): void
    {
        $stripe = new TestableStripeService();
        self::assertSame('invalid', eb_ph_plan_subscription_cancel_for_msp(0, 1, '', $stripe)['message']);
        self::assertSame('invalid', eb_ph_plan_subscription_cancel_for_msp(1, 0, '', $stripe)['message']);
        self::assertSame([], $stripe->calls);
    }

    private function seedActivePlanInstance(int $mspId, int $tenantId, string $accountId, string $stripeSubId, string $status = 'active'): int
    {
        $now = date('Y-m-d H:i:s');
        return (int) Capsule::table('eb_plan_instances')->insertGetId([
            'msp_id' => $mspId,
            'customer_id' => 0,
            'tenant_id' => $tenantId,
            'comet_user_id' => 'phg-can-' . bin2hex(random_bytes(3)),
            'plan_id' => 0,
            'plan_version' => 1,
            'stripe_account_id' => $accountId,
            'stripe_customer_id' => 'cus_phg_' . bin2hex(random_bytes(4)),
            'stripe_subscription_id' => $stripeSubId,
            'anchor_date' => date('Y-m-d'),
            'status' => $status,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
