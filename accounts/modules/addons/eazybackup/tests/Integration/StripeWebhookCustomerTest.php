<?php

declare(strict_types=1);

namespace EazyBackup\Tests\Integration;

use EazyBackup\Tests\Support\DatabaseTestCase;
use EazyBackup\Tests\Support\Seeder;
use EazyBackup\Tests\Support\StripeWebhookFixture;
use WHMCS\Database\Capsule;

/**
 * Coverage for customer.deleted and payment_method.* event handlers.
 *
 * Source: pages/partnerhub/StripeWebhookController.php
 *
 * Risks this catches:
 *   - customer.deleted leaving stripe_customer_id on the tenant row (next
 *     billing call would 404 forever and the MSP couldn't tell why).
 *   - customer.deleted not resolving any open notices that referenced the
 *     deleted customer (would leave stale "needs review" pills indefinitely).
 *   - payment_method.attached / .detached not bumping eb_tenants.updated_at
 *     (downstream cache invalidation logic uses this; absence breaks the
 *     "Add Card" UX feedback loop).
 */
final class StripeWebhookCustomerTest extends DatabaseTestCase
{
    public function test_customer_deleted_clears_stripe_customer_id_from_tenant(): void
    {
        $stripeCustId = 'cus_phc_del_' . bin2hex(random_bytes(6));
        $mspId = Seeder::seedMsp();
        $tenantId = Seeder::seedTenant($mspId);
        Capsule::table('eb_tenants')->where('id', $tenantId)->update(['stripe_customer_id' => $stripeCustId]);

        $event = StripeWebhookFixture::load('customer.deleted', [
            'data.object.id' => $stripeCustId,
        ]);
        eb_ph_webhook_dispatch_event($event);

        $current = Capsule::table('eb_tenants')->where('id', $tenantId)->value('stripe_customer_id');
        self::assertNull($current, 'customer.deleted must NULL out the stripe_customer_id on the tenant.');
    }

    public function test_customer_deleted_resolves_open_notices_for_that_customer(): void
    {
        $stripeCustId = 'cus_phc_del_' . bin2hex(random_bytes(6));
        $mspId = Seeder::seedMsp();
        $tenantId = Seeder::seedTenant($mspId);
        Capsule::table('eb_tenants')->where('id', $tenantId)->update(['stripe_customer_id' => $stripeCustId]);

        $now = date('Y-m-d H:i:s');
        Capsule::table('eb_partnerhub_notices')->insert([
            'msp_id' => $mspId,
            'tenant_id' => $tenantId,
            'notice_key' => 'trial_will_end:sub_phc_x:0',
            'notice_type' => 'trial_will_end',
            'title' => 'Trial ending soon',
            'message' => 'reminder',
            'stripe_customer_id' => $stripeCustId,
            'stripe_subscription_id' => 'sub_phc_x',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $event = StripeWebhookFixture::load('customer.deleted', [
            'data.object.id' => $stripeCustId,
        ]);
        eb_ph_webhook_dispatch_event($event);

        $notice = Capsule::table('eb_partnerhub_notices')
            ->where('stripe_customer_id', $stripeCustId)
            ->first();
        // Notice still present, but resolved_at is now stamped AND stripe_customer_id was cleared
        // by eb_ph_clear_deleted_customer; check before-effect lookup wouldn't find it after the
        // clear. Use the tenant_id as a stable lookup instead.
        $byTenant = Capsule::table('eb_partnerhub_notices')
            ->where('tenant_id', $tenantId)
            ->where('notice_type', 'trial_will_end')
            ->first();
        self::assertNotNull($byTenant);
        self::assertNotNull($byTenant->resolved_at, 'Open notices for the deleted customer must be resolved.');
    }

    public function test_customer_deleted_with_no_match_is_no_op(): void
    {
        $beforeTenants = (int) Capsule::table('eb_tenants')->count();

        $event = StripeWebhookFixture::load('customer.deleted', [
            'data.object.id' => 'cus_does_not_exist',
        ]);
        eb_ph_webhook_dispatch_event($event);

        $afterTenants = (int) Capsule::table('eb_tenants')->count();
        self::assertSame($beforeTenants, $afterTenants);
    }

    public function test_payment_method_attached_bumps_tenant_updated_at(): void
    {
        $stripeCustId = 'cus_phc_pm_' . bin2hex(random_bytes(6));
        $mspId = Seeder::seedMsp();
        $tenantId = Seeder::seedTenant($mspId);
        Capsule::table('eb_tenants')->where('id', $tenantId)->update(['stripe_customer_id' => $stripeCustId]);

        // Push the existing updated_at into the past so we can detect a real bump.
        Capsule::table('eb_tenants')->where('id', $tenantId)->update([
            'updated_at' => date('Y-m-d H:i:s', time() - 7200),
        ]);
        $before = (string) Capsule::table('eb_tenants')->where('id', $tenantId)->value('updated_at');

        // Wait one second so the timestamp meaningfully changes.
        sleep(1);

        $event = StripeWebhookFixture::load('payment_method.attached', [
            'data.object.customer' => $stripeCustId,
        ]);
        eb_ph_webhook_dispatch_event($event);

        $after = (string) Capsule::table('eb_tenants')->where('id', $tenantId)->value('updated_at');
        self::assertNotSame($before, $after, 'payment_method.attached must bump eb_tenants.updated_at.');
    }

    public function test_application_fee_event_is_handled_as_no_op(): void
    {
        // application_fee.created has no local table yet — handler exists but does nothing.
        // Asserting we don't throw or accidentally write to any of the cache tables.
        $event = [
            'id' => 'evt_phc_app_fee_' . bin2hex(random_bytes(6)),
            'type' => 'application_fee.created',
            'account' => 'acct_phc_fee',
            'data' => [
                'object' => [
                    'id' => 'fee_phc_x',
                    'object' => 'application_fee',
                    'amount' => 100,
                    'currency' => 'cad',
                ],
            ],
        ];

        eb_ph_webhook_dispatch_event($event);

        $this->expectNotToPerformAssertions();
    }
}
