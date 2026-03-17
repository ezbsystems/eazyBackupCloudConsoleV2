<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\UuidBinary;

class MspController
{
    public static function hasCanonicalTenantModel(): bool
    {
        return Capsule::schema()->hasTable('eb_tenants') && Capsule::schema()->hasTable('eb_msp_accounts');
    }

    public static function getTenantTableName(): string
    {
        return self::hasCanonicalTenantModel() ? 'eb_tenants' : 's3_backup_tenants';
    }

    public static function getTenantUsersTableName(): string
    {
        return Capsule::schema()->hasTable('eb_tenant_users') ? 'eb_tenant_users' : 's3_backup_tenant_users';
    }

    public static function getMspIdForClient(int $clientId): ?int
    {
        if ($clientId <= 0 || !self::hasCanonicalTenantModel()) {
            return null;
        }
        $msp = Capsule::table('eb_msp_accounts')->where('whmcs_client_id', $clientId)->first();
        return $msp ? (int)$msp->id : null;
    }

    public static function hasTenantPublicIds(): bool
    {
        return self::hasCanonicalTenantModel() && Capsule::schema()->hasColumn('eb_tenants', 'public_id');
    }

    public static function scopeTenantOwnership($query, string $tenantAlias, int $clientId): void
    {
        if (self::hasCanonicalTenantModel()) {
            $mspId = self::getMspIdForClient($clientId);
            if ($mspId === null) {
                $query->whereRaw('1 = 0');
                return;
            }
            $query->where($tenantAlias . '.msp_id', $mspId);
            return;
        }
        $query->where($tenantAlias . '.client_id', $clientId);
    }

    /**
     * Check if a client is an MSP based on client group membership.
     */
    public static function isMspClient(int $clientId): bool
    {
        if ($clientId <= 0) {
            return false;
        }

        try {
            $gid = (int)(Capsule::table('tblclients')->where('id', $clientId)->value('groupid') ?? 0);
            if ($gid <= 0) {
                return false;
            }

            $mspGroupsCsv = (string)(Capsule::table('tbladdonmodules')
                ->where('module', 'cloudstorage')
                ->where('setting', 'msp_client_groups')
                ->value('value') ?? '');

            if ($mspGroupsCsv === '') {
                return false;
            }

            $ids = array_filter(array_map('trim', explode(',', $mspGroupsCsv)), function ($v) {
                return $v !== '';
            });
            $ids = array_map('intval', $ids);
            return in_array($gid, $ids, true);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get all tenants for an MSP client.
     * Uses eb_tenants when present (eazybackup canonical); otherwise s3_backup_tenants.
     */
    public static function getTenants(int $clientId): array
    {
        if (self::hasCanonicalTenantModel()) {
            $mspId = self::getMspIdForClient($clientId);
            if ($mspId === null) {
                return [];
            }
            if (self::hasTenantPublicIds()) {
                return Capsule::table('eb_tenants')
                    ->where('msp_id', $mspId)
                    ->where('status', '!=', 'deleted')
                    ->orderBy('name')
                    ->get([
                        Capsule::raw('public_id as id'),
                        'public_id',
                        'name',
                        'slug',
                        'status',
                        'contact_email',
                        'contact_name',
                        'contact_phone',
                        'address_line1',
                        'address_line2',
                        'city',
                        'state',
                        'postal_code',
                        'country',
                        'created_at',
                        'updated_at',
                    ])
                    ->toArray();
            }
            return Capsule::table('eb_tenants')
                ->where('msp_id', $mspId)
                ->where('status', '!=', 'deleted')
                ->orderBy('name')
                ->get()
                ->toArray();
        }
        return Capsule::table('s3_backup_tenants')
            ->where('client_id', $clientId)
            ->where('status', '!=', 'deleted')
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    /**
     * Get a tenant by ID, ensuring it belongs to the specified client.
     * Uses eb_tenants when present; otherwise s3_backup_tenants.
     */
    public static function getTenant(int $tenantId, int $clientId): ?object
    {
        if (self::hasCanonicalTenantModel()) {
            $mspId = self::getMspIdForClient($clientId);
            if ($mspId === null) {
                return null;
            }
            return Capsule::table('eb_tenants')
                ->where('id', $tenantId)
                ->where('msp_id', $mspId)
                ->where('status', '!=', 'deleted')
                ->first();
        }
        return Capsule::table('s3_backup_tenants')
            ->where('id', $tenantId)
            ->where('client_id', $clientId)
            ->where('status', '!=', 'deleted')
            ->first();
    }

    public static function getTenantByPublicId(string $tenantPublicId, int $clientId): ?object
    {
        $tenantPublicId = trim($tenantPublicId);
        if ($tenantPublicId === '') {
            return null;
        }

        if (self::hasTenantPublicIds()) {
            $mspId = self::getMspIdForClient($clientId);
            if ($mspId === null) {
                return null;
            }

            return Capsule::table('eb_tenants')
                ->where('public_id', $tenantPublicId)
                ->where('msp_id', $mspId)
                ->where('status', '!=', 'deleted')
                ->first();
        }

        if ((int) $tenantPublicId <= 0) {
            return null;
        }

        return self::getTenant((int) $tenantPublicId, $clientId);
    }

    public static function resolveTenantPublicIdForClient(string $tenantReference, int $clientId): ?string
    {
        $tenantReference = trim($tenantReference);
        if ($tenantReference === '') {
            return null;
        }

        if (self::hasTenantPublicIds()) {
            $tenant = self::getTenantByPublicId($tenantReference, $clientId);
            if ($tenant && trim((string) ($tenant->public_id ?? '')) !== '') {
                return trim((string) $tenant->public_id);
            }

            if ((int) $tenantReference > 0) {
                $tenant = self::getTenant((int) $tenantReference, $clientId);
                if ($tenant && trim((string) ($tenant->public_id ?? '')) !== '') {
                    return trim((string) $tenant->public_id);
                }
            }

            return null;
        }

        if ((int) $tenantReference <= 0) {
            return null;
        }

        $tenant = self::getTenant((int) $tenantReference, $clientId);
        if (!$tenant) {
            return null;
        }

        return (string) ($tenant->id ?? '');
    }

    /**
     * Validate that a client has access to a job.
     * For non-MSP clients, checks basic ownership via client_id.
     * For MSP clients, additionally validates the agent/tenant chain.
     *
     * @param string $jobId The job ID (UUID) to validate access for
     * @param int $clientId The client ID requesting access
     * @return array ['valid' => bool, 'message' => string, 'job' => array|null]
     */
    public static function validateJobAccess(string $jobId, int $clientId): array
    {
        $hasJobIdPk = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'job_id');

        if ($hasJobIdPk && UuidBinary::isUuid($jobId)) {
            $jobIdNorm = UuidBinary::normalize($jobId);
            $job = Capsule::table('s3_cloudbackup_jobs')
                ->whereRaw('job_id = ' . UuidBinary::toDbExpr($jobIdNorm))
                ->where('client_id', $clientId)
                ->first();
        } else {
            return [
                'valid' => false,
                'message' => 'Job not found or access denied.',
                'job' => null,
            ];
        }

        if (!$job) {
            return [
                'valid' => false,
                'message' => 'Job not found or access denied.',
                'job' => null,
            ];
        }

        // Convert to array for consistent return
        $jobArray = (array) $job;

        // Non-MSP clients: basic ownership is sufficient
        if (!self::isMspClient($clientId)) {
            return [
                'valid' => true,
                'message' => 'Access granted.',
                'job' => $jobArray,
            ];
        }

        $jobTenantId = null;
        if (Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'tenant_id')) {
            $jobTenantId = $job->tenant_id !== null ? (int) $job->tenant_id : null;
        }

        // MSP clients: always validate agent ownership when present.
        $agent = null;
        if (!empty($job->agent_id)) {
            $agent = Capsule::table('s3_cloudbackup_agents')
                ->where('id', $job->agent_id)
                ->where('client_id', $clientId)
                ->first();
            if (!$agent) {
                return [
                    'valid' => false,
                    'message' => 'Agent not found or does not belong to this account.',
                    'job' => null,
                ];
            }
        }

        // Primary ownership source: jobs.tenant_id snapshot.
        if ($jobTenantId !== null) {
            $tenant = self::getTenant($jobTenantId, $clientId);
            if (!$tenant) {
                return [
                    'valid' => false,
                    'message' => 'Tenant not found or does not belong to this account.',
                    'job' => null,
                ];
            }
            // Secondary sanity check only: if agent tenant exists, it must match snapshot.
            if ($agent && !empty($agent->tenant_id) && (int) $agent->tenant_id !== $jobTenantId) {
                return [
                    'valid' => false,
                    'message' => 'Job tenant ownership mismatch detected.',
                    'job' => null,
                ];
            }
        }

        return [
            'valid' => true,
            'message' => 'Access granted.',
            'job' => $jobArray,
        ];
    }
}


