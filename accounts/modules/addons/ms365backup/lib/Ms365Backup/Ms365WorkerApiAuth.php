<?php
declare(strict_types=1);

namespace Ms365Backup;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class Ms365WorkerApiAuth
{
    public static function authenticate(Request $request): ?JsonResponse
    {
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
