<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

require_once __DIR__ . '/AgentIngestSupport.php';
require_once __DIR__ . '/RedisConnection.php';

class AgentLiveness
{
    public const REDIS_KEY_PREFIX = 'agent:liveness:';

    public static function isEnabled(): bool
    {
        $env = getenv('CLOUDBACKUP_REDIS_LIVENESS_ENABLED');
        if ($env !== false && $env !== '') {
            return !in_array(strtolower(trim((string) $env)), ['0', 'false', 'off', 'no'], true);
        }

        $setting = strtolower(trim((string) AgentIngestSupport::getModuleSetting(
            'cloudbackup_redis_liveness_enabled',
            '0'
        )));

        return in_array($setting, ['1', 'on', 'yes', 'true'], true);
    }

    public static function getRedisTtlSeconds(): int
    {
        $env = getenv('CLOUDBACKUP_LIVENESS_REDIS_TTL');
        if ($env !== false && $env !== '') {
            $parsed = (int) $env;
            if ($parsed > 0) {
                return $parsed;
            }
        }

        $ttl = (int) AgentIngestSupport::getModuleSetting('cloudbackup_liveness_redis_ttl', 180);
        return $ttl > 0 ? $ttl : 180;
    }

    public static function redisKey(string $agentUuid): string
    {
        return self::REDIS_KEY_PREFIX . $agentUuid;
    }

    /**
     * Record agent liveness in Redis (SETEX on miss). Returns true when Redis accepted a write.
     */
    public static function touchRedis(string $agentUuid): bool
    {
        if (!self::isEnabled()) {
            return false;
        }

        $redis = RedisConnection::get();
        if (!$redis->isAvailable()) {
            return false;
        }

        $key = self::redisKey($agentUuid);
        if ($redis->exists($key)) {
            return true;
        }

        return $redis->setex($key, self::getRedisTtlSeconds(), '1');
    }

    /**
     * Redis-first online probe. Returns true when live, false when Redis is up but key is missing,
     * or null when Redis liveness is disabled/unavailable (caller should use last_seen_at).
     */
    public static function isOnline(string $agentUuid): ?bool
    {
        if (!self::isEnabled()) {
            return null;
        }

        $redis = RedisConnection::get();
        if (!$redis->isAvailable()) {
            return null;
        }

        return $redis->exists(self::redisKey($agentUuid));
    }

    /**
     * @param list<string> $agentUuids
     * @return array<string,bool|null> uuid => online|null
     */
    public static function bulkOnlineStatus(array $agentUuids): array
    {
        $agentUuids = array_values(array_unique(array_filter(array_map('strval', $agentUuids))));
        $out = [];
        foreach ($agentUuids as $uuid) {
            $out[$uuid] = null;
        }
        if ($agentUuids === [] || !self::isEnabled()) {
            return $out;
        }

        $redis = RedisConnection::get();
        if (!$redis->isAvailable()) {
            return $out;
        }

        $keys = [];
        $keyToUuid = [];
        foreach ($agentUuids as $uuid) {
            $key = self::redisKey($uuid);
            $keys[] = $key;
            $keyToUuid[$key] = $uuid;
        }

        $exists = $redis->existsMany($keys);
        foreach ($exists as $key => $hit) {
            $uuid = $keyToUuid[$key] ?? null;
            if ($uuid !== null) {
                $out[$uuid] = $hit;
            }
        }

        return $out;
    }
}
