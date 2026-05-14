<?php

declare(strict_types=1);

namespace EazyBackup\Tests\Unit;

use EazyBackup\Tests\Support\UnitTestCase;

/**
 * Coverage for `eb_ph_plan_assignment_mode` — the per-plan classifier that
 * decides whether assignment requires a Comet user, an MSP-owned S3 user, or
 * neither.
 *
 * Source: pages/partnerhub/TenantsController.php
 *
 * Risks this catches:
 *   - A plan that mixes E3_STORAGE_GIB with another metric being misclassified
 *     as `e3_storage` (would route customers through the wrong assignment UI
 *     and silently drop the comet_user_id requirement).
 *   - Empty / unknown metric falling through to a non-`comet_user` mode.
 *   - The `requires_comet_user` / `requires_s3_user` boolean pair drifting
 *     out of sync with the resolved `mode`.
 *
 * The helper is normally driven from the DB; we exercise the in-memory
 * `$planComponents` parameter so this test stays a pure unit (no DB).
 */
final class PlanAssignmentModeTest extends UnitTestCase
{
    public function test_storage_only_plan_resolves_to_comet_user_mode(): void
    {
        $mode = eb_ph_plan_assignment_mode(0, [
            ['price_metric' => 'STORAGE_TB'],
        ]);

        self::assertSame('comet_user', $mode['mode']);
        self::assertTrue($mode['requires_comet_user']);
        self::assertFalse($mode['requires_s3_user']);
        self::assertSame(['STORAGE_TB'], $mode['metrics']);
        self::assertSame('STORAGE_TB', $mode['primary_metric']);
    }

    public function test_e3_storage_only_plan_resolves_to_e3_storage_mode(): void
    {
        $mode = eb_ph_plan_assignment_mode(0, [
            ['price_metric' => 'E3_STORAGE_GIB'],
        ]);

        self::assertSame('e3_storage', $mode['mode']);
        self::assertFalse($mode['requires_comet_user']);
        self::assertTrue($mode['requires_s3_user']);
        self::assertSame(['E3_STORAGE_GIB'], $mode['metrics']);
    }

    public function test_mixed_metrics_with_e3_storage_falls_back_to_comet_user_mode(): void
    {
        // CRITICAL: any metric beyond E3_STORAGE_GIB must NOT classify as e3_storage
        // (otherwise the comet_user_id guard would be skipped for non-E3 components).
        $mode = eb_ph_plan_assignment_mode(0, [
            ['price_metric' => 'E3_STORAGE_GIB'],
            ['price_metric' => 'DEVICE_COUNT'],
        ]);

        self::assertSame('comet_user', $mode['mode']);
        self::assertTrue($mode['requires_comet_user']);
        self::assertFalse($mode['requires_s3_user']);
    }

    public function test_metric_resolution_falls_back_through_keys(): void
    {
        // Order of preference: price_metric -> metric_code -> product_base_metric.
        $mode = eb_ph_plan_assignment_mode(0, [
            ['metric_code' => 'M365_USER'],
            ['product_base_metric' => 'HYPERV_VM'],
        ]);

        self::assertSame('comet_user', $mode['mode']);
        self::assertContains('M365_USER', $mode['metrics']);
        self::assertContains('HYPERV_VM', $mode['metrics']);
    }

    public function test_metrics_are_uppercased_and_deduplicated(): void
    {
        $mode = eb_ph_plan_assignment_mode(0, [
            ['price_metric' => '  storage_tb  '],
            ['price_metric' => 'STORAGE_TB'],
            ['price_metric' => 'Device_Count'],
        ]);

        self::assertSame(['STORAGE_TB', 'DEVICE_COUNT'], $mode['metrics']);
    }

    public function test_empty_components_default_to_comet_user_mode(): void
    {
        $mode = eb_ph_plan_assignment_mode(0, []);
        self::assertSame('comet_user', $mode['mode']);
        self::assertSame('GENERIC', $mode['primary_metric']);
        self::assertSame([], $mode['metrics']);
    }
}
