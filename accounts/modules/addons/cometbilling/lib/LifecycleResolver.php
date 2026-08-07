<?php
namespace CometBilling;

use WHMCS\Database\Capsule;

/**
 * Lifecycle evidence for devices and boosters.
 */
class LifecycleResolver
{
    /** @var array<string, array<string, mixed>> */
    private static array $cache = [];

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
        $cacheKey = $deviceHash . '|' . $category;
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        $index = ServiceIdentityResolver::loadIndex();
        $device = $index['by_hash'][$deviceHash] ?? null;

        $registeredAt = $device['registered_at'] ?? null;
        $revokedAt = null;
        if ($device !== null && !empty($device['revoked_at'])) {
            $revokedAt = BillingPeriodCalculator::utcDateOnly((string) $device['revoked_at']);
        }

        $removeDate = $revokedAt;
        $removeEnd = $revokedAt;
        $confidence = 'unknown';
        $source = null;

        if ($revokedAt !== null) {
            $confidence = 'high';
            $source = 'comet_devices.revoked_at';
        }

        // Inventory lookback only needed when revoke is unknown or for daily boosters
        // confirming disappearance. Skip when host revoke already provides a stop date
        // for non-booster categories to avoid per-row inventory scans.
        $needsInventory = in_array($category, [
            'hyperv_vms', 'vmware_vms', 'proxmox_vms', 'disk_image', 'mssql', 'm365_accounts',
        ], true) && ($revokedAt === null);

        if ($needsInventory) {
            $inventoryRemove = self::findInventoryRemoveDate($deviceHash, $category);
            if ($inventoryRemove !== null) {
                $removeDate = $inventoryRemove;
                $removeEnd = $inventoryRemove;
                $confidence = 'medium';
                $source = 'cb_server_device_inventory';
            }
        }

        if ($removeDate === null) {
            $result = [
                'registered_at' => $registeredAt,
                'revoked_at' => null,
                'remove_date' => null,
                'remove_date_end' => null,
                'confidence' => 'unknown',
                'source' => null,
            ];
            self::$cache[$cacheKey] = $result;
            return $result;
        }

        $result = [
            'registered_at' => $registeredAt,
            'revoked_at' => $revokedAt,
            'remove_date' => $removeDate,
            'remove_date_end' => $removeEnd,
            'confidence' => $confidence,
            'source' => $source,
        ];
        // #region agent log
        if (str_starts_with($deviceHash, '32789ae') || $category === 'hyperv_vms') {
            self::debugLog('LifecycleResolver.php:resolve', 'lifecycle_resolved', 'A', [
                'deviceHash' => $deviceHash,
                'category' => $category,
                'is_active' => $device['is_active'] ?? null,
                'revoked_at' => $revokedAt,
                'remove_date' => $removeDate,
                'source' => $source,
                'confidence' => $confidence,
                'registered_at' => $registeredAt,
            ]);
        }
        // #endregion
        self::$cache[$cacheKey] = $result;
        return $result;
    }

    public static function clearCache(): void
    {
        self::$cache = [];
    }

    /**
     * Last inventory date the booster was present, only when a later snapshot
     * shows quantity dropped to zero. Still-positive latest qty means active → null.
     */
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

        if ((is_countable($rows) ? count($rows) : 0) === 0) {
            return null;
        }

        $lastPositive = null;
        $disappeared = false;
        $latestQty = 0;
        $latestDate = null;
        $rowCount = 0;
        foreach ($rows as $row) {
            $rowCount++;
            $qty = (int) ($row->{$column} ?? 0);
            $latestQty = $qty;
            $latestDate = (string) $row->snapshot_date;
            if ($qty > 0) {
                $lastPositive = (string) $row->snapshot_date;
                $disappeared = false;
            } elseif ($lastPositive !== null) {
                $disappeared = true;
            }
        }

        // Booster still present on the newest inventory snapshot → not removed.
        $result = null;
        if ($latestQty > 0) {
            $result = null;
        } else {
            $result = $disappeared ? $lastPositive : null;
        }
        // #region agent log
        if (str_starts_with($deviceHash, '32789ae') || $result !== null) {
            self::debugLog('LifecycleResolver.php:findInventoryRemoveDate', 'inventory_scan', 'A', [
                'deviceHash' => $deviceHash,
                'category' => $category,
                'column' => $column,
                'rowCount' => $rowCount,
                'lastPositive' => $lastPositive,
                'latestDate' => $latestDate,
                'latestQty' => $latestQty,
                'disappeared' => $disappeared,
                'removeDate' => $result,
            ]);
        }
        // #endregion
        return $result;
    }

    // #region agent log
    private static function debugLog(string $location, string $message, string $hypothesisId, array $data): void
    {
        $payload = [
            'sessionId' => 'd2324a',
            'runId' => 'pre-fix',
            'hypothesisId' => $hypothesisId,
            'location' => $location,
            'message' => $message,
            'data' => $data,
            'timestamp' => (int) round(microtime(true) * 1000),
        ];
        @file_put_contents('/var/www/eazybackup.ca/.cursor/debug-d2324a.log', json_encode($payload) . "\n", FILE_APPEND);
    }
    // #endregion
}
