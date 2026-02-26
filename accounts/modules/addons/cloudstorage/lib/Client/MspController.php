<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\UuidBinary;

class MspController
{
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
     */
    public static function getTenants(int $clientId): array
    {
        return Capsule::table('s3_backup_tenants')
            ->where('client_id', $clientId)
            ->where('status', '!=', 'deleted')
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    /**
     * Get a tenant by ID, ensuring it belongs to the specified client.
     */
    public static function getTenant(int $tenantId, int $clientId): ?object
    {
        return Capsule::table('s3_backup_tenants')
            ->where('id', $tenantId)
            ->where('client_id', $clientId)
            ->first();
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


