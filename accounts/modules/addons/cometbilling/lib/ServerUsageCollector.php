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
     */
    private const DEFAULT_SERVERS = ['cometbackup', 'obc'];

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

        foreach ($allProfiles as $username => $profile) {
            $userUsage = self::extractUsageFromProfile($profile);
            
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

        return $totals;
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
     * Get a Comet\Server instance for a server key.
     * Looks up server credentials from WHMCS tblservers.
     * 
     * @param string $serverKey Server key matching tblservergroups.name
     * @return \Comet\Server|null
     */
    private static function getCometServer(string $serverKey): ?\Comet\Server
    {
        // Look up server group in WHMCS by name pattern
        $serverGroup = Capsule::table('tblservergroups')
            ->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($serverKey) . '%'])
            ->first();

        if (!$serverGroup) {
            return null;
        }

        // Get server associated with this group
        $server = Capsule::table('tblservers')
            ->where('name', $serverGroup->name)
            ->first();

        if (!$server) {
            // Try getting any server in the group
            $serverRel = Capsule::table('tblservergroupsrel')
                ->where('groupid', $serverGroup->id)
                ->first();
            
            if ($serverRel) {
                $server = Capsule::table('tblservers')
                    ->where('id', $serverRel->serverid)
                    ->first();
            }
        }

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

        $protocol = $server->secure ? 'https' : 'http';
        $port = !empty($server->port) ? ':' . $server->port : '';
        $url = "{$protocol}://{$server->hostname}{$port}/";

        return new \Comet\Server($url, $server->username, $password);
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
