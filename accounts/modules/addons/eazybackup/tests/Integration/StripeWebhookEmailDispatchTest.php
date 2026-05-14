<?php

declare(strict_types=1);

namespace EazyBackup\Tests\Integration;

use EazyBackup\Tests\Support\DatabaseTestCase;
use EazyBackup\Tests\Support\Seeder;
use EazyBackup\Tests\Support\StripeWebhookFixture;
use PartnerHub\MailService;
use WHMCS\Database\Capsule;

/**
 * Coverage for the email side-effects of the webhook dispatcher.
 *
 * Source: pages/partnerhub/StripeWebhookController.php
 *
 * Phase C exercised the dispatcher's DB writes; this file pins the EMAIL
 * dispatches via the Phase C2 MailService transport seam.
 *
 * Risks this catches:
 *   - invoice.payment_failed quietly skipping the customer email (MSPs would
 *     lose a key dunning signal).
 *   - invoice.created NOT firing the new_invoice email (customers wouldn't
 *     know they have an invoice to view).
 *   - customer.subscription.updated/deleted not firing subscription_changed
 *     (customers stay confused about state).
 *   - The wrong template key being used (e.g. payment_failed firing under
 *     the welcome template).
 *   - Tenant context (name, email) being lost between dispatch and render.
 */
final class StripeWebhookEmailDispatchTest extends DatabaseTestCase
{
    /** @var array<int,array> Capture array reset per test. */
    private array $sentMessages = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->sentMessages = [];
        MailService::setTransport(function (array $message): array {
            $this->sentMessages[] = $message;
            return ['ok' => true, 'spy' => true];
        });
    }

    protected function tearDown(): void
    {
        MailService::clearTransport();
        parent::tearDown();
    }

    public function test_invoice_payment_failed_dispatches_payment_failed_email_to_tenant(): void
    {
        $accountId = 'acct_phc2_email_failed_' . bin2hex(random_bytes(4));
        $stripeCustId = 'cus_phc2_email_failed_' . bin2hex(random_bytes(6));
        $invoiceId = 'in_phc2_failed_' . bin2hex(random_bytes(6));

        $mspId = Seeder::seedMsp(['stripe_connect_id' => $accountId]);
        $tenantId = Seeder::seedTenant($mspId, [
            'name' => 'Acme Holdings',
            'contact_email' => 'billing@acme.test',
            'contact_name' => 'Sam Buyer',
        ]);
        Capsule::table('eb_tenants')->where('id', $tenantId)->update(['stripe_customer_id' => $stripeCustId]);

        $event = StripeWebhookFixture::load('invoice.payment_failed', [
            'account' => $accountId,
            'data.object.id' => $invoiceId,
            'data.object.customer' => $stripeCustId,
            'data.object.amount_due' => 12500,
            'data.object.hosted_invoice_url' => 'https://invoice.stripe.test/' . $invoiceId,
        ]);

        eb_ph_webhook_dispatch_event($event);

        self::assertCount(1, $this->sentMessages, 'payment_failed must dispatch exactly one email.');
        $msg = $this->sentMessages[0];
        self::assertSame('payment_failed', $msg['key']);
        self::assertSame('billing@acme.test', $msg['to']);
        self::assertSame($mspId, $msg['msp_id']);

        // The tenant's contact name should have been threaded through into the
        // template vars (used by {{ customer.name }} tokens).
        self::assertSame('Sam Buyer', $msg['vars']['customer']['name']);
        self::assertSame('billing@acme.test', $msg['vars']['customer']['email']);

        // Invoice context is forwarded for body interpolation.
        self::assertSame($invoiceId, $msg['vars']['invoice']['id']);
        self::assertSame('12500', $msg['vars']['invoice']['amount']);
        self::assertSame('https://invoice.stripe.test/' . $invoiceId, $msg['vars']['invoice']['url']);
    }

    public function test_invoice_payment_failed_for_unknown_customer_does_not_dispatch_email(): void
    {
        $accountId = 'acct_phc2_email_orphan_' . bin2hex(random_bytes(4));
        Seeder::seedMsp(['stripe_connect_id' => $accountId]);

        $event = StripeWebhookFixture::load('invoice.payment_failed', [
            'account' => $accountId,
            'data.object.id' => 'in_orphan_' . bin2hex(random_bytes(4)),
            'data.object.customer' => 'cus_does_not_exist',
        ]);

        eb_ph_webhook_dispatch_event($event);

        self::assertSame([], $this->sentMessages, 'No tenant => no email.');
    }

    public function test_invoice_created_dispatches_new_invoice_email(): void
    {
        $accountId = 'acct_phc2_email_new_' . bin2hex(random_bytes(4));
        $stripeCustId = 'cus_phc2_email_new_' . bin2hex(random_bytes(6));
        $invoiceId = 'in_phc2_new_' . bin2hex(random_bytes(6));

        $mspId = Seeder::seedMsp(['stripe_connect_id' => $accountId]);
        $tenantId = Seeder::seedTenant($mspId, ['contact_email' => 'pay@acme.test']);
        Capsule::table('eb_tenants')->where('id', $tenantId)->update(['stripe_customer_id' => $stripeCustId]);

        // We need an invoice.created event — load the invoice.paid fixture and
        // override the type. The handler reads `event.type` for routing.
        $event = StripeWebhookFixture::load('invoice.paid', [
            'type' => 'invoice.created',
            'account' => $accountId,
            'data.object.id' => $invoiceId,
            'data.object.customer' => $stripeCustId,
        ]);

        eb_ph_webhook_dispatch_event($event);

        self::assertCount(1, $this->sentMessages);
        $msg = $this->sentMessages[0];
        self::assertSame('new_invoice', $msg['key']);
        self::assertSame('pay@acme.test', $msg['to']);
        self::assertSame($invoiceId, $msg['vars']['invoice']['id']);
    }

    public function test_invoice_paid_does_not_dispatch_an_email(): void
    {
        // invoice.paid only writes to eb_invoice_cache; no email is sent.
        $accountId = 'acct_phc2_email_paid_' . bin2hex(random_bytes(4));
        $stripeCustId = 'cus_phc2_email_paid_' . bin2hex(random_bytes(6));
        $mspId = Seeder::seedMsp(['stripe_connect_id' => $accountId]);
        $tenantId = Seeder::seedTenant($mspId);
        Capsule::table('eb_tenants')->where('id', $tenantId)->update(['stripe_customer_id' => $stripeCustId]);

        $event = StripeWebhookFixture::load('invoice.paid', [
            'account' => $accountId,
            'data.object.id' => 'in_paid_no_email_' . bin2hex(random_bytes(4)),
            'data.object.customer' => $stripeCustId,
        ]);

        eb_ph_webhook_dispatch_event($event);

        self::assertSame([], $this->sentMessages, 'invoice.paid must not fire any email.');
    }

    public function test_subscription_updated_dispatches_subscription_changed_email(): void
    {
        $accountId = 'acct_phc2_email_sub_' . bin2hex(random_bytes(4));
        $stripeCustId = 'cus_phc2_email_sub_' . bin2hex(random_bytes(6));
        $stripeSubId = 'sub_phc2_email_' . bin2hex(random_bytes(6));

        $mspId = Seeder::seedMsp(['stripe_connect_id' => $accountId]);
        $tenantId = Seeder::seedTenant($mspId, ['contact_email' => 'subs@acme.test']);
        Capsule::table('eb_tenants')->where('id', $tenantId)->update(['stripe_customer_id' => $stripeCustId]);
        Capsule::table('eb_subscriptions')->insert([
            'msp_id' => $mspId,
            'customer_id' => 0,
            'tenant_id' => $tenantId,
            'plan_id' => 0,
            'stripe_subscription_id' => $stripeSubId,
            'stripe_status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $event = StripeWebhookFixture::load('customer.subscription.updated', [
            'account' => $accountId,
            'data.object.id' => $stripeSubId,
            'data.object.status' => 'past_due',
            'data.object.customer' => $stripeCustId,
        ]);

        eb_ph_webhook_dispatch_event($event);

        self::assertCount(1, $this->sentMessages);
        $msg = $this->sentMessages[0];
        self::assertSame('subscription_changed', $msg['key']);
        self::assertSame('subs@acme.test', $msg['to']);
        self::assertSame($stripeSubId, $msg['vars']['subscription']['id']);
        self::assertSame('past_due', $msg['vars']['subscription']['status']);
    }

    public function test_subscription_created_does_not_dispatch_subscription_changed_email(): void
    {
        // Only updated/deleted fire the email — created is silent so the customer
        // doesn't get emailed about a state they JUST signed up for.
        $accountId = 'acct_phc2_email_sub_' . bin2hex(random_bytes(4));
        $stripeCustId = 'cus_phc2_email_sub_' . bin2hex(random_bytes(6));
        $stripeSubId = 'sub_phc2_email_new_' . bin2hex(random_bytes(6));
        $mspId = Seeder::seedMsp(['stripe_connect_id' => $accountId]);
        $tenantId = Seeder::seedTenant($mspId);
        Capsule::table('eb_tenants')->where('id', $tenantId)->update(['stripe_customer_id' => $stripeCustId]);
        Capsule::table('eb_subscriptions')->insert([
            'msp_id' => $mspId,
            'customer_id' => 0,
            'tenant_id' => $tenantId,
            'plan_id' => 0,
            'stripe_subscription_id' => $stripeSubId,
            'stripe_status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $event = StripeWebhookFixture::load('customer.subscription.created', [
            'account' => $accountId,
            'data.object.id' => $stripeSubId,
            'data.object.status' => 'active',
            'data.object.customer' => $stripeCustId,
        ]);

        eb_ph_webhook_dispatch_event($event);

        self::assertSame([], $this->sentMessages);
    }

    public function test_subscription_deleted_dispatches_subscription_changed_email(): void
    {
        $accountId = 'acct_phc2_email_sub_' . bin2hex(random_bytes(4));
        $stripeCustId = 'cus_phc2_email_sub_' . bin2hex(random_bytes(6));
        $stripeSubId = 'sub_phc2_email_del_' . bin2hex(random_bytes(6));
        $mspId = Seeder::seedMsp(['stripe_connect_id' => $accountId]);
        $tenantId = Seeder::seedTenant($mspId, ['contact_email' => 'goodbye@acme.test']);
        Capsule::table('eb_tenants')->where('id', $tenantId)->update(['stripe_customer_id' => $stripeCustId]);
        Capsule::table('eb_subscriptions')->insert([
            'msp_id' => $mspId,
            'customer_id' => 0,
            'tenant_id' => $tenantId,
            'plan_id' => 0,
            'stripe_subscription_id' => $stripeSubId,
            'stripe_status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $event = StripeWebhookFixture::load('customer.subscription.deleted', [
            'account' => $accountId,
            'data.object.id' => $stripeSubId,
            'data.object.status' => 'canceled',
            'data.object.customer' => $stripeCustId,
        ]);

        eb_ph_webhook_dispatch_event($event);

        self::assertCount(1, $this->sentMessages);
        self::assertSame('subscription_changed', $this->sentMessages[0]['key']);
        self::assertSame('goodbye@acme.test', $this->sentMessages[0]['to']);
        self::assertSame('canceled', $this->sentMessages[0]['vars']['subscription']['status']);
    }

    public function test_subscription_event_with_no_tenant_does_not_dispatch_email(): void
    {
        $accountId = 'acct_phc2_email_sub_' . bin2hex(random_bytes(4));
        $stripeSubId = 'sub_phc2_email_orphan_' . bin2hex(random_bytes(6));
        $mspId = Seeder::seedMsp(['stripe_connect_id' => $accountId]);
        Capsule::table('eb_subscriptions')->insert([
            'msp_id' => $mspId,
            'customer_id' => 0,
            'tenant_id' => null,
            'plan_id' => 0,
            'stripe_subscription_id' => $stripeSubId,
            'stripe_status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $event = StripeWebhookFixture::load('customer.subscription.updated', [
            'account' => $accountId,
            'data.object.id' => $stripeSubId,
            'data.object.status' => 'active',
            'data.object.customer' => 'cus_does_not_exist',
        ]);

        eb_ph_webhook_dispatch_event($event);

        self::assertSame([], $this->sentMessages);
    }

    public function test_payout_paid_does_not_fire_any_email(): void
    {
        $accountId = 'acct_phc2_email_payout_' . bin2hex(random_bytes(4));
        Seeder::seedMsp(['stripe_connect_id' => $accountId]);

        $event = StripeWebhookFixture::load('payout.paid', [
            'account' => $accountId,
            'data.object.id' => 'po_phc2_email_' . bin2hex(random_bytes(4)),
        ]);

        eb_ph_webhook_dispatch_event($event);

        self::assertSame([], $this->sentMessages, 'Payout events are silent — no email dispatch.');
    }
}
