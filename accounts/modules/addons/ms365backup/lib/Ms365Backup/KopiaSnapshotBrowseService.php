<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * Browse Kopia snapshot directories via ms365-backup-worker browse CLI.
 */
final class KopiaSnapshotBrowseService
{
    /**
     * @return array{entries: list<array<string, mixed>>, total_count: int, has_more: bool, offset: int, limit: int, warnings?: list<string>}
     */
    public static function listDirectory(
        array $tenantRecord,
        string $manifestId,
        string $path = '',
        ?string $e3JobId = null,
        int $limit = 0,
        int $offset = 0,
    ): array {
        $manifestId = trim($manifestId);
        if ($manifestId === '') {
            throw new \RuntimeException('manifest_id is required.');
        }

        $dest = self::resolveDestination($tenantRecord, $e3JobId);
        $payload = [
            'manifest_id' => $manifestId,
            'path' => $path,
            'limit' => $limit,
            'offset' => $offset,
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

        return self::normalizeBrowseResult($result, $offset, $limit);
    }

    /**
     * Union multiple shard manifests in one browse session (requires worker >= multiSourceBrowseMinWorkerVersion).
     *
     * @param list<array{child_run_id?: string, manifest_id: string, candidate_paths?: list<string>}> $sources
     * @return array{entries: list<array<string, mixed>>, total_count: int, has_more: bool, offset: int, limit: int, warnings?: list<string>}
     */
    public static function listDirectoryMultiSource(
        array $tenantRecord,
        string $logicalPath,
        array $sources,
        ?string $e3JobId = null,
        int $limit = 0,
        int $offset = 0,
    ): array {
        if ($sources === []) {
            throw new \RuntimeException('browse sources are required.');
        }

        $logicalPath = trim($logicalPath, '/');
        $dest = self::resolveDestination($tenantRecord, $e3JobId);
        $primaryManifest = trim((string) ($sources[0]['manifest_id'] ?? ''));
        if ($primaryManifest === '') {
            throw new \RuntimeException('manifest_id is required.');
        }

        $browseSources = [];
        foreach ($sources as $source) {
            if (!is_array($source)) {
                continue;
            }
            $manifestId = trim((string) ($source['manifest_id'] ?? ''));
            if ($manifestId === '') {
                continue;
            }
            $candidatePaths = [];
            foreach (($source['candidate_paths'] ?? []) as $candidate) {
                if (is_string($candidate) && trim($candidate) !== '') {
                    $candidatePaths[] = trim($candidate, '/');
                }
            }
            $browseSources[] = [
                'child_run_id' => (string) ($source['child_run_id'] ?? ''),
                'manifest_id' => $manifestId,
                'candidate_paths' => $candidatePaths,
            ];
        }
        if ($browseSources === []) {
            throw new \RuntimeException('browse sources are required.');
        }

        $payload = [
            'manifest_id' => $primaryManifest,
            'path' => $logicalPath,
            'sources' => $browseSources,
            'limit' => $limit,
            'offset' => $offset,
            'dest_endpoint' => $dest['endpoint'],
            'dest_region' => $dest['region'],
            'dest_bucket' => $dest['bucket'],
            'dest_prefix' => $dest['prefix'],
            'dest_access_key' => $dest['access_key'],
            'dest_secret_key' => $dest['secret_key'],
            'repo_password' => $dest['repo_password'],
            'repo_config' => sys_get_temp_dir() . '/ms365-browse-' . md5($primaryManifest) . '.config',
        ];

        $result = self::invokeBrowseCli($payload);

        return self::normalizeBrowseResult($result, $offset, $limit);
    }

    public static function supportsMultiSourceBrowse(): bool
    {
        $minVersion = Ms365EngineConfig::multiSourceBrowseMinWorkerVersion();
        if ($minVersion === '') {
            return false;
        }

        $status = Fleet\BrowseBinaryInstaller::status();
        $installed = trim((string) ($status['installed_version'] ?? ''));
        if ($installed !== '') {
            return Fleet\ReleaseRepository::compareVersions($installed, $minVersion) >= 0;
        }

        $target = trim((string) ($status['target_version'] ?? ''));
        if ($target !== '' && Fleet\ReleaseRepository::compareVersions($target, $minVersion) >= 0) {
            return is_executable(self::workerBinaryPath());
        }

        return false;
    }

    /**
     * @param array<string, mixed> $result
     * @return array{entries: list<array<string, mixed>>, total_count: int, has_more: bool, offset: int, limit: int, warnings?: list<string>}
     */
    private static function normalizeBrowseResult(array $result, int $offset, int $limit): array
    {
        $entries = $result['entries'] ?? [];
        if (!is_array($entries)) {
            $entries = [];
        }

        $out = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $normalized = [
                'name' => (string) ($entry['name'] ?? ''),
                'label' => (string) ($entry['label'] ?? $entry['name'] ?? ''),
                'subtitle' => (string) ($entry['subtitle'] ?? ''),
                'path' => (string) ($entry['path'] ?? ''),
                'type' => (string) ($entry['type'] ?? 'file'),
                'has_children' => (bool) ($entry['has_children'] ?? false),
                'size' => (int) ($entry['size'] ?? 0),
            ];
            if (isset($entry['source_refs']) && is_array($entry['source_refs'])) {
                $refs = [];
                foreach ($entry['source_refs'] as $ref) {
                    if (!is_array($ref)) {
                        continue;
                    }
                    $refs[] = [
                        'child_run_id' => (string) ($ref['child_run_id'] ?? ''),
                        'manifest_id' => (string) ($ref['manifest_id'] ?? ''),
                        'source_path' => (string) ($ref['source_path'] ?? ''),
                    ];
                }
                if ($refs !== []) {
                    $normalized['source_refs'] = $refs;
                }
            }
            if (array_key_exists('selectable', $entry)) {
                $normalized['selectable'] = (bool) $entry['selectable'];
            }

            $out[] = $normalized;
        }

        $response = [
            'entries' => $out,
            'total_count' => (int) ($result['total_count'] ?? count($out)),
            'has_more' => (bool) ($result['has_more'] ?? false),
            'offset' => (int) ($result['offset'] ?? $offset),
            'limit' => (int) ($result['limit'] ?? $limit),
        ];
        if (isset($result['warnings']) && is_array($result['warnings']) && $result['warnings'] !== []) {
            $response['warnings'] = array_values(array_map('strval', $result['warnings']));
        }

        return $response;
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
            $e = new \RuntimeException('Browse failed: ' . trim($stderr !== '' ? $stderr : (string) $stdout));
            Ms365CustomerError::log('kopia_browse_cli', $e);
            throw $e;
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

        $candidates = [];
        $repoPath = Fleet\FleetSettings::repoPath();
        if ($repoPath !== '') {
            $candidates[] = rtrim($repoPath, '/') . '/ms365-backup-worker';
        }
        $artifactRoot = Fleet\FleetSettings::artifactRoot();
        if ($artifactRoot !== '') {
            $latest = Fleet\ReleaseRepository::latest();
            $artifactPath = trim((string) ($latest['artifact_path'] ?? ''));
            if ($artifactPath !== '') {
                $candidates[] = $artifactPath;
            }
            $version = trim((string) ($latest['version'] ?? ''));
            if ($version !== '') {
                $candidates[] = rtrim($artifactRoot, '/') . '/' . $version . '/ms365-backup-worker';
            }
        }
        $candidates = array_merge($candidates, [
            '/var/www/eazybackup.ca/ms365-backup-worker/ms365-backup-worker',
            '/usr/local/bin/ms365-backup-worker',
            '/tmp/ms365-backup-worker',
        ]);

        foreach (array_values(array_unique($candidates)) as $path) {
            if ($path !== '' && is_executable($path)) {
                return $path;
            }
        }

        return '/tmp/ms365-backup-worker';
    }

    /** @return array{endpoint: string, region: string, bucket: string, prefix: string, access_key: string, secret_key: string, repo_password: string} */
    private static function resolveDestination(array $record, ?string $e3JobId = null): array
    {
        if ($e3JobId !== null && $e3JobId !== '') {
            return Ms365JobDestinationService::resolveForJobId($e3JobId, $record);
        }

        return Ms365JobDestinationService::resolveLegacyTenantBucket($record);
    }
}
