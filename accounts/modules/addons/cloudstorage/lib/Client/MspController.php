<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

use WHMCS\Database\Capsule;

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
     * @param int $jobId The job ID to validate access for
     * @param int $clientId The client ID requesting access
     * @return array ['valid' => bool, 'message' => string, 'job' => array|null]
     */
    public static function validateJobAccess(int $jobId, int $clientId): array
    {
        // First check basic ownership - job must belong to this client
        $job = Capsule::table('s3_cloudbackup_jobs')
            ->where('id', $jobId)
            ->where('client_id', $clientId)
            ->first();

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

        // MSP clients: validate agent/tenant chain if job has an agent
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

            // If agent belongs to a tenant, verify the tenant is owned by this MSP
            if (!empty($agent->tenant_id)) {
                $tenant = self::getTenant((int) $agent->tenant_id, $clientId);
                if (!$tenant) {
                    return [
                        'valid' => false,
                        'message' => 'Tenant not found or does not belong to this account.',
                        'job' => null,
                    ];
                }
            }
        }

        return [
            'valid' => true,
            'message' => 'Access granted.',
            'job' => $jobArray,
        ];
    }
}


