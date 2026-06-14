<?php
declare(strict_types=1);

namespace Ms365Backup\Fleet;

use Ms365Backup\Ms365EngineConfig;
use WHMCS\Database\Capsule;

final class ArtifactService
{
    public static function issueNonce(int $releaseId, string $nodeId): string
    {
        $ttl = FleetSettings::artifactNonceTtlSeconds();
        $expires = time() + $ttl;
        $payload = $releaseId . '|' . $nodeId . '|' . $expires;
        $sig = hash_hmac('sha256', $payload, self::signingKey());
        $nonce = base64_encode($payload . '|' . $sig);

        return rtrim(strtr($nonce, '+/', '-_'), '=');
    }

    /** @return array{release_id: int, node_id: string}|null */
    public static function verifyNonce(string $nonce): ?array
    {
        $decoded = base64_decode(strtr($nonce, '-_', '+/') . str_repeat('=', (4 - strlen($nonce) % 4) % 4), true);
        if ($decoded === false) {
            return null;
        }
        $parts = explode('|', $decoded);
        if (count($parts) !== 4) {
            return null;
        }
        [$releaseId, $nodeId, $expires, $sig] = $parts;
        if ((int) $expires < time()) {
            return null;
        }
        $payload = $releaseId . '|' . $nodeId . '|' . $expires;
        $expected = hash_hmac('sha256', $payload, self::signingKey());
        if (!hash_equals($expected, $sig)) {
            return null;
        }

        return ['release_id' => (int) $releaseId, 'node_id' => $nodeId];
    }

    public static function downloadUrl(int $releaseId, string $nodeId): string
    {
        $nonce = self::issueNonce($releaseId, $nodeId);

        return FleetSettings::workerApiBaseUrl() . '/ms365_worker_artifact.php?release_id=' . $releaseId . '&nonce=' . rawurlencode($nonce);
    }

    public static function logDownload(int $releaseId, string $nodeId, string $nonce, string $ip): void
    {
        if (!Capsule::schema()->hasTable('ms365_worker_artifact_downloads')) {
            return;
        }
        Capsule::table('ms365_worker_artifact_downloads')->insert([
            'release_id' => $releaseId,
            'node_id' => $nodeId,
            'nonce' => mb_substr($nonce, 0, 128),
            'ip_address' => mb_substr($ip, 0, 45),
            'downloaded_at' => time(),
        ]);
    }

    private static function signingKey(): string
    {
        $token = Ms365EngineConfig::workerToken();

        return hash('sha256', 'ms365-artifact|' . $token);
    }
}
