<?php

declare(strict_types=1);

namespace EazyBackup\Tests\Unit;

use EazyBackup\Tests\Support\TestableCatalogService;
use EazyBackup\Tests\Support\UnitTestCase;

/**
 * Coverage for the parameter-shaping contracts inside CatalogService public methods.
 *
 * Source: lib/PartnerHub/CatalogService.php
 *
 * Risks this catches:
 *   - createSubscriptionMulti losing items when the input list isn't already
 *     reindexed (assoc keys would silently drop).
 *   - createSubscriptionMulti including application_fee_percent when zero/null
 *     (Stripe rejects 0 with a 400; null would format as "" and 400 too).
 *   - createSubscriptionMulti not setting trial_period_days when supplied.
 *   - updateProduct sending `description` as the literal string "null" rather
 *     than empty string when explicitly cleared.
 *   - updateProduct/updatePrice sending PHP boolean directly instead of
 *     Stripe's expected "true"/"false" string.
 *   - createProduct + createPrice not forwarding the Idempotency-Key header
 *     (would create duplicates on retries).
 *   - listProducts / listPrices ignoring the limit clamp (Stripe caps at 100).
 */
final class CatalogServiceParameterShapingTest extends UnitTestCase
{
    public function test_create_subscription_multi_flattens_items_to_indexed_form(): void
    {
        $svc = new TestableCatalogService();
        $svc->queueResponse(['id' => 'sub_multi']);

        $svc->createSubscriptionMulti(
            'cus_1',
            [
                ['price' => 'price_storage', 'quantity' => 100],
                ['price' => 'price_seat', 'quantity' => 5],
            ],
            'acct_msp',
            null,
            null
        );
        $call = $svc->lastCall();

        self::assertSame('POST', $call->method);
        self::assertSame('/v1/subscriptions', $call->path);
        self::assertSame('cus_1', $call->params['customer']);
        self::assertSame('charge_automatically', $call->params['collection_method']);
        self::assertSame('create_prorations', $call->params['proration_behavior']);
        self::assertSame('true', $call->params['automatic_tax[enabled]']);

        self::assertSame('price_storage', $call->params['items[0][price]']);
        self::assertSame(100, $call->params['items[0][quantity]']);
        self::assertSame('price_seat', $call->params['items[1][price]']);
        self::assertSame(5, $call->params['items[1][quantity]']);
    }

    public function test_create_subscription_multi_handles_assoc_keyed_items(): void
    {
        $svc = new TestableCatalogService();
        $svc->queueResponse(['id' => 'sub_x']);

        // Pass associative keys that would naively collide with index 0.
        $svc->createSubscriptionMulti(
            'cus_1',
            [
                'storage' => ['price' => 'price_storage'],
                'seat' => ['price' => 'price_seat'],
            ],
            'acct_msp'
        );
        $call = $svc->lastCall();

        self::assertSame('price_storage', $call->params['items[0][price]']);
        self::assertSame('price_seat', $call->params['items[1][price]']);
    }

    public function test_create_subscription_multi_omits_application_fee_when_zero(): void
    {
        $svc = new TestableCatalogService();
        $svc->queueResponse(['id' => 'sub_y']);

        $svc->createSubscriptionMulti('cus_1', [['price' => 'price_x']], 'acct_msp', 0.0);
        $call = $svc->lastCall();

        self::assertArrayNotHasKey('application_fee_percent', $call->params);
    }

    public function test_create_subscription_multi_includes_trial_when_positive(): void
    {
        $svc = new TestableCatalogService();
        $svc->queueResponse(['id' => 'sub_t']);

        $svc->createSubscriptionMulti('cus_1', [['price' => 'price_x']], 'acct_msp', 5.0, 14);
        $call = $svc->lastCall();

        self::assertSame(14, $call->params['trial_period_days']);
        self::assertSame(5.0, $call->params['application_fee_percent']);
    }

    public function test_update_product_active_uses_string_boolean(): void
    {
        $svc = new TestableCatalogService();
        $svc->queueResponse(['id' => 'prod_1', 'active' => false]);

        $svc->updateProduct('prod_1', ['active' => false], 'acct_msp');
        $call = $svc->lastCall();

        self::assertSame('false', $call->params['active'], 'Stripe expects literal "true"/"false" strings, not PHP booleans.');
    }

    public function test_update_product_explicit_null_description_becomes_empty_string(): void
    {
        $svc = new TestableCatalogService();
        $svc->queueResponse(['id' => 'prod_1', 'description' => '']);

        // Caller wants to clear the description.
        $svc->updateProduct('prod_1', ['description' => null], 'acct_msp');
        $call = $svc->lastCall();

        self::assertArrayHasKey('description', $call->params);
        self::assertSame('', $call->params['description']);
        self::assertNotSame('null', $call->params['description'], 'Must not send the literal string "null".');
    }

    public function test_update_product_path_url_encodes_product_id(): void
    {
        $svc = new TestableCatalogService();
        $svc->queueResponse(['id' => 'prod_strange']);

        $svc->updateProduct('prod with space', ['name' => 'X'], 'acct_msp');
        $call = $svc->lastCall();

        self::assertSame('/v1/products/prod%20with%20space', $call->path);
    }

    public function test_update_price_active_uses_string_boolean(): void
    {
        $svc = new TestableCatalogService();
        $svc->queueResponse(['id' => 'price_1', 'active' => true]);

        $svc->updatePrice('price_1', ['active' => true, 'nickname' => 'New nickname'], 'acct_msp');
        $call = $svc->lastCall();

        self::assertSame('true', $call->params['active']);
        self::assertSame('New nickname', $call->params['nickname']);
    }

    public function test_list_prices_active_filter_serialises_to_string(): void
    {
        $svc = new TestableCatalogService();
        $svc->queueResponse(['data' => []]);

        $svc->listPrices('prod_1', 'acct_msp', 50, false);
        $call = $svc->lastCall();

        self::assertSame('GET', $call->method);
        self::assertSame('false', $call->params['active']);
        self::assertSame('prod_1', $call->params['product']);
        self::assertSame(50, $call->params['limit']);
    }

    public function test_list_prices_clamps_limit_to_stripe_maximum(): void
    {
        $svc = new TestableCatalogService();
        $svc->queueResponse(['data' => []]);

        $svc->listPrices('prod_1', 'acct_msp', 5_000);
        $call = $svc->lastCall();

        self::assertSame(100, $call->params['limit'], 'Stripe limit cap is 100 — caller should never get a 400.');
    }

    public function test_list_products_clamps_limit_to_stripe_maximum(): void
    {
        $svc = new TestableCatalogService();
        $svc->queueResponse(['data' => []]);

        $svc->listProducts('acct_msp', 5_000);
        $call = $svc->lastCall();

        self::assertSame(100, $call->params['limit']);
    }

    public function test_create_product_forwards_idempotency_key(): void
    {
        $svc = new TestableCatalogService();
        $svc->queueResponse(['id' => 'prod_idem']);

        $svc->createProduct('Storage', 'A storage product', 'acct_msp', 'create-storage-2026-05');
        $call = $svc->lastCall();

        self::assertNotNull($call->extraHeaders);
        self::assertContains('Idempotency-Key: create-storage-2026-05', $call->extraHeaders);
        self::assertSame('Storage', $call->params['name']);
        self::assertSame('A storage product', $call->params['description']);
    }

    public function test_create_price_forwards_idempotency_key(): void
    {
        $svc = new TestableCatalogService();
        $svc->queueResponse(['id' => 'price_idem']);

        $svc->createPrice([
            'product' => 'prod_1',
            'currency' => 'cad',
            'unit_amount' => 250,
            'recurring[interval]' => 'month',
        ], 'acct_msp', 'price-storage-month-cad-1');
        $call = $svc->lastCall();

        self::assertNotNull($call->extraHeaders);
        self::assertContains('Idempotency-Key: price-storage-month-cad-1', $call->extraHeaders);
    }

    public function test_update_subscription_item_quantity_default_proration_behavior(): void
    {
        $svc = new TestableCatalogService();
        $svc->queueResponse(['id' => 'si_1', 'quantity' => 7]);

        $svc->updateSubscriptionItemQuantity('si_1', 7, 'acct_msp');
        $call = $svc->lastCall();

        self::assertSame('POST', $call->method);
        self::assertSame('/v1/subscription_items/si_1', $call->path);
        self::assertSame(7, $call->params['quantity']);
        self::assertSame('create_prorations', $call->params['proration_behavior']);
    }
}
