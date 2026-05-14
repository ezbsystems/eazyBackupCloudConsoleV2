<?php

declare(strict_types=1);

namespace EazyBackup\Tests\Integration;

use EazyBackup\Tests\Support\DatabaseTestCase;
use EazyBackup\Tests\Support\Seeder;
use EazyBackup\Tests\Support\TestableCatalogService;
use EazyBackup\Tests\Support\TestableStripeService;
use WHMCS\Database\Capsule;

/**
 * Coverage for `eb_ph_plan_assign_for_msp` — the pure-function backend
 * extracted in Phase G from the assign-plan controller.
 *
 * Source: pages/partnerhub/CatalogPlansController.php
 *
 * The assign-plan flow is the most state-heavy operation in the Partner Hub
 * canonical billing model. It performs:
 *   - scope validation (plan and tenant must belong to the calling MSP)
 *   - plan must be `active` (not draft/archived)
 *   - tenant must exist and not be deleted
 *   - for `comet_user` plans: the comet_user_id must be owned by the MSP
 *     (via eb_tenant_comet_accounts, eb_service_links, or
 *     eb_ph_discover_msp_comet_usernames)
 *   - for `e3_storage` plans: an MSP-owned s3 user is required and gets
 *     prefixed `e3:`
 *   - duplicate-guard: refuses if an active instance already exists for
 *     (tenant, plan, comet_user)
 *   - on success: creates a Stripe subscription + persists eb_plan_instances,
 *     eb_plan_instance_items, and eb_plan_instance_usage_map (for metered).
 *
 * Risks this catches:
 *   - Plan/tenant scope leakage (one MSP assigning another MSP's plan).
 *   - Draft plan being assigned (should be blocked).
 *   - Duplicate active assignment slipping through.
 *   - plan_version snapshot drifting from the plan template version.
 *   - Stripe subscription created but local instance row not persisted (or
 *     vice versa) — orphaned resources on either side.
 *   - Metered components missing their usage_map row (downstream usage
 *     pushes will then route to nothing).
 *   - Component-to-stripe-item matching by stripe_price_id (if this breaks,
 *     instance items get mismatched, billing math goes wrong).
 */
final class PlanAssignmentTest extends DatabaseTestCase
{
    public function test_assign_creates_stripe_subscription_and_persists_canonical_rows(): void
    {
        $accountId = 'acct_phg_' . bin2hex(random_bytes(4));
        $stripePriceId = 'price_phg_' . bin2hex(random_bytes(4));
        $stripeSubItemId = 'si_phg_' . bin2hex(random_bytes(4));
        $cometUserId = 'phg-comet-' . bin2hex(random_bytes(3));

        $mspId = Seeder::seedMsp(['stripe_connect_id' => $accountId]);
        $tenant = $this->seedTenantWithPublicId($mspId);
        $this->seedTenantOwnsCometUser((int) $tenant->id, $cometUserId);

        $planId = $this->seedActiveStoragePlanWithMeteredPrice($mspId, $stripePriceId, 100, 'bill_all');

        $stripe = new TestableStripeService();
        $stripe->queueResponse(['id' => 'cus_phg_' . bin2hex(random_bytes(4))]); // ensureStripeCustomerFor

        $catalog = new TestableCatalogService();
        $catalog->queueResponse([
            'id' => 'sub_phg_' . bin2hex(random_bytes(4)),
            'status' => 'active',
            'items' => [
                'data' => [
                    ['id' => $stripeSubItemId, 'price' => ['id' => $stripePriceId]],
                ],
            ],
        ]);

        $result = eb_ph_plan_assign_for_msp(
            $mspId,
            (int) Capsule::table('eb_msp_accounts')->where('id', $mspId)->value('whmcs_client_id'),
            [
                'tenant_public_id' => (string) $tenant->public_id,
                'comet_user_id' => $cometUserId,
                'plan_id' => $planId,
                'application_fee_percent' => 5.0,
            ],
            $stripe,
            $catalog
        );

        self::assertSame('success', $result['status'], (string)($result['message'] ?? ''));
        self::assertNotEmpty($result['subscription_id']);
        self::assertGreaterThan(0, $result['plan_instance_id']);

        // Stripe subscription POST included our items + fee.
        $subCall = $catalog->lastCall();
        self::assertSame('POST', $subCall->method);
        self::assertSame('/v1/subscriptions', $subCall->path);
        self::assertSame($accountId, $subCall->stripeAccount);
        self::assertSame($stripePriceId, $subCall->params['items[0][price]']);
        self::assertSame(5.0, $subCall->params['application_fee_percent']);

        // Plan instance + item + usage_map persisted.
        $instance = Capsule::table('eb_plan_instances')->where('id', $result['plan_instance_id'])->first();
        self::assertNotNull($instance);
        self::assertSame($mspId, (int) $instance->msp_id);
        self::assertSame((int) $tenant->id, (int) $instance->tenant_id);
        self::assertSame($cometUserId, $instance->comet_user_id);
        self::assertSame(1, (int) $instance->plan_version, 'plan_version must snapshot the template version at time of assignment.');
        self::assertSame('active', $instance->status);

        $itemRow = Capsule::table('eb_plan_instance_items')
            ->where('plan_instance_id', $result['plan_instance_id'])
            ->first();
        self::assertNotNull($itemRow);
        self::assertSame($stripeSubItemId, $itemRow->stripe_subscription_item_id);
        self::assertSame('STORAGE_TB', $itemRow->metric_code);

        $usageMap = Capsule::table('eb_plan_instance_usage_map')
            ->where('plan_instance_item_id', (int) $itemRow->id)
            ->first();
        self::assertNotNull($usageMap, 'Metered components MUST seed a usage_map row.');
        self::assertSame($stripeSubItemId, $usageMap->stripe_subscription_item_id);
    }

    public function test_assign_blocks_when_plan_belongs_to_different_msp(): void
    {
        $myMspId = Seeder::seedMsp();
        $otherMspId = Seeder::seedMsp(['whmcs_client_id' => random_int(900_000_000, 999_999_999) - 1]);

        $tenant = $this->seedTenantWithPublicId($myMspId);
        $planId = $this->seedActiveStoragePlanWithMeteredPrice($otherMspId, 'price_other_' . bin2hex(random_bytes(4)));

        $result = eb_ph_plan_assign_for_msp($myMspId, 1, [
            'tenant_public_id' => (string) $tenant->public_id,
            'comet_user_id' => 'phg-x',
            'plan_id' => $planId,
        ]);

        self::assertSame('error', $result['status']);
        self::assertSame('scope', $result['message']);
    }

    public function test_assign_blocks_when_plan_status_not_active(): void
    {
        $accountId = 'acct_phg_' . bin2hex(random_bytes(4));
        $mspId = Seeder::seedMsp(['stripe_connect_id' => $accountId]);
        $tenant = $this->seedTenantWithPublicId($mspId);
        $planId = $this->seedActiveStoragePlanWithMeteredPrice($mspId);

        // Demote the plan to draft.
        Capsule::table('eb_plan_templates')->where('id', $planId)->update(['status' => 'draft', 'active' => 0]);

        $result = eb_ph_plan_assign_for_msp($mspId, 1, [
            'tenant_public_id' => (string) $tenant->public_id,
            'comet_user_id' => 'phg-x',
            'plan_id' => $planId,
        ]);

        self::assertSame('error', $result['status']);
        self::assertSame('plan_not_active', $result['message']);
    }

    public function test_assign_blocks_when_tenant_not_owned_by_msp(): void
    {
        $myMspId = Seeder::seedMsp();
        $otherMspId = Seeder::seedMsp(['whmcs_client_id' => random_int(900_000_000, 999_999_999) - 2]);
        $stranger = $this->seedTenantWithPublicId($otherMspId);
        $planId = $this->seedActiveStoragePlanWithMeteredPrice($myMspId);

        $result = eb_ph_plan_assign_for_msp($myMspId, 1, [
            'tenant_public_id' => (string) $stranger->public_id,
            'comet_user_id' => 'phg-x',
            'plan_id' => $planId,
        ]);

        self::assertSame('error', $result['status']);
        self::assertSame('tenant_not_found', $result['message']);
    }

    public function test_assign_blocks_when_msp_has_no_stripe_connect_id(): void
    {
        $accountId = '';
        $mspId = Seeder::seedMsp(['stripe_connect_id' => $accountId]);
        Capsule::table('eb_msp_accounts')->where('id', $mspId)->update(['stripe_connect_id' => null]);
        $tenant = $this->seedTenantWithPublicId($mspId);
        $cometUserId = 'phg-comet-' . bin2hex(random_bytes(3));
        $this->seedTenantOwnsCometUser((int) $tenant->id, $cometUserId);
        $planId = $this->seedActiveStoragePlanWithMeteredPrice($mspId);

        $result = eb_ph_plan_assign_for_msp($mspId, 1, [
            'tenant_public_id' => (string) $tenant->public_id,
            'comet_user_id' => $cometUserId,
            'plan_id' => $planId,
        ]);

        self::assertSame('error', $result['status']);
        self::assertSame('not_connected', $result['message']);
    }

    public function test_assign_blocks_when_comet_user_not_owned_by_tenant_or_msp(): void
    {
        $accountId = 'acct_phg_' . bin2hex(random_bytes(4));
        $mspId = Seeder::seedMsp(['stripe_connect_id' => $accountId]);
        $tenant = $this->seedTenantWithPublicId($mspId);
        $planId = $this->seedActiveStoragePlanWithMeteredPrice($mspId);

        $result = eb_ph_plan_assign_for_msp($mspId, 999_999_999, [
            'tenant_public_id' => (string) $tenant->public_id,
            'comet_user_id' => 'unknown-comet-user',
            'plan_id' => $planId,
        ]);

        self::assertSame('error', $result['status']);
        self::assertSame('comet_user_not_found', $result['message']);
    }

    public function test_assign_blocks_when_plan_has_no_components(): void
    {
        $accountId = 'acct_phg_' . bin2hex(random_bytes(4));
        $mspId = Seeder::seedMsp(['stripe_connect_id' => $accountId]);
        $tenant = $this->seedTenantWithPublicId($mspId);
        $cometUserId = 'phg-comet-' . bin2hex(random_bytes(3));
        $this->seedTenantOwnsCometUser((int) $tenant->id, $cometUserId);

        // Plan template with no components.
        $now = date('Y-m-d H:i:s');
        $planId = (int) Capsule::table('eb_plan_templates')->insertGetId([
            'msp_id' => $mspId,
            'name' => 'EB_PHASE_G_SEED Empty Plan',
            'trial_days' => 0,
            'billing_interval' => 'month',
            'currency' => 'CAD',
            'version' => 1,
            'active' => 1,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $result = eb_ph_plan_assign_for_msp($mspId, 1, [
            'tenant_public_id' => (string) $tenant->public_id,
            'comet_user_id' => $cometUserId,
            'plan_id' => $planId,
        ]);

        self::assertSame('error', $result['status']);
        self::assertSame('no_components', $result['message']);
    }

    public function test_duplicate_active_assignment_is_blocked(): void
    {
        $accountId = 'acct_phg_' . bin2hex(random_bytes(4));
        $stripePriceId = 'price_phg_dup_' . bin2hex(random_bytes(4));
        $cometUserId = 'phg-comet-' . bin2hex(random_bytes(3));

        $mspId = Seeder::seedMsp(['stripe_connect_id' => $accountId]);
        $tenant = $this->seedTenantWithPublicId($mspId);
        $this->seedTenantOwnsCometUser((int) $tenant->id, $cometUserId);
        $planId = $this->seedActiveStoragePlanWithMeteredPrice($mspId, $stripePriceId);

        $stripe = new TestableStripeService();
        $stripe->queueResponse(['id' => 'cus_phg_dup_a']);
        $stripe->queueResponse(['id' => 'cus_phg_dup_b']); // not actually reached

        $catalog = new TestableCatalogService();
        $catalog->queueResponse([
            'id' => 'sub_phg_dup',
            'status' => 'active',
            'items' => ['data' => [['id' => 'si_dup', 'price' => ['id' => $stripePriceId]]]],
        ]);

        $args = [
            'tenant_public_id' => (string) $tenant->public_id,
            'comet_user_id' => $cometUserId,
            'plan_id' => $planId,
        ];
        $first = eb_ph_plan_assign_for_msp($mspId, 1, $args, $stripe, $catalog);
        $second = eb_ph_plan_assign_for_msp($mspId, 1, $args, $stripe, $catalog);

        self::assertSame('success', $first['status']);
        self::assertSame('error', $second['status']);
        self::assertStringContainsString('already assigned', (string) $second['message']);

        // Only one plan instance for the (tenant, plan, comet_user) tuple.
        $count = (int) Capsule::table('eb_plan_instances')
            ->where('tenant_id', (int) $tenant->id)
            ->where('plan_id', $planId)
            ->where('comet_user_id', $cometUserId)
            ->count();
        self::assertSame(1, $count);
    }

    public function test_e3_storage_mode_blocks_when_no_s3_user_supplied(): void
    {
        $accountId = 'acct_phg_' . bin2hex(random_bytes(4));
        $mspId = Seeder::seedMsp(['stripe_connect_id' => $accountId]);
        $tenant = $this->seedTenantWithPublicId($mspId);
        $planId = $this->seedActiveE3StoragePlan($mspId);

        $result = eb_ph_plan_assign_for_msp($mspId, 1, [
            'tenant_public_id' => (string) $tenant->public_id,
            's3_user_id' => 0,
            'plan_id' => $planId,
        ]);

        self::assertSame('error', $result['status']);
        self::assertSame('invalid', $result['message']);
    }

    public function test_e3_storage_mode_blocks_when_s3_user_not_owned_by_msp(): void
    {
        $accountId = 'acct_phg_' . bin2hex(random_bytes(4));
        $mspId = Seeder::seedMsp(['stripe_connect_id' => $accountId]);
        $tenant = $this->seedTenantWithPublicId($mspId);
        $planId = $this->seedActiveE3StoragePlan($mspId);

        $result = eb_ph_plan_assign_for_msp($mspId, 999_999_999, [
            'tenant_public_id' => (string) $tenant->public_id,
            's3_user_id' => 12345, // doesn't exist; discoverer returns []
            'plan_id' => $planId,
        ]);

        self::assertSame('error', $result['status']);
        self::assertSame('s3_user_not_found', $result['message']);
    }

    public function test_invalid_input_returns_error_invalid(): void
    {
        $mspId = Seeder::seedMsp();
        $result = eb_ph_plan_assign_for_msp($mspId, 1, [
            'tenant_public_id' => '',
            'plan_id' => 0,
        ]);
        self::assertSame('error', $result['status']);
        self::assertSame('invalid', $result['message']);
    }

    private function seedTenantWithPublicId(int $mspId): object
    {
        $publicId = Seeder::generatePublicId();
        $tenantId = Seeder::seedTenant($mspId, ['public_id' => $publicId]);
        return Capsule::table('eb_tenants')->where('id', $tenantId)->first();
    }

    private function seedTenantOwnsCometUser(int $tenantId, string $cometUserId): void
    {
        $now = date('Y-m-d H:i:s');
        Capsule::table('eb_tenant_comet_accounts')->insert([
            'tenant_id' => $tenantId,
            'comet_user_id' => $cometUserId,
            'comet_username' => $cometUserId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Insert an active plan with a single metered storage component.
     * Returns the plan template id.
     */
    private function seedActiveStoragePlanWithMeteredPrice(
        int $mspId,
        ?string $stripePriceId = null,
        int $defaultQty = 0,
        string $overageMode = 'bill_all'
    ): int {
        $stripePriceId = $stripePriceId ?? 'price_phg_' . bin2hex(random_bytes(4));
        $now = date('Y-m-d H:i:s');

        $productId = (int) Capsule::table('eb_catalog_products')->insertGetId([
            'msp_id' => $mspId,
            'name' => 'EB_PHASE_G_SEED Storage Product',
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
            'name' => 'EB_PHASE_G_SEED Per-GiB Metered',
            'kind' => 'metered',
            'currency' => 'CAD',
            'unit_label' => 'GiB',
            'unit_amount' => 5,
            'interval' => 'month',
            'aggregate_usage' => 'last_during_period',
            'metric_code' => 'STORAGE_TB',
            'active' => 1,
            'billing_type' => 'metered',
            'is_published' => 1,
            'pricing_scheme' => 'per_unit',
            'stripe_price_id' => $stripePriceId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $planId = (int) Capsule::table('eb_plan_templates')->insertGetId([
            'msp_id' => $mspId,
            'name' => 'EB_PHASE_G_SEED Storage Plan',
            'trial_days' => 0,
            'billing_interval' => 'month',
            'currency' => 'CAD',
            'version' => 1,
            'active' => 1,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        Capsule::table('eb_plan_components')->insert([
            'plan_id' => $planId,
            'price_id' => $priceId,
            'metric_code' => 'STORAGE_TB',
            'default_qty' => $defaultQty,
            'overage_mode' => $overageMode,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $planId;
    }

    /**
     * Insert an active plan whose component metric is E3_STORAGE_GIB so the
     * assignment-mode helper returns mode=e3_storage.
     */
    private function seedActiveE3StoragePlan(int $mspId): int
    {
        $now = date('Y-m-d H:i:s');
        $productId = (int) Capsule::table('eb_catalog_products')->insertGetId([
            'msp_id' => $mspId,
            'name' => 'EB_PHASE_G_SEED E3 Storage',
            'category' => 'Backup',
            'active' => 1,
            'is_published' => 1,
            'default_currency' => 'CAD',
            'base_metric_code' => 'E3_STORAGE_GIB',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $priceId = (int) Capsule::table('eb_catalog_prices')->insertGetId([
            'product_id' => $productId,
            'name' => 'EB_PHASE_G_SEED E3 Per-GiB',
            'kind' => 'metered',
            'currency' => 'CAD',
            'unit_label' => 'GiB',
            'unit_amount' => 2,
            'interval' => 'month',
            'aggregate_usage' => 'last_during_period',
            'metric_code' => 'E3_STORAGE_GIB',
            'active' => 1,
            'billing_type' => 'metered',
            'is_published' => 1,
            'pricing_scheme' => 'per_unit',
            'stripe_price_id' => 'price_phg_e3_' . bin2hex(random_bytes(4)),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $planId = (int) Capsule::table('eb_plan_templates')->insertGetId([
            'msp_id' => $mspId,
            'name' => 'EB_PHASE_G_SEED E3 Plan',
            'trial_days' => 0,
            'billing_interval' => 'month',
            'currency' => 'CAD',
            'version' => 1,
            'active' => 1,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        Capsule::table('eb_plan_components')->insert([
            'plan_id' => $planId,
            'price_id' => $priceId,
            'metric_code' => 'E3_STORAGE_GIB',
            'default_qty' => 0,
            'overage_mode' => 'bill_all',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return $planId;
    }
}
