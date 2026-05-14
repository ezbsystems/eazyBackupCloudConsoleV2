<?php

declare(strict_types=1);

namespace EazyBackup\Tests\Unit;

use EazyBackup\Tests\Support\DatabaseTestCase;
use PartnerHub\TenantCustomerService;
use WHMCS\Database\Capsule;

/**
 * Coverage for TenantCustomerService::ensureCustomerForTenant /
 * getCustomerForTenant.
 *
 * Source: lib/PartnerHub/TenantCustomerService.php
 *
 * Risks this catches:
 *   - A double-call creating two canonical eb_tenants rows (would orphan one
 *     and break Stripe customer linkage for the second).
 *   - getCustomerForTenant returning the wrong tenant when the canonical link
 *     is missing.
 *   - Errors being swallowed when invariants are violated (so the controller
 *     never sees that the operation failed).
 *
 * Note on "concurrent" coverage: true cross-process concurrency requires
 * multiple PHP processes hitting MySQL at the same time. Within a single
 * PHPUnit transaction we still cover the most important property — that the
 * service is internally idempotent across repeated calls and respects an
 * already-set canonical_tenant_id without re-inserting.
 */
final class TenantCustomerServiceIdempotencyTest extends DatabaseTestCase
{
    public function test_ensure_creates_eb_tenants_row_and_links_via_canonical_tenant_id(): void
    {
        $clientId = $this->seedTblclient();
        $wlTenantId = $this->seedWhitelabelTenant($clientId);

        $svc = new TenantCustomerService();
        $row = $svc->ensureCustomerForTenant($wlTenantId);

        self::assertArrayHasKey('id', $row);
        self::assertGreaterThan(0, (int) $row['id']);

        $linkedId = (int) Capsule::table('eb_whitelabel_tenants')
            ->where('id', $wlTenantId)
            ->value('canonical_tenant_id');
        self::assertSame((int) $row['id'], $linkedId, 'eb_whitelabel_tenants.canonical_tenant_id must be stamped.');
    }

    public function test_ensure_is_idempotent_on_repeat_calls(): void
    {
        $clientId = $this->seedTblclient();
        $wlTenantId = $this->seedWhitelabelTenant($clientId);

        $svc = new TenantCustomerService();
        $first = $svc->ensureCustomerForTenant($wlTenantId);
        $second = $svc->ensureCustomerForTenant($wlTenantId);
        $third = $svc->ensureCustomerForTenant($wlTenantId);

        self::assertSame($first['id'], $second['id']);
        self::assertSame($first['id'], $third['id']);

        // The eb_tenants table should contain exactly one row matching this canonical id.
        $count = (int) Capsule::table('eb_tenants')
            ->where('id', (int) $first['id'])
            ->count();
        self::assertSame(1, $count);
    }

    public function test_ensure_returns_existing_canonical_row_when_link_already_set(): void
    {
        $clientId = $this->seedTblclient();
        $wlTenantId = $this->seedWhitelabelTenant($clientId);

        // Pre-existing canonical row + link.
        $now = date('Y-m-d H:i:s');
        $publicId = function_exists('eazybackup_generate_ulid')
            ? eazybackup_generate_ulid()
            : strtoupper(bin2hex(random_bytes(13)));
        $canonicalId = (int) Capsule::table('eb_tenants')->insertGetId([
            'public_id' => $publicId,
            'msp_id' => 0,
            'name' => 'Pre-existing Canonical',
            'slug' => 'pre-existing',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        Capsule::table('eb_whitelabel_tenants')
            ->where('id', $wlTenantId)
            ->update(['canonical_tenant_id' => $canonicalId]);

        $svc = new TenantCustomerService();
        $row = $svc->ensureCustomerForTenant($wlTenantId);

        self::assertSame($canonicalId, (int) $row['id']);
        self::assertSame('Pre-existing Canonical', $row['name']);

        // No new eb_tenants row should have been inserted.
        $count = (int) Capsule::table('eb_tenants')
            ->where('id', '>', $canonicalId)
            ->count();
        self::assertSame(0, $count);
    }

    public function test_get_returns_null_when_no_canonical_link(): void
    {
        $clientId = $this->seedTblclient();
        $wlTenantId = $this->seedWhitelabelTenant($clientId);

        $svc = new TenantCustomerService();
        self::assertNull($svc->getCustomerForTenant($wlTenantId));
    }

    public function test_get_with_invalid_id_returns_null(): void
    {
        $svc = new TenantCustomerService();
        self::assertNull($svc->getCustomerForTenant(0));
        self::assertNull($svc->getCustomerForTenant(-1));
    }

    public function test_ensure_throws_on_missing_whitelabel_tenant(): void
    {
        $svc = new TenantCustomerService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tenant_not_found');
        $svc->ensureCustomerForTenant(999_999_999);
    }

    public function test_ensure_throws_on_invalid_tenant_id(): void
    {
        $svc = new TenantCustomerService();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('tenant_id_required');
        $svc->ensureCustomerForTenant(0);
    }

    public function test_ensure_throws_when_owner_client_id_is_missing(): void
    {
        // Client id 0 means we have no owner — must hard-fail rather than silently create
        // an MSP/tenant under the wrong account.
        $wlTenantId = $this->seedWhitelabelTenant(0);

        $svc = new TenantCustomerService();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tenant_owner_client_missing');
        $svc->ensureCustomerForTenant($wlTenantId);
    }

    /**
     * Insert a tblclients row inside the test transaction and return its id.
     */
    private function seedTblclient(): int
    {
        $now = date('Y-m-d H:i:s');
        return (int) Capsule::table('tblclients')->insertGetId([
            'firstname' => 'EB Phase B',
            'lastname' => 'TestClient ' . bin2hex(random_bytes(3)),
            'email' => 'phaseb-' . bin2hex(random_bytes(4)) . '@example.test',
            'companyname' => 'EB_PHASE_B_SEED Co',
            'datecreated' => $now,
        ]);
    }

    private function seedWhitelabelTenant(int $clientId): int
    {
        $now = date('Y-m-d H:i:s');
        $unique = bin2hex(random_bytes(6));
        return (int) Capsule::table('eb_whitelabel_tenants')->insertGetId([
            'public_id' => strtoupper(bin2hex(random_bytes(13))),
            'client_id' => $clientId,
            'status' => 'active',
            'subdomain' => 'phb-' . $unique,
            'fqdn' => 'phb-' . $unique . '.example.test',
            'idempotency_key' => 'phb-' . $unique,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
