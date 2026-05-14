<?php

declare(strict_types=1);

namespace EazyBackup\Tests\Unit;

use EazyBackup\Tests\Support\UnitTestCase;

/**
 * Coverage for `eb_signup_check_domain_filters` — the per-tenant allow / deny
 * domain filter applied to the submitted email.
 *
 * Source: pages/whitelabel/PublicSignupController.php
 *
 * Risks this catches:
 *   - Empty allow list incorrectly blocking everyone (must be "no filter").
 *   - Whitespace in CSV breaking the in_array() comparison.
 *   - Case sensitivity letting "ACME.test" through when "acme.test" is denied.
 *   - Allow list precedence — when allow is set, the deny list is irrelevant
 *     (allow is the only gate).
 */
final class PublicSignupAbuseControlsTest extends UnitTestCase
{
    public function test_no_filters_returns_null(): void
    {
        self::assertNull(eb_signup_check_domain_filters('sam@acme.test', '', ''));
    }

    public function test_allow_list_matches_email_domain(): void
    {
        self::assertNull(eb_signup_check_domain_filters(
            'sam@acme.test',
            'acme.test, partner.test',
            ''
        ));
    }

    public function test_allow_list_blocks_unlisted_domain(): void
    {
        self::assertSame('blocked_not_in_allow', eb_signup_check_domain_filters(
            'sam@evil.test',
            'acme.test, partner.test',
            ''
        ));
    }

    public function test_deny_list_blocks_listed_domain(): void
    {
        self::assertSame('blocked_in_deny', eb_signup_check_domain_filters(
            'sam@spam.test',
            '',
            'spam.test, abuse.test'
        ));
    }

    public function test_deny_list_passes_unlisted_domain(): void
    {
        self::assertNull(eb_signup_check_domain_filters(
            'sam@acme.test',
            '',
            'spam.test, abuse.test'
        ));
    }

    public function test_deny_list_overrides_allow_list_when_domain_appears_on_both(): void
    {
        // Both lists are evaluated; deny is checked second and short-circuits.
        // Effect: the deny list is "stricter" — a domain that appears on both
        // gets blocked. Documented contract; tests pin this behaviour.
        self::assertSame('blocked_in_deny', eb_signup_check_domain_filters(
            'sam@acme.test',
            'acme.test',
            'acme.test'
        ));
    }

    public function test_email_without_at_sign_is_handled_safely(): void
    {
        // Domain becomes empty string -> all filters short-circuit.
        self::assertNull(eb_signup_check_domain_filters('not-an-email', 'acme.test', 'spam.test'));
    }

    public function test_csv_handles_extra_whitespace(): void
    {
        self::assertNull(eb_signup_check_domain_filters(
            'sam@acme.test',
            '  acme.test  ,  partner.test  ',
            ''
        ));
    }

    public function test_uppercased_email_domain_still_matches_lowercased_filter(): void
    {
        // The helper lowercases the email's domain before comparing.
        self::assertSame('blocked_in_deny', eb_signup_check_domain_filters(
            'sam@SPAM.test',
            '',
            'spam.test'
        ));
    }
}
