<?php

namespace PartnerHub;

use WHMCS\Database\Capsule;

class TenantCustomerService
{
    public function getCustomerForTenant(int $tenantId): ?array
    {
        if ($tenantId <= 0) {
            return null;
        }

        try {
            $row = Capsule::table('eb_customers')
                ->where('tenant_id', $tenantId)
                ->first();
        } catch (\Throwable $__) {
            return null;
        }

        return $row ? (array)$row : null;
    }

    public function ensureCustomerForTenant(int $tenantId): array
    {
        if ($tenantId <= 0) {
            throw new \InvalidArgumentException('tenant_id_required');
        }

        return Capsule::connection()->transaction(function () use ($tenantId): array {
            $tenant = Capsule::table('eb_whitelabel_tenants')
                ->where('id', $tenantId)
                ->lockForUpdate()
                ->first();
            if (!$tenant) {
                throw new \RuntimeException('tenant_not_found');
            }

            $ownerClientId = (int)($tenant->client_id ?? 0);
            if ($ownerClientId <= 0) {
                throw new \RuntimeException('tenant_owner_client_missing');
            }

            $mspId = $this->ensureMspAccountForClient($ownerClientId);
            $now = date('Y-m-d H:i:s');

            $existingLocked = Capsule::table('eb_customers')
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->first();
            if ($existingLocked) {
                if ((int)($existingLocked->whmcs_client_id ?? 0) !== $ownerClientId) {
                    throw new \RuntimeException('tenant_customer_owner_conflict');
                }

                if ((int)($existingLocked->msp_id ?? 0) !== $mspId) {
                    Capsule::table('eb_customers')
                        ->where('id', (int)$existingLocked->id)
                        ->update([
                            'msp_id' => $mspId,
                            'updated_at' => $now,
                        ]);
                    $existingLocked = Capsule::table('eb_customers')->where('id', (int)$existingLocked->id)->first();
                }

                if ($existingLocked) {
                    return (array)$existingLocked;
                }
            }

            $existingByClient = Capsule::table('eb_customers')
                ->where('whmcs_client_id', $ownerClientId)
                ->lockForUpdate()
                ->first();
            if ($existingByClient) {
                $boundTenantId = (int)($existingByClient->tenant_id ?? 0);
                if ($boundTenantId > 0 && $boundTenantId !== $tenantId) {
                    throw new \RuntimeException('tenant_customer_conflict');
                }

                Capsule::table('eb_customers')
                    ->where('id', (int)$existingByClient->id)
                    ->update([
                        'msp_id' => $mspId,
                        'tenant_id' => $tenantId,
                        'updated_at' => $now,
                    ]);

                $updated = Capsule::table('eb_customers')->where('id', (int)$existingByClient->id)->first();
                if ($updated) {
                    return (array)$updated;
                }
            }

            $displayName = $this->resolveClientDisplayName($ownerClientId);

            try {
                $customerId = (int)Capsule::table('eb_customers')->insertGetId([
                    'msp_id' => $mspId,
                    'tenant_id' => $tenantId,
                    'whmcs_client_id' => $ownerClientId,
                    'name' => $displayName,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } catch (\Throwable $e) {
                $raced = Capsule::table('eb_customers')->where('tenant_id', $tenantId)->first();
                if ($raced) {
                    if ((int)($raced->whmcs_client_id ?? 0) !== $ownerClientId) {
                        throw new \RuntimeException('tenant_customer_owner_conflict');
                    }
                    if ((int)($raced->msp_id ?? 0) !== $mspId) {
                        Capsule::table('eb_customers')->where('id', (int)$raced->id)->update([
                            'msp_id' => $mspId,
                            'updated_at' => $now,
                        ]);
                        $raced = Capsule::table('eb_customers')->where('id', (int)$raced->id)->first();
                    }
                    return (array)$raced;
                }

                $racedByClient = Capsule::table('eb_customers')->where('whmcs_client_id', $ownerClientId)->first();
                if ($racedByClient) {
                    $boundTenantId = (int)($racedByClient->tenant_id ?? 0);
                    if ($boundTenantId > 0 && $boundTenantId !== $tenantId) {
                        throw $e;
                    }

                    Capsule::table('eb_customers')
                        ->where('id', (int)$racedByClient->id)
                        ->update([
                            'msp_id' => $mspId,
                            'tenant_id' => $tenantId,
                            'updated_at' => $now,
                        ]);
                    $updated = Capsule::table('eb_customers')->where('id', (int)$racedByClient->id)->first();
                    if ($updated) {
                        return (array)$updated;
                    }
                }

                throw $e;
            }

            $created = Capsule::table('eb_customers')->where('id', $customerId)->first();
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
        $name = '';

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
            // Ignore and fall back below.
        }

        if ($name !== '') {
            return $name;
        }

        return 'Client #' . $clientId;
    }
}
