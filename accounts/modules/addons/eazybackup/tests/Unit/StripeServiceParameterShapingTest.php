<?php

declare(strict_types=1);

namespace EazyBackup\Tests\Unit;

use EazyBackup\Tests\Support\TestableStripeService;
use EazyBackup\Tests\Support\UnitTestCase;

/**
 * Coverage for the parameter-shaping contracts inside StripeService public methods.
 *
 * Source: lib/PartnerHub/StripeService.php
 *
 * Risks this catches:
 *   - createSubscription dropping `application_fee_percent` when a positive value is supplied.
 *   - createSubscription forwarding zero/negative fees (Stripe will reject; we should suppress).
 *   - updateCustomer flattening `address` correctly into `address[<key>]` form Stripe expects.
 *   - updateCustomer not normalising email to lower case / country to upper case.
 *   - pause/resume sending the wrong shape for `pause_collection`.
 *   - cancelSubscription / detachPaymentMethod / updateCustomer raising on empty ids
 *     (defensive guards that protect against constructing a `/v1/subscriptions/` URL
 *     that hits the listing endpoint).
 *   - createUsageRecord forwarding the Idempotency-Key header when supplied.
 */
final class StripeServiceParameterShapingTest extends UnitTestCase
{
    public function test_create_subscription_includes_application_fee_when_positive(): void
    {
        $svc = new TestableStripeService();
        $svc->queueResponse(['id' => 'sub_x', 'status' => 'active']);

        $svc->createSubscription('cus_1', 'price_1', 'acct_msp', 4.5);
        $call = $svc->lastCall();

        self::assertSame('POST', $call->method);
        self::assertSame('/v1/subscriptions', $call->path);
        self::assertSame('acct_msp', $call->stripeAccount);
        self::assertSame('cus_1', $call->params['customer']);
        self::assertSame('price_1', $call->params['items[0][price]']);
        self::assertSame('true', $call->params['automatic_tax[enabled]']);
        self::assertSame('default_incomplete', $call->params['payment_behavior']);
        self::assertSame(4.5, $call->params['application_fee_percent']);
    }

    public function test_create_subscription_omits_application_fee_when_null(): void
    {
        $svc = new TestableStripeService();
        $svc->queueResponse(['id' => 'sub_y']);

        $svc->createSubscription('cus_1', 'price_1', 'acct_msp', null);
        $call = $svc->lastCall();

        self::assertArrayNotHasKey(
            'application_fee_percent',
            $call->params,
            'Null fee must not be sent to Stripe at all.'
        );
    }

    public function test_create_subscription_omits_application_fee_when_zero(): void
    {
        $svc = new TestableStripeService();
        $svc->queueResponse(['id' => 'sub_z']);

        $svc->createSubscription('cus_1', 'price_1', 'acct_msp', 0.0);
        $call = $svc->lastCall();

        self::assertArrayNotHasKey('application_fee_percent', $call->params);
    }

    public function test_update_customer_normalises_email_and_country(): void
    {
        $svc = new TestableStripeService();
        $svc->queueResponse(['id' => 'cus_norm']);

        $svc->updateCustomer('cus_norm', [
            'name' => '  Acme Co  ',
            'email' => '  Mixed.CASE@Example.TEST  ',
            'phone' => '+1 555 0100',
            'address' => [
                'line1' => '123 Main',
                'city' => 'Vancouver',
                'state' => 'BC',
                'postal_code' => 'V5K0A1',
                'country' => 'ca',
            ],
        ], 'acct_target');

        $call = $svc->lastCall();
        self::assertSame('POST', $call->method);
        self::assertSame('/v1/customers/cus_norm', $call->path);
        self::assertSame('acct_target', $call->stripeAccount);

        // Trim + casing
        self::assertSame('Acme Co', $call->params['name']);
        self::assertSame('mixed.case@example.test', $call->params['email']);
        self::assertSame('+1 555 0100', $call->params['phone']);

        // Address flattened to Stripe's bracketed form
        self::assertSame('123 Main', $call->params['address[line1]']);
        self::assertSame('Vancouver', $call->params['address[city]']);
        self::assertSame('BC', $call->params['address[state]']);
        self::assertSame('V5K0A1', $call->params['address[postal_code]']);
        self::assertSame('CA', $call->params['address[country]']);
    }

    public function test_update_customer_with_empty_fields_falls_back_to_retrieve(): void
    {
        $svc = new TestableStripeService();
        $svc->queueResponse(['id' => 'cus_x', 'object' => 'customer']);

        $result = $svc->updateCustomer('cus_x', [], 'acct_target');
        $call = $svc->lastCall();

        self::assertSame('GET', $call->method);
        self::assertSame('/v1/customers/cus_x', $call->path);
        self::assertSame('cus_x', $result['id']);
    }

    public function test_update_customer_throws_on_empty_id(): void
    {
        $svc = new TestableStripeService();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Missing Stripe customer id');
        $svc->updateCustomer('   ', ['name' => 'X']);
    }

    public function test_pause_subscription_sets_void_behavior(): void
    {
        $svc = new TestableStripeService();
        $svc->queueResponse(['id' => 'sub_p', 'pause_collection' => ['behavior' => 'void']]);

        $svc->pauseSubscription('sub_p', 'acct_msp');
        $call = $svc->lastCall();

        self::assertSame('POST', $call->method);
        self::assertSame('/v1/subscriptions/sub_p', $call->path);
        self::assertSame('acct_msp', $call->stripeAccount);
        self::assertSame('void', $call->params['pause_collection[behavior]']);
    }

    public function test_resume_subscription_clears_pause_collection(): void
    {
        $svc = new TestableStripeService();
        $svc->queueResponse(['id' => 'sub_r', 'pause_collection' => null]);

        $svc->resumeSubscription('sub_r', 'acct_msp');
        $call = $svc->lastCall();

        // Stripe's wire convention: passing pause_collection='' (empty string) clears it.
        self::assertSame('', $call->params['pause_collection']);
    }

    public function test_cancel_subscription_throws_on_empty_id(): void
    {
        $svc = new TestableStripeService();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Missing Stripe subscription id');
        $svc->cancelSubscription('  ');
    }

    public function test_detach_payment_method_throws_on_empty_id(): void
    {
        $svc = new TestableStripeService();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Missing payment method id');
        $svc->detachPaymentMethod('');
    }

    public function test_create_refund_includes_amount_when_positive(): void
    {
        $svc = new TestableStripeService();
        $svc->queueResponse(['id' => 're_1']);

        $svc->createRefund('pi_1', 1500, 'acct_msp');
        $call = $svc->lastCall();

        self::assertSame('POST', $call->method);
        self::assertSame('/v1/refunds', $call->path);
        self::assertSame('pi_1', $call->params['payment_intent']);
        self::assertSame(1500, $call->params['amount']);
    }

    public function test_create_refund_omits_amount_when_null(): void
    {
        $svc = new TestableStripeService();
        $svc->queueResponse(['id' => 're_2']);

        $svc->createRefund('pi_1', null, 'acct_msp');
        $call = $svc->lastCall();

        self::assertArrayNotHasKey('amount', $call->params, 'Full refund must omit `amount`.');
    }

    public function test_create_usage_record_forwards_idempotency_key_header(): void
    {
        $svc = new TestableStripeService();
        $svc->queueResponse(['id' => 'usage_1']);

        $svc->createUsageRecord('si_1', 42, 1234567890, 'acct_msp', 'idem-abc-123');
        $call = $svc->lastCall();

        self::assertSame('POST', $call->method);
        self::assertSame('/v1/subscription_items/si_1/usage_records', $call->path);
        self::assertSame(42, $call->params['quantity']);
        self::assertSame('set', $call->params['action']);
        self::assertNotNull($call->extraHeaders);
        self::assertContains('Idempotency-Key: idem-abc-123', $call->extraHeaders);
    }

    public function test_create_usage_record_omits_idempotency_header_when_not_supplied(): void
    {
        $svc = new TestableStripeService();
        $svc->queueResponse(['id' => 'usage_2']);

        $svc->createUsageRecord('si_2', 1, 0, null, null);
        $call = $svc->lastCall();

        self::assertNull($call->extraHeaders);
    }

    public function test_create_price_metered_sets_recurring_usage_type(): void
    {
        $svc = new TestableStripeService();
        $svc->queueResponse(['id' => 'price_metered']);

        $svc->createPrice('prod_1', 'CAD', 250, 'month', true, 'acct_msp');
        $call = $svc->lastCall();

        self::assertSame('cad', $call->params['currency']);
        self::assertSame(250, $call->params['unit_amount']);
        self::assertSame('month', $call->params['recurring[interval]']);
        self::assertSame('metered', $call->params['recurring[usage_type]']);
        self::assertSame('sum', $call->params['recurring[aggregate_usage]']);
    }

    public function test_create_price_per_unit_omits_metered_keys(): void
    {
        $svc = new TestableStripeService();
        $svc->queueResponse(['id' => 'price_per_unit']);

        $svc->createPrice('prod_1', 'usd', 999, 'year', false, 'acct_msp');
        $call = $svc->lastCall();

        self::assertSame('usd', $call->params['currency']);
        self::assertSame(999, $call->params['unit_amount']);
        self::assertSame('year', $call->params['recurring[interval]']);
        self::assertArrayNotHasKey('recurring[usage_type]', $call->params);
        self::assertArrayNotHasKey('recurring[aggregate_usage]', $call->params);
    }
}
