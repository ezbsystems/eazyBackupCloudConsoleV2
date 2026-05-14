<?php

declare(strict_types=1);

namespace EazyBackup\Tests\Integration;

use EazyBackup\Tests\Support\DatabaseTestCase;
use EazyBackup\Tests\Support\Seeder;
use EazyBackup\Tests\Support\StripeWebhookFixture;
use WHMCS\Database\Capsule;

/**
 * Coverage for invoice.*, charge.*, and payment_intent.* event handlers.
 *
 * Source: pages/partnerhub/StripeWebhookController.php
 *
 * Risks this catches:
 *   - eb_invoice_cache being inserted twice for the same invoice id (would
 *     double-count revenue on the Overview dashboard).
 *   - tenant_id resolution from stripe_customer_id breaking — would orphan
 *     the invoice and break the per-tenant invoice list.
 *   - tenant_id stored as 0 when no tenant matches (must be NULL per the
 *     April 2026 hardening note in PARTNER_HUB.md).
 *   - invoice.payment_failed not creating an actionable notice (MSPs would
 *     miss collection follow-up).
 *   - Repeated invoice.updated calls leaking duplicate rows.
 *   - charge.* / payment_intent.* not upserting on the same key.
 */
final class StripeWebhookInvoiceChargeTest extends DatabaseTestCase
{
    public function test_invoice_paid_inserts_cache_row_with_resolved_tenant(): void
    {
        $accountId = 'acct_phc_inv_' . bin2hex(random_bytes(4));
        $stripeCustId = 'cus_phc_inv_' . bin2hex(random_bytes(6));
        $invoiceId = 'in_phc_' . bin2hex(random_bytes(6));

        $mspId = Seeder::seedMsp(['stripe_connect_id' => $accountId]);
        $tenantId = Seeder::seedTenant($mspId);
        Capsule::table('eb_tenants')->where('id', $tenantId)->update(['stripe_customer_id' => $stripeCustId]);

        $event = StripeWebhookFixture::load('invoice.paid', [
            'account' => $accountId,
            'data.object.id' => $invoiceId,
            'data.object.customer' => $stripeCustId,
        ]);
        eb_ph_webhook_dispatch_event($event);

        $row = Capsule::table('eb_invoice_cache')
            ->where('stripe_invoice_id', $invoiceId)
            ->first();
        self::assertNotNull($row);
        self::assertSame($tenantId, (int) $row->tenant_id);
        self::assertSame(12500, (int) $row->amount_total);
        self::assertSame(1500, (int) $row->amount_tax);
        self::assertSame('paid', $row->status);
        self::assertSame('cad', $row->currency);
        self::assertSame('https://invoice.stripe.test/in_placeholder', $row->hosted_invoice_url);
    }

    public function test_invoice_for_unknown_customer_stores_null_tenant_id(): void
    {
        $accountId = 'acct_phc_inv_' . bin2hex(random_bytes(4));
        Seeder::seedMsp(['stripe_connect_id' => $accountId]);
        $invoiceId = 'in_phc_unknown_' . bin2hex(random_bytes(6));

        $event = StripeWebhookFixture::load('invoice.paid', [
            'account' => $accountId,
            'data.object.id' => $invoiceId,
            'data.object.customer' => 'cus_does_not_exist',
        ]);
        eb_ph_webhook_dispatch_event($event);

        $row = Capsule::table('eb_invoice_cache')
            ->where('stripe_invoice_id', $invoiceId)
            ->first();
        self::assertNotNull($row);
        self::assertNull($row->tenant_id, 'tenant_id must be NULL (not 0) when no tenant matches.');
    }

    public function test_repeated_invoice_event_upserts_in_place(): void
    {
        $accountId = 'acct_phc_inv_' . bin2hex(random_bytes(4));
        $stripeCustId = 'cus_phc_inv_' . bin2hex(random_bytes(6));
        $invoiceId = 'in_phc_dup_' . bin2hex(random_bytes(6));

        $mspId = Seeder::seedMsp(['stripe_connect_id' => $accountId]);
        $tenantId = Seeder::seedTenant($mspId);
        Capsule::table('eb_tenants')->where('id', $tenantId)->update(['stripe_customer_id' => $stripeCustId]);

        for ($i = 0; $i < 3; $i++) {
            $event = StripeWebhookFixture::load('invoice.paid', [
                'account' => $accountId,
                'data.object.id' => $invoiceId,
                'data.object.customer' => $stripeCustId,
                'data.object.amount_total' => 12500 + $i,
            ]);
            eb_ph_webhook_dispatch_event($event);
        }

        $rows = Capsule::table('eb_invoice_cache')
            ->where('stripe_invoice_id', $invoiceId)
            ->get();
        self::assertCount(1, $rows, 'Repeated deliveries must upsert, not insert duplicates.');
        self::assertSame(12502, (int) $rows[0]->amount_total, 'Latest values win.');
    }

    public function test_invoice_payment_failed_creates_billing_notice(): void
    {
        $accountId = 'acct_phc_failed_' . bin2hex(random_bytes(4));
        $stripeCustId = 'cus_phc_failed_' . bin2hex(random_bytes(6));
        $invoiceId = 'in_failed_' . bin2hex(random_bytes(6));

        $mspId = Seeder::seedMsp(['stripe_connect_id' => $accountId]);
        $tenantId = Seeder::seedTenant($mspId, ['name' => 'Risky Co']);
        Capsule::table('eb_tenants')->where('id', $tenantId)->update(['stripe_customer_id' => $stripeCustId]);

        $event = StripeWebhookFixture::load('invoice.payment_failed', [
            'account' => $accountId,
            'data.object.id' => $invoiceId,
            'data.object.customer' => $stripeCustId,
        ]);
        eb_ph_webhook_dispatch_event($event);

        $notice = Capsule::table('eb_partnerhub_notices')
            ->where('msp_id', $mspId)
            ->where('notice_type', 'payment_failed')
            ->where('notice_key', 'billing_payment_failed_' . $invoiceId)
            ->first();
        self::assertNotNull($notice, 'invoice.payment_failed must create a payment_failed notice.');
        self::assertSame($tenantId, (int) $notice->tenant_id);
        self::assertStringContainsString('Risky Co', (string) $notice->message);
        self::assertNull($notice->resolved_at);
    }

    public function test_charge_succeeded_writes_payment_cache(): void
    {
        $accountId = 'acct_phc_charge_' . bin2hex(random_bytes(4));
        $stripeCustId = 'cus_phc_charge_' . bin2hex(random_bytes(6));
        $paymentIntentId = 'pi_phc_' . bin2hex(random_bytes(6));

        $mspId = Seeder::seedMsp(['stripe_connect_id' => $accountId]);
        $tenantId = Seeder::seedTenant($mspId);
        Capsule::table('eb_tenants')->where('id', $tenantId)->update(['stripe_customer_id' => $stripeCustId]);

        $event = StripeWebhookFixture::load('charge.succeeded', [
            'account' => $accountId,
            'data.object.payment_intent' => $paymentIntentId,
            'data.object.customer' => $stripeCustId,
        ]);
        eb_ph_webhook_dispatch_event($event);

        $row = Capsule::table('eb_payment_cache')
            ->where('stripe_payment_intent_id', $paymentIntentId)
            ->first();
        self::assertNotNull($row);
        self::assertSame($tenantId, (int) $row->tenant_id);
        self::assertSame(12500, (int) $row->amount);
        self::assertSame('succeeded', $row->status);
    }

    public function test_payment_intent_succeeded_writes_payment_cache(): void
    {
        $accountId = 'acct_phc_pi_' . bin2hex(random_bytes(4));
        $stripeCustId = 'cus_phc_pi_' . bin2hex(random_bytes(6));
        $paymentIntentId = 'pi_phc_' . bin2hex(random_bytes(6));

        $mspId = Seeder::seedMsp(['stripe_connect_id' => $accountId]);
        $tenantId = Seeder::seedTenant($mspId);
        Capsule::table('eb_tenants')->where('id', $tenantId)->update(['stripe_customer_id' => $stripeCustId]);

        $event = StripeWebhookFixture::load('payment_intent.succeeded', [
            'account' => $accountId,
            'data.object.id' => $paymentIntentId,
            'data.object.customer' => $stripeCustId,
        ]);
        eb_ph_webhook_dispatch_event($event);

        $row = Capsule::table('eb_payment_cache')
            ->where('stripe_payment_intent_id', $paymentIntentId)
            ->first();
        self::assertSame('succeeded', $row->status);
        self::assertSame($tenantId, (int) $row->tenant_id);
    }

    public function test_payment_intent_for_unknown_customer_stores_null_tenant_id(): void
    {
        $accountId = 'acct_phc_pi_' . bin2hex(random_bytes(4));
        Seeder::seedMsp(['stripe_connect_id' => $accountId]);
        $paymentIntentId = 'pi_unknown_' . bin2hex(random_bytes(6));

        $event = StripeWebhookFixture::load('payment_intent.succeeded', [
            'account' => $accountId,
            'data.object.id' => $paymentIntentId,
            'data.object.customer' => 'cus_no_match',
        ]);
        eb_ph_webhook_dispatch_event($event);

        $row = Capsule::table('eb_payment_cache')
            ->where('stripe_payment_intent_id', $paymentIntentId)
            ->first();
        self::assertNotNull($row);
        self::assertNull($row->tenant_id, 'Unmatched customer must store NULL, not 0.');
    }
}
