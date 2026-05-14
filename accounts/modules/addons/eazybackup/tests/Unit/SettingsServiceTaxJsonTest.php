<?php

declare(strict_types=1);

namespace EazyBackup\Tests\Unit;

use EazyBackup\Tests\Support\DatabaseTestCase;
use PartnerHub\SettingsService;
use WHMCS\Database\Capsule;

/**
 * Round-trip + deep-merge coverage for SettingsService tax settings.
 *
 * Source: lib/PartnerHub/SettingsService.php (getTaxSettings / saveTaxSettings)
 *
 * The Tax & Invoicing UI consumes the full default shape; if save() ever lets
 * a partial overwrite the defaults, the UI breaks silently. These tests lock
 * the deep-merge contract in place.
 */
final class SettingsServiceTaxJsonTest extends DatabaseTestCase
{
    public function test_get_tax_settings_for_unknown_msp_returns_full_default_shape(): void
    {
        $settings = SettingsService::getTaxSettings($this->testMspId);

        // Top-level sections the UI binds to.
        self::assertArrayHasKey('tax_mode', $settings);
        self::assertArrayHasKey('registrations', $settings);
        self::assertArrayHasKey('invoice_presentation', $settings);
        self::assertArrayHasKey('credit_notes', $settings);
        self::assertArrayHasKey('rounding', $settings);

        // Critical sub-keys the controller writes through to Stripe.
        self::assertArrayHasKey('stripe_tax_enabled', $settings['tax_mode']);
        self::assertArrayHasKey('default_tax_behavior', $settings['tax_mode']);
        self::assertArrayHasKey('respect_exemption', $settings['tax_mode']);

        self::assertArrayHasKey('business_address', $settings['registrations']);
        self::assertArrayHasKey('country', $settings['registrations']['business_address']);

        self::assertArrayHasKey('invoice_prefix', $settings['invoice_presentation']);
        self::assertArrayHasKey('payment_terms', $settings['invoice_presentation']);

        self::assertArrayHasKey('rounding_mode', $settings['rounding']);
        self::assertArrayHasKey('writeoff_threshold_cents', $settings['rounding']);

        // Defaults that other code branches depend on.
        self::assertSame('exclusive', $settings['tax_mode']['default_tax_behavior']);
        self::assertSame('CA', $settings['registrations']['business_address']['country']);
        self::assertSame('due_immediately', $settings['invoice_presentation']['payment_terms']);
    }

    public function test_save_then_get_deep_merges_into_defaults(): void
    {
        SettingsService::saveTaxSettings($this->testMspId, [
            'tax_mode' => [
                'stripe_tax_enabled' => true,
            ],
            'invoice_presentation' => [
                'invoice_prefix' => 'EBT-',
            ],
        ]);

        $settings = SettingsService::getTaxSettings($this->testMspId);

        // Saved keys took effect.
        self::assertTrue($settings['tax_mode']['stripe_tax_enabled']);
        self::assertSame('EBT-', $settings['invoice_presentation']['invoice_prefix']);

        // Non-saved keys at the same level were preserved from defaults (deep merge).
        self::assertSame('exclusive', $settings['tax_mode']['default_tax_behavior']);
        self::assertTrue($settings['tax_mode']['respect_exemption']);
        self::assertSame('due_immediately', $settings['invoice_presentation']['payment_terms']);
        self::assertTrue($settings['invoice_presentation']['show_logo']);

        // Unrelated top-level sections still present.
        self::assertSame('bankers_rounding', $settings['rounding']['rounding_mode']);
    }

    public function test_save_twice_updates_in_place_no_duplicate_rows(): void
    {
        SettingsService::saveTaxSettings($this->testMspId, [
            'invoice_presentation' => ['invoice_prefix' => 'V1-'],
        ]);
        SettingsService::saveTaxSettings($this->testMspId, [
            'invoice_presentation' => ['invoice_prefix' => 'V2-'],
        ]);

        $rowCount = (int) Capsule::table('eb_msp_settings')
            ->where('msp_id', $this->testMspId)
            ->count();
        self::assertSame(1, $rowCount, 'eb_msp_settings should be upserted, not duplicated.');

        $settings = SettingsService::getTaxSettings($this->testMspId);
        self::assertSame('V2-', $settings['invoice_presentation']['invoice_prefix']);
    }

    public function test_saving_empty_array_preserves_defaults_on_read(): void
    {
        SettingsService::saveTaxSettings($this->testMspId, []);

        $settings = SettingsService::getTaxSettings($this->testMspId);
        self::assertSame('exclusive', $settings['tax_mode']['default_tax_behavior']);
        self::assertSame('CA', $settings['registrations']['business_address']['country']);
        self::assertSame('due_immediately', $settings['invoice_presentation']['payment_terms']);
    }
}
