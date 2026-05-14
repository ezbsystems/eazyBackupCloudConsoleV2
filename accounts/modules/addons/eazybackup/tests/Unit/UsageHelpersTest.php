<?php

declare(strict_types=1);

namespace EazyBackup\Tests\Unit;

use EazyBackup\Tests\Support\UnitTestCase;

/**
 * Pure-helper coverage for the usage-push orchestration in UsageController.
 *
 * Source: pages/partnerhub/UsageController.php
 *
 * These functions are referenced from both the on-demand HTTP push
 * (`eb_ph_usage_push`) and the nightly rollup CLI (`bin/partnerhub_usage_job.php`),
 * so a regression here mis-bills every metered tenant.
 */
final class UsageHelpersTest extends UnitTestCase
{
    public function test_idempotency_key_is_stable_for_identical_inputs(): void
    {
        $a = eb_usage_tenant_period_idempotency_key(42, 'STORAGE_TB', 1700000000, 1702592000);
        $b = eb_usage_tenant_period_idempotency_key(42, 'STORAGE_TB', 1700000000, 1702592000);
        self::assertSame($a, $b);
        self::assertSame(40, strlen($a), 'sha1 hex must be 40 chars long.');
    }

    public function test_idempotency_key_changes_when_any_input_changes(): void
    {
        $base = eb_usage_tenant_period_idempotency_key(42, 'STORAGE_TB', 1700000000, 1702592000);
        self::assertNotSame($base, eb_usage_tenant_period_idempotency_key(43, 'STORAGE_TB', 1700000000, 1702592000));
        self::assertNotSame($base, eb_usage_tenant_period_idempotency_key(42, 'DEVICE_COUNT', 1700000000, 1702592000));
        self::assertNotSame($base, eb_usage_tenant_period_idempotency_key(42, 'STORAGE_TB', 1700000001, 1702592000));
        self::assertNotSame($base, eb_usage_tenant_period_idempotency_key(42, 'STORAGE_TB', 1700000000, 1702592001));
    }

    public function test_idempotency_key_normalises_metric_whitespace(): void
    {
        $clean = eb_usage_tenant_period_idempotency_key(1, 'STORAGE_TB', 100, 200);
        $dirty = eb_usage_tenant_period_idempotency_key(1, '  STORAGE_TB  ', 100, 200);
        self::assertSame($clean, $dirty);
    }

    public function test_normalize_period_bounds_defaults_to_current_month(): void
    {
        [$start, $end] = eb_usage_normalize_period_bounds(0, 0);
        // Start should align with the first day of the current UTC month.
        $monthStart = (new \DateTimeImmutable('first day of this month 00:00:00', new \DateTimeZone('UTC')))->getTimestamp();
        self::assertSame($monthStart, $start);
        $nextMonthStart = (new \DateTimeImmutable('first day of next month 00:00:00', new \DateTimeZone('UTC')))->getTimestamp();
        // End is clamped to "now" when the month-end is in the future.
        self::assertGreaterThanOrEqual($start + 1, $end);
        self::assertLessThanOrEqual($nextMonthStart, $end);
    }

    public function test_normalize_period_bounds_throws_when_period_in_future(): void
    {
        $future = time() + 86400;
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('period_in_future');
        eb_usage_normalize_period_bounds($future, $future + 3600);
    }

    public function test_normalize_period_bounds_clamps_end_to_now(): void
    {
        $now = time();
        [, $end] = eb_usage_normalize_period_bounds($now - 3600, $now + 86400);
        self::assertLessThanOrEqual($now, $end, 'End must be clamped down to "now".');
    }

    public function test_normalize_period_bounds_throws_when_end_before_start(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid_period');
        eb_usage_normalize_period_bounds(2000, 1500);
    }

    public function test_pick_subscription_item_id_returns_first_metered_item(): void
    {
        $items = [
            ['id' => 'si_seat', 'price' => ['recurring' => ['usage_type' => 'licensed']]],
            ['id' => 'si_storage', 'price' => ['recurring' => ['usage_type' => 'metered']]],
            ['id' => 'si_other', 'price' => ['recurring' => ['usage_type' => 'metered']]],
        ];
        self::assertSame('si_storage', eb_usage_pick_subscription_item_id($items));
    }

    public function test_pick_subscription_item_id_fails_closed_when_no_metered_item(): void
    {
        $items = [
            ['id' => 'si_seat', 'price' => ['recurring' => ['usage_type' => 'licensed']]],
        ];
        self::assertSame('', eb_usage_pick_subscription_item_id($items));
        self::assertSame('', eb_usage_pick_subscription_item_id([]));
    }

    public function test_pick_subscription_item_id_skips_malformed_rows(): void
    {
        $items = [
            'not-an-array',
            ['no_price' => 'here'],
            ['id' => 'si_metered', 'price' => ['recurring' => ['usage_type' => 'METERED']]],
        ];
        self::assertSame('si_metered', eb_usage_pick_subscription_item_id($items));
    }

    public function test_clamp_usage_timestamp_uses_one_second_before_period_end(): void
    {
        $start = 1000;
        $end = 2000;
        $now = 5000;
        self::assertSame(1999, eb_usage_clamp_usage_timestamp($start, $end, $now));
    }

    public function test_clamp_usage_timestamp_clamps_against_now(): void
    {
        $start = 1000;
        $end = 9999;
        $now = 1500;
        self::assertSame(1499, eb_usage_clamp_usage_timestamp($start, $end, $now));
    }

    public function test_clamp_usage_timestamp_falls_back_to_period_start_when_window_collapses(): void
    {
        // If both ceilings are below period_start, fall back to period_start (clamped >= 1).
        self::assertSame(1000, eb_usage_clamp_usage_timestamp(1000, 999, 500));
        self::assertSame(1, eb_usage_clamp_usage_timestamp(0, 0, 0));
    }
}
