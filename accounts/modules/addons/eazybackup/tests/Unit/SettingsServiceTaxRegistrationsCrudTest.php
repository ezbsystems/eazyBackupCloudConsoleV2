<?php

declare(strict_types=1);

namespace EazyBackup\Tests\Unit;

use EazyBackup\Tests\Support\DatabaseTestCase;
use PartnerHub\SettingsService;
use WHMCS\Database\Capsule;

/**
 * CRUD + tenancy + audit-trail coverage for SettingsService tax registrations.
 *
 * Source: lib/PartnerHub/SettingsService.php
 *
 * Risks this catches:
 *   - Cross-MSP delete (one MSP wiping another's registrations).
 *   - Country/region not normalised to upper case (breaks Stripe Tax mapping).
 *   - Audit row schema drift (downstream compliance reports rely on the JSON shape).
 */
final class SettingsServiceTaxRegistrationsCrudTest extends DatabaseTestCase
{
    public function test_upsert_inserts_row_when_id_missing(): void
    {
        $row = SettingsService::upsertRegistration($this->testMspId, [
            'country' => 'ca',
            'region' => 'bc',
            'registration_number' => '123456789RT0001',
            'legal_name' => 'Acme Co',
        ]);

        self::assertGreaterThan(0, $row['id']);
        self::assertSame('CA', $row['country']);
        self::assertSame('BC', $row['region']);

        $persisted = Capsule::table('eb_msp_tax_regs')
            ->where('id', $row['id'])
            ->first();
        self::assertNotNull($persisted);
        self::assertSame('CA', $persisted->country);
        self::assertSame($this->testMspId, (int) $persisted->msp_id);
    }

    public function test_upsert_updates_existing_row_in_place(): void
    {
        $created = SettingsService::upsertRegistration($this->testMspId, [
            'country' => 'CA',
            'region' => 'BC',
            'registration_number' => '111',
        ]);
        $beforeCount = (int) Capsule::table('eb_msp_tax_regs')
            ->where('msp_id', $this->testMspId)->count();

        $updated = SettingsService::upsertRegistration($this->testMspId, [
            'id' => $created['id'],
            'country' => 'CA',
            'region' => 'ON',
            'registration_number' => '222',
        ]);

        $afterCount = (int) Capsule::table('eb_msp_tax_regs')
            ->where('msp_id', $this->testMspId)->count();
        self::assertSame($beforeCount, $afterCount, 'Update must not insert a new row.');

        $persisted = Capsule::table('eb_msp_tax_regs')
            ->where('id', $updated['id'])->first();
        self::assertSame('ON', $persisted->region);
        self::assertSame('222', $persisted->registration_number);
    }

    public function test_country_is_uppercased_and_missing_region_stored_null(): void
    {
        $row = SettingsService::upsertRegistration($this->testMspId, [
            'country' => 'gb',
            'registration_number' => 'GB123',
        ]);

        $persisted = Capsule::table('eb_msp_tax_regs')
            ->where('id', $row['id'])->first();
        self::assertSame('GB', $persisted->country);
        self::assertNull($persisted->region);
    }

    public function test_source_field_collapses_to_local_for_anything_other_than_stripe(): void
    {
        $row = SettingsService::upsertRegistration($this->testMspId, [
            'country' => 'CA',
            'registration_number' => 'X',
            'source' => 'manual',
        ]);
        $persisted = Capsule::table('eb_msp_tax_regs')->where('id', $row['id'])->first();
        self::assertSame('local', $persisted->source);

        $row = SettingsService::upsertRegistration($this->testMspId, [
            'country' => 'CA',
            'registration_number' => 'Y',
            'source' => 'stripe',
        ]);
        $persisted = Capsule::table('eb_msp_tax_regs')->where('id', $row['id'])->first();
        self::assertSame('stripe', $persisted->source);
    }

    public function test_delete_removes_owned_row_and_returns_true(): void
    {
        $row = SettingsService::upsertRegistration($this->testMspId, [
            'country' => 'CA',
            'registration_number' => 'DEL-ME',
        ]);

        $result = SettingsService::deleteRegistration($this->testMspId, $row['id']);
        self::assertTrue($result);

        $exists = Capsule::table('eb_msp_tax_regs')
            ->where('id', $row['id'])->exists();
        self::assertFalse($exists);
    }

    public function test_cross_msp_delete_does_not_remove_other_msps_row(): void
    {
        $otherMsp = $this->testMspId + 1;

        $row = SettingsService::upsertRegistration($otherMsp, [
            'country' => 'CA',
            'registration_number' => 'PROTECTED',
        ]);

        SettingsService::deleteRegistration($this->testMspId, $row['id']);

        $stillThere = Capsule::table('eb_msp_tax_regs')
            ->where('id', $row['id'])->exists();
        self::assertTrue($stillThere, 'deleteRegistration must scope by msp_id.');
    }

    public function test_audit_tax_writes_expected_shape(): void
    {
        SettingsService::auditTax(
            $this->testMspId,
            'create',
            ['was' => null],
            ['country' => 'CA'],
            ['source_ip' => '127.0.0.1'],
            42
        );

        $row = Capsule::table('eb_msp_tax_audit')
            ->where('msp_id', $this->testMspId)
            ->orderByDesc('id')
            ->first();

        self::assertNotNull($row);
        self::assertSame('create', $row->action);
        self::assertSame(42, (int) $row->user_id);

        $before = json_decode((string) $row->before_json, true);
        $after = json_decode((string) $row->after_json, true);
        $meta = json_decode((string) $row->meta_json, true);

        self::assertSame(['was' => null], $before);
        self::assertSame(['country' => 'CA'], $after);
        self::assertSame(['source_ip' => '127.0.0.1'], $meta);
    }
}
