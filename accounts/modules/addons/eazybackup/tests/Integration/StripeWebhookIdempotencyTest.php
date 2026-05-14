<?php

declare(strict_types=1);

namespace EazyBackup\Tests\Integration;

use EazyBackup\Tests\Support\DatabaseTestCase;
use WHMCS\Database\Capsule;

/**
 * Coverage for eb_ph_webhook_record_idempotent.
 *
 * Source: pages/partnerhub/StripeWebhookController.php
 *
 * Risks this catches:
 *   - A second delivery of the same event being processed again (would, e.g.,
 *     send a payment_failed email twice, double-stamp a notice, or mis-update
 *     a cache row that was concurrently corrected by a later event).
 *   - The duplicate detection accidentally returning 'error' instead of
 *     'duplicate' for the unique-constraint case (would 500 every replay
 *     and Stripe would keep retrying forever).
 *   - Empty event_ids being stamped (would give us one row that future events
 *     collide with, breaking idempotency for everything).
 */
final class StripeWebhookIdempotencyTest extends DatabaseTestCase
{
    public function test_first_event_is_stamped_and_returns_fresh(): void
    {
        $eventId = 'evt_phc_idem_' . bin2hex(random_bytes(8));

        $result = eb_ph_webhook_record_idempotent($eventId);

        self::assertSame('fresh', $result);
        $count = (int) Capsule::table('eb_stripe_events')
            ->where('event_id', $eventId)
            ->count();
        self::assertSame(1, $count);
    }

    public function test_duplicate_event_returns_duplicate_without_error(): void
    {
        $eventId = 'evt_phc_dup_' . bin2hex(random_bytes(8));

        $first = eb_ph_webhook_record_idempotent($eventId);
        $second = eb_ph_webhook_record_idempotent($eventId);
        $third = eb_ph_webhook_record_idempotent($eventId);

        self::assertSame('fresh', $first);
        self::assertSame('duplicate', $second, 'Second delivery must be detected as duplicate.');
        self::assertSame('duplicate', $third, 'Third delivery still duplicate.');

        // Exactly one stamped row.
        $count = (int) Capsule::table('eb_stripe_events')
            ->where('event_id', $eventId)
            ->count();
        self::assertSame(1, $count, 'No duplicate eb_stripe_events rows must be inserted.');
    }

    public function test_empty_event_id_returns_skip_without_inserting(): void
    {
        $beforeCount = (int) Capsule::table('eb_stripe_events')->count();

        $result = eb_ph_webhook_record_idempotent('');

        self::assertSame('skip', $result);
        $afterCount = (int) Capsule::table('eb_stripe_events')->count();
        self::assertSame($beforeCount, $afterCount, 'Empty event id must not stamp anything.');
    }

    public function test_dispatch_invoked_after_idempotent_record_is_a_no_op_on_replay(): void
    {
        // End-to-end: simulate the orchestration by calling the helpers in sequence
        // for the same event id. Walks the same path eb_ph_stripe_webhook would take.
        $payload = [
            'id' => 'evt_phc_orch_' . bin2hex(random_bytes(8)),
            'type' => 'application_fee.created', // dispatcher is a no-op for this
            'account' => 'acct_test_phc',
            'data' => ['object' => ['id' => 'fee_test', 'object' => 'application_fee']],
        ];

        $first = eb_ph_webhook_record_idempotent($payload['id']);
        if ($first === 'fresh') {
            eb_ph_webhook_dispatch_event($payload);
        }

        $second = eb_ph_webhook_record_idempotent($payload['id']);
        self::assertSame('duplicate', $second);
        // The orchestration would short-circuit here without calling the dispatcher again.
        // We assert the contract — the second iteration MUST NOT result in another insert.
        $count = (int) Capsule::table('eb_stripe_events')
            ->where('event_id', $payload['id'])
            ->count();
        self::assertSame(1, $count);
    }
}
