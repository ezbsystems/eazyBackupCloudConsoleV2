<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;

/**
 * Persists Graph delta links for Kopia worker incremental sync.
 */
final class DeltaStateRepository
{
    /**
     * Coerce legacy/corrupt delta_states into the shape Go expects:
     * map[workload]map[state_key]delta_link (JSON object of objects).
     *
     * Historical rows may store [] or {"mail":[]} which break ClaimBatch decode
     * (cannot unmarshal array into map[string]string) and orphan the batch lease.
     *
     * @param mixed $decoded
     * @return array<string, array<string, string>>
     */
    public static function normalizeStatesForWorker($decoded): array
    {
        if (!is_array($decoded) || $decoded === []) {
            return [];
        }
        // PHP json_decode('[]') is a list; reject list-shaped outer payloads.
        if (array_is_list($decoded)) {
            return [];
        }

        $out = [];
        foreach ($decoded as $workload => $states) {
            if (!is_string($workload) || $workload === '' || !is_array($states)) {
                continue;
            }
            if ($states === [] || array_is_list($states)) {
                continue;
            }
            $inner = [];
            foreach ($states as $stateKey => $deltaLink) {
                if (!is_string($stateKey) || $stateKey === '') {
                    continue;
                }
                if (is_int($deltaLink) || is_float($deltaLink)) {
                    $deltaLink = (string) $deltaLink;
                }
                if (!is_string($deltaLink)) {
                    continue;
                }
                $deltaLink = trim($deltaLink);
                if ($deltaLink === '') {
                    continue;
                }
                $inner[$stateKey] = $deltaLink;
            }
            if ($inner !== []) {
                $out[$workload] = $inner;
            }
        }

        return $out;
    }

    /**
     * Claim/run payload field: empty maps must encode as {} not [] for the Go decoder.
     *
     * @param mixed $decoded
     * @return \stdClass|array<string, array<string, string>>
     */
    public static function encodeForWorkerPayload($decoded)
    {
        $normalized = self::normalizeStatesForWorker($decoded);

        return $normalized === [] ? new \stdClass() : $normalized;
    }

    /** @return array<string, array<string, string>> workload => state_key => delta_link */
    public static function getStatesForSource(int $tenantRecordId, string $physicalKey, ?string $e3JobId = null): array
    {
        if ($tenantRecordId <= 0 || $physicalKey === '') {
            return [];
        }
        $scopedJobId = self::scopedJobId($e3JobId, $tenantRecordId);
        $all = self::getStatesForSources($tenantRecordId, [$physicalKey], $scopedJobId);

        return $all[$physicalKey] ?? [];
    }

    /**
     * @param list<string> $physicalKeys
     *
     * @return array<string, array<string, array<string, string>>> physical_key => workload => state_key => delta_link
     */
    public static function getStatesForSources(int $tenantRecordId, array $physicalKeys, ?string $scopedJobId): array
    {
        if ($tenantRecordId <= 0 || $physicalKeys === [] || !self::tableReady()) {
            return [];
        }

        $physicalKeys = array_values(array_unique(array_filter(
            $physicalKeys,
            static fn ($key) => is_string($key) && $key !== '',
        )));
        if ($physicalKeys === []) {
            return [];
        }

        $q = Capsule::table('ms365_delta_state')
            ->where('tenant_record_id', $tenantRecordId)
            ->whereIn('physical_key', $physicalKeys);

        if (self::jobColumnReady()) {
            if ($scopedJobId === null) {
                $q->whereNull('e3_job_id');
            } else {
                $q->where('e3_job_id', $scopedJobId);
            }
        }

        $rows = $q->get(['physical_key', 'workload', 'state_key', 'delta_link']);
        $out = [];
        foreach ($rows as $row) {
            $physicalKey = (string) ($row->physical_key ?? '');
            $workload = (string) ($row->workload ?? '');
            $stateKey = (string) ($row->state_key ?? '');
            $link = (string) ($row->delta_link ?? '');
            if ($physicalKey === '' || $workload === '' || $stateKey === '' || $link === '') {
                continue;
            }
            if (!isset($out[$physicalKey])) {
                $out[$physicalKey] = [];
            }
            if (!isset($out[$physicalKey][$workload])) {
                $out[$physicalKey][$workload] = [];
            }
            $out[$physicalKey][$workload][$stateKey] = $link;
        }

        return $out;
    }

    /**
     * @return array{e3_job_id: string, legacy_shared_bucket: bool, scoped_job_id: ?string}
     */
    public static function computeJobScope(?string $e3JobId, int $tenantRecordId): array
    {
        $e3JobId = trim((string) $e3JobId);
        if ($e3JobId === '') {
            return [
                'e3_job_id' => '',
                'legacy_shared_bucket' => true,
                'scoped_job_id' => null,
            ];
        }

        $job = Ms365JobDestinationService::loadJobRow($e3JobId);
        $tenant = TenantRecordRepository::getById($tenantRecordId);
        $legacy = $job !== null && $tenant !== null && Ms365JobDestinationService::isLegacySharedBucket($job, $tenant);

        return [
            'e3_job_id' => $e3JobId,
            'legacy_shared_bucket' => $legacy,
            'scoped_job_id' => $legacy ? null : $e3JobId,
        ];
    }

    /** @param array<string, mixed> $deltaStates */
    public static function advanceOnShardSuccess(int $tenantRecordId, string $physicalKey, array $deltaStates, ?string $e3JobId = null): void
    {
        self::saveStates($tenantRecordId, $physicalKey, $deltaStates, $e3JobId);
    }

    /** @param array<string, mixed> $deltaStates */
    public static function saveStates(int $tenantRecordId, string $physicalKey, array $deltaStates, ?string $e3JobId = null): void
    {
        $deltaStates = self::normalizeStatesForWorker($deltaStates);
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
        return self::computeJobScope($e3JobId, $tenantRecordId)['scoped_job_id'];
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
