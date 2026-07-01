<?php
/**
 * Dev/ops check: Redis + CLOUDBACKUP_REDIS_URL from PHP-FPM.
 * curl -s -H 'Host: dev.eazybackup.ca' http://127.0.0.1/modules/addons/cloudstorage/tests/verify_redis_setup.php
 */
header('Content-Type: application/json');

$url = trim((string) (getenv('CLOUDBACKUP_REDIS_URL') ?: ''));
$out = [
    'CLOUDBACKUP_REDIS_URL' => $url !== '' ? $url : null,
    'CLOUDBACKUP_REDIS_LIVENESS_ENABLED' => getenv('CLOUDBACKUP_REDIS_LIVENESS_ENABLED') ?: null,
    'php_redis_extension' => extension_loaded('redis'),
    'redis_ping' => null,
    'rate_limiter_backend' => 'none',
];

if ($url !== '' && extension_loaded('redis')) {
    try {
        $parts = parse_url($url);
        $redis = new Redis();
        $host = (string) ($parts['host'] ?? '127.0.0.1');
        $port = (int) ($parts['port'] ?? 6379);
        if ($redis->connect($host, $port, 0.5)) {
            if (!empty($parts['pass'])) {
                $redis->auth((string) $parts['pass']);
            }
            if (isset($parts['path']) && $parts['path'] !== '' && $parts['path'] !== '/') {
                $db = (int) ltrim((string) $parts['path'], '/');
                if ($db > 0) {
                    $redis->select($db);
                }
            }
            $out['redis_ping'] = $redis->ping();
            $testKey = 'cloudbackup:verify:' . getmypid();
            $redis->setex($testKey, 10, 'ok');
            $out['redis_setex'] = $redis->get($testKey);
            $redis->del($testKey);
            $out['rate_limiter_backend'] = 'redis';
        }
    } catch (Throwable $e) {
        $out['error'] = $e->getMessage();
    }
} elseif (function_exists('apcu_fetch') && ini_get('apc.enabled')) {
    $out['rate_limiter_backend'] = 'apcu';
}

$ok = $out['redis_ping'] !== null && $out['rate_limiter_backend'] === 'redis';
http_response_code($ok ? 200 : 503);
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
