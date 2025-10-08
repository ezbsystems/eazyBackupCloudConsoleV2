<?php
namespace EazyBackup;

use WHMCS\Database\Capsule;

class InvoiceUsage
{
    // Simple in-process cache so we do not re-query per duplicate username on the same invoice
    protected static array $cache = [];

    /**
     * Case-sensitive usage lookup for a Comet username.
     * Optionally scope by $clientId (tblhosting.userid) to be extra safe for multi-tenant setups.
     *
     * Returns:
     * [
     *   'username'    => string,
     *   'total_bytes' => int|null,
     *   'vaults'      => [ ['name'=>string, 'total_bytes'=>int], ... ],
     *   'devices'     => [ ['name'=>string, 'hash'=>string, 'platform_os'=>string, 'platform_arch'=>string, 'is_active'=>int], ... ],
     * ]
     */
    public static function getUsageForUsername(string $username, ?int $clientId = null): array
    {
        $key = $username . '|' . ($clientId ?? 0);
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        // Verify the username actually exists in comet_users (CASE-SENSITIVE)
        $exists = Capsule::table('comet_users')
            ->when($clientId !== null, function ($q) use ($clientId) {
                $q->where('user_id', $clientId);
            })
            ->whereRaw('BINARY `username` = ?', [$username])
            ->exists();

        if (!$exists) {
            return self::$cache[$key] = [
                'username'    => $username,
                'total_bytes' => null,
                'vaults'      => [],
                'devices'     => [],
            ];
        }

        // Vaults (active, not removed), totals per vault and overall sum
        $vaultRows = Capsule::table('comet_vaults')
            ->select(['name', 'total_bytes'])
            ->when($clientId !== null, function ($q) use ($clientId) {
                $q->where('client_id', $clientId);
            })
            ->whereRaw('BINARY `username` = ?', [$username])
            ->where('is_active', 1)
            ->whereNull('removed_at')
            ->orderBy('name')
            ->get();

        $vaults = [];
        $totalBytes = 0;
        foreach ($vaultRows as $r) {
            $bytes = is_null($r->total_bytes) ? 0 : (int) $r->total_bytes;
            $vaults[] = [
                'name'        => (string) $r->name,
                'total_bytes' => $bytes,
            ];
            $totalBytes += $bytes;
        }

        // Devices (active)
        $deviceRows = Capsule::table('comet_devices')
            ->select(['name', 'hash', 'platform_os', 'platform_arch', 'is_active'])
            ->when($clientId !== null, function ($q) use ($clientId) {
                $q->where('client_id', $clientId);
            })
            ->whereRaw('BINARY `username` = ?', [$username])
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        $devices = [];
        foreach ($deviceRows as $d) {
            $devices[] = [
                'name'         => (string) ($d->name ?? ''),
                'hash'         => (string) ($d->hash ?? ''),
                'platform_os'  => (string) ($d->platform_os ?? ''),
                'platform_arch'=> (string) ($d->platform_arch ?? ''),
                'is_active'    => (int) $d->is_active,
            ];
        }

        return self::$cache[$key] = [
            'username'    => $username,
            'total_bytes' => $totalBytes,
            'vaults'      => $vaults,
            'devices'     => $devices,
        ];
    }
}
