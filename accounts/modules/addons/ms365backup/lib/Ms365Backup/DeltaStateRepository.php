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
    public static function getStatesForSource(int $tenantRecordId, string $physicalKey): array
    {
        if ($tenantRecordId <= 0 || $physicalKey === '' || !self::tableReady()) {
            return [];
        }
        $rows = Capsule::table('ms365_delta_state')
            ->where('tenant_record_id', $tenantRecordId)
            ->where('physical_key', $physicalKey)
            ->get(['workload', 'state_key', 'delta_link']);
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
    public static function advanceOnShardSuccess(int $tenantRecordId, string $physicalKey, array $deltaStates): void
    {
        self::saveStates($tenantRecordId, $physicalKey, $deltaStates);
    }

    /** @param array<string, mixed> $deltaStates */
    public static function saveStates(int $tenantRecordId, string $physicalKey, array $deltaStates): void
    {
        if ($tenantRecordId <= 0 || $physicalKey === '' || $deltaStates === [] || !self::tableReady()) {
            return;
        }
        $now = time();
        foreach ($deltaStates as $workload => $states) {
            if (!is_string($workload) || !is_array($states)) {
                continue;
            }
            foreach ($states as $stateKey => $deltaLink) {
                if (!is_string($stateKey) || !is_string($deltaLink) || trim($deltaLink) === '') {
                    continue;
                }
                Capsule::table('ms365_delta_state')->updateOrInsert(
                    [
                        'tenant_record_id' => $tenantRecordId,
                        'physical_key' => $physicalKey,
                        'workload' => $workload,
                        'state_key' => $stateKey,
                    ],
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
}
