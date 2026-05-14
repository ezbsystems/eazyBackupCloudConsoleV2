<?php

declare(strict_types=1);

namespace EazyBackup\Tests\Integration;

use EazyBackup\Tests\Support\DatabaseTestCase;
use EazyBackup\Tests\Support\Seeder;
use WHMCS\Database\Capsule;

/**
 * Coverage for `eb_signup_check_rate_limits` and
 * `eb_signup_existing_event_state` — the two DB-backed abuse-control helpers
 * extracted in Phase F.
 *
 * Source: pages/whitelabel/PublicSignupController.php
 *
 * Risks this catches:
 *   - Per-IP rate limit being applied across tenants (a high-traffic MSP
 *     blocking a separate MSP's signups).
 *   - Per-email rate limit being applied across tenants (same risk).
 *   - The 1-hour window starting from the wrong reference point.
 *   - Limit of 0 being treated as "block all" instead of "no limit".
 *   - Idempotency check classifying terminal states (emailed, completed,
 *     provisioned, accepted) as anything other than 'completed'.
 *   - pending_approval not being preserved as its own state (would push
 *     duplicate orders if collapsed into 'completed' or null).
 */
final class PublicSignupRateLimitTest extends DatabaseTestCase
{
    public function test_zero_limits_disable_rate_limiting(): void
    {
        $tenantId = $this->seedSignupContext();

        // Pre-populate 5 events for this IP — would trip any non-zero limit.
        for ($i = 0; $i < 5; $i++) {
            $this->insertEvent($tenantId, 'someone' . $i . '@spam.test', '203.0.113.1', 'received');
        }

        $result = eb_signup_check_rate_limits($tenantId, '203.0.113.1', 'new@example.test', 0, 0);
        self::assertNull($result, 'Limits of 0 must be a no-op, not "block everyone".');
    }

    public function test_per_ip_limit_returns_rate_ip_after_threshold(): void
    {
        $tenantId = $this->seedSignupContext();

        $this->insertEvent($tenantId, 'a@x.test', '203.0.113.42', 'received');
        $this->insertEvent($tenantId, 'b@x.test', '203.0.113.42', 'failed');

        // Limit of 2 -> two prior events == at threshold -> next must be blocked.
        $result = eb_signup_check_rate_limits($tenantId, '203.0.113.42', 'c@x.test', 2, 0);
        self::assertSame('rate_ip', $result);

        // Limit of 3 -> still under threshold.
        $result = eb_signup_check_rate_limits($tenantId, '203.0.113.42', 'c@x.test', 3, 0);
        self::assertNull($result);
    }

    public function test_per_email_limit_returns_rate_email_after_threshold(): void
    {
        $tenantId = $this->seedSignupContext();

        $this->insertEvent($tenantId, 'sam@acme.test', '203.0.113.1', 'received');

        $result = eb_signup_check_rate_limits($tenantId, '203.0.113.99', 'sam@acme.test', 0, 1);
        self::assertSame('rate_email', $result);
    }

    public function test_rate_limit_is_scoped_per_tenant(): void
    {
        $tenantA = $this->seedSignupContext();
        $tenantB = $this->seedSignupContext();

        // Tenant A is being rate-pumped by an attacker.
        for ($i = 0; $i < 5; $i++) {
            $this->insertEvent($tenantA, 'a' . $i . '@x.test', '203.0.113.7', 'received');
        }

        // Tenant B's same IP must not be impacted.
        $result = eb_signup_check_rate_limits($tenantB, '203.0.113.7', 'fresh@example.test', 2, 0);
        self::assertNull($result, 'Per-tenant rate limit must NOT bleed across tenants.');
    }

    public function test_old_events_outside_window_do_not_count(): void
    {
        $tenantId = $this->seedSignupContext();

        // Insert an event with created_at older than 1 hour.
        $this->insertEvent($tenantId, 'old@x.test', '203.0.113.8', 'received', 7200);

        $result = eb_signup_check_rate_limits($tenantId, '203.0.113.8', 'fresh@x.test', 1, 0);
        self::assertNull($result, '1-hour window: events older than the cutoff must not count.');
    }

    public function test_existing_event_state_returns_null_when_no_prior_submission(): void
    {
        $tenantId = $this->seedSignupContext();
        self::assertNull(eb_signup_existing_event_state($tenantId, 'never@example.test'));
    }

    public function test_existing_event_state_classifies_pending_approval(): void
    {
        $tenantId = $this->seedSignupContext();
        $this->insertEvent($tenantId, 'sam@acme.test', '203.0.113.1', 'pending_approval');

        self::assertSame('pending_approval', eb_signup_existing_event_state($tenantId, 'sam@acme.test'));
    }

    public function test_existing_event_state_classifies_terminal_completed_states(): void
    {
        foreach (['emailed', 'completed', 'provisioned', 'accepted'] as $status) {
            $tenantId = $this->seedSignupContext();
            $this->insertEvent($tenantId, 'sam@acme.test', '203.0.113.1', $status);
            self::assertSame('completed', eb_signup_existing_event_state($tenantId, 'sam@acme.test'), "status={$status}");
        }
    }

    public function test_existing_event_state_classifies_failed_state(): void
    {
        $tenantId = $this->seedSignupContext();
        $this->insertEvent($tenantId, 'sam@acme.test', '203.0.113.1', 'failed');
        self::assertSame('failed', eb_signup_existing_event_state($tenantId, 'sam@acme.test'));
    }

    public function test_existing_event_state_with_invalid_inputs_returns_null(): void
    {
        self::assertNull(eb_signup_existing_event_state(0, 'sam@acme.test'));
        $tenantId = $this->seedSignupContext();
        self::assertNull(eb_signup_existing_event_state($tenantId, ''));
    }

    /**
     * Insert a whitelabel tenant + return its id.
     * (eb_whitelabel_signup_events.tenant_id references eb_whitelabel_tenants.id.)
     */
    private function seedSignupContext(): int
    {
        $now = date('Y-m-d H:i:s');
        $unique = bin2hex(random_bytes(6));
        return (int) Capsule::table('eb_whitelabel_tenants')->insertGetId([
            'public_id' => Seeder::generatePublicId(),
            'client_id' => 1,
            'status' => 'active',
            'subdomain' => 'phf-' . $unique,
            'fqdn' => 'phf-' . $unique . '.example.test',
            'idempotency_key' => 'phf-' . $unique,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function insertEvent(int $tenantId, string $email, string $ip, string $status = 'received', int $ageSeconds = 0): void
    {
        $created = date('Y-m-d H:i:s', time() - $ageSeconds);
        Capsule::table('eb_whitelabel_signup_events')->insert([
            'tenant_id' => $tenantId,
            'host_header' => 'phf.example.test',
            'email' => $email,
            'status' => $status,
            'ip' => $ip,
            'user_agent' => 'phpunit',
            'created_at' => $created,
            'updated_at' => $created,
        ]);
    }
}
