<?php
/**
 * Graceful phpredis wrapper for cloudstorage agent scaling features.
 *
 * Environment:
 *   CLOUDBACKUP_REDIS_URL — e.g. redis://:password@127.0.0.1:6379/0
 *
 * When the extension or DSN is missing, all methods no-op safely.
 */

namespace WHMCS\Module\Addon\CloudStorage\Client;

class RedisConnection
{
    /** @var self|null */
    private static $instance;

    /** @var \Redis|null */
    private $redis;

    /** @var bool */
    private $available = false;

    private function __construct()
    {
        $url = trim((string) (getenv('CLOUDBACKUP_REDIS_URL') ?: ''));
        if ($url === '' || !extension_loaded('redis')) {
            return;
        }

        try {
            $parts = parse_url($url);
            if ($parts === false || empty($parts['host'])) {
                return;
            }
            $client = new \Redis();
            $port = (int) ($parts['port'] ?? 6379);
            if (!$client->connect((string) $parts['host'], $port, 0.5)) {
                return;
            }
            if (!empty($parts['pass'])) {
                $client->auth((string) $parts['pass']);
            }
            if (isset($parts['path']) && $parts['path'] !== '' && $parts['path'] !== '/') {
                $db = (int) ltrim((string) $parts['path'], '/');
                if ($db > 0) {
                    $client->select($db);
                }
            }
            $this->redis = $client;
            $this->available = true;
        } catch (\Throwable $e) {
            $this->redis = null;
            $this->available = false;
        }
    }

    public static function get(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function isAvailable(): bool
    {
        return $this->available && $this->redis instanceof \Redis;
    }

    public function exists(string $key): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }
        try {
            return (bool) $this->redis->exists($key);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function getValue(string $key): ?string
    {
        if (!$this->isAvailable()) {
            return null;
        }
        try {
            $val = $this->redis->get($key);
            return $val === false ? null : (string) $val;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function setex(string $key, int $ttlSeconds, string $value): bool
    {
        if (!$this->isAvailable() || $ttlSeconds <= 0) {
            return false;
        }
        try {
            return (bool) $this->redis->setex($key, $ttlSeconds, $value);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param list<string> $keys
     * @return array<string,bool> map of key => exists
     */
    public function existsMany(array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = false;
        }
        if (!$this->isAvailable() || $keys === []) {
            return $out;
        }
        try {
            $vals = $this->redis->mget($keys);
            if (!is_array($vals)) {
                return $out;
            }
            foreach ($keys as $i => $key) {
                $out[$key] = isset($vals[$i]) && $vals[$i] !== false;
            }
        } catch (\Throwable $e) {
            // keep false defaults
        }
        return $out;
    }

    public function blpop(string $key, int $timeoutSeconds): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }
        try {
            $res = $this->redis->blPop([$key], max(1, $timeoutSeconds));
            return is_array($res) && count($res) >= 2;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function lpush(string $key, string $value): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }
        try {
            return (bool) $this->redis->lPush($key, $value);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
