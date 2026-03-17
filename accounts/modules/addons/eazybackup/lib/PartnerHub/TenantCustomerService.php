<?php

namespace PartnerHub;

use WHMCS\Database\Capsule;

/**
 * Bridges eb_whitelabel_tenants and eb_tenants via canonical_tenant_id.
 * No WHMCS client or eb_customers; tenants are eb_tenants only.
 */
class TenantCustomerService
{
    /**
     * Return the canonical tenant (eb_tenants) for a whitelabel tenant, or null.
     *
     * @param int $whitelabelTenantId eb_whitelabel_tenants.id
     * @return array|null eb_tenants row as array, or null
     */
    public function getCustomerForTenant(int $whitelabelTenantId): ?array
    {
        if ($whitelabelTenantId <= 0) {
            return null;
        }

        try {
            $wl = Capsule::table('eb_whitelabel_tenants')
                ->where('id', $whitelabelTenantId)
                ->first(['canonical_tenant_id']);
            $canonicalId = (int)($wl->canonical_tenant_id ?? 0);
            if ($canonicalId <= 0) {
                return null;
            }

            $row = Capsule::table('eb_tenants')
                ->where('id', $canonicalId)
                ->first();
        } catch (\Throwable $__) {
            return null;
        }

        return $row ? (array)$row : null;
    }

    /**
     * Ensure a canonical eb_tenants row exists for the whitelabel tenant and link via canonical_tenant_id.
     * Returns the eb_tenants row (existing or newly created).
     *
     * @param int $whitelabelTenantId eb_whitelabel_tenants.id
     * @return array eb_tenants row as array
     * @throws \RuntimeException tenant_not_found, tenant_owner_client_missing, tenant_customer_create_failed
     */
    public function ensureCustomerForTenant(int $whitelabelTenantId): array
    {
        if ($whitelabelTenantId <= 0) {
            throw new \InvalidArgumentException('tenant_id_required');
        }

        return Capsule::connection()->transaction(function () use ($whitelabelTenantId): array {
            $schema = Capsule::schema();
            $wlTenant = Capsule::table('eb_whitelabel_tenants')
                ->where('id', $whitelabelTenantId)
                ->lockForUpdate()
                ->first();
            if (!$wlTenant) {
                throw new \RuntimeException('tenant_not_found');
            }

            $ownerClientId = (int)($wlTenant->client_id ?? 0);
            if ($ownerClientId <= 0) {
                throw new \RuntimeException('tenant_owner_client_missing');
            }

            $canonicalId = (int)($wlTenant->canonical_tenant_id ?? 0);
            if ($canonicalId > 0) {
                $existing = Capsule::table('eb_tenants')->where('id', $canonicalId)->first();
                if ($existing) {
                    if (
                        $schema->hasTable('eb_tenants')
                        && $schema->hasColumn('eb_tenants', 'public_id')
                        && (string)($existing->public_id ?? '') === ''
                    ) {
                        try {
                            $publicId = eazybackup_generate_ulid();
                            $updated = Capsule::table('eb_tenants')
                                ->where('id', $canonicalId)
                                ->where(function ($query) {
                                    $query->whereNull('public_id')
                                        ->orWhere('public_id', '');
                                })
                                ->update(['public_id' => $publicId]);
                            if ((int)$updated > 0) {
                                $existing->public_id = $publicId;
                            } else {
                                $currentPublicId = (string)(Capsule::table('eb_tenants')
                                    ->where('id', $canonicalId)
                                    ->value('public_id') ?? '');
                                if ($currentPublicId !== '') {
                                    $existing->public_id = $currentPublicId;
                                }
                            }
                        } catch (\Throwable $__) {
                            // Keep serving the canonical row; module upgrade handles bulk backfill.
                        }
                    }
                    return (array)$existing;
                }
            }

            $mspId = $this->ensureMspAccountForClient($ownerClientId);
            $name = $this->resolveClientDisplayName($ownerClientId);
            $slug = 'wl-' . $whitelabelTenantId;
            $now = date('Y-m-d H:i:s');
            $insert = [
                'msp_id' => $mspId,
                'name' => $name !== '' ? $name : 'Tenant ' . $whitelabelTenantId,
                'slug' => $slug,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if ($schema->hasTable('eb_tenants') && $schema->hasColumn('eb_tenants', 'public_id')) {
                $insert['public_id'] = eazybackup_generate_ulid();
            }

            try {
                $newId = (int)Capsule::table('eb_tenants')->insertGetId($insert);
            } catch (\Throwable $e) {
                $raced = Capsule::table('eb_whitelabel_tenants')
                    ->where('id', $whitelabelTenantId)
                    ->first(['canonical_tenant_id']);
                $racedCanonical = (int)($raced->canonical_tenant_id ?? 0);
                if ($racedCanonical > 0) {
                    $row = Capsule::table('eb_tenants')->where('id', $racedCanonical)->first();
                    if ($row) {
                        return (array)$row;
                    }
                }
                throw $e;
            }

            Capsule::table('eb_whitelabel_tenants')
                ->where('id', $whitelabelTenantId)
                ->update([
                    'canonical_tenant_id' => $newId,
                ]);

            $created = Capsule::table('eb_tenants')->where('id', $newId)->first();
            if ($created) {
                return (array)$created;
            }

            throw new \RuntimeException('tenant_customer_create_failed');
        });
    }

    private function ensureMspAccountForClient(int $clientId): int
    {
        $existing = Capsule::table('eb_msp_accounts')
            ->where('whmcs_client_id', $clientId)
            ->lockForUpdate()
            ->first();
        if ($existing) {
            return (int)$existing->id;
        }

        $name = $this->resolveClientDisplayName($clientId);
        $now = date('Y-m-d H:i:s');

        try {
            return (int)Capsule::table('eb_msp_accounts')->insertGetId([
                'whmcs_client_id' => $clientId,
                'name' => $name,
                'status' => 'active',
                'billing_mode' => 'stripe_connect',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (\Throwable $__) {
            $raced = Capsule::table('eb_msp_accounts')->where('whmcs_client_id', $clientId)->first();
            if ($raced) {
                return (int)$raced->id;
            }
            throw $__;
        }
    }

    private function resolveClientDisplayName(int $clientId): string
    {
        try {
            $row = Capsule::table('tblclients')
                ->where('id', $clientId)
                ->first(['companyname', 'firstname', 'lastname', 'email']);
            if ($row) {
                $company = trim((string)($row->companyname ?? ''));
                if ($company !== '') {
                    return $company;
                }

                $full = trim(((string)($row->firstname ?? '')) . ' ' . ((string)($row->lastname ?? '')));
                if ($full !== '') {
                    return $full;
                }

                $email = trim((string)($row->email ?? ''));
                if ($email !== '') {
                    return $email;
                }
            }
        } catch (\Throwable $__) {
            // ignore
        }

        return 'Client #' . $clientId;
    }
}
