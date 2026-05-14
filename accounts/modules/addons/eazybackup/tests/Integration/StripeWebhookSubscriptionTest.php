<?php

declare(strict_types=1);

namespace EazyBackup\Tests\Integration;

use EazyBackup\Tests\Support\DatabaseTestCase;
use EazyBackup\Tests\Support\Seeder;
use EazyBackup\Tests\Support\StripeWebhookFixture;
use WHMCS\Database\Capsule;

/**
 * Coverage for the customer.subscription.* event handlers.
 *
 * Source: pages/partnerhub/StripeWebhookController.php
 *
 * Risks this catches:
 *   - eb_subscriptions.stripe_status falling out of sync with Stripe (would
 *     show stale "active" badges in Partner Hub for canceled subscriptions).
 *   - eb_plan_instances NOT being mirrored on cancel (the canonical billing
 *     model stores the per-tenant subscription state here; missing this
 *     means "Past due" / "Canceled" badges never light up on the Tenant
 *     Detail page).
 *   - trial_will_end NOT producing an eb_partnerhub_notices row (MSPs lose
 *     the heads-up before billing kicks in).
 *   - Trial notice not being resolved when status flips off "trialing".
 *   - Subscription deletion failing to resolve a still-open trial notice.
 */
final class StripeWebhookSubscriptionTest extends DatabaseTestCase
{
    public function test_subscription_created_updates_eb_subscriptions_status(): void
    {
        $accountId = 'acct_phc_sub_' . bin2hex(random_bytes(4));
        $stripeSubId = 'sub_phc_' . bin2hex(random_bytes(6));
        $stripeCustId = 'cus_phc_' . bin2hex(random_bytes(6));

        $mspId = Seeder::seedMsp(['stripe_connect_id' => $accountId]);
        $tenantId = Seeder::seedTenant($mspId);
        Capsule::table('eb_tenants')->where('id', $tenantId)->update(['stripe_customer_id' => $stripeCustId]);

        $this->seedSubscription($mspId, $tenantId, $stripeSubId, 'incomplete');

        $event = StripeWebhookFixture::load('customer.subscription.created', [
            'account' => $accountId,
            'data.object.id' => $stripeSubId,
            'data.object.status' => 'active',
            'data.object.customer' => $stripeCustId,
        ]);
        eb_ph_webhook_dispatch_event($event);

        $row = Capsule::table('eb_subscriptions')
            ->where('stripe_subscription_id', $stripeSubId)
            ->first();
        self::assertSame('active', $row->stripe_status);
    }

    public function test_subscription_updated_mirrors_status_to_eb_plan_instances(): void
    {
        $accountId = 'acct_phc_sub_' . bin2hex(random_bytes(4));
        $stripeSubId = 'sub_phc_' . bin2hex(random_bytes(6));
        $stripeCustId = 'cus_phc_' . bin2hex(random_bytes(6));

        $mspId = Seeder::seedMsp(['stripe_connect_id' => $accountId]);
        $tenantId = Seeder::seedTenant($mspId);
        Capsule::table('eb_tenants')->where('id', $tenantId)->update(['stripe_customer_id' => $stripeCustId]);
        $this->seedSubscription($mspId, $tenantId, $stripeSubId, 'active');
        $this->seedPlanInstance($mspId, $tenantId, $stripeSubId, $accountId, 'active');

        $event = StripeWebhookFixture::load('customer.subscription.updated', [
            'account' => $accountId,
            'data.object.id' => $stripeSubId,
            'data.object.status' => 'past_due',
            'data.object.customer' => $stripeCustId,
        ]);
        eb_ph_webhook_dispatch_event($event);

        $instance = Capsule::table('eb_plan_instances')
            ->where('stripe_subscription_id', $stripeSubId)
            ->first();
        self::assertNotNull($instance);
        self::assertSame('past_due', $instance->status);
        self::assertNull($instance->cancelled_at, 'Past-due is not cancellation; cancelled_at must stay null.');
    }

    public function test_subscription_deleted_marks_plan_instance_canceled_with_timestamp(): void
    {
        $accountId = 'acct_phc_sub_' . bin2hex(random_bytes(4));
        $stripeSubId = 'sub_phc_' . bin2hex(random_bytes(6));
        $stripeCustId = 'cus_phc_' . bin2hex(random_bytes(6));

        $mspId = Seeder::seedMsp(['stripe_connect_id' => $accountId]);
        $tenantId = Seeder::seedTenant($mspId);
        Capsule::table('eb_tenants')->where('id', $tenantId)->update(['stripe_customer_id' => $stripeCustId]);
        $this->seedSubscription($mspId, $tenantId, $stripeSubId, 'active');
        $this->seedPlanInstance($mspId, $tenantId, $stripeSubId, $accountId, 'active');

        $event = StripeWebhookFixture::load('customer.subscription.deleted', [
            'account' => $accountId,
            'data.object.id' => $stripeSubId,
            'data.object.status' => 'canceled',
            'data.object.customer' => $stripeCustId,
        ]);
        eb_ph_webhook_dispatch_event($event);

        $instance = Capsule::table('eb_plan_instances')
            ->where('stripe_subscription_id', $stripeSubId)
            ->first();
        self::assertSame('canceled', $instance->status);
        self::assertNotNull($instance->cancelled_at);
    }

    public function test_trial_will_end_creates_partnerhub_notice(): void
    {
        $accountId = 'acct_phc_trial_' . bin2hex(random_bytes(4));
        $stripeSubId = 'sub_trial_' . bin2hex(random_bytes(6));
        $stripeCustId = 'cus_trial_' . bin2hex(random_bytes(6));
        $trialEnd = time() + 86400 * 3;

        $mspId = Seeder::seedMsp(['stripe_connect_id' => $accountId]);
        $tenantId = Seeder::seedTenant($mspId, ['name' => 'Trial Tenant Co']);
        Capsule::table('eb_tenants')->where('id', $tenantId)->update(['stripe_customer_id' => $stripeCustId]);

        $event = StripeWebhookFixture::load('customer.subscription.trial_will_end', [
            'account' => $accountId,
            'data.object.id' => $stripeSubId,
            'data.object.customer' => $stripeCustId,
            'data.object.trial_end' => $trialEnd,
        ]);
        eb_ph_webhook_dispatch_event($event);

        $notice = Capsule::table('eb_partnerhub_notices')
            ->where('msp_id', $mspId)
            ->where('notice_type', 'trial_will_end')
            ->where('stripe_subscription_id', $stripeSubId)
            ->first();
        self::assertNotNull($notice, 'trial_will_end must create a notice row.');
        self::assertSame($tenantId, (int) $notice->tenant_id);
        self::assertSame($stripeCustId, $notice->stripe_customer_id);
        self::assertStringContainsString('Trial Tenant Co', (string) $notice->message);
        self::assertNull($notice->resolved_at);
    }

    public function test_subscription_status_change_off_trialing_resolves_open_notice(): void
    {
        $accountId = 'acct_phc_trial_' . bin2hex(random_bytes(4));
        $stripeSubId = 'sub_trial_' . bin2hex(random_bytes(6));
        $stripeCustId = 'cus_trial_' . bin2hex(random_bytes(6));

        $mspId = Seeder::seedMsp(['stripe_connect_id' => $accountId]);
        $tenantId = Seeder::seedTenant($mspId);
        Capsule::table('eb_tenants')->where('id', $tenantId)->update(['stripe_customer_id' => $stripeCustId]);
        $this->seedSubscription($mspId, $tenantId, $stripeSubId, 'trialing');

        // Pre-existing open trial notice.
        Capsule::table('eb_partnerhub_notices')->insert([
            'msp_id' => $mspId,
            'tenant_id' => $tenantId,
            'notice_key' => 'trial_will_end:' . $stripeSubId . ':0',
            'notice_type' => 'trial_will_end',
            'title' => 'Trial ending soon',
            'message' => 'reminder',
            'stripe_customer_id' => $stripeCustId,
            'stripe_subscription_id' => $stripeSubId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Subscription transitions trialing -> active. Notice should resolve.
        $event = StripeWebhookFixture::load('customer.subscription.updated', [
            'account' => $accountId,
            'data.object.id' => $stripeSubId,
            'data.object.status' => 'active',
            'data.object.customer' => $stripeCustId,
        ]);
        eb_ph_webhook_dispatch_event($event);

        $notice = Capsule::table('eb_partnerhub_notices')
            ->where('msp_id', $mspId)
            ->where('stripe_subscription_id', $stripeSubId)
            ->first();
        self::assertNotNull($notice->resolved_at, 'Notice must resolve once status flips off trialing.');
    }

    public function test_subscription_remains_trialing_does_not_resolve_notice(): void
    {
        $accountId = 'acct_phc_trial_' . bin2hex(random_bytes(4));
        $stripeSubId = 'sub_trial_' . bin2hex(random_bytes(6));
        $stripeCustId = 'cus_trial_' . bin2hex(random_bytes(6));

        $mspId = Seeder::seedMsp(['stripe_connect_id' => $accountId]);
        $tenantId = Seeder::seedTenant($mspId);
        Capsule::table('eb_tenants')->where('id', $tenantId)->update(['stripe_customer_id' => $stripeCustId]);
        $this->seedSubscription($mspId, $tenantId, $stripeSubId, 'trialing');

        Capsule::table('eb_partnerhub_notices')->insert([
            'msp_id' => $mspId,
            'tenant_id' => $tenantId,
            'notice_key' => 'trial_will_end:' . $stripeSubId . ':0',
            'notice_type' => 'trial_will_end',
            'title' => 'Trial ending soon',
            'message' => 'reminder',
            'stripe_customer_id' => $stripeCustId,
            'stripe_subscription_id' => $stripeSubId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $event = StripeWebhookFixture::load('customer.subscription.updated', [
            'account' => $accountId,
            'data.object.id' => $stripeSubId,
            'data.object.status' => 'trialing', // unchanged
            'data.object.customer' => $stripeCustId,
        ]);
        eb_ph_webhook_dispatch_event($event);

        $notice = Capsule::table('eb_partnerhub_notices')
            ->where('msp_id', $mspId)
            ->where('stripe_subscription_id', $stripeSubId)
            ->first();
        self::assertNull($notice->resolved_at, 'Notice must remain open while still trialing.');
    }

    private function seedSubscription(int $mspId, int $tenantId, string $stripeSubId, string $status): void
    {
        $now = date('Y-m-d H:i:s');
        Capsule::table('eb_subscriptions')->insert([
            'msp_id' => $mspId,
            'customer_id' => 0,
            'tenant_id' => $tenantId,
            'plan_id' => 0,
            'stripe_subscription_id' => $stripeSubId,
            'stripe_status' => $status,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function seedPlanInstance(int $mspId, int $tenantId, string $stripeSubId, string $accountId, string $status): void
    {
        $now = date('Y-m-d H:i:s');
        Capsule::table('eb_plan_instances')->insert([
            'msp_id' => $mspId,
            'customer_id' => 0,
            'tenant_id' => $tenantId,
            'comet_user_id' => 'phc-comet-' . bin2hex(random_bytes(3)),
            'plan_id' => 0,
            'plan_version' => 1,
            'stripe_account_id' => $accountId,
            'stripe_customer_id' => 'cus_phc_pi_' . bin2hex(random_bytes(4)),
            'stripe_subscription_id' => $stripeSubId,
            'anchor_date' => date('Y-m-d'),
            'status' => $status,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
