<?php
declare(strict_types=1);

namespace Ms365Backup;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class Ms365WorkerApiAuth
{
    public static function authenticate(Request $request): ?JsonResponse
    {
        // #region agent log
        static $agentDebugRegistered = false;
        if (!$agentDebugRegistered) {
            $agentDebugRegistered = true;
            $startedAt = (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
            $endpoint = basename((string) $request->getPathInfo());
            $nodeId = trim((string) $request->headers->get('X-MS365-Worker-Node', ''));
            $nodeHash = $nodeId === '' ? '' : substr(hash('sha256', $nodeId), 0, 8);
            $bodyBytes = max(0, (int) $request->headers->get('Content-Length', 0));
            $sampled = random_int(1, 20) === 1;
            register_shutdown_function(static function () use ($startedAt, $endpoint, $nodeHash, $bodyBytes, $sampled): void {
                $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);
                if (!$sampled && $elapsedMs < 1000) {
                    return;
                }
                $status = http_response_code();
                @file_put_contents('/var/www/eazybackup.ca/.cursor/debug-21b182.log', json_encode([
                    'sessionId' => '21b182',
                    'runId' => 'pre-fix',
                    'hypothesisId' => 'H1,H2,H3,H4,H5',
                    'location' => 'Ms365WorkerApiAuth.php:authenticate',
                    'message' => 'worker API request timing',
                    'data' => [
                        'endpoint' => $endpoint,
                        'status' => is_int($status) ? $status : 200,
                        'elapsed_ms' => $elapsedMs,
                        'peak_memory_mib' => round(memory_get_peak_usage(true) / 1048576, 2),
                        'request_body_bytes' => $bodyBytes,
                        'node_hash' => $nodeHash,
                        'sampled' => $sampled,
                    ],
                    'timestamp' => (int) round(microtime(true) * 1000),
                ], JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
            });
        }
        // #endregion

        $expected = Ms365EngineConfig::workerToken();
        if ($expected === '') {
            return new JsonResponse(['status' => 'error', 'message' => 'Worker token not configured'], 503);
        }
        $provided = trim((string) $request->headers->get('X-MS365-Worker-Token', ''));
        if ($provided === '' || !hash_equals($expected, $provided)) {
            return new JsonResponse(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        return null;
    }

    /** @return array<string, mixed> */
    public static function jsonBody(Request $request): array
    {
        $raw = (string) $request->getContent();
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
