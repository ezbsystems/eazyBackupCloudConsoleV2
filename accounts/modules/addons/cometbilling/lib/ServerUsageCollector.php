<?php
namespace CometBilling;

use WHMCS\Database\Capsule;

/**
 * Collect usage data from Comet Backup servers via the Admin API.
 * Uses AdminListUsersFull() for efficient bulk data retrieval.
 */
class ServerUsageCollector
{
    /**
     * Default server keys to collect from.
     * Keys are resolved against WHMCS server groups / tblservers (see getCometServer).
     */
    private const DEFAULT_SERVERS = ['cometbackup', 'obc'];

    /**
     * Explicit aliases from collect keys → preferred WHMCS group/server names.
     * "cometbackup" must map to "Comet Backup" (space) — LIKE '%cometbackup%' never matches.
     */
    private const SERVER_KEY_ALIASES = [
        'cometbackup' => ['Comet Backup', 'cometbackup', 'eazybackup'],
        'obc' => ['OBC', 'obc'],
    ];

    /**
     * Collect aggregate usage from all configured Comet servers.
     * 
     * @param array|null $serverKeys Optional list of server keys to collect from
     * @return array Combined usage totals from all servers
     */
    public static function collectAll(?array $serverKeys = null): array
    {
        $servers = $serverKeys ?? self::DEFAULT_SERVERS;
        $combined = self::emptyTotals();
        $combined['servers'] = [];
        $combined['errors'] = [];

        foreach ($servers as $serverKey) {
            try {
                $serverTotals = self::collectFromServer($serverKey);
                $combined = self::mergeTotals($combined, $serverTotals);
                $combined['servers'][$serverKey] = $serverTotals;
            } catch (\Exception $e) {
                $combined['errors'][$serverKey] = $e->getMessage();
            }
        }

        $combined['collected_at'] = date('Y-m-d H:i:s');
        $combined['server_count'] = count($servers);
        $combined['success_count'] = count($combined['servers']);
        $combined['error_count'] = count($combined['errors']);

        return $combined;
    }

    /**
     * Collect usage from a single Comet server.
     * 
     * @param string $serverKey Server profile key (e.g., 'cometbackup', 'obc')
     * @return array Usage totals for this server
     * @throws \RuntimeException If server connection fails
     */
    public static function collectFromServer(string $serverKey): array
    {
        $server = self::getCometServer($serverKey);
        if (!$server) {
            throw new \RuntimeException("Cannot connect to Comet server: {$serverKey}");
        }

        $allProfiles = $server->AdminListUsersFull();
        $totals = self::emptyTotals();
        $totals['server_key'] = $serverKey;
        $totals['users'] = count($allProfiles);
        $totals['collected_at'] = date('Y-m-d H:i:s');
        $totals['device_inventory'] = [];

        foreach ($allProfiles as $username => $profile) {
            $userUsage = self::extractUsageFromProfile($profile);
            foreach (self::extractDeviceInventoryFromProfile($username, $profile) as $deviceRow) {
                $totals['device_inventory'][] = $deviceRow;
            }

            // Accumulate totals
            $totals['devices'] += $userUsage['devices'];
            $totals['hyperv_vms'] += $userUsage['hyperv_vms'];
            $totals['vmware_vms'] += $userUsage['vmware_vms'];
            $totals['proxmox_vms'] += $userUsage['proxmox_vms'];
            $totals['disk_image'] += $userUsage['disk_image'];
            $totals['mssql'] += $userUsage['mssql'];
            $totals['m365_accounts'] += $userUsage['m365_accounts'];
            $totals['storage_bytes'] += $userUsage['storage_bytes'];
            $totals['protected_items'] += $userUsage['protected_items'];
        }

        $totals['device_inventory'] = self::deduplicateDeviceInventory($totals['device_inventory']);

        return $totals;
    }

    public static function openServer(string $serverKey): ?\Comet\Server
    {
        return self::getCometServer($serverKey);
    }

    /**
     * Extract billable usage counts from a user profile.
     * 
     * @param object $profile Comet UserProfileConfig object
     * @return array Usage counts
     */
    public static function extractUsageFromProfile($profile): array
    {
        $usage = [
            'devices' => 0,
            'hyperv_vms' => 0,
            'vmware_vms' => 0,
            'proxmox_vms' => 0,
            'disk_image' => 0,
            'mssql' => 0,
            'm365_accounts' => 0,
            'storage_bytes' => 0,
            'protected_items' => 0,
        ];

        // Device count
        if (isset($profile->Devices)) {
            $usage['devices'] = count((array)$profile->Devices);
        }

        // Source-based counts (VMs, M365, engines)
        if (isset($profile->Sources)) {
            $usage['protected_items'] = count((array)$profile->Sources);
            
            foreach ($profile->Sources as $source) {
                $engine = strtolower($source->Engine ?? '');

                // Get VM count - use the higher of LastBackupJob or LastSuccessfulBackupJob
                $lastBackupVmCount = 0;
                $lastSuccessfulVmCount = 0;
                
                if (isset($source->Statistics->LastBackupJob->TotalVmCount)) {
                    $lastBackupVmCount = (int)$source->Statistics->LastBackupJob->TotalVmCount;
                }
                if (isset($source->Statistics->LastSuccessfulBackupJob->TotalVmCount)) {
                    $lastSuccessfulVmCount = (int)$source->Statistics->LastSuccessfulBackupJob->TotalVmCount;
                }
                $vmCount = max($lastBackupVmCount, $lastSuccessfulVmCount);

                // Categorize by engine type
                switch ($engine) {
                    case 'engine1/hyperv':
                        $usage['hyperv_vms'] += $vmCount;
                        break;
                    case 'engine1/vmware':
                        $usage['vmware_vms'] += $vmCount;
                        break;
                    case 'engine1/proxmox':
                        $usage['proxmox_vms'] += $vmCount;
                        break;
                    case 'engine1/windisk':
                        $usage['disk_image']++;
                        break;
                    case 'engine1/mssql':
                        $usage['mssql']++;
                        break;
                    case 'engine1/winmsofficemail':
                        // M365 accounts count
                        $lastBackupAccounts = (int)($source->Statistics->LastBackupJob->TotalAccountsCount ?? 0);
                        $lastSuccessfulAccounts = (int)($source->Statistics->LastSuccessfulBackupJob->TotalAccountsCount ?? 0);
                        $usage['m365_accounts'] += max($lastBackupAccounts, $lastSuccessfulAccounts);
                        break;
                }
            }
        }

        // Storage usage (vault sizes for S3-compatible and Comet Storage types)
        if (isset($profile->Destinations)) {
            foreach ($profile->Destinations as $dest) {
                $destType = (int)($dest->DestinationType ?? $dest->Type ?? 0);
                // Only count S3-compatible (1000) and Comet Storage (1003)
                if ($destType === 1000 || $destType === 1003) {
                    $size = (int)($dest->Statistics->ClientProvidedSize->Size ?? 0);
                    $usage['storage_bytes'] += $size;
                }
            }
        }

        return $usage;
    }

    /**
     * Extract per-device inventory for reconciliation drill-down.
     *
     * @return list<array<string, mixed>>
     */
    public static function extractDeviceInventoryFromProfile(string $username, $profile): array
    {
        $devices = [];

        if (isset($profile->Devices)) {
            foreach ((array) $profile->Devices as $deviceId => $deviceConfig) {
                $friendlyName = null;
                if (is_object($deviceConfig)) {
                    $friendlyName = $deviceConfig->FriendlyName
                        ?? ($deviceConfig->DeviceConfig->FriendlyName ?? null);
                }

                $devices[(string) $deviceId] = [
                    'username' => $username,
                    'device_id' => (string) $deviceId,
                    'friendly_name' => $friendlyName,
                    'hyperv_vms' => 0,
                    'vmware_vms' => 0,
                    'proxmox_vms' => 0,
                    'disk_image' => 0,
                    'mssql' => 0,
                    'm365_accounts' => 0,
                ];
            }
        }

        if (isset($profile->Sources)) {
            foreach ($profile->Sources as $source) {
                $ownerDevice = (string) ($source->OwnerDevice ?? '');
                if ($ownerDevice === '' || !isset($devices[$ownerDevice])) {
                    continue;
                }

                $engine = strtolower($source->Engine ?? '');
                $lastBackupVmCount = (int) ($source->Statistics->LastBackupJob->TotalVmCount ?? 0);
                $lastSuccessfulVmCount = (int) ($source->Statistics->LastSuccessfulBackupJob->TotalVmCount ?? 0);
                $vmCount = max($lastBackupVmCount, $lastSuccessfulVmCount);

                switch ($engine) {
                    case 'engine1/hyperv':
                        $devices[$ownerDevice]['hyperv_vms'] += $vmCount;
                        break;
                    case 'engine1/vmware':
                        $devices[$ownerDevice]['vmware_vms'] += $vmCount;
                        break;
                    case 'engine1/proxmox':
                        $devices[$ownerDevice]['proxmox_vms'] += $vmCount;
                        break;
                    case 'engine1/windisk':
                        $devices[$ownerDevice]['disk_image']++;
                        break;
                    case 'engine1/mssql':
                        $devices[$ownerDevice]['mssql']++;
                        break;
                    case 'engine1/winmsofficemail':
                        $lastBackupAccounts = (int) ($source->Statistics->LastBackupJob->TotalAccountsCount ?? 0);
                        $lastSuccessfulAccounts = (int) ($source->Statistics->LastSuccessfulBackupJob->TotalAccountsCount ?? 0);
                        $devices[$ownerDevice]['m365_accounts'] += max($lastBackupAccounts, $lastSuccessfulAccounts);
                        break;
                }
            }
        }

        return array_values($devices);
    }

    /**
     * Deduplicate device inventory rows by device_id within a server collection.
     * Merges booster counts and keeps the best friendly_name/username.
     *
     * @param list<array<string, mixed>> $devices
     * @return list<array<string, mixed>>
     */
    public static function deduplicateDeviceInventory(array $devices): array
    {
        $byDeviceId = [];

        foreach ($devices as $device) {
            $deviceId = (string) ($device['device_id'] ?? '');
            if ($deviceId === '') {
                continue;
            }

            if (isset($byDeviceId[$deviceId])) {
                $byDeviceId[$deviceId] = self::mergeDeviceInventoryEntry($byDeviceId[$deviceId], $device);
            } else {
                $byDeviceId[$deviceId] = $device;
            }
        }

        return array_values($byDeviceId);
    }

    /**
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $incoming
     * @return array<string, mixed>
     */
    private static function mergeDeviceInventoryEntry(array $existing, array $incoming): array
    {
        foreach (['hyperv_vms', 'vmware_vms', 'proxmox_vms', 'disk_image', 'mssql', 'm365_accounts'] as $key) {
            $existing[$key] = (int) ($existing[$key] ?? 0) + (int) ($incoming[$key] ?? 0);
        }

        $existing['friendly_name'] = self::preferFriendlyName(
            $existing['friendly_name'] ?? null,
            $incoming['friendly_name'] ?? null
        );
        $existing['username'] = self::preferUsername(
            (string) ($existing['username'] ?? ''),
            (string) ($incoming['username'] ?? '')
        );

        return $existing;
    }

    private static function preferFriendlyName(?string $a, ?string $b): ?string
    {
        $a = trim((string) ($a ?? ''));
        $b = trim((string) ($b ?? ''));

        if ($a === '') {
            return $b !== '' ? $b : null;
        }
        if ($b === '') {
            return $a;
        }

        return strlen($b) > strlen($a) ? $b : $a;
    }

    private static function preferUsername(string $a, string $b): string
    {
        $a = trim($a);
        $b = trim($b);

        if ($a === '') {
            return $b;
        }
        if ($b === '') {
            return $a;
        }

        return $a;
    }

    /**
     * Persist per-device inventory rows for a snapshot date.
     */
    public static function persistDeviceInventory(string $snapshotDate, array $allData): void
    {
        DeviceMatcher::ensureTable();

        $now = date('Y-m-d H:i:s');

        foreach ($allData['servers'] ?? [] as $serverKey => $srvData) {
            Capsule::table('cb_server_device_inventory')
                ->where('snapshot_date', $snapshotDate)
                ->where('server_key', $serverKey)
                ->delete();

            $dedupedDevices = self::deduplicateDeviceInventory($srvData['device_inventory'] ?? []);
            $batch = [];

            foreach ($dedupedDevices as $device) {
                $batch[] = [
                    'snapshot_date' => $snapshotDate,
                    'server_key' => $serverKey,
                    'username' => (string) ($device['username'] ?? ''),
                    'device_id' => (string) ($device['device_id'] ?? ''),
                    'friendly_name' => $device['friendly_name'] ?? null,
                    'hyperv_vms' => (int) ($device['hyperv_vms'] ?? 0),
                    'vmware_vms' => (int) ($device['vmware_vms'] ?? 0),
                    'proxmox_vms' => (int) ($device['proxmox_vms'] ?? 0),
                    'disk_image' => (int) ($device['disk_image'] ?? 0),
                    'mssql' => (int) ($device['mssql'] ?? 0),
                    'm365_accounts' => (int) ($device['m365_accounts'] ?? 0),
                    'created_at' => $now,
                ];
            }

            foreach (array_chunk($batch, 500) as $chunk) {
                Capsule::table('cb_server_device_inventory')->insertOrIgnore($chunk);
            }
        }
    }

    /**
     * Get a Comet\Server instance for a server key.
     * Looks up server credentials from WHMCS tblservers via server groups.
     *
     * @param string $serverKey Server profile key (e.g., 'cometbackup', 'obc')
     * @return \Comet\Server|null
     */
    private static function getCometServer(string $serverKey): ?\Comet\Server
    {
        $server = self::resolveWhmcsServer($serverKey);
        if (!$server) {
            return null;
        }

        // Decrypt password using WHMCS API
        $password = '';
        try {
            $result = localAPI('DecryptPassword', ['password2' => $server->password]);
            $password = $result['password'] ?? '';
        } catch (\Exception $e) {
            return null;
        }

        if ($password === '') {
            return null;
        }

        $protocol = $server->secure ? 'https' : 'http';
        $port = !empty($server->port) ? ':' . $server->port : '';
        $url = "{$protocol}://{$server->hostname}{$port}/";

        return new \Comet\Server($url, $server->username, $password);
    }

    /**
     * Resolve a WHMCS tblservers row for a collect key.
     *
     * Prefers exact alias / normalized name matches so key "obc" resolves to group "OBC"
     * rather than the first of many "*obcbackup.com*" groups.
     */
    private static function resolveWhmcsServer(string $serverKey): ?object
    {
        $key = strtolower(trim($serverKey));
        if ($key === '') {
            return null;
        }

        $normalizedKey = self::normalizeLookupToken($key);
        $aliasNames = self::SERVER_KEY_ALIASES[$key] ?? [$serverKey];

        // 1) Exact case-insensitive match on preferred alias names
        foreach ($aliasNames as $alias) {
            $group = Capsule::table('tblservergroups')
                ->whereRaw('LOWER(name) = ?', [strtolower($alias)])
                ->orderBy('id')
                ->first();
            if ($group) {
                $resolved = self::serverFromGroup($group);
                if ($resolved) {
                    return $resolved;
                }
            }
        }

        // 2) Space/punct-insensitive group match; prefer shortest name (primary group)
        $candidates = Capsule::table('tblservergroups')
            ->select(['id', 'name'])
            ->get()
            ->filter(static function ($group) use ($normalizedKey) {
                return str_contains(self::normalizeLookupToken((string) $group->name), $normalizedKey);
            })
            ->sortBy(static function ($group) {
                return [strlen((string) $group->name), (int) $group->id];
            })
            ->values();

        foreach ($candidates as $group) {
            $resolved = self::serverFromGroup($group);
            if ($resolved) {
                return $resolved;
            }
        }

        // 3) Fall back to tblservers name/hostname containing the key
        $server = Capsule::table('tblservers')
            ->where('type', 'comet')
            ->where(function ($q) use ($key, $normalizedKey, $aliasNames) {
                foreach ($aliasNames as $alias) {
                    $q->orWhereRaw('LOWER(name) = ?', [strtolower($alias)]);
                }
                $q->orWhereRaw('LOWER(hostname) LIKE ?', ['%' . $key . '%']);
                $q->orWhereRaw(
                    "REPLACE(REPLACE(LOWER(name), ' ', ''), '-', '') LIKE ?",
                    ['%' . $normalizedKey . '%']
                );
            })
            ->orderByRaw('CASE WHEN active = 1 THEN 0 ELSE 1 END')
            ->orderBy('id')
            ->first();

        return $server ?: null;
    }

    /** @param object $group tblservergroups row */
    private static function serverFromGroup(object $group): ?object
    {
        $server = Capsule::table('tblservers')
            ->where('name', $group->name)
            ->first();
        if ($server) {
            return $server;
        }

        $serverRel = Capsule::table('tblservergroupsrel')
            ->where('groupid', $group->id)
            ->orderBy('serverid')
            ->first();
        if ($serverRel) {
            return Capsule::table('tblservers')
                ->where('id', $serverRel->serverid)
                ->first();
        }

        return null;
    }

    private static function normalizeLookupToken(string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($value)) ?? '';
    }

    /**
     * Get empty totals structure.
     * 
     * @return array
     */
    private static function emptyTotals(): array
    {
        return [
            'server_key' => null,
            'users' => 0,
            'devices' => 0,
            'hyperv_vms' => 0,
            'vmware_vms' => 0,
            'proxmox_vms' => 0,
            'disk_image' => 0,
            'mssql' => 0,
            'm365_accounts' => 0,
            'storage_bytes' => 0,
            'protected_items' => 0,
        ];
    }

    /**
     * Merge two totals arrays by summing numeric values.
     * 
     * @param array $a Base totals
     * @param array $b Totals to add
     * @return array Merged totals
     */
    private static function mergeTotals(array $a, array $b): array
    {
        $numericKeys = ['users', 'devices', 'hyperv_vms', 'vmware_vms', 'proxmox_vms', 
                        'disk_image', 'mssql', 'm365_accounts', 'storage_bytes', 'protected_items'];
        
        foreach ($numericKeys as $k) {
            $a[$k] = ($a[$k] ?? 0) + ($b[$k] ?? 0);
        }
        
        return $a;
    }

    /**
     * Format bytes to human-readable string.
     * 
     * @param int $bytes
     * @param int $decimals
     * @return string
     */
    public static function formatBytes(int $bytes, int $decimals = 2): string
    {
        if ($bytes === 0) return '0 B';
        
        $k = 1024;
        $sizes = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $i = floor(log($bytes) / log($k));
        
        return round($bytes / pow($k, $i), $decimals) . ' ' . $sizes[$i];
    }
}
