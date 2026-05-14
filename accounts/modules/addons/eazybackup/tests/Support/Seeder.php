<?php

declare(strict_types=1);

namespace EazyBackup\Tests\Support;

use PartnerHub\SettingsService;
use WHMCS\Database\Capsule;

/**
 * Deterministic fixture seeder for Phase A.
 *
 * Two consumption modes:
 *   - From DatabaseTestCase: every call lives inside a rolled-back transaction,
 *     so the inserts disappear when the test ends.
 *   - From bin/dev/seed_phase_a.php: the CLI commits, so QA can interact with
 *     the seeded rows in the dev WHMCS UI. Reset script removes them by
 *     SEED_TAG_PREFIX.
 *
 * Every row that has a free-text "name" / "description" / "metadata_json"
 * column gets the SEED_TAG_PREFIX baked in, so reset can find them even if
 * the manifest file is lost.
 */
class Seeder
{
    /** Embedded in user-visible name fields so reset can find seeded rows. */
    public const SEED_TAG_PREFIX = 'EB_PHASE_A_SEED::';

    /** Tables we touch. Reset deletes from each, in reverse-FK order. */
    public const SEEDED_TABLES = [
        'eb_msp_tax_audit',
        'eb_msp_tax_regs',
        'eb_msp_settings',
        'eb_plan_components',
        'eb_plan_templates',
        'eb_catalog_prices',
        'eb_catalog_products',
        'eb_tenants',
        'eb_msp_accounts',
    ];

    /**
     * Insert an eb_msp_accounts row. Returns msp id.
     *
     * Uses a sentinel whmcs_client_id high in the int range so we never collide
     * with real clients. Caller can override.
     */
    public static function seedMsp(array $opts = []): int
    {
        $now = date('Y-m-d H:i:s');
        $whmcsClientId = (int) ($opts['whmcs_client_id'] ?? random_int(900_000_000, 999_999_999));
        $name = (string) ($opts['name'] ?? (self::SEED_TAG_PREFIX . 'MSP ' . $whmcsClientId));

        // Idempotent on whmcs_client_id (which is unique on the table).
        $existing = Capsule::table('eb_msp_accounts')
            ->where('whmcs_client_id', $whmcsClientId)
            ->first(['id']);
        if ($existing) {
            return (int) $existing->id;
        }

        return (int) Capsule::table('eb_msp_accounts')->insertGetId([
            'whmcs_client_id' => $whmcsClientId,
            'name' => $name,
            'status' => (string) ($opts['status'] ?? 'active'),
            'billing_mode' => (string) ($opts['billing_mode'] ?? 'stripe_connect'),
            'stripe_connect_id' => (string) ($opts['stripe_connect_id'] ?? 'acct_test_phaseA'),
            'default_currency' => strtoupper((string) ($opts['default_currency'] ?? 'CAD')),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Insert an eb_tenants row. Returns tenant id.
     */
    public static function seedTenant(int $mspId, array $opts = []): int
    {
        $now = date('Y-m-d H:i:s');
        $name = (string) ($opts['name'] ?? (self::SEED_TAG_PREFIX . 'Tenant'));
        $publicId = (string) ($opts['public_id'] ?? self::generatePublicId());

        return (int) Capsule::table('eb_tenants')->insertGetId([
            'public_id' => $publicId,
            'msp_id' => $mspId,
            'name' => $name,
            'slug' => (string) ($opts['slug'] ?? ('eb-phasea-' . substr($publicId, -8))),
            'contact_name' => (string) ($opts['contact_name'] ?? 'Phase A Tenant Admin'),
            'contact_email' => (string) ($opts['contact_email'] ?? 'phase-a@example.test'),
            'contact_phone' => (string) ($opts['contact_phone'] ?? ''),
            'address_line1' => (string) ($opts['address_line1'] ?? ''),
            'city' => (string) ($opts['city'] ?? ''),
            'country' => (string) ($opts['country'] ?? 'CA'),
            'status' => (string) ($opts['status'] ?? 'active'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Insert an eb_catalog_products row. Returns product id.
     */
    public static function seedCatalogProduct(int $mspId, array $opts = []): int
    {
        $now = date('Y-m-d H:i:s');
        $name = (string) ($opts['name'] ?? (self::SEED_TAG_PREFIX . 'Storage Product'));

        return (int) Capsule::table('eb_catalog_products')->insertGetId([
            'msp_id' => $mspId,
            'name' => $name,
            'description' => (string) ($opts['description'] ?? (self::SEED_TAG_PREFIX . 'Phase A seeded storage product.')),
            'category' => (string) ($opts['category'] ?? 'Backup'),
            'active' => (int) ($opts['active'] ?? 1),
            'is_published' => (int) ($opts['is_published'] ?? 0),
            'default_currency' => strtoupper((string) ($opts['default_currency'] ?? 'CAD')),
            'base_metric_code' => (string) ($opts['base_metric_code'] ?? 'STORAGE_TB'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Insert an eb_catalog_prices row. Returns price id.
     */
    public static function seedCatalogPrice(int $productId, array $opts = []): int
    {
        $now = date('Y-m-d H:i:s');
        $name = (string) ($opts['name'] ?? (self::SEED_TAG_PREFIX . 'Per-GiB Metered'));

        return (int) Capsule::table('eb_catalog_prices')->insertGetId([
            'product_id' => $productId,
            'name' => $name,
            'kind' => (string) ($opts['kind'] ?? 'metered'),
            'currency' => strtoupper((string) ($opts['currency'] ?? 'CAD')),
            'unit_label' => (string) ($opts['unit_label'] ?? 'GiB'),
            'unit_amount' => (int) ($opts['unit_amount'] ?? 5),
            'interval' => (string) ($opts['interval'] ?? 'month'),
            'aggregate_usage' => (string) ($opts['aggregate_usage'] ?? 'last_during_period'),
            'metric_code' => (string) ($opts['metric_code'] ?? 'STORAGE_TB'),
            'active' => (int) ($opts['active'] ?? 1),
            'billing_type' => (string) ($opts['billing_type'] ?? 'metered'),
            'is_published' => (int) ($opts['is_published'] ?? 0),
            'pricing_scheme' => (string) ($opts['pricing_scheme'] ?? 'per_unit'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Insert an eb_plan_templates row plus one component.
     * Returns ['plan_id' => int, 'component_id' => int].
     */
    public static function seedPlanTemplate(int $mspId, int $priceId, array $opts = []): array
    {
        $now = date('Y-m-d H:i:s');
        $name = (string) ($opts['name'] ?? (self::SEED_TAG_PREFIX . 'Storage Plan'));

        $planId = (int) Capsule::table('eb_plan_templates')->insertGetId([
            'msp_id' => $mspId,
            'name' => $name,
            'description' => (string) ($opts['description'] ?? (self::SEED_TAG_PREFIX . 'Phase A seeded plan template.')),
            'trial_days' => (int) ($opts['trial_days'] ?? 0),
            'billing_interval' => (string) ($opts['billing_interval'] ?? 'month'),
            'currency' => strtoupper((string) ($opts['currency'] ?? 'CAD')),
            'version' => (int) ($opts['version'] ?? 1),
            'active' => (int) ($opts['active'] ?? 1),
            'status' => (string) ($opts['status'] ?? 'active'),
            'metadata_json' => json_encode(['_seed_tag' => self::SEED_TAG_PREFIX]),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $componentId = (int) Capsule::table('eb_plan_components')->insertGetId([
            'plan_id' => $planId,
            'price_id' => $priceId,
            'metric_code' => (string) ($opts['metric_code'] ?? 'STORAGE_TB'),
            'default_qty' => (int) ($opts['default_qty'] ?? 100),
            'overage_mode' => (string) ($opts['overage_mode'] ?? 'bill_all'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return ['plan_id' => $planId, 'component_id' => $componentId];
    }

    /**
     * Persist tax + email settings for an MSP using the real SettingsService so
     * encryption / merging behaviour matches production.
     */
    public static function seedMspSettings(int $mspId, array $taxJson = [], array $emailJson = []): void
    {
        if ($taxJson !== []) {
            SettingsService::saveTaxSettings($mspId, $taxJson);
        }
        if ($emailJson !== []) {
            SettingsService::saveEmailSettings($mspId, $emailJson);
        }
    }

    /**
     * Insert a tax registration tagged with the seed prefix.
     */
    public static function seedTaxRegistration(int $mspId, array $opts = []): int
    {
        $now = date('Y-m-d H:i:s');
        $legalName = (string) ($opts['legal_name'] ?? (self::SEED_TAG_PREFIX . 'Acme Co'));

        return (int) Capsule::table('eb_msp_tax_regs')->insertGetId([
            'msp_id' => $mspId,
            'country' => strtoupper((string) ($opts['country'] ?? 'CA')),
            'region' => (string) ($opts['region'] ?? 'BC'),
            'registration_number' => (string) ($opts['registration_number'] ?? '123456789RT0001'),
            'legal_name' => $legalName,
            'source' => (string) ($opts['source'] ?? 'local'),
            'is_active' => (int) ($opts['is_active'] ?? 1),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Best-effort ULID-ish generator. Falls back to bin2hex(random_bytes()) when the
     * addon's eazybackup_generate_ulid() helper isn't loaded (rare, but keeps the
     * seeder usable from stand-alone scripts).
     */
    public static function generatePublicId(): string
    {
        if (function_exists('eazybackup_generate_ulid')) {
            try {
                $id = eazybackup_generate_ulid();
                if (is_string($id) && $id !== '') {
                    return $id;
                }
            } catch (\Throwable $__) {
            }
        }
        return strtoupper(bin2hex(random_bytes(13)));
    }
}
