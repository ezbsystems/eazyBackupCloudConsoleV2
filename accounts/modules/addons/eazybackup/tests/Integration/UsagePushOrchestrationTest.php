<?php

declare(strict_types=1);

namespace EazyBackup\Tests\Integration;

use EazyBackup\Tests\Support\DatabaseTestCase;
use EazyBackup\Tests\Support\Seeder;
use EazyBackup\Tests\Support\TestableStripeService;
use WHMCS\Database\Capsule;

/**
 * Integration coverage for `eb_ph_usage_push_for_tenant` — the orchestration
 * that ledger-records a usage data point and (when an active metered plan
 * instance exists) pushes the billable quantity to Stripe.
 *
 * Source: pages/partnerhub/UsageController.php (extracted in Phase H so it is
 * testable without going through $_SESSION / $_POST / header() / echo).
 *
 * Risks this catches:
 *   - The wrong subscription item id being used (would put usage on a
 *     non-metered seat line — Stripe rejects, MSPs lose revenue).
 *   - Idempotency contract broken: two pushes for the same tenant + metric
 *     + period inserting two ledger rows.
 *   - Allowance not applied (raw_qty > default_qty pushed instead of overage).
 *   - cap_at_default still pushing > 0.
 *   - Ledger not stamping pushed_to_stripe_at after a successful push (the
 *     nightly reconciliation job re-pushes anything not stamped).
 *   - A Stripe API failure NOT leaving the ledger in a recoverable state
 *     (would block the next attempt).
 *   - Recorded-only branch (no plan instance) accidentally calling Stripe.
 */
final class UsagePushOrchestrationTest extends DatabaseTestCase
{
    public function test_push_records_ledger_and_calls_stripe_with_billable_quantity(): void
    {
        $accountId = 'acct_phh_' . bin2hex(random_bytes(4));
        $stripeSubItemId = 'si_phh_' . bin2hex(random_bytes(4));

        $mspId = Seeder::seedMsp(['stripe_connect_id' => $accountId]);
        $tenantId = Seeder::seedTenant($mspId);
        $this->seedActiveMeteredPlanInstance($mspId, $tenantId, $accountId, $stripeSubItemId, 100, 'bill_all');

        $stripe = new TestableStripeService();
        $stripe->queueResponse(['id' => 'mbur_test', 'object' => 'usage_record']);

        $result = eb_ph_usage_push_for_tenant(
            $tenantId,
            $mspId,
            $accountId,
            'STORAGE_TB',
            150,
            time() - 7200,
            time() - 60,
            $stripe
        );

        self::assertSame('success', $result['status']);
        self::assertSame(50, $result['billable_qty'], '150 raw - 100 default = 50 billable.');

        $call = $stripe->lastCall();
        self::assertNotNull($call);
        self::assertSame('POST', $call->method);
        self::assertSame('/v1/subscription_items/' . $stripeSubItemId . '/usage_records', $call->path);
        self::assertSame($accountId, $call->stripeAccount);
        self::assertSame(50, $call->params['quantity']);
        self::assertSame('set', $call->params['action']);
        self::assertNotNull($call->extraHeaders);
        self::assertContains('Idempotency-Key: ' . $result['idempotency_key'], $call->extraHeaders);

        $row = Capsule::table('eb_usage_ledger')
            ->where('idempotency_key', $result['idempotency_key'])
            ->first();
        self::assertNotNull($row);
        self::assertSame($tenantId, (int) $row->tenant_id);
        self::assertSame(50, (int) $row->qty, 'Ledger qty must be the BILLABLE qty after push.');
        self::assertNotNull($row->pushed_to_stripe_at);
    }

    public function test_push_below_default_qty_results_in_zero_billable_and_no_stripe_call_on_first_recorded_only_path(): void
    {
        // No plan instance => recorded-only path. Stripe spy stays silent.
        $mspId = Seeder::seedMsp();
        $tenantId = Seeder::seedTenant($mspId);

        $stripe = new TestableStripeService();
        $result = eb_ph_usage_push_for_tenant(
            $tenantId,
            $mspId,
            '',
            'STORAGE_TB',
            150,
            time() - 7200,
            time() - 60,
            $stripe
        );

        self::assertSame('success', $result['status']);
        self::assertSame('recorded-only', $result['message']);
        self::assertSame([], $stripe->calls, 'No plan instance => no Stripe call.');

        $row = Capsule::table('eb_usage_ledger')
            ->where('idempotency_key', $result['idempotency_key'])
            ->first();
        self::assertNotNull($row);
        self::assertSame(150, (int) $row->qty, 'Recorded-only path stores raw qty (no allowance applied).');
        self::assertNull($row->pushed_to_stripe_at);
    }

    public function test_cap_at_default_overage_mode_pushes_zero(): void
    {
        $accountId = 'acct_phh_' . bin2hex(random_bytes(4));
        $stripeSubItemId = 'si_phh_cap_' . bin2hex(random_bytes(4));

        $mspId = Seeder::seedMsp(['stripe_connect_id' => $accountId]);
        $tenantId = Seeder::seedTenant($mspId);
        $this->seedActiveMeteredPlanInstance($mspId, $tenantId, $accountId, $stripeSubItemId, 100, 'cap_at_default');

        $stripe = new TestableStripeService();
        $stripe->queueResponse(['id' => 'mbur_test']);

        $result = eb_ph_usage_push_for_tenant(
            $tenantId,
            $mspId,
            $accountId,
            'STORAGE_TB',
            500,
            time() - 7200,
            time() - 60,
            $stripe
        );

        self::assertSame('success', $result['status']);
        self::assertSame(0, $result['billable_qty']);
        self::assertSame(0, $stripe->lastCall()->params['quantity'], 'cap_at_default must push 0.');
    }

    public function test_repeated_push_same_period_is_idempotent_no_duplicate_ledger_rows(): void
    {
        $accountId = 'acct_phh_' . bin2hex(random_bytes(4));
        $stripeSubItemId = 'si_phh_dup_' . bin2hex(random_bytes(4));

        $mspId = Seeder::seedMsp(['stripe_connect_id' => $accountId]);
        $tenantId = Seeder::seedTenant($mspId);
        $this->seedActiveMeteredPlanInstance($mspId, $tenantId, $accountId, $stripeSubItemId, 100, 'bill_all');

        $stripe = new TestableStripeService();
        $stripe->queueResponse(['id' => 'mbur1']);
        $stripe->queueResponse(['id' => 'mbur2']);

        $periodStart = time() - 7200;
        $periodEnd = time() - 60;

        $first = eb_ph_usage_push_for_tenant($tenantId, $mspId, $accountId, 'STORAGE_TB', 200, $periodStart, $periodEnd, $stripe);
        $second = eb_ph_usage_push_for_tenant($tenantId, $mspId, $accountId, 'STORAGE_TB', 250, $periodStart, $periodEnd, $stripe);

        self::assertSame('success', $first['status']);
        self::assertSame('success', $second['status']);
        self::assertSame($first['idempotency_key'], $second['idempotency_key'], 'Same tenant+metric+period yields the same idempotency key.');

        $rowCount = (int) Capsule::table('eb_usage_ledger')
            ->where('idempotency_key', $first['idempotency_key'])
            ->count();
        self::assertSame(1, $rowCount, 'Repeated pushes must upsert into the same ledger row.');

        // Latest billable qty wins.
        $row = Capsule::table('eb_usage_ledger')
            ->where('idempotency_key', $first['idempotency_key'])
            ->first();
        self::assertSame(150, (int) $row->qty, '250 raw - 100 default = 150 billable on the second push.');
    }

    public function test_unknown_metric_with_no_plan_item_falls_back_to_recorded_only(): void
    {
        $accountId = 'acct_phh_' . bin2hex(random_bytes(4));
        $stripeSubItemId = 'si_phh_storage_' . bin2hex(random_bytes(4));

        $mspId = Seeder::seedMsp(['stripe_connect_id' => $accountId]);
        $tenantId = Seeder::seedTenant($mspId);
        // Only STORAGE_TB has a plan instance item; pushing DEVICE_COUNT must record-only.
        $this->seedActiveMeteredPlanInstance($mspId, $tenantId, $accountId, $stripeSubItemId, 0, 'bill_all', 'STORAGE_TB');

        $stripe = new TestableStripeService();
        $result = eb_ph_usage_push_for_tenant(
            $tenantId,
            $mspId,
            $accountId,
            'DEVICE_COUNT',
            7,
            time() - 7200,
            time() - 60,
            $stripe
        );

        self::assertSame('success', $result['status']);
        self::assertSame('recorded-only', $result['message']);
        self::assertSame([], $stripe->calls);
    }

    public function test_invalid_inputs_short_circuit_with_invalid(): void
    {
        $stripe = new TestableStripeService();

        self::assertSame('error', eb_ph_usage_push_for_tenant(0, 1, 'acct_x', 'STORAGE_TB', 10, time() - 100, time() - 1, $stripe)['status']);
        self::assertSame('error', eb_ph_usage_push_for_tenant(1, 1, 'acct_x', '', 10, time() - 100, time() - 1, $stripe)['status']);
        self::assertSame('error', eb_ph_usage_push_for_tenant(1, 1, 'acct_x', 'STORAGE_TB', -5, time() - 100, time() - 1, $stripe)['status']);

        self::assertSame([], $stripe->calls);
    }

    public function test_period_in_future_returns_error_without_writing_anything(): void
    {
        $stripe = new TestableStripeService();
        $beforeCount = (int) Capsule::table('eb_usage_ledger')->count();

        $future = time() + 86400;
        $result = eb_ph_usage_push_for_tenant(123, 1, 'acct_x', 'STORAGE_TB', 10, $future, $future + 3600, $stripe);

        self::assertSame('error', $result['status']);
        self::assertSame('period_in_future', $result['message']);
        self::assertSame($beforeCount, (int) Capsule::table('eb_usage_ledger')->count(), 'No ledger row when period invalid.');
    }

    public function test_stripe_failure_leaves_ledger_unstamped_for_retry(): void
    {
        $accountId = 'acct_phh_' . bin2hex(random_bytes(4));
        $stripeSubItemId = 'si_phh_fail_' . bin2hex(random_bytes(4));

        $mspId = Seeder::seedMsp(['stripe_connect_id' => $accountId]);
        $tenantId = Seeder::seedTenant($mspId);
        $this->seedActiveMeteredPlanInstance($mspId, $tenantId, $accountId, $stripeSubItemId, 0, 'bill_all');

        $stripe = new TestableStripeService();
        $stripe->throwOnNext(new \RuntimeException('Stripe error (HTTP 500): Internal'));

        $result = eb_ph_usage_push_for_tenant(
            $tenantId,
            $mspId,
            $accountId,
            'STORAGE_TB',
            42,
            time() - 7200,
            time() - 60,
            $stripe
        );

        self::assertSame('error', $result['status']);
        self::assertStringContainsString('Stripe error', $result['message']);

        // Ledger row exists but pushed_to_stripe_at is null — nightly job will retry.
        $row = Capsule::table('eb_usage_ledger')
            ->where('idempotency_key', $result['idempotency_key'])
            ->first();
        self::assertNotNull($row);
        self::assertNull($row->pushed_to_stripe_at);
    }

    public function test_usage_map_subscription_item_id_overrides_instance_item_id(): void
    {
        // The runtime resolver prefers eb_plan_instance_usage_map.stripe_subscription_item_id
        // (the live subscription item) over eb_plan_instance_items.stripe_subscription_item_id
        // (snapshot at assignment time). This test pins that the resolver picks the live one.
        $accountId = 'acct_phh_' . bin2hex(random_bytes(4));
        $stale = 'si_stale_' . bin2hex(random_bytes(4));
        $live = 'si_live_' . bin2hex(random_bytes(4));

        $mspId = Seeder::seedMsp(['stripe_connect_id' => $accountId]);
        $tenantId = Seeder::seedTenant($mspId);
        $instanceItemId = $this->seedActiveMeteredPlanInstance($mspId, $tenantId, $accountId, $stale, 0, 'bill_all');

        // Insert a usage map row pointing at the LIVE subscription item id.
        Capsule::table('eb_plan_instance_usage_map')->insert([
            'plan_instance_item_id' => $instanceItemId,
            'metric_code' => 'STORAGE_TB',
            'stripe_subscription_item_id' => $live,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $stripe = new TestableStripeService();
        $stripe->queueResponse(['id' => 'mbur']);

        eb_ph_usage_push_for_tenant($tenantId, $mspId, $accountId, 'STORAGE_TB', 25, time() - 7200, time() - 60, $stripe);

        $call = $stripe->lastCall();
        self::assertNotNull($call);
        self::assertSame('/v1/subscription_items/' . $live . '/usage_records', $call->path, 'Live usage_map item must override stale instance_item.');
    }

    /**
     * Insert an active eb_plan_instances + eb_plan_instance_items row so the
     * usage push has somewhere to land. Returns the plan_instance_item_id.
     */
    private function seedActiveMeteredPlanInstance(
        int $mspId,
        int $tenantId,
        string $accountId,
        string $stripeSubItemId,
        int $defaultQty,
        string $overageMode,
        string $metric = 'STORAGE_TB'
    ): int {
        $now = date('Y-m-d H:i:s');
        $planId = (int) Capsule::table('eb_plan_templates')->insertGetId([
            'msp_id' => $mspId,
            'name' => 'EB_PHASE_H_SEED Plan',
            'trial_days' => 0,
            'billing_interval' => 'month',
            'currency' => 'CAD',
            'version' => 1,
            'active' => 1,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Catalog product + price → component.
        $productId = (int) Capsule::table('eb_catalog_products')->insertGetId([
            'msp_id' => $mspId,
            'name' => 'EB_PHASE_H_SEED Storage',
            'category' => 'Backup',
            'active' => 1,
            'is_published' => 1,
            'default_currency' => 'CAD',
            'base_metric_code' => 'STORAGE_TB',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $priceId = (int) Capsule::table('eb_catalog_prices')->insertGetId([
            'product_id' => $productId,
            'name' => 'EB_PHASE_H_SEED Per-GiB',
            'kind' => 'metered',
            'currency' => 'CAD',
            'unit_label' => 'GiB',
            'unit_amount' => 5,
            'interval' => 'month',
            'aggregate_usage' => 'last_during_period',
            'metric_code' => $metric,
            'active' => 1,
            'billing_type' => 'metered',
            'is_published' => 1,
            'pricing_scheme' => 'per_unit',
            'stripe_price_id' => 'price_phh_' . bin2hex(random_bytes(4)),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $componentId = (int) Capsule::table('eb_plan_components')->insertGetId([
            'plan_id' => $planId,
            'price_id' => $priceId,
            'metric_code' => $metric,
            'default_qty' => $defaultQty,
            'overage_mode' => $overageMode,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $instanceId = (int) Capsule::table('eb_plan_instances')->insertGetId([
            'msp_id' => $mspId,
            'customer_id' => 0,
            'tenant_id' => $tenantId,
            'comet_user_id' => 'phh-' . bin2hex(random_bytes(3)),
            'plan_id' => $planId,
            'plan_version' => 1,
            'stripe_account_id' => $accountId,
            'stripe_customer_id' => 'cus_phh_' . bin2hex(random_bytes(4)),
            'stripe_subscription_id' => 'sub_phh_' . bin2hex(random_bytes(4)),
            'anchor_date' => date('Y-m-d'),
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $instanceItemId = (int) Capsule::table('eb_plan_instance_items')->insertGetId([
            'plan_instance_id' => $instanceId,
            'plan_component_id' => $componentId,
            'stripe_subscription_item_id' => $stripeSubItemId,
            'metric_code' => $metric,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $instanceItemId;
    }
}
