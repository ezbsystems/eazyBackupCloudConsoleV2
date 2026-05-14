<?php

declare(strict_types=1);

namespace EazyBackup\Tests\Integration;

use EazyBackup\Tests\Support\DatabaseTestCase;
use EazyBackup\Tests\Support\Seeder;
use EazyBackup\Tests\Support\StripeWebhookFixture;
use WHMCS\Database\Capsule;

/**
 * Coverage for the account.* event handlers in eb_ph_webhook_dispatch_event.
 *
 * Source: pages/partnerhub/StripeWebhookController.php
 *
 * Risks this catches:
 *   - account.updated dropping the charges_enabled / payouts_enabled flag flip
 *     (would leave Partner Hub badge stale and let MSPs continue trying to
 *     create subscriptions Stripe will reject).
 *   - account.updated not stamping last_verification_check (the UI uses this to
 *     show "checked X minutes ago"; absence breaks the UI and audit trail).
 *   - capabilities/requirements JSON not being persisted (downstream
 *     onboarding-banner logic reads these to drive the requirements checklist).
 *   - account.application.deauthorized leaving the stripe_connect_id on the row
 *     (would let the platform keep trying to create resources on a disconnected
 *     account, returning 401s forever).
 */
final class StripeWebhookAccountTest extends DatabaseTestCase
{
    public function test_account_updated_persists_flags_capabilities_and_requirements(): void
    {
        $accountId = 'acct_phc_account_' . bin2hex(random_bytes(4));
        $mspId = Seeder::seedMsp(['stripe_connect_id' => $accountId]);

        // Mark the row as "never verified" so the test asserts the timestamp gets stamped.
        Capsule::table('eb_msp_accounts')->where('id', $mspId)->update([
            'charges_enabled' => 0,
            'payouts_enabled' => 0,
            'last_verification_check' => null,
        ]);

        $event = StripeWebhookFixture::load('account.updated', [
            'account' => $accountId,
            'data.object.id' => $accountId,
            'data.object.charges_enabled' => true,
            'data.object.payouts_enabled' => true,
            'data.object.capabilities' => ['card_payments' => 'active', 'transfers' => 'active'],
            'data.object.requirements' => ['currently_due' => [], 'past_due' => []],
        ]);

        eb_ph_webhook_dispatch_event($event);

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

    public function test_account_updated_falls_back_to_transfers_enabled_when_payouts_missing(): void
    {
        $accountId = 'acct_phc_xfers_' . bin2hex(random_bytes(4));
        $mspId = Seeder::seedMsp(['stripe_connect_id' => $accountId]);

        $event = StripeWebhookFixture::load('account.updated', [
            'account' => $accountId,
            'data.object.id' => $accountId,
            'data.object.charges_enabled' => false,
            // payouts_enabled deliberately removed; transfers_enabled drives the flag.
            'data.object.transfers_enabled' => true,
        ]);
        unset($event['data']['object']['payouts_enabled']);

        eb_ph_webhook_dispatch_event($event);

        $row = Capsule::table('eb_msp_accounts')->where('id', $mspId)->first();
        self::assertSame(0, (int) $row->charges_enabled);
        self::assertSame(1, (int) $row->payouts_enabled, 'transfers_enabled must drive payouts_enabled when payouts_enabled missing.');
    }

    public function test_account_updated_clears_flags_when_set_to_false(): void
    {
        $accountId = 'acct_phc_off_' . bin2hex(random_bytes(4));
        $mspId = Seeder::seedMsp(['stripe_connect_id' => $accountId]);
        Capsule::table('eb_msp_accounts')->where('id', $mspId)->update([
            'charges_enabled' => 1,
            'payouts_enabled' => 1,
        ]);

        $event = StripeWebhookFixture::load('account.updated', [
            'account' => $accountId,
            'data.object.id' => $accountId,
            'data.object.charges_enabled' => false,
            'data.object.payouts_enabled' => false,
        ]);

        eb_ph_webhook_dispatch_event($event);

        $row = Capsule::table('eb_msp_accounts')->where('id', $mspId)->first();
        self::assertSame(0, (int) $row->charges_enabled);
        self::assertSame(0, (int) $row->payouts_enabled);
    }

    public function test_account_updated_for_unknown_account_id_is_no_op(): void
    {
        $orphanAccount = 'acct_phc_orphan_' . bin2hex(random_bytes(4));
        $beforeCount = (int) Capsule::table('eb_msp_accounts')->count();

        $event = StripeWebhookFixture::load('account.updated', [
            'account' => $orphanAccount,
            'data.object.id' => $orphanAccount,
        ]);
        eb_ph_webhook_dispatch_event($event);

        $afterCount = (int) Capsule::table('eb_msp_accounts')->count();
        self::assertSame($beforeCount, $afterCount, 'Unknown account must not insert a new MSP row.');
    }

    public function test_account_application_deauthorized_clears_connect_id_and_flags(): void
    {
        $accountId = 'acct_phc_deauth_' . bin2hex(random_bytes(4));
        $mspId = Seeder::seedMsp(['stripe_connect_id' => $accountId]);
        Capsule::table('eb_msp_accounts')->where('id', $mspId)->update([
            'charges_enabled' => 1,
            'payouts_enabled' => 1,
            'connect_capabilities' => json_encode(['card_payments' => 'active']),
        ]);

        $event = StripeWebhookFixture::load('account.application.deauthorized', [
            'account' => $accountId,
        ]);
        eb_ph_webhook_dispatch_event($event);

        $row = Capsule::table('eb_msp_accounts')->where('id', $mspId)->first();
        self::assertNull($row->stripe_connect_id, 'Deauth must clear the connected-account id.');
        self::assertSame(0, (int) $row->charges_enabled);
        self::assertSame(0, (int) $row->payouts_enabled);
        self::assertNull($row->connect_capabilities, 'Capabilities snapshot must be cleared.');

        $reqs = json_decode((string) $row->connect_requirements, true);
        self::assertSame(true, $reqs['deauthorized'] ?? null);
        self::assertSame($accountId, $reqs['account_id'] ?? null);
    }
}
