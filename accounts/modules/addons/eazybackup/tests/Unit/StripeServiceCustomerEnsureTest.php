<?php

declare(strict_types=1);

namespace EazyBackup\Tests\Unit;

use EazyBackup\Tests\Support\DatabaseTestCase;
use EazyBackup\Tests\Support\Seeder;
use EazyBackup\Tests\Support\TestableStripeService;
use WHMCS\Database\Capsule;

/**
 * Coverage for StripeService::ensureStripeCustomerFor.
 *
 * Source: lib/PartnerHub/StripeService.php
 *
 * Risks this catches:
 *   - A second call hitting Stripe again instead of returning the cached
 *     stripe_customer_id from eb_tenants (would create duplicate Stripe
 *     customers and orphan one of them).
 *   - The newly-minted Stripe customer id NOT being persisted back to
 *     eb_tenants (next call would re-create infinitely).
 *   - The Stripe-Account header not being forwarded for connected-account
 *     customers (would create the customer on the platform account by
 *     mistake).
 *   - Throwing on a missing tenant (so the controller surfaces a real error
 *     rather than silently no-oping).
 */
final class StripeServiceCustomerEnsureTest extends DatabaseTestCase
{
    public function test_returns_existing_stripe_customer_id_without_calling_stripe(): void
    {
        $mspId = Seeder::seedMsp();
        $tenantId = Seeder::seedTenant($mspId);
        Capsule::table('eb_tenants')->where('id', $tenantId)->update([
            'stripe_customer_id' => 'cus_already_set',
        ]);

        $svc = new TestableStripeService();
        $result = $svc->ensureStripeCustomerFor($tenantId, 'acct_test');

        self::assertSame('cus_already_set', $result);
        self::assertSame([], $svc->calls, 'No Stripe API calls should fire when customer id is already cached.');
    }

    public function test_creates_customer_when_missing_and_persists_id_to_db(): void
    {
        $mspId = Seeder::seedMsp();
        $tenantId = Seeder::seedTenant($mspId, [
            'name' => 'Acme Holdings Ltd',
            'contact_email' => 'billing@acme.test',
        ]);

        $svc = new TestableStripeService();
        $svc->queueResponse(['id' => 'cus_freshly_created']);

        $result = $svc->ensureStripeCustomerFor($tenantId, 'acct_msp_connect');

        self::assertSame('cus_freshly_created', $result);

        // Stripe API call assertions
        $call = $svc->lastCall();
        self::assertNotNull($call);
        self::assertSame('POST', $call->method);
        self::assertSame('/v1/customers', $call->path);
        self::assertSame('acct_msp_connect', $call->stripeAccount, 'Stripe-Account header must be forwarded.');
        self::assertSame('Acme Holdings Ltd', $call->params['name']);
        self::assertSame('billing@acme.test', $call->params['email']);

        // Persistence assertion — next call must short-circuit.
        $persisted = (string) (Capsule::table('eb_tenants')
            ->where('id', $tenantId)
            ->value('stripe_customer_id') ?? '');
        self::assertSame('cus_freshly_created', $persisted);
    }

    public function test_second_call_after_create_does_not_hit_stripe_again(): void
    {
        $mspId = Seeder::seedMsp();
        $tenantId = Seeder::seedTenant($mspId);

        $svc = new TestableStripeService();
        $svc->queueResponse(['id' => 'cus_first_call']);

        $first = $svc->ensureStripeCustomerFor($tenantId, 'acct_x');
        $second = $svc->ensureStripeCustomerFor($tenantId, 'acct_x');

        self::assertSame('cus_first_call', $first);
        self::assertSame('cus_first_call', $second);
        self::assertCount(1, $svc->calls, 'ensureStripeCustomerFor must be idempotent across repeated invocations.');
    }

    public function test_missing_tenant_throws(): void
    {
        $svc = new TestableStripeService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Tenant not found');
        $svc->ensureStripeCustomerFor(999_999_999, 'acct_x');
    }

    public function test_falls_back_to_synthetic_name_when_tenant_name_blank(): void
    {
        $mspId = Seeder::seedMsp();
        $tenantId = Seeder::seedTenant($mspId, ['name' => '', 'contact_name' => '', 'contact_email' => '']);

        $svc = new TestableStripeService();
        $svc->queueResponse(['id' => 'cus_synthetic']);

        $svc->ensureStripeCustomerFor($tenantId, null);
        $call = $svc->lastCall();

        self::assertNotNull($call);
        self::assertSame('Tenant ' . $tenantId, $call->params['name']);
        self::assertNull($call->params['email']);
        self::assertNull($call->stripeAccount, 'Null connect id must not turn into the literal string "null".');
    }
}
