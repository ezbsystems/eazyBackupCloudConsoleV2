<?php

declare(strict_types=1);

namespace EazyBackup\Tests\Support;

/**
 * Helpers for driving the Partner Hub Stripe webhook handler from JSON fixtures.
 *
 * Two consumption modes:
 *   - load($name, $overrides) — returns a fully-formed event array with deterministic
 *     ids and "now"-aligned timestamps. Pass straight to eb_ph_webhook_dispatch_event().
 *   - sign($payload, $secret) — produces a v1 Stripe-Signature header for an arbitrary
 *     payload. Lets signature tests assert verifier behaviour without leaning on the
 *     official SDK's quirks.
 *
 * Fixtures live under tests/fixtures/stripe_webhooks/<name>.json. Each is a minimal
 * Stripe event envelope: type, data.object, account, etc. Fields that should vary
 * per test (e.g. `object.customer`, `object.id`) are filled in via overrides.
 */
final class StripeWebhookFixture
{
    private const FIXTURE_DIR = __DIR__ . '/../fixtures/stripe_webhooks';

    /**
     * Load a fixture by short name (no .json) and apply overrides via dot-paths.
     *
     * Example:
     *   StripeWebhookFixture::load('account.updated', [
     *     'account' => 'acct_test',
     *     'data.object.charges_enabled' => true,
     *   ]);
     *
     * The returned event always has:
     *   - id        — unique per call (`evt_test_<bin2hex(8)>`).
     *   - created   — current epoch.
     *   - data.object.created — current epoch (where applicable).
     */
    public static function load(string $name, array $overrides = []): array
    {
        $path = self::FIXTURE_DIR . '/' . $name . '.json';
        if (!is_file($path)) {
            throw new \RuntimeException("Stripe webhook fixture not found: {$path}");
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException("Could not read fixture: {$path}");
        }
        $event = json_decode($raw, true);
        if (!is_array($event)) {
            throw new \RuntimeException("Fixture is not a JSON object: {$path}");
        }

        // Fill canonical envelope fields if the fixture left them as placeholders or absent.
        $now = time();
        $event['id'] = 'evt_test_' . bin2hex(random_bytes(8));
        $event['created'] = $now;
        if (isset($event['data']['object']) && is_array($event['data']['object'])) {
            if (!isset($event['data']['object']['created'])) {
                $event['data']['object']['created'] = $now;
            }
        }

        foreach ($overrides as $path => $value) {
            self::setByDotPath($event, (string)$path, $value);
        }

        return $event;
    }

    /**
     * Sign an arbitrary JSON payload using Stripe's v1 HMAC scheme.
     *
     * Returns the raw header value (e.g. "t=1234567890,v1=abcdef..."). When
     * $timestamp is null, "now" is used. Pass an explicit timestamp to test
     * the verifier's tolerance window.
     */
    public static function sign(string $payload, string $secret, ?int $timestamp = null): string
    {
        $ts = $timestamp ?? time();
        $signature = hash_hmac('sha256', $ts . '.' . $payload, $secret);
        return 't=' . $ts . ',v1=' . $signature;
    }

    /**
     * Convenience: load a fixture, encode it, sign it, and return everything
     * the entry-point test needs.
     *
     * @return array{payload: string, signature: string, event: array}
     */
    public static function loadSigned(string $name, string $secret, array $overrides = [], ?int $timestamp = null): array
    {
        $event = self::load($name, $overrides);
        $payload = json_encode($event, JSON_UNESCAPED_SLASHES);
        return [
            'payload' => $payload,
            'signature' => self::sign($payload, $secret, $timestamp),
            'event' => $event,
        ];
    }

    /**
     * Apply a value at the given dot-path inside a nested array, creating
     * intermediate arrays as needed. Numeric path segments (e.g. items.0.price)
     * are coerced to int keys.
     */
    private static function setByDotPath(array &$arr, string $path, $value): void
    {
        $segments = explode('.', $path);
        $cursor =& $arr;
        $last = array_pop($segments);
        foreach ($segments as $seg) {
            $key = is_numeric($seg) ? (int)$seg : $seg;
            if (!isset($cursor[$key]) || !is_array($cursor[$key])) {
                $cursor[$key] = [];
            }
            $cursor =& $cursor[$key];
        }
        $cursor[is_numeric($last) ? (int)$last : $last] = $value;
    }
}
