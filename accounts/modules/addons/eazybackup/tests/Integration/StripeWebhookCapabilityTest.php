<?php

declare(strict_types=1);

namespace EazyBackup\Tests\Integration;

use EazyBackup\Tests\Support\DatabaseTestCase;
use EazyBackup\Tests\Support\Seeder;
use EazyBackup\Tests\Support\StripeWebhookFixture;
use EazyBackup\Tests\Support\TestableStripeService;
use WHMCS\Database\Capsule;

/**
 * Coverage for the capability.updated event handler.
 *
 * Source: pages/partnerhub/StripeWebhookController.php
 *
 * Why this lives in its own file: the handler is the only one in the dispatcher
 * that calls back into Stripe (`StripeService::retrieveAccount()`). It needs an
 * injected service to be testable. Phase C2 added the injection seam — this test
 * exercises both the happy path (Stripe returns a fresh snapshot, mirrored to
 * eb_msp_accounts) and the resilient fallback (Stripe call throws, we still
 * stamp `last_verification_check` so the dashboard doesn't appear stuck).
 *
 * Risks this catches:
 *   - capability.updated being a no-op when the injected service responds.
 *   - flags / capabilities / requirements not being mirrored from the fresh
 *     snapshot (would let the dashboard show stale data after a capability
 *     state change).
 *   - The fallback failing to update last_verification_check on Stripe error
 *     (operators could not tell the webhook even fired).
 */
final class StripeWebhookCapabilityTest extends DatabaseTestCase
{
    public function test_capability_updated_pulls_fresh_account_snapshot_from_stripe(): void
    {
        $accountId = 'acct_phc2_cap_' . bin2hex(random_bytes(4));
        $mspId = Seeder::seedMsp(['stripe_connect_id' => $accountId]);
        Capsule::table('eb_msp_accounts')->where('id', $mspId)->update([
            'charges_enabled' => 0,
            'payouts_enabled' => 0,
            'last_verification_check' => null,
        ]);

        // The injected StripeService returns a fresh snapshot — flags + capabilities + requirements.
        $stripe = new TestableStripeService();
        $stripe->queueResponse([
            'id' => $accountId,
            'object' => 'account',
            'charges_enabled' => true,
            'payouts_enabled' => true,
            'capabilities' => ['card_payments' => 'active', 'transfers' => 'active'],
            'requirements' => ['currently_due' => [], 'past_due' => []],
        ]);

        $event = StripeWebhookFixture::load('capability.updated', [
            'account' => $accountId,
            'data.object.account' => $accountId,
        ]);
        eb_ph_webhook_dispatch_event($event, $stripe);

        // The handler should have called retrieveAccount with the right account id.
        $call = $stripe->lastCall();
        self::assertNotNull($call);
        self::assertSame('GET', $call->method);
        self::assertSame('/v1/accounts/' . $accountId, $call->path);

        $row = Capsule::table('eb_msp_accounts')->where('id', $mspId)->first();
        self::assertSame(1, (int) $row->charges_enabled);
        self::assertSame(1, (int) $row->payouts_enabled);
        self::assertNotNull($row->last_verification_check);

        $caps = json_decode((string) $row->connect_capabilities, true);
        self::assertSame('active', $caps['card_payments']);
        self::assertSame('active', $caps['transfers']);

        $reqs = json_decode((string) $row->connect_requirements, true);
        self::assertSame([], $reqs['currently_due']);
    }

    public function test_capability_updated_falls_back_to_transfers_enabled_when_payouts_missing(): void
    {
        $accountId = 'acct_phc2_cap_' . bin2hex(random_bytes(4));
        $mspId = Seeder::seedMsp(['stripe_connect_id' => $accountId]);

        $stripe = new TestableStripeService();
        $stripe->queueResponse([
            'id' => $accountId,
            'object' => 'account',
            'charges_enabled' => false,
            // payouts_enabled deliberately absent; transfers_enabled drives the flag.
            'transfers_enabled' => true,
        ]);

        $event = StripeWebhookFixture::load('capability.updated', [
            'account' => $accountId,
            'data.object.account' => $accountId,
        ]);
        eb_ph_webhook_dispatch_event($event, $stripe);

        $row = Capsule::table('eb_msp_accounts')->where('id', $mspId)->first();
        self::assertSame(0, (int) $row->charges_enabled);
        self::assertSame(1, (int) $row->payouts_enabled, 'transfers_enabled must drive payouts_enabled when payouts_enabled missing.');
    }

    public function test_capability_updated_stamps_last_verification_check_even_when_stripe_call_fails(): void
    {
        $accountId = 'acct_phc2_cap_err_' . bin2hex(random_bytes(4));
        $mspId = Seeder::seedMsp(['stripe_connect_id' => $accountId]);
        Capsule::table('eb_msp_accounts')->where('id', $mspId)->update([
            'charges_enabled' => 1,
            'payouts_enabled' => 1,
            'last_verification_check' => null,
        ]);

        $stripe = new TestableStripeService();
        $stripe->throwOnNext(new \RuntimeException('Stripe error (HTTP 500): Internal'));

        $event = StripeWebhookFixture::load('capability.updated', [
            'account' => $accountId,
            'data.object.account' => $accountId,
        ]);
        eb_ph_webhook_dispatch_event($event, $stripe);

        $row = Capsule::table('eb_msp_accounts')->where('id', $mspId)->first();
        // Existing flags are preserved (the fallback only stamps the timestamp).
        self::assertSame(1, (int) $row->charges_enabled);
        self::assertSame(1, (int) $row->payouts_enabled);
        // The verification timestamp must still be stamped.
        self::assertNotNull($row->last_verification_check, 'Failed Stripe call must still bump last_verification_check.');
    }

    public function test_capability_updated_for_unknown_account_does_not_call_stripe(): void
    {
        $orphanAccount = 'acct_phc2_orphan_' . bin2hex(random_bytes(4));
        $stripe = new TestableStripeService();

        $event = StripeWebhookFixture::load('capability.updated', [
            'account' => '', // No account header at all
            'data.object.account' => $orphanAccount,
        ]);
        // Force the event-level account to be empty so the handler skips.
        unset($event['account']);
        $event['data']['object']['account'] = '';

        eb_ph_webhook_dispatch_event($event, $stripe);

        self::assertSame([], $stripe->calls, 'Empty account id must not trigger a Stripe API call.');
    }
}
