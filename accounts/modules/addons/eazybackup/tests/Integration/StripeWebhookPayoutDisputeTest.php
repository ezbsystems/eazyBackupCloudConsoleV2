<?php

declare(strict_types=1);

namespace EazyBackup\Tests\Integration;

use EazyBackup\Tests\Support\DatabaseTestCase;
use EazyBackup\Tests\Support\Seeder;
use EazyBackup\Tests\Support\StripeWebhookFixture;
use WHMCS\Database\Capsule;

/**
 * Coverage for payout.* and charge.dispute.* event handlers.
 *
 * Source: pages/partnerhub/StripeWebhookController.php
 *
 * Risks this catches:
 *   - eb_payouts upserting into the wrong msp_id (would surface another MSP's
 *     payouts in Money → Payouts).
 *   - Repeated payout deliveries duplicating cache rows.
 *   - charge.dispute.created NOT producing a dispute_opened notice (MSPs miss
 *     evidence-submission deadlines).
 *   - charge.dispute.closed leaving the notice unresolved (Partner Hub UI
 *     shows a stale "needs review" badge).
 *   - Dispute payout/disputes for unknown account_ids leaking into other
 *     MSPs' caches.
 */
final class StripeWebhookPayoutDisputeTest extends DatabaseTestCase
{
    public function test_payout_paid_inserts_payout_cache_with_correct_msp(): void
    {
        $accountId = 'acct_phc_payout_' . bin2hex(random_bytes(4));
        $payoutId = 'po_phc_' . bin2hex(random_bytes(6));
        $mspId = Seeder::seedMsp(['stripe_connect_id' => $accountId]);

        $event = StripeWebhookFixture::load('payout.paid', [
            'account' => $accountId,
            'data.object.id' => $payoutId,
        ]);
        eb_ph_webhook_dispatch_event($event);

        $row = Capsule::table('eb_payouts')->where('stripe_payout_id', $payoutId)->first();
        self::assertNotNull($row);
        self::assertSame($mspId, (int) $row->msp_id);
        self::assertSame(25000, (int) $row->amount);
        self::assertSame('paid', $row->status);
        self::assertSame('cad', $row->currency);
    }

    public function test_repeated_payout_event_upserts_in_place(): void
    {
        $accountId = 'acct_phc_payout_' . bin2hex(random_bytes(4));
        $payoutId = 'po_phc_dup_' . bin2hex(random_bytes(6));
        Seeder::seedMsp(['stripe_connect_id' => $accountId]);

        for ($i = 0; $i < 3; $i++) {
            $event = StripeWebhookFixture::load('payout.paid', [
                'account' => $accountId,
                'data.object.id' => $payoutId,
                'data.object.amount' => 25000 + ($i * 100),
            ]);
            eb_ph_webhook_dispatch_event($event);
        }

        $rows = Capsule::table('eb_payouts')->where('stripe_payout_id', $payoutId)->get();
        self::assertCount(1, $rows);
        self::assertSame(25200, (int) $rows[0]->amount);
    }

    public function test_payout_for_unknown_account_is_no_op(): void
    {
        $orphanAccount = 'acct_orphan_' . bin2hex(random_bytes(4));
        $payoutId = 'po_orphan_' . bin2hex(random_bytes(6));

        $event = StripeWebhookFixture::load('payout.paid', [
            'account' => $orphanAccount,
            'data.object.id' => $payoutId,
        ]);
        eb_ph_webhook_dispatch_event($event);

        $exists = Capsule::table('eb_payouts')->where('stripe_payout_id', $payoutId)->exists();
        self::assertFalse($exists, 'Payout for unrecognised account must not be cached.');
    }

    public function test_dispute_created_inserts_dispute_and_creates_notice(): void
    {
        $accountId = 'acct_phc_dispute_' . bin2hex(random_bytes(4));
        $disputeId = 'dp_phc_' . bin2hex(random_bytes(6));
        $mspId = Seeder::seedMsp(['stripe_connect_id' => $accountId]);

        $event = StripeWebhookFixture::load('charge.dispute.created', [
            'account' => $accountId,
            'data.object.id' => $disputeId,
        ]);
        eb_ph_webhook_dispatch_event($event);

        $dispute = Capsule::table('eb_disputes')->where('stripe_dispute_id', $disputeId)->first();
        self::assertNotNull($dispute);
        self::assertSame($mspId, (int) $dispute->msp_id);
        self::assertSame('warning_needs_response', $dispute->status);
        self::assertSame('fraudulent', $dispute->reason);
        self::assertSame(1738367999, (int) $dispute->evidence_due_by);

        $notice = Capsule::table('eb_partnerhub_notices')
            ->where('msp_id', $mspId)
            ->where('notice_type', 'dispute_opened')
            ->where('notice_key', 'billing_dispute_opened_' . $disputeId)
            ->first();
        self::assertNotNull($notice, 'charge.dispute.created must create a dispute_opened notice.');
        self::assertNull($notice->resolved_at);
        self::assertStringContainsString('Stripe dispute', (string) $notice->message);
    }

    public function test_dispute_closed_updates_dispute_and_resolves_notice(): void
    {
        $accountId = 'acct_phc_dispute_' . bin2hex(random_bytes(4));
        $disputeId = 'dp_phc_close_' . bin2hex(random_bytes(6));
        $mspId = Seeder::seedMsp(['stripe_connect_id' => $accountId]);

        // Open the dispute first, then close it.
        $createEvent = StripeWebhookFixture::load('charge.dispute.created', [
            'account' => $accountId,
            'data.object.id' => $disputeId,
        ]);
        eb_ph_webhook_dispatch_event($createEvent);

        $closeEvent = StripeWebhookFixture::load('charge.dispute.closed', [
            'account' => $accountId,
            'data.object.id' => $disputeId,
        ]);
        eb_ph_webhook_dispatch_event($closeEvent);

        $dispute = Capsule::table('eb_disputes')->where('stripe_dispute_id', $disputeId)->first();
        self::assertSame('lost', $dispute->status);

        $notice = Capsule::table('eb_partnerhub_notices')
            ->where('msp_id', $mspId)
            ->where('notice_key', 'billing_dispute_opened_' . $disputeId)
            ->first();
        self::assertNotNull($notice->resolved_at, 'charge.dispute.closed must resolve the dispute_opened notice.');
    }

    public function test_dispute_closed_without_prior_open_is_no_op_for_notice(): void
    {
        $accountId = 'acct_phc_dispute_' . bin2hex(random_bytes(4));
        $disputeId = 'dp_phc_naked_close_' . bin2hex(random_bytes(6));
        $mspId = Seeder::seedMsp(['stripe_connect_id' => $accountId]);

        $event = StripeWebhookFixture::load('charge.dispute.closed', [
            'account' => $accountId,
            'data.object.id' => $disputeId,
        ]);
        eb_ph_webhook_dispatch_event($event);

        // The dispute row gets cached (upsert) but no notice exists to resolve.
        $dispute = Capsule::table('eb_disputes')->where('stripe_dispute_id', $disputeId)->first();
        self::assertNotNull($dispute);

        $noticeExists = Capsule::table('eb_partnerhub_notices')
            ->where('notice_key', 'billing_dispute_opened_' . $disputeId)
            ->exists();
        self::assertFalse($noticeExists, 'No notice should exist if it was never created.');
    }
}
