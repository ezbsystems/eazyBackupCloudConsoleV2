<?php
namespace CometBilling;

use WHMCS\Database\Capsule;

/**
 * Extract and aggregate usage data from the Portal API snapshots (cb_active_services).
 * Parses the `extra` JSON and `service_name` to categorize items by type.
 */
class PortalUsageExtractor
{
    /**
     * Get aggregated counts from the latest Portal snapshot.
     * 
     * @return array Totals by category with counts and amounts
     */
    public static function getLatestSnapshot(): array
    {
        // Get the most recent pulled_at timestamp
        $latestPull = Capsule::table('cb_active_services')->max('pulled_at');
        
        if (!$latestPull) {
            return self::emptyTotals();
        }

        $rows = Capsule::table('cb_active_services')
            ->where('pulled_at', $latestPull)
            ->get();

        return self::aggregateRows($rows, $latestPull);
    }

    /**
     * Get aggregated counts from a specific snapshot timestamp.
     * 
     * @param string $pulledAt The pulled_at timestamp to use
     * @return array Totals by category
     */
    public static function getSnapshot(string $pulledAt): array
    {
        $rows = Capsule::table('cb_active_services')
            ->where('pulled_at', $pulledAt)
            ->get();

        if ($rows->isEmpty()) {
            return self::emptyTotals();
        }

        return self::aggregateRows($rows, $pulledAt);
    }

    /**
     * List available snapshot timestamps.
     * 
     * @param int $limit Max number of snapshots to return
     * @return array List of pulled_at timestamps with row counts
     */
    public static function listSnapshots(int $limit = 50): array
    {
        return Capsule::table('cb_active_services')
            ->select('pulled_at', Capsule::raw('COUNT(*) as row_count'))
            ->groupBy('pulled_at')
            ->orderBy('pulled_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Aggregate rows into totals by category.
     * 
     * @param \Illuminate\Support\Collection $rows
     * @param string $pulledAt
     * @return array
     */
    private static function aggregateRows($rows, string $pulledAt): array
    {
        $totals = self::emptyTotals();
        $totals['snapshot_time'] = $pulledAt;
        $totals['raw_rows'] = count($rows);

        foreach ($rows as $row) {
            $extra = json_decode($row->extra ?? '{}', true);
            $type = strtolower($extra['Type'] ?? '');
            $serviceName = $row->service_name ?? '';
            $qty = (float)($row->quantity ?? 1);
            $amount = (float)($row->amount ?? 0);

            switch ($type) {
                case 'device':
                    $totals['devices']['count']++;
                    $totals['devices']['amount'] += $amount;
                    $totals['devices']['items'][] = self::extractDeviceInfo($serviceName, $row);
                    break;

                case 'booster':
                    // Parse booster type from service_name
                    $boosterType = self::parseBoosterType($serviceName);
                    if ($boosterType && isset($totals[$boosterType])) {
                        // For VMs, qty = number of VMs. For others, qty = 1 typically.
                        $totals[$boosterType]['count'] += $qty;
                        $totals[$boosterType]['amount'] += $amount;
                        $totals[$boosterType]['items'][] = self::extractBoosterInfo($serviceName, $row, $qty);
                    } else {
                        // Unknown booster type
                        $totals['other_boosters']['count'] += $qty;
                        $totals['other_boosters']['amount'] += $amount;
                        $totals['other_boosters']['items'][] = self::extractBoosterInfo($serviceName, $row, $qty);
                    }
                    break;

                case 'account':
                    $totals['account_fees']['count']++;
                    $totals['account_fees']['amount'] += $amount;
                    break;

                case 'serial':
                    $totals['server_licenses']['count']++;
                    $totals['server_licenses']['amount'] += $amount;
                    break;

                default:
                    // Track unrecognized types
                    if (!isset($totals['unknown'])) {
                        $totals['unknown'] = ['count' => 0, 'amount' => 0.0, 'types' => []];
                    }
                    $totals['unknown']['count']++;
                    $totals['unknown']['amount'] += $amount;
                    if (!in_array($type, $totals['unknown']['types'])) {
                        $totals['unknown']['types'][] = $type;
                    }
                    break;
            }
        }

        // Calculate total billable amount
        $totals['total_amount'] = 
            $totals['devices']['amount'] +
            $totals['hyperv_vms']['amount'] +
            $totals['vmware_vms']['amount'] +
            $totals['proxmox_vms']['amount'] +
            $totals['disk_image']['amount'] +
            $totals['mssql']['amount'] +
            $totals['m365_accounts']['amount'] +
            $totals['other_boosters']['amount'] +
            $totals['account_fees']['amount'] +
            $totals['server_licenses']['amount'];

        return $totals;
    }

    /**
     * Parse booster type from service_name.
     * e.g., "Account X - Device abc123 - Booster (Microsoft Hyper-V) Guest Count 2"
     * 
     * @param string $serviceName
     * @return string|null The booster type key or null if unknown
     */
    private static function parseBoosterType(string $serviceName): ?string
    {
        $sn = strtolower($serviceName);
        
        if (str_contains($sn, 'hyper-v') || str_contains($sn, 'hyperv')) {
            return 'hyperv_vms';
        }
        if (str_contains($sn, 'vmware')) {
            return 'vmware_vms';
        }
        if (str_contains($sn, 'proxmox')) {
            return 'proxmox_vms';
        }
        if (str_contains($sn, 'disk image')) {
            return 'disk_image';
        }
        if (str_contains($sn, 'sql server') || str_contains($sn, 'mssql')) {
            return 'mssql';
        }
        if (str_contains($sn, 'office 365') || str_contains($sn, 'm365') || str_contains($sn, 'microsoft 365')) {
            return 'm365_accounts';
        }
        
        return null; // Unknown booster type
    }

    /**
     * Extract device info from service_name.
     * Pattern: "Account <AccountName> - Device <DeviceId>..."
     * 
     * @param string $serviceName
     * @param object $row
     * @return array
     */
    private static function extractDeviceInfo(string $serviceName, $row): array
    {
        $info = [
            'raw_service_name' => $serviceName,
            'amount' => (float)($row->amount ?? 0),
            'account' => null,
            'device_id' => null,
        ];

        // Try to extract account name
        if (preg_match('/Account\s+([^\-]+)/i', $serviceName, $m)) {
            $info['account'] = trim($m[1]);
        }
        
        // Try to extract device ID
        if (preg_match('/Device\s+([a-f0-9]+)/i', $serviceName, $m)) {
            $info['device_id'] = trim($m[1]);
        }

        return $info;
    }

    /**
     * Extract booster info from service_name.
     * 
     * @param string $serviceName
     * @param object $row
     * @param float $qty
     * @return array
     */
    private static function extractBoosterInfo(string $serviceName, $row, float $qty): array
    {
        $info = [
            'raw_service_name' => $serviceName,
            'quantity' => $qty,
            'amount' => (float)($row->amount ?? 0),
            'account' => null,
            'device_id' => null,
            'booster_type' => null,
        ];

        // Try to extract account name
        if (preg_match('/Account\s+([^\-]+)/i', $serviceName, $m)) {
            $info['account'] = trim($m[1]);
        }
        
        // Try to extract device ID
        if (preg_match('/Device\s+([a-f0-9]+)/i', $serviceName, $m)) {
            $info['device_id'] = trim($m[1]);
        }

        // Try to extract booster type from parentheses
        if (preg_match('/Booster\s*\(([^)]+)\)/i', $serviceName, $m)) {
            $info['booster_type'] = trim($m[1]);
        }

        return $info;
    }

    /**
     * Get empty totals structure.
     * 
     * @return array
     */
    private static function emptyTotals(): array
    {
        return [
            'snapshot_time' => null,
            'raw_rows' => 0,
            'total_amount' => 0.0,
            'devices' => ['count' => 0, 'amount' => 0.0, 'items' => []],
            'hyperv_vms' => ['count' => 0, 'amount' => 0.0, 'items' => []],
            'vmware_vms' => ['count' => 0, 'amount' => 0.0, 'items' => []],
            'proxmox_vms' => ['count' => 0, 'amount' => 0.0, 'items' => []],
            'disk_image' => ['count' => 0, 'amount' => 0.0, 'items' => []],
            'mssql' => ['count' => 0, 'amount' => 0.0, 'items' => []],
            'm365_accounts' => ['count' => 0, 'amount' => 0.0, 'items' => []],
            'other_boosters' => ['count' => 0, 'amount' => 0.0, 'items' => []],
            'account_fees' => ['count' => 0, 'amount' => 0.0],
            'server_licenses' => ['count' => 0, 'amount' => 0.0],
        ];
    }

    /**
     * Get a summary (without detailed items) for display.
     * 
     * @return array
     */
    public static function getSummary(): array
    {
        $full = self::getLatestSnapshot();
        
        // Remove detailed items for summary view
        $summary = $full;
        foreach (['devices', 'hyperv_vms', 'vmware_vms', 'proxmox_vms', 'disk_image', 'mssql', 'm365_accounts', 'other_boosters'] as $key) {
            if (isset($summary[$key]['items'])) {
                unset($summary[$key]['items']);
            }
        }
        
        return $summary;
    }
}
