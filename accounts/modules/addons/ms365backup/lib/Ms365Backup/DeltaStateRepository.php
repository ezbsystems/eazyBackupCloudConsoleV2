<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;

/**
 * Persists Graph delta links for Kopia worker incremental sync.
 */
final class DeltaStateRepository
{
    /** @return array<string, array<string, string>> workload => state_key => delta_link */
    public static function getStatesForSource(int $tenantRecordId, string $physicalKey, ?string $e3JobId = null): array
    {
        if ($tenantRecordId <= 0 || $physicalKey === '' || !self::tableReady()) {
            return [];
        }

        $q = Capsule::table('ms365_delta_state')
            ->where('tenant_record_id', $tenantRecordId)
            ->where('physical_key', $physicalKey);

        self::applyJobScope($q, $e3JobId, $tenantRecordId);

        $rows = $q->get(['workload', 'state_key', 'delta_link']);
        $out = [];
        foreach ($rows as $row) {
            $workload = (string) ($row->workload ?? '');
            $stateKey = (string) ($row->state_key ?? '');
            $link = (string) ($row->delta_link ?? '');
            if ($workload === '' || $stateKey === '' || $link === '') {
                continue;
            }
            if (!isset($out[$workload])) {
                $out[$workload] = [];
            }
            $out[$workload][$stateKey] = $link;
        }

        return $out;
    }

    /** @param array<string, mixed> $deltaStates */
    public static function advanceOnShardSuccess(int $tenantRecordId, string $physicalKey, array $deltaStates, ?string $e3JobId = null): void
    {
        self::saveStates($tenantRecordId, $physicalKey, $deltaStates, $e3JobId);
    }

    /** @param array<string, mixed> $deltaStates */
    public static function saveStates(int $tenantRecordId, string $physicalKey, array $deltaStates, ?string $e3JobId = null): void
    {
        if ($tenantRecordId <= 0 || $physicalKey === '' || $deltaStates === [] || !self::tableReady()) {
            return;
        }

        $jobColumnReady = self::jobColumnReady();
        $scopedJobId = self::scopedJobId($e3JobId, $tenantRecordId);
        $now = time();
        foreach ($deltaStates as $workload => $states) {
            if (!is_string($workload) || !is_array($states)) {
                continue;
            }
            foreach ($states as $stateKey => $deltaLink) {
                if (!is_string($stateKey) || !is_string($deltaLink) || trim($deltaLink) === '') {
                    continue;
                }
                $keys = [
                    'tenant_record_id' => $tenantRecordId,
                    'physical_key' => $physicalKey,
                    'workload' => $workload,
                    'state_key' => $stateKey,
                ];
                if ($jobColumnReady) {
                    $keys['e3_job_id'] = $scopedJobId;
                }
                Capsule::table('ms365_delta_state')->updateOrInsert(
                    $keys,
                    [
                        'delta_link' => $deltaLink,
                        'updated_at' => $now,
                    ]
                );
            }
        }
    }

    public static function tableReady(): bool
    {
        return class_exists(Capsule::class) && Capsule::schema()->hasTable('ms365_delta_state');
    }

    public static function jobColumnReady(): bool
    {
        return self::tableReady() && Capsule::schema()->hasColumn('ms365_delta_state', 'e3_job_id');
    }

    private static function scopedJobId(?string $e3JobId, int $tenantRecordId): ?string
    {
        $e3JobId = trim((string) $e3JobId);
        if ($e3JobId === '') {
            return null;
        }
        $job = Ms365JobDestinationService::loadJobRow($e3JobId);
        $tenant = TenantRecordRepository::getById($tenantRecordId);
        if ($job !== null && $tenant !== null && Ms365JobDestinationService::isLegacySharedBucket($job, $tenant)) {
            return null;
        }

        return $e3JobId;
    }

  /**
     * @param \Illuminate\Database\Query\Builder $q
     */
    private static function applyJobScope($q, ?string $e3JobId, int $tenantRecordId): void
    {
        if (!self::jobColumnReady()) {
            return;
        }

        $scopedJobId = self::scopedJobId($e3JobId, $tenantRecordId);
        if ($scopedJobId === null) {
            $q->whereNull('e3_job_id');
        } else {
            $q->where('e3_job_id', $scopedJobId);
        }
    }
}
