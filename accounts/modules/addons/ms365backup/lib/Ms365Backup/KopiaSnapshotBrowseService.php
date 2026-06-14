<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * Browse Kopia snapshot directories via ms365-backup-worker browse CLI.
 */
final class KopiaSnapshotBrowseService
{
    /**
     * @return list<array{name: string, path: string, type: string, has_children: bool, size: int}>
     */
    public static function listDirectory(
        array $tenantRecord,
        string $manifestId,
        string $path = '',
    ): array {
        $manifestId = trim($manifestId);
        if ($manifestId === '') {
            throw new \RuntimeException('manifest_id is required.');
        }

        $dest = self::resolveDestination($tenantRecord);
        $payload = [
            'manifest_id' => $manifestId,
            'path' => $path,
            'dest_endpoint' => $dest['endpoint'],
            'dest_region' => $dest['region'],
            'dest_bucket' => $dest['bucket'],
            'dest_prefix' => $dest['prefix'],
            'dest_access_key' => $dest['access_key'],
            'dest_secret_key' => $dest['secret_key'],
            'repo_password' => $dest['repo_password'],
            'repo_config' => sys_get_temp_dir() . '/ms365-browse-' . md5($manifestId) . '.config',
        ];

        $result = self::invokeBrowseCli($payload);
        $entries = $result['entries'] ?? [];
        if (!is_array($entries)) {
            return [];
        }

        $out = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $out[] = [
                'name' => (string) ($entry['name'] ?? ''),
                'label' => (string) ($entry['label'] ?? $entry['name'] ?? ''),
                'subtitle' => (string) ($entry['subtitle'] ?? ''),
                'path' => (string) ($entry['path'] ?? ''),
                'type' => (string) ($entry['type'] ?? 'file'),
                'has_children' => (bool) ($entry['has_children'] ?? false),
                'size' => (int) ($entry['size'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private static function invokeBrowseCli(array $payload): array
    {
        $binary = self::workerBinaryPath();
        if (!is_executable($binary)) {
            throw new \RuntimeException('MS365 backup worker binary is not available for snapshot browse.');
        }

        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $cmd = escapeshellarg($binary) . ' browse 2>&1';
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($proc)) {
            throw new \RuntimeException('Failed to start browse worker.');
        }
        fwrite($pipes[0], $json);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($proc);

        if ($exit !== 0) {
            throw new \RuntimeException('Browse failed: ' . trim($stderr !== '' ? $stderr : (string) $stdout));
        }

        $decoded = json_decode((string) $stdout, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Invalid browse worker response.');
        }

        return $decoded;
    }

    private static function workerBinaryPath(): string
    {
        $fromSetting = trim((string) (Ms365EngineConfig::moduleSettingPublic('ms365_worker_binary_path') ?? ''));
        if ($fromSetting !== '' && is_executable($fromSetting)) {
            return $fromSetting;
        }
        $candidates = [
            '/var/www/eazybackup.ca/ms365-backup-worker/ms365-backup-worker',
            '/usr/local/bin/ms365-backup-worker',
            '/tmp/ms365-backup-worker',
        ];
        foreach ($candidates as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        return '/tmp/ms365-backup-worker';
    }

    /** @return array{endpoint: string, region: string, bucket: string, prefix: string, access_key: string, secret_key: string, repo_password: string} */
    private static function resolveDestination(array $record): array
    {
        return WorkerClaimService::destinationForTenantRecord($record);
    }
}
