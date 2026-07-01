<?php

/**
 * Per-UUID and per-IP token-bucket rate limiter for agent poll endpoints.
 * Uses Redis when CLOUDBACKUP_REDIS_URL is set; APCu when available.
 * Without Redis or APCu, rate limiting is skipped (fail-open) — the former
 * file-based fallback was removed because it created millions of temp files.
 */
class AgentRateLimiter
{
    private const UUID_RATE = 4;
    private const UUID_BURST = 8;
    private const IP_RATE = 30;
    private const IP_BURST = 60;
    private const WINDOW_SECS = 1;

    public static function enforceOrExit(): void
    {
        $uuid = trim((string) ($_SERVER['HTTP_X_AGENT_UUID'] ?? ($_POST['agent_uuid'] ?? '')));
        $ip = self::clientIp();

        foreach (self::scopes($uuid, $ip) as $scope) {
            $result = self::consume($scope['key'], $scope['rate'], $scope['burst']);
            if (!$result['allowed']) {
                self::respond429((int) $result['retry_after']);
            }
        }
    }

    /** @return list<array{key:string,rate:int,burst:int}> */
    private static function scopes(string $uuid, string $ip): array
    {
        $scopes = [
            ['key' => 'ip:' . $ip, 'rate' => self::IP_RATE, 'burst' => self::IP_BURST],
        ];
        if ($uuid !== '') {
            $scopes[] = ['key' => 'uuid:' . $uuid, 'rate' => self::UUID_RATE, 'burst' => self::UUID_BURST];
        }
        return $scopes;
    }

    private static function clientIp(): string
    {
        $xff = trim((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
        if ($xff !== '') {
            $parts = explode(',', $xff);
            $candidate = trim((string) ($parts[0] ?? ''));
            if ($candidate !== '') {
                return $candidate;
            }
        }
        return trim((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));
    }

    /** @return array{allowed:bool,retry_after:int} */
    private static function consume(string $scopeKey, int $rate, int $burst): array
    {
        $window = (int) floor(time() / self::WINDOW_SECS);
        $key = 'agent:rl:' . $scopeKey . ':' . $window;

        $redis = self::redis();
        if ($redis !== null) {
            try {
                $count = (int) $redis->incr($key);
                if ($count === 1) {
                    $redis->expire($key, self::WINDOW_SECS + 1);
                }
                if ($count > $burst) {
                    $retry = self::WINDOW_SECS - (time() % self::WINDOW_SECS);
                    return ['allowed' => false, 'retry_after' => max(1, $retry)];
                }
                return ['allowed' => true, 'retry_after' => 0];
            } catch (\Throwable $e) {
                // fall through to local backends
            }
        }

        if (function_exists('apcu_fetch') && ini_get('apc.enabled')) {
            $apcuKey = 'cloudbackup_' . $key;
            $count = apcu_inc($apcuKey, 1, $success, self::WINDOW_SECS + 1);
            if (!$success) {
                apcu_store($apcuKey, 1, self::WINDOW_SECS + 1);
                $count = 1;
            }
            if ((int) $count > $burst) {
                $retry = self::WINDOW_SECS - (time() % self::WINDOW_SECS);
                return ['allowed' => false, 'retry_after' => max(1, $retry)];
            }
            return ['allowed' => true, 'retry_after' => 0];
        }

        // No Redis or APCu: skip rate limiting rather than writing per-request temp files.
        return ['allowed' => true, 'retry_after' => 0];
    }

    private static function redis(): ?\Redis
    {
        static $client = false;
        if ($client === false) {
            $client = null;
            $url = trim((string) (getenv('CLOUDBACKUP_REDIS_URL') ?: ''));
            if ($url === '' || !extension_loaded('redis')) {
                return null;
            }
            try {
                $parts = parse_url($url);
                if ($parts === false || empty($parts['host'])) {
                    return null;
                }
                $redis = new \Redis();
                $port = (int) ($parts['port'] ?? 6379);
                $timeout = 0.25;
                if (!$redis->connect((string) $parts['host'], $port, $timeout)) {
                    return null;
                }
                if (!empty($parts['pass'])) {
                    $redis->auth((string) $parts['pass']);
                }
                $db = 0;
                if (isset($parts['path']) && $parts['path'] !== '' && $parts['path'] !== '/') {
                    $db = (int) ltrim((string) $parts['path'], '/');
                }
                if ($db > 0) {
                    $redis->select($db);
                }
                $client = $redis;
            } catch (\Throwable $e) {
                $client = null;
            }
        }
        return $client;
    }

    private static function respond429(int $retryAfter): void
    {
        if (!headers_sent()) {
            http_response_code(429);
            header('Content-Type: application/json');
            header('Retry-After: ' . max(1, $retryAfter));
        }
        echo json_encode([
            'status' => 'fail',
            'message' => 'Rate limit exceeded',
            'retry_after_secs' => max(1, $retryAfter),
        ]);
        exit;
    }
}
