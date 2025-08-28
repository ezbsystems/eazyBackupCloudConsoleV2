#!/usr/bin/env php
<?php
/**
 * One‑off (or on‑demand) script that scans every active WHMCS service,
 * asks the matching Comet Server for its live DeviceIDs, and flags any
 * rows that no longer exist upstream as  is_active = 0  in comet_devices.
 *
 * Usage:  php -q modules/addons/eazybackup/crons/cleanup_revoked_devices.php
 *
 * You can safely run it multiple times; it only flips the flag when needed.
 */

require __DIR__ . '/../init.php';

use Comet\Server;
use WHMCS\Database\Capsule;

// ───────────────────────────────────────────────────────────────────────────────
// 1.  Find every product we care about (same exclusions as sync cron)
// ───────────────────────────────────────────────────────────────────────────────
$excludeProductgroupIds = [2, 11];

$products = Capsule::table('tblproducts')
    ->select('id', 'servergroup')
    ->whereNotIn('gid', $excludeProductgroupIds)
    ->get();

$productServerGroupMap = [];
foreach ($products as $product) {
    if ($product->servergroup > 0) {
        $productServerGroupMap[$product->id] = $product->servergroup;
    }
}

$productIds = array_keys($productServerGroupMap);
$groupIds   = array_unique(array_values($productServerGroupMap));

// ───────────────────────────────────────────────────────────────────────────────
// 2.  Build the same  $serverHosts[$servergroupId]  array used in the cron
// ───────────────────────────────────────────────────────────────────────────────
$servergroups = Capsule::table('tblservergroups')
    ->select('id', 'name')
    ->whereIn('id', $groupIds)
    ->pluck('name', 'id')
    ->toArray();

$servers = Capsule::table('tblservers')
    ->select('name', 'hostname', 'secure', 'port', 'username', 'password')
    ->whereIn('name', array_values($servergroups))
    ->get();

$serverHosts = [];
foreach ($servers as $server) {
    $passwordRow = localAPI('DecryptPassword', ['password2' => $server->password]);

    $scheme   = $server->secure ? 'https' : 'http';
    $portPart = $server->port ? ':' . $server->port : '';
    $hostname = preg_replace(['/^https?:\/\//i', '/\/$/'], '', $server->hostname);
    $url      = "{$scheme}://{$hostname}{$portPart}/";

    $groupId = array_search($server->name, $servergroups);   // match tblservers.name → tblservergroups.name
    $serverHosts[$groupId] = [
        'url'      => $url,
        'username' => $server->username,
        'password' => $passwordRow['password'],
    ];
}

// ───────────────────────────────────────────────────────────────────────────────
// 3.  Pull every ACTIVE hosting service that belongs to those products
// ───────────────────────────────────────────────────────────────────────────────
$hostings = Capsule::table('tblhosting')
    ->select('id', 'userid', 'username', 'packageid')
    ->where('domainstatus', 'Active')
    ->whereIn('packageid', $productIds)
    ->get();

echo "Starting device‑reconciliation cleanup …\n";

$totalDeactivated = 0;

foreach ($hostings as $hosting) {

    // Which Comet server looks after this package?
    $groupId = $productServerGroupMap[$hosting->packageid] ?? null;
    $srv     = $serverHosts[$groupId]        ?? null;

    if (!$srv) {
        logModuleCall(
            'eazybackup',
            'cleanup missingServer',
            ['serviceid' => $hosting->id, 'packageid' => $hosting->packageid],
            'No matching server found'
        );
        echo "• Service {$hosting->id}: skipped (no server mapping)\n";
        continue;
    }

    try {
        $server      = new Server($srv['url'], $srv['username'], $srv['password']);
        $userProfile = $server->AdminGetUserProfile($hosting->username);

        // DeviceIDs that are STILL present upstream
        $liveHashes = array_keys((array) $userProfile->Devices);

        // Flag everything else as revoked locally
        $rows = Capsule::table('comet_devices')
            ->where('client_id', $hosting->userid)
            ->whereNotIn('hash', $liveHashes)
            ->update([
                'is_active'  => 0,
                'updated_at' => date('Y-m-d H:i:s'),
                // 'revoked_at' => date('Y-m-d H:i:s'),   // ← Uncomment if you added this column
            ]);

        $totalDeactivated += $rows;
        echo "✓ Service {$hosting->id} (client {$hosting->userid}) – de‑activated {$rows} stale devices\n";

    } catch (\Throwable $e) {        // catch both \Exception and \Error
        logModuleCall(
            'eazybackup',
            'cleanup getUserProfile',
            ['serviceid' => $hosting->id, 'server' => $srv['url']],
            $e->getMessage(),
            $e->getTraceAsString()
        );
        echo "× Service {$hosting->id}: " . $e->getMessage() . "\n";
    }
}

echo "Done.  Total devices marked inactive: {$totalDeactivated}\n";
