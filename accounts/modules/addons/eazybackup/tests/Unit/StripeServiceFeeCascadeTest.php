<?php

declare(strict_types=1);

namespace EazyBackup\Tests\Unit;

use EazyBackup\Tests\Support\DatabaseTestCase;
use PartnerHub\StripeService;
use WHMCS\Database\Capsule;

/**
 * Coverage for StripeService::resolveApplicationFeePercent — the canonical
 * 4-tier application_fee_percent cascade.
 *
 * Source: lib/PartnerHub/StripeService.php (extracted from
 * pages/partnerhub/SubscriptionsController.php in Phase B so it could be
 * tested in isolation).
 *
 * Risks this catches:
 *   - A higher tier silently masking a real fee at a lower tier (e.g. zero from
 *     a row breaking the cascade so the module default never gets a chance).
 *   - A lower tier being preferred over an explicit override (revenue leak).
 *   - Garbage / non-numeric values from the DB tripping the cascade.
 */
final class StripeServiceFeeCascadeTest extends DatabaseTestCase
{
    /** Unique per-test module name so existing tbladdonmodules rows can't leak into the cascade. */
    private string $moduleName;
    private string $stripePriceId;

    protected function setUp(): void
    {
        parent::setUp();
        $suffix = bin2hex(random_bytes(4));
        $this->moduleName = 'eb_phb_' . $suffix;
        $this->stripePriceId = 'price_phb_' . $suffix;
    }

    public function test_tier_1_explicit_override_wins(): void
    {
        // Lower tiers all populated to prove they don't shadow the override.
        $this->seedPlanPriceFee($this->stripePriceId, 5.0);
        $this->seedMspDefaultFee($this->testMspId, 4.0);
        $this->seedModuleDefaultFee('3.00');

        $resolved = StripeService::resolveApplicationFeePercent(
            7.5,
            $this->stripePriceId,
            $this->testMspId,
            $this->moduleName
        );
        self::assertSame(7.5, $resolved);
    }

    public function test_tier_2_plan_price_default_used_when_no_override(): void
    {
        $this->seedPlanPriceFee($this->stripePriceId, 6.25);
        // MSP + module defaults set lower so we'd notice if the cascade falls past.
        $this->seedMspDefaultFee($this->testMspId, 4.0);
        $this->seedModuleDefaultFee('3.00');

        $resolved = StripeService::resolveApplicationFeePercent(
            null,
            $this->stripePriceId,
            $this->testMspId,
            $this->moduleName
        );
        self::assertSame(6.25, $resolved);
    }

    public function test_tier_3_msp_default_used_when_plan_price_lookup_fails(): void
    {
        // No plan_prices row for this stripe_price_id.
        $this->seedMspDefaultFee($this->testMspId, 4.5);
        $this->seedModuleDefaultFee('3.00');

        $resolved = StripeService::resolveApplicationFeePercent(
            null,
            'price_does_not_exist_' . bin2hex(random_bytes(4)),
            $this->testMspId,
            $this->moduleName
        );
        self::assertSame(4.5, $resolved);
    }

    public function test_tier_4_module_default_used_when_higher_tiers_empty(): void
    {
        // No plan_prices row, no MSP record.
        $this->seedModuleDefaultFee('2.50');

        $resolved = StripeService::resolveApplicationFeePercent(
            null,
            null,
            0,
            $this->moduleName
        );
        self::assertSame(2.50, $resolved);
    }

    public function test_returns_null_when_every_tier_is_empty_or_zero(): void
    {
        // Every tier present but explicitly 0 — caller should omit the param entirely.
        $this->seedPlanPriceFee($this->stripePriceId, 0.0);
        $this->seedMspDefaultFee($this->testMspId, 0.0);
        $this->seedModuleDefaultFee('0.00');

        $resolved = StripeService::resolveApplicationFeePercent(
            0.0,
            $this->stripePriceId,
            $this->testMspId,
            $this->moduleName
        );
        self::assertNull($resolved);
    }

    public function test_zero_at_higher_tier_does_not_block_lower_tier(): void
    {
        // Zero on the plan price must not be treated as an explicit "no fee" override —
        // the cascade must keep walking and find the MSP default. (Caller can pass an
        // explicit override of 0.0 to truly disable fees.)
        $this->seedPlanPriceFee($this->stripePriceId, 0.0);
        $this->seedMspDefaultFee($this->testMspId, 4.0);

        $resolved = StripeService::resolveApplicationFeePercent(
            null,
            $this->stripePriceId,
            $this->testMspId,
            $this->moduleName
        );
        self::assertSame(4.0, $resolved);
    }

    public function test_negative_override_is_ignored_and_cascade_continues(): void
    {
        // Negative would be a programming error / coercion mishap — never trust it.
        $this->seedPlanPriceFee($this->stripePriceId, 6.0);

        $resolved = StripeService::resolveApplicationFeePercent(
            -1.0,
            $this->stripePriceId,
            $this->testMspId,
            $this->moduleName
        );
        self::assertSame(6.0, $resolved);
    }

    public function test_non_numeric_module_default_does_not_throw(): void
    {
        // Set a junk value in tbladdonmodules. The cascade must shrug and return null.
        Capsule::table('tbladdonmodules')->insert([
            'module' => $this->moduleName,
            'setting' => 'partnerhub_default_fee_percent',
            'value' => 'not-a-number',
        ]);

        $resolved = StripeService::resolveApplicationFeePercent(
            null,
            null,
            0,
            $this->moduleName
        );
        self::assertNull($resolved);
    }

    private function seedPlanPriceFee(string $stripePriceId, float $fee): void
    {
        $now = date('Y-m-d H:i:s');
        Capsule::table('eb_plan_prices')->insert([
            'plan_id' => 0,
            'nickname' => 'EB_PHASE_B_SEED Fee Cascade',
            'billing_cycle' => 'month',
            'stripe_price_id' => $stripePriceId,
            'application_fee_percent' => $fee,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function seedMspDefaultFee(int $mspId, float $fee): void
    {
        // testMspId is a sentinel int; insert a matching eb_msp_accounts row only
        // when it doesn't already exist (each test gets a fresh transaction).
        $now = date('Y-m-d H:i:s');
        $exists = Capsule::table('eb_msp_accounts')->where('id', $mspId)->exists();
        if (!$exists) {
            Capsule::table('eb_msp_accounts')->insert([
                'id' => $mspId,
                'whmcs_client_id' => $mspId,
                'name' => 'EB_PHASE_B_SEED MSP ' . $mspId,
                'status' => 'active',
                'billing_mode' => 'stripe_connect',
                'default_fee_percent' => $fee,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            Capsule::table('eb_msp_accounts')
                ->where('id', $mspId)
                ->update(['default_fee_percent' => $fee, 'updated_at' => $now]);
        }
    }

    private function seedModuleDefaultFee(string $value): void
    {
        Capsule::table('tbladdonmodules')->insert([
            'module' => $this->moduleName,
            'setting' => 'partnerhub_default_fee_percent',
            'value' => $value,
        ]);
    }
}
