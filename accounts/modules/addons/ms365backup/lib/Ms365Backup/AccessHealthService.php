<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;

/**
 * Aggregates per-resource access probe results for e3 health dashboard.
 */
final class AccessHealthService
{
    /** @return array{status: string, resources: list<array<string, mixed>>, checked_at: int} */
    public static function summaryForClient(int $clientId): array
    {
        $record = TenantRecordRepository::getPrimaryForClient($clientId);
        if ($record === null) {
            return ['status' => 'not_connected', 'resources' => [], 'checked_at' => time()];
        }

        if (!Capsule::schema()->hasTable('ms365_resource_access')) {
            return ['status' => 'unknown', 'resources' => [], 'checked_at' => time()];
        }

        $tenantRecordId = (int) $record['id'];
        $rows = Capsule::table('ms365_resource_access')
            ->where('tenant_record_id', $tenantRecordId)
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get()
            ->map(static fn ($r) => (array) $r)
            ->all();

        $blocked = 0;
        foreach ($rows as $row) {
            if (($row['access_status'] ?? '') === 'blocked') {
                $blocked++;
            }
        }

        $status = $blocked > 0 ? 'action_required' : 'healthy';
        if (($record['connection_status'] ?? '') !== 'connected') {
            $status = (string) $record['connection_status'];
        }

        return [
            'status' => $status,
            'resources' => $rows,
            'checked_at' => time(),
            'blocked_count' => $blocked,
        ];
    }
}
