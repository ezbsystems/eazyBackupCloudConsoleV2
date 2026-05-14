<?php

declare(strict_types=1);

namespace EazyBackup\Tests\Unit;

use EazyBackup\Tests\Support\UnitTestCase;
use function PartnerHub\computeBillableMeteredUsage;

/**
 * Pure-logic coverage for PartnerHub\computeBillableMeteredUsage.
 *
 * Source: lib/PartnerHub/MeteredUsage.php
 *
 * The function decides how much of a tenant's measured usage gets pushed to
 * Stripe as billable, given the plan component's included allowance and
 * overage_mode. Off-by-one errors here turn directly into customer billing
 * disputes, so each branch is asserted individually.
 */
final class MeteredUsageBillableComputationTest extends UnitTestCase
{
    public function test_bill_all_with_usage_over_default_returns_difference(): void
    {
        self::assertSame(50, computeBillableMeteredUsage(150, 100, 'bill_all'));
    }

    public function test_bill_all_with_usage_below_default_returns_zero(): void
    {
        self::assertSame(0, computeBillableMeteredUsage(50, 100, 'bill_all'));
    }

    public function test_bill_all_with_usage_equal_to_default_returns_zero(): void
    {
        self::assertSame(0, computeBillableMeteredUsage(100, 100, 'bill_all'));
    }

    public function test_cap_at_default_with_any_usage_returns_zero(): void
    {
        self::assertSame(0, computeBillableMeteredUsage(0, 100, 'cap_at_default'));
        self::assertSame(0, computeBillableMeteredUsage(50, 100, 'cap_at_default'));
        self::assertSame(0, computeBillableMeteredUsage(100, 100, 'cap_at_default'));
        self::assertSame(0, computeBillableMeteredUsage(99_999_999, 100, 'cap_at_default'));
    }

    public function test_unknown_overage_mode_falls_back_to_bill_all_semantics(): void
    {
        // The current implementation treats unknown modes as bill_all (subtract default).
        // Locking that in so silent regressions surface immediately.
        self::assertSame(40, computeBillableMeteredUsage(140, 100, 'rollover'));
        self::assertSame(0, computeBillableMeteredUsage(80, 100, 'wat'));
    }

    public function test_negative_usage_clamps_to_zero(): void
    {
        self::assertSame(0, computeBillableMeteredUsage(-10, 0, 'bill_all'));
        self::assertSame(0, computeBillableMeteredUsage(-9_999, 100, 'bill_all'));
        self::assertSame(0, computeBillableMeteredUsage(-1, 0, 'cap_at_default'));
    }

    public function test_negative_default_clamps_to_zero(): void
    {
        // Negative default becomes 0; bill_all then equals raw usage.
        self::assertSame(120, computeBillableMeteredUsage(120, -50, 'bill_all'));
    }

    public function test_overage_mode_is_normalised_for_whitespace_and_case(): void
    {
        self::assertSame(0, computeBillableMeteredUsage(500, 100, ' Cap_At_Default '));
        self::assertSame(0, computeBillableMeteredUsage(500, 100, "\tCAP_AT_DEFAULT\n"));
        self::assertSame(400, computeBillableMeteredUsage(500, 100, ' BILL_ALL '));
    }

    public function test_zero_default_with_bill_all_returns_full_usage(): void
    {
        self::assertSame(7, computeBillableMeteredUsage(7, 0, 'bill_all'));
    }
}
