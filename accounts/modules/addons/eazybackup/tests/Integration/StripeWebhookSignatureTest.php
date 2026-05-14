<?php

declare(strict_types=1);

namespace EazyBackup\Tests\Integration;

use EazyBackup\Tests\Support\StripeWebhookFixture;
use EazyBackup\Tests\Support\UnitTestCase;

/**
 * Coverage for eb_ph_webhook_verify_signature.
 *
 * Source: pages/partnerhub/StripeWebhookController.php (extracted in Phase C).
 *
 * Why integration: the helper conditionally delegates to Stripe\Webhook (loaded via
 * the WHMCS root vendor) so we exercise the production path. No DB writes here, so
 * extends UnitTestCase to keep the suite fast.
 *
 * Risks this catches:
 *   - A regression that makes valid Stripe-signed payloads bounce (would silently
 *     drop every webhook in production).
 *   - The replay-window guard being weakened (>5 min replays could re-trigger
 *     billing notices).
 *   - A signature header tampered after the fact still being accepted (dev once
 *     made hash_equals -> string comparison: would have leaked here).
 */
final class StripeWebhookSignatureTest extends UnitTestCase
{
    private const SECRET = 'whsec_eb_phaseC_test_secret_value';

    public function test_valid_signature_returns_decoded_event(): void
    {
        $signed = StripeWebhookFixture::loadSigned('invoice.paid', self::SECRET);

        $result = eb_ph_webhook_verify_signature($signed['payload'], $signed['signature'], self::SECRET);

        self::assertTrue($result['ok']);
        self::assertSame('', $result['error']);
        self::assertIsArray($result['event']);
        self::assertSame('invoice.paid', $result['event']['type']);
        self::assertSame($signed['event']['id'], $result['event']['id']);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $signed = StripeWebhookFixture::loadSigned('invoice.paid', self::SECRET);

        // Sign with a DIFFERENT secret — should be rejected by the verifier.
        $bogus = StripeWebhookFixture::sign($signed['payload'], 'whsec_attacker_secret');

        $result = eb_ph_webhook_verify_signature($signed['payload'], $bogus, self::SECRET);

        self::assertFalse($result['ok']);
        self::assertSame('invalid-sig', $result['error']);
        self::assertNull($result['event']);
    }

    public function test_tampered_payload_after_signing_is_rejected(): void
    {
        $signed = StripeWebhookFixture::loadSigned('invoice.paid', self::SECRET);

        // Mutate the payload AFTER signing. HMAC must catch this.
        $tampered = str_replace('"amount_total":12500', '"amount_total":99999999', $signed['payload']);
        self::assertNotSame($signed['payload'], $tampered, 'fixture must contain the original amount before tampering');

        $result = eb_ph_webhook_verify_signature($tampered, $signed['signature'], self::SECRET);

        self::assertFalse($result['ok']);
        self::assertSame('invalid-sig', $result['error']);
    }

    public function test_replay_outside_tolerance_window_is_rejected(): void
    {
        $signed = StripeWebhookFixture::loadSigned(
            'invoice.paid',
            self::SECRET,
            [],
            time() - 3600 // signed an hour ago
        );

        $result = eb_ph_webhook_verify_signature($signed['payload'], $signed['signature'], self::SECRET);

        self::assertFalse($result['ok']);
        // Stripe SDK reports invalid-sig for old timestamps; lightweight verifier reports sig-timeout.
        // Either is acceptable; we assert SOMETHING failed and the event is not exposed.
        self::assertContains($result['error'], ['sig-timeout', 'invalid-sig'], "error: {$result['error']}");
        self::assertNull($result['event']);
    }

    public function test_missing_signature_header_is_rejected(): void
    {
        $payload = '{"id":"evt_no_sig","type":"invoice.paid"}';

        $result = eb_ph_webhook_verify_signature($payload, '', self::SECRET);

        self::assertFalse($result['ok']);
        self::assertContains($result['error'], ['missing-sig', 'invalid-sig'], "error: {$result['error']}");
    }

    public function test_garbage_signature_header_is_rejected(): void
    {
        $payload = '{"id":"evt_garbage_sig","type":"invoice.paid"}';

        $result = eb_ph_webhook_verify_signature($payload, 'gibberish', self::SECRET);

        self::assertFalse($result['ok']);
        self::assertSame('invalid-sig', $result['error']);
    }
}
