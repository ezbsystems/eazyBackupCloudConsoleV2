<?php
namespace CometBilling;

use WHMCS\Database\Capsule;

/**
 * Lifecycle evidence for devices and boosters.
 */
class LifecycleResolver
{
    /**
     * @return array{
     *   registered_at: ?string,
     *   revoked_at: ?string,
     *   remove_date: ?string,
     *   remove_date_end: ?string,
     *   confidence: string,
     *   source: ?string
     * }
     */
    public static function resolve(string $deviceHash, string $category, ?string $account = null): array
    {
        $index = ServiceIdentityResolver::loadIndex();
        $device = $index['by_hash'][$deviceHash] ?? null;

        $registeredAt = $device['registered_at'] ?? null;
        $revokedAt = null;
        if ($device !== null && !empty($device['revoked_at'])) {
            $revokedAt = BillingPeriodCalculator::dateOnly((string) $device['revoked_at']);
        }

        $removeDate = $revokedAt;
        $removeEnd = $revokedAt;
        $confidence = 'unknown';
        $source = null;

        if ($revokedAt !== null) {
            $confidence = 'high';
            $source = 'comet_devices.revoked_at';
        }

        $inventoryRemove = self::findInventoryRemoveDate($deviceHash, $category);
        if ($inventoryRemove !== null) {
            if ($removeDate === null) {
                $removeDate = $inventoryRemove;
                $removeEnd = $inventoryRemove;
                $confidence = 'medium';
                $source = 'cb_server_device_inventory';
            } elseif ($inventoryRemove <= $removeDate) {
                $removeDate = $inventoryRemove;
                $source = 'inventory_and_revoked';
            }
        }

        if ($removeDate === null) {
            return [
                'registered_at' => $registeredAt,
                'revoked_at' => null,
                'remove_date' => null,
                'remove_date_end' => null,
                'confidence' => 'unknown',
                'source' => null,
            ];
        }

        return [
            'registered_at' => $registeredAt,
            'revoked_at' => $revokedAt,
            'remove_date' => $removeDate,
            'remove_date_end' => $removeEnd,
            'confidence' => $confidence,
            'source' => $source,
        ];
    }

    private static function findInventoryRemoveDate(string $deviceHash, string $category): ?string
    {
        if (!Capsule::schema()->hasTable('cb_server_device_inventory')) {
            return null;
        }

        $column = match ($category) {
            'hyperv_vms' => 'hyperv_vms',
            'vmware_vms' => 'vmware_vms',
            'proxmox_vms' => 'proxmox_vms',
            'disk_image' => 'disk_image',
            'mssql' => 'mssql',
            'm365_accounts' => 'm365_accounts',
            default => null,
        };

        if ($column === null) {
            return null;
        }

        $rows = Capsule::table('cb_server_device_inventory')
            ->where('device_id', $deviceHash)
            ->orderBy('snapshot_date')
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $lastPositive = null;
        foreach ($rows as $row) {
            $qty = (int) ($row->{$column} ?? 0);
            if ($qty > 0) {
                $lastPositive = (string) $row->snapshot_date;
            }
        }

        return $lastPositive;
    }
}
