<?php

require __DIR__ . '/../init.php';

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\MigrationController;

// Local requires within addon module
require_once __DIR__ . '/../modules/addons/cloudstorage/lib/Admin/ClusterManager.php';
require_once __DIR__ . '/../modules/addons/cloudstorage/lib/Admin/AdminOps.php';

use WHMCS\Module\Addon\CloudStorage\Admin\ClusterManager;
use WHMCS\Module\Addon\CloudStorage\Admin\AdminOps;

echo "Starting s3_access_keys sync...\n";

$countUsers = 0;
$countKeysInserted = 0;
$countKeysUpdated = 0;
$countKeysRevoked = 0;

// Preload users and hosting maps to handle parent/tenant relationships
$users = Capsule::table('s3_users')->select('id', 'username', 'parent_id', 'tenant_id')->get();
$idToUser = [];
foreach ($users as $u) { $idToUser[$u->id] = $u; }
$hostingMap = Capsule::table('tblhosting')->pluck('userid', 'username')->toArray();

foreach ($users as $user) {
    // Resolve WHMCS client via hosting.username or via parent user mapping
    $clientId = null;
    if (!empty($user->username) && isset($hostingMap[$user->username])) {
        $clientId = (int)$hostingMap[$user->username];
    } elseif (!empty($user->parent_id) && isset($idToUser[$user->parent_id])) {
        $parent = $idToUser[$user->parent_id];
        if (!empty($parent->username) && isset($hostingMap[$parent->username])) {
            $clientId = (int)$hostingMap[$parent->username];
        }
    }
    if (!$clientId) {
        continue; // Not mapped to any WHMCS client; skip
    }
    $backendAlias = MigrationController::getBackendForClient($clientId);
    $cluster = ClusterManager::getClusterByAlias($backendAlias);
    if (!$cluster) {
        $cluster = ClusterManager::getDefaultCluster();
        if (!$cluster) {
            // Cannot proceed without cluster credentials
            continue;
        }
    }

    // Build UID for RGW (tenant$uid when tenant_id present)
    $uid = $user->username;
    if (!empty($user->tenant_id)) {
        $uid = $user->tenant_id . '$' . $user->username;
    }

    // Query RGW for user info (includes keys)
    $resp = AdminOps::getUserInfo($cluster->s3_endpoint, $cluster->admin_access_key, $cluster->admin_secret_key, $uid);
    if (($resp['status'] ?? 'fail') !== 'success') {
        continue;
    }
    $data = $resp['data'] ?? [];
    $keys = isset($data['keys']) && is_array($data['keys']) ? $data['keys'] : [];

    // Build current set
    $currentAccessKeys = [];
    foreach ($keys as $k) {
        if (!empty($k['access_key'])) {
            $currentAccessKeys[] = $k['access_key'];
        }
    }

    // Upsert present keys as active
    foreach ($currentAccessKeys as $ak) {
        $existing = Capsule::table('s3_access_keys')->where('access_key', $ak)->first();
        if ($existing) {
            Capsule::table('s3_access_keys')->where('id', $existing->id)->update([
                'user_id' => $user->id,
                'state' => 'active',
                'migrated_to_alias' => $existing->migrated_to_alias, // keep
            ]);
            $countKeysUpdated++;
        } else {
            Capsule::table('s3_access_keys')->insert([
                'user_id' => $user->id,
                'access_key' => $ak,
                'secret_hash' => null, // RGW does not expose secret values when listing
                'state' => 'active',
                'migrated_to_alias' => null,
                'flipped_at' => null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $countKeysInserted++;
        }
    }

    // Keys existing in DB for this user but not returned by RGW are considered revoked
    $revokedQuery = Capsule::table('s3_access_keys')
        ->where('user_id', $user->id)
        ->where('state', '!=', 'revoked');
    if (!empty($currentAccessKeys)) {
        $revokedQuery->whereNotIn('access_key', $currentAccessKeys);
    }
    $revoked = $revokedQuery->update(['state' => 'revoked']);
    $countKeysRevoked += (int)$revoked;

    $countUsers++;
}

echo "Sync complete. Users processed: {$countUsers}, inserted: {$countKeysInserted}, updated: {$countKeysUpdated}, revoked: {$countKeysRevoked}\n";

// Log to module log
if (!function_exists('logModuleCall')) {
    function logModuleCall($module, $action, $request, $response) {
        // no-op in minimal environments
    }
}

logModuleCall('cloudstorage', 'sync_access_keys', [
    'users' => $countUsers,
    'inserted' => $countKeysInserted,
    'updated' => $countKeysUpdated,
    'revoked' => $countKeysRevoked,
], 'done');


