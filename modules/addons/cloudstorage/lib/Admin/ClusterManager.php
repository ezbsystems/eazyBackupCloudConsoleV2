<?php

namespace WHMCS\Module\Addon\CloudStorage\Admin;

use WHMCS\Database\Capsule;
use Predis\Client as RedisClient;
use WHMCS\Module\Addon\CloudStorage\MigrationController;

/**
 * Helper class for interacting with the s3_clusters table.
 *
 * All methods are static for ease-of-use inside cron jobs and business logic
 * without needing to repeatedly instantiate the class.
 */
class ClusterManager
{
    /**
     * Simple per-request cache for clusters and alias lookups
     * @var array{
     *   all?: \Illuminate\Support\Collection,
     *   alias?: array<string, object>
     * }
     */
    private static $cache = [
        'all' => null,
        'alias' => [],
    ];
    /**
     * Retrieve a single cluster record by its unique alias.
     *
     * @param string $alias  e.g. "old_ceph_cluster" or "new_ceph_cluster"
     * @return object|null  Row from s3_clusters or null if not found
     */
    public static function getClusterByAlias(string $alias)
    {
        if (isset(self::$cache['alias'][$alias])) {
            return self::$cache['alias'][$alias];
        }
        $row = Capsule::table('s3_clusters')->where('cluster_alias', $alias)->first();
        if ($row) {
            self::$cache['alias'][$alias] = $row;
        }
        return $row;
    }

    /**
     * Return the cluster that is flagged as the default one.
     *
     * @return object|null
     */
    public static function getDefaultCluster()
    {
        return Capsule::table('s3_clusters')->where('is_default', 1)->first();
    }

    /**
     * Get every configured cluster.
     *
     * @return \Illuminate\Support\Collection
     */
    public static function getAllClusters()
    {
        if (self::$cache['all'] !== null) {
            return self::$cache['all'];
        }
        $all = Capsule::table('s3_clusters')->get();
        self::$cache['all'] = $all;
        // Prime alias cache
        foreach ($all as $row) {
            self::$cache['alias'][$row->cluster_alias] = $row;
        }
        return $all;
    }

    /**
     * Retrieve all access keys (including tenants) for a given WHMCS client id.
     * Returns an array of associative arrays with keys: access_key, state, migrated_to_alias, flipped_at, user_id
     */
    public static function getAccessKeysByClient(int $clientId): array
    {
        // Resolve primary s3_user ids mapped to this WHMCS client via hosting username
        // Avoid explicit COLLATE to prevent charset mismatch issues across environments
        $usernames = Capsule::table('tblhosting')
            ->where('userid', $clientId)
            ->pluck('username')
            ->toArray();

        if (empty($usernames)) {
            return [];
        }

        $primaryUserIds = [];
        if (!empty($usernames)) {
            $primaryUserIds = Capsule::table('s3_users')
                ->whereIn('username', $usernames)
                ->pluck('id')
                ->toArray();
        }

        if (empty($primaryUserIds)) {
            return [];
        }

        // Include tenant users under these primaries
        $tenantUserIds = Capsule::table('s3_users')
            ->whereIn('parent_id', $primaryUserIds)
            ->pluck('id')
            ->toArray();

        $allUserIds = array_values(array_unique(array_merge($primaryUserIds, $tenantUserIds)));

        if (empty($allUserIds)) {
            return [];
        }

        $rows = Capsule::table('s3_access_keys')
            ->whereIn('user_id', $allUserIds)
            ->get(['user_id','access_key','state','migrated_to_alias','flipped_at'])
            ->map(function ($r) {
                return [
                    'user_id' => (int)$r->user_id,
                    'access_key' => $r->access_key,
                    'state' => $r->state,
                    'migrated_to_alias' => $r->migrated_to_alias,
                    'flipped_at' => $r->flipped_at,
                ];
            })
            ->toArray();

        return $rows;
    }

    /**
     * Freeze or unfreeze all access keys for a client in Redis and DB.
     * Writes ak_state:{clientId} hash => access_key -> 'frozen'|'active'
     */
    public static function setClientFrozen(int $clientId, bool $frozen): bool
    {
        $keys = self::getAccessKeysByClient($clientId);
        // It's valid for clients to have no keys
        $redis = self::getRedisClient();
        $hashName = self::akStateHash($clientId);
        $state = $frozen ? 'frozen' : 'active';

        try {
            if (!empty($keys)) {
                $payload = [];
                foreach ($keys as $k) {
                    $payload[$k['access_key']] = $state;
                }
                if (!empty($payload)) {
                    $redis->hmset($hashName, $payload);
                }
                // Reflect state in DB as well
                $userIds = array_values(array_unique(array_map(function($k){return $k['user_id'];}, $keys)));
                Capsule::table('s3_access_keys')->whereIn('user_id', $userIds)->update(['state' => $state]);
            } else {
                // Clear the hash
                $redis->del([$hashName]);
            }

            // Audit event
            self::recordMigrationEvent($clientId, $frozen ? 'freeze' : 'unfreeze', null, null, ['count_keys' => count($keys)]);
            return true;
        } catch (\Throwable $e) {
            logModuleCall('cloudstorage', __METHOD__, ['client_id' => $clientId, 'frozen' => $frozen], $e->getMessage());
            return false;
        }
    }

    /**
     * Flip client to the provided cluster alias.
     * - Writes ak_map:{clientId} hash => access_key -> alias
     * - Updates customer_migration_status via MigrationController
     * - Updates DB s3_access_keys.migrated_to_alias + flipped_at
     * - Records migration event
     */
    public static function flipClientTo(int $clientId, string $targetAlias): bool
    {
        $keys = self::getAccessKeysByClient($clientId);
        $redis = self::getRedisClient();
        $akMapHash = self::akMapHash($clientId);

        try {
            if (!empty($keys)) {
                $payload = [];
                foreach ($keys as $k) {
                    $payload[$k['access_key']] = $targetAlias;
                }
                if (!empty($payload)) {
                    $redis->hmset($akMapHash, $payload);
                }
                // Update DB annotations
                $userIds = array_values(array_unique(array_map(function($k){return $k['user_id'];}, $keys)));
                Capsule::table('s3_access_keys')
                    ->whereIn('user_id', $userIds)
                    ->update([
                        'migrated_to_alias' => $targetAlias,
                        'flipped_at' => date('Y-m-d H:i:s'),
                    ]);
            }

            // Set customer migration status
            MigrationController::setClientBackend($clientId, $targetAlias);

            // Audit
            self::recordMigrationEvent($clientId, 'flip', self::getCurrentAlias($clientId), $targetAlias, ['count_keys' => count($keys)]);
            return true;
        } catch (\Throwable $e) {
            logModuleCall('cloudstorage', __METHOD__, ['client_id' => $clientId, 'alias' => $targetAlias], $e->getMessage());
            return false;
        }
    }

    /**
     * Helper: get the currently configured alias for a client via Redis (or default).
     */
    private static function getCurrentAlias(int $clientId): string
    {
        try {
            return MigrationController::getBackendForClient($clientId);
        } catch (\Throwable $e) {
            return 'unknown';
        }
    }

    /**
     * Create a migration audit entry
     */
    private static function recordMigrationEvent(int $clientId, string $action, ?string $fromAlias, ?string $toAlias, array $notes = []): void
    {
        try {
            Capsule::table('s3_migration_events')->insert([
                'client_id' => $clientId,
                'actor_admin_id' => null,
                'action' => $action,
                'from_alias' => $fromAlias,
                'to_alias' => $toAlias,
                'notes' => !empty($notes) ? json_encode($notes) : null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // Best effort; ignore
        }
    }

    /**
     * Build Redis client using addon settings
     */
    private static function getRedisClient(): RedisClient
    {
        $host = '127.0.0.1';
        $port = 6379;
        try {
            $rows = Capsule::table('tbladdonmodules')->where('module', 'cloudstorage')->get(['setting','value']);
            $map = [];
            foreach ($rows as $r) { $map[$r->setting] = $r->value; }
            if (!empty($map['redis_host'])) { $host = $map['redis_host']; }
            if (!empty($map['redis_port']) && ctype_digit((string)$map['redis_port'])) { $port = (int)$map['redis_port']; }
        } catch (\Throwable $e) { /* defaults */ }
        return new RedisClient(['scheme' => 'tcp', 'host' => $host, 'port' => $port]);
    }

    private static function akStateHash(int $clientId): string
    {
        return 'ak_state:' . $clientId;
    }

    private static function akMapHash(int $clientId): string
    {
        return 'ak_map:' . $clientId;
    }
}
