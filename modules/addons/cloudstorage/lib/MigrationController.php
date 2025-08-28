<?php

namespace WHMCS\Module\Addon\CloudStorage;

use Predis\Client as RedisClient;
use WHMCS\Database\Capsule;

/**
 * Centralised helper for reading/writing customer migration status to Redis.
 * The value stored is the cluster alias that traffic and background jobs
 * should be routed to for the given WHMCS client ID.
 */
class MigrationController
{
    /** @var string */
    private static $redisHost = '127.0.0.1';

    /** @var int */
    private static $redisPort = 6379;

    /** @var string */
    private static $redisHashName = 'customer_migration_status';

    /** @var string */
    private static $defaultBackend = 'old_ceph_cluster';

    /** @var string */
    private static $migratedBackend = 'new_ceph_cluster';

    /** @var bool */
    private static $settingsLoaded = false;

    private static function loadSettings(): void
    {
        if (self::$settingsLoaded) {
            return;
        }
        try {
            $rows = Capsule::table('tbladdonmodules')
                ->where('module', 'cloudstorage')
                ->get(['setting', 'value']);
            if ($rows && count($rows) > 0) {
                $map = [];
                foreach ($rows as $row) {
                    $map[$row->setting] = $row->value;
                }
                self::$redisHost = $map['redis_host'] ?? self::$redisHost;
                if (!empty($map['redis_port']) && ctype_digit((string)$map['redis_port'])) {
                    self::$redisPort = (int)$map['redis_port'];
                }
                self::$redisHashName = $map['redis_hash'] ?? self::$redisHashName;
                self::$defaultBackend = $map['default_backend_alias'] ?? self::$defaultBackend;
                self::$migratedBackend = $map['migrated_backend_alias'] ?? self::$migratedBackend;
            }
        } catch (\Throwable $e) {
            // Ignore; fall back to defaults
        } finally {
            self::$settingsLoaded = true;
        }
    }

    /**
     * Determine which backend a client should use.
     * Falls back gracefully to the default cluster on any error.
     */
    public static function getBackendForClient(int $clientId): string
    {
        try {
            self::loadSettings();
            $redis = new RedisClient([
                'scheme' => 'tcp',
                'host'   => self::$redisHost,
                'port'   => self::$redisPort,
            ]);

            $backend = $redis->hget(self::$redisHashName, $clientId);

            return $backend ?: self::$defaultBackend;
        } catch (\Throwable $e) {
            // Safety first – default to the old cluster and log the exception.
            logModuleCall('cloudstorage', __METHOD__, ['client_id' => $clientId], $e->getMessage());
            return self::$defaultBackend;
        }
    }

    /**
     * Mark a client as migrated (points them to the new cluster).
     */
    public static function setClientAsMigrated(int $clientId): bool
    {
        try {
            self::loadSettings();
            $redis = new RedisClient([
                'scheme' => 'tcp',
                'host'   => self::$redisHost,
                'port'   => self::$redisPort,
            ]);

            $redis->hset(self::$redisHashName, $clientId, self::$migratedBackend);
            return true;
        } catch (\Throwable $e) {
            logModuleCall('cloudstorage', __METHOD__, ['client_id' => $clientId], $e->getMessage());
            return false;
        }
    }

    /**
     * Explicitly set a client's backend to a specific cluster alias.
     * Allows switching between clusters at any time.
     */
    public static function setClientBackend(int $clientId, string $clusterAlias): bool
    {
        try {
            self::loadSettings();
            $redis = new RedisClient([
                'scheme' => 'tcp',
                'host'   => self::$redisHost,
                'port'   => self::$redisPort,
            ]);

            $redis->hset(self::$redisHashName, $clientId, $clusterAlias);
            return true;
        } catch (\Throwable $e) {
            logModuleCall('cloudstorage', __METHOD__, ['client_id' => $clientId, 'alias' => $clusterAlias], $e->getMessage());
            return false;
        }
    }

    /**
     * Clear a client's backend setting, causing fallback to the default cluster.
     */
    public static function resetClientBackend(int $clientId): bool
    {
        try {
            self::loadSettings();
            $redis = new RedisClient([
                'scheme' => 'tcp',
                'host'   => self::$redisHost,
                'port'   => self::$redisPort,
            ]);

            $redis->hdel(self::$redisHashName, $clientId);
            return true;
        } catch (\Throwable $e) {
            logModuleCall('cloudstorage', __METHOD__, ['client_id' => $clientId], $e->getMessage());
            return false;
        }
    }
}
