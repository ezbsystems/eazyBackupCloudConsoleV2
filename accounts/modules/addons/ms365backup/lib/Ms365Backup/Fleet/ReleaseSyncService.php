<?php
declare(strict_types=1);

namespace Ms365Backup\Fleet;

use Ms365Backup\Ms365EngineConfig;
use WHMCS\Database\Capsule;

/**
 * Push worker releases from dev WHMCS to production fleet.
 */
final class ReleaseSyncService
{
    /** @return array<string, mixed> */
    public static function publishToProduction(int $releaseId): array
    {
        if (!FleetContext::isDevelopmentServer()) {
            throw new \RuntimeException('Release publish to production is only available on the development server');
        }

        $release = ReleaseRepository::get($releaseId);
        if ($release === null) {
            throw new \RuntimeException('Release not found');
        }

        $artifactPath = (string) ($release['artifact_path'] ?? '');
        if ($artifactPath === '' || !is_file($artifactPath)) {
            throw new \RuntimeException('Release artifact file missing: ' . $artifactPath);
        }

        $response = FleetRemoteClient::post('fleet_release_upsert', [
            'version' => (string) $release['version'],
            'git_ref' => (string) ($release['git_ref'] ?? ''),
            'sha256' => (string) $release['sha256'],
            'artifact_size' => (int) ($release['artifact_size'] ?? 0),
            'build_job_id' => (int) ($release['build_job_id'] ?? 0),
            'source_release_id' => $releaseId,
        ], $artifactPath);

        FleetAuditLog::write(
            'release_sync_push',
            'Synced release ' . $release['version'] . ' to production',
            'release',
            (string) $releaseId,
            ['remote_release_id' => $response['release_id'] ?? null]
        );

        return $response;
    }

    /** @return array{status: string, message: string, release_id?: int} */
    public static function pullFromDevelopment(): array
    {
        if (!FleetContext::isProductionServer()) {
            return ['status' => 'skipped', 'message' => 'Pull sync runs on production server only'];
        }
        if (!FleetContext::releaseSyncEnabledOnProd()) {
            return ['status' => 'skipped', 'message' => 'Production release sync disabled'];
        }

        $devUrl = FleetContext::developmentSystemUrl();
        if ($devUrl === '') {
            return ['status' => 'skipped', 'message' => 'Development system URL not configured'];
        }

        $token = FleetRemoteAuth::sharedToken();
        if ($token === '') {
            return ['status' => 'failed', 'message' => 'Fleet deploy shared secret not configured'];
        }

        $manifestUrl = FleetContext::fleetRemoteApiUrl($devUrl, 'fleet_release_manifest');

        try {
            $manifest = self::fetchDevManifest($manifestUrl, $token);
        } catch (\Throwable $e) {
            self::recordSyncOutcome(null, 'failed', $e->getMessage());

            return ['status' => 'failed', 'message' => $e->getMessage()];
        }

        if ($manifest === []) {
            return ['status' => 'skipped', 'message' => 'No releases on development server'];
        }

        $releaseId = (int) ($manifest['id'] ?? 0);
        $version = (string) ($manifest['version'] ?? '');
        if ($releaseId <= 0 || $version === '') {
            return ['status' => 'skipped', 'message' => 'Development manifest incomplete'];
        }

        $existing = ReleaseRepository::getByVersion($version);
        if ($existing !== null && (string) ($existing['sha256'] ?? '') === (string) ($manifest['sha256'] ?? '')) {
            self::recordSyncOutcome((int) $existing['id'], 'skipped', 'Already have release ' . $version);

            return ['status' => 'skipped', 'message' => 'Already synced release ' . $version];
        }

        try {
            $localId = self::downloadAndInstall($devUrl, $token, $manifest);
            self::recordSyncOutcome($localId, 'succeeded', 'Pulled release ' . $version . ' from development');

            return ['status' => 'succeeded', 'message' => 'Installed release ' . $version, 'release_id' => $localId];
        } catch (\Throwable $e) {
            self::recordSyncOutcome(null, 'failed', $e->getMessage());

            return ['status' => 'failed', 'message' => $e->getMessage()];
        }
    }

    /** @return array<string, mixed> */
    private static function fetchDevManifest(string $url, string $token): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                FleetRemoteAuth::HEADER . ': ' . $token,
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 120,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false || $code >= 400) {
            throw new \RuntimeException('Development manifest HTTP ' . $code);
        }
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded) || empty($decoded['ok'])) {
            throw new \RuntimeException((string) ($decoded['error'] ?? 'Invalid manifest response'));
        }

        return is_array($decoded['release'] ?? null) ? $decoded['release'] : [];
    }

    /** @param array<string, mixed> $manifest */
    private static function downloadAndInstall(string $devUrl, string $token, array $manifest): int
    {
        $sourceId = (int) ($manifest['id'] ?? 0);
        $version = (string) ($manifest['version'] ?? '');
        $artifactUrl = FleetContext::fleetRemoteApiUrl($devUrl, 'fleet_release_artifact')
            . '&release_id=' . $sourceId;

        $tmp = tempnam(sys_get_temp_dir(), 'ms365rel_');
        if ($tmp === false) {
            throw new \RuntimeException('temp file failed');
        }

        $fp = fopen($tmp, 'wb');
        if ($fp === false) {
            @unlink($tmp);
            throw new \RuntimeException('temp file open failed');
        }

        $ch = curl_init($artifactUrl);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_HTTPHEADER => [FleetRemoteAuth::HEADER . ': ' . $token],
            CURLOPT_TIMEOUT => 600,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $ok = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if ($ok === false || $code >= 400) {
            @unlink($tmp);
            throw new \RuntimeException('Artifact download HTTP ' . $code);
        }

        $sha256 = hash_file('sha256', $tmp) ?: '';
        $expected = (string) ($manifest['sha256'] ?? '');
        if ($expected !== '' && !hash_equals($expected, $sha256)) {
            @unlink($tmp);
            throw new \RuntimeException('Downloaded artifact sha256 mismatch');
        }

        $artifactDir = FleetSettings::artifactRoot() . '/' . $version;
        if (!is_dir($artifactDir)) {
            @mkdir($artifactDir, 0755, true);
        }
        $dest = $artifactDir . '/ms365-backup-worker';
        if (!@rename($tmp, $dest)) {
            if (!@copy($tmp, $dest)) {
                @unlink($tmp);
                throw new \RuntimeException('Failed to store pulled artifact');
            }
            @unlink($tmp);
        }
        @chmod($dest, 0755);

        $existing = ReleaseRepository::getByVersion($version);
        if ($existing !== null) {
            Capsule::table('ms365_worker_releases')->where('id', (int) $existing['id'])->update([
                'git_ref' => (string) ($manifest['git_ref'] ?? ''),
                'sha256' => $sha256,
                'artifact_path' => $dest,
                'artifact_size' => (int) filesize($dest),
            ]);
            $id = (int) $existing['id'];
        } else {
            $id = ReleaseRepository::create([
                'version' => $version,
                'git_ref' => (string) ($manifest['git_ref'] ?? ''),
                'sha256' => $sha256,
                'artifact_path' => $dest,
                'artifact_size' => (int) filesize($dest),
                'build_job_id' => null,
                'created_by_admin_id' => null,
                'notes' => 'Pulled from development release #' . $sourceId,
            ]);
        }
        BrowseBinaryInstaller::syncFromLatestRelease();

        return $id;
    }

    private static function recordSyncOutcome(?int $releaseId, string $status, string $detail): void
    {
        if (!Capsule::schema()->hasTable('ms365_worker_release_sync')) {
            return;
        }
        Capsule::table('ms365_worker_release_sync')->insert([
            'release_id' => $releaseId,
            'status' => $status,
            'detail' => mb_substr($detail, 0, 500),
            'synced_at' => time(),
        ]);
    }

    public static function autoPublishAfterBuild(int $releaseId): void
    {
        $auto = strtolower(trim(Ms365EngineConfig::moduleSettingPublic('ms365_auto_sync_release_to_prod', 'on')));
        if (in_array($auto, ['off', '0', 'no'], true)) {
            return;
        }
        if (!FleetContext::isDevelopmentServer() || FleetRemoteAuth::sharedToken() === '') {
            return;
        }
        try {
            self::publishToProduction($releaseId);
        } catch (\Throwable $e) {
            FleetAuditLog::write('release_sync_push_failed', $e->getMessage(), 'release', (string) $releaseId);
        }
    }
}
