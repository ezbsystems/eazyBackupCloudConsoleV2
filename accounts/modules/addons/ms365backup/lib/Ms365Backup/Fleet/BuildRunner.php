<?php
declare(strict_types=1);

namespace Ms365Backup\Fleet;

use Ms365Backup\Ms365EngineConfig;

final class BuildRunner
{
    /** @param array<string, mixed> $job */
    public function run(array $job): void
    {
        $jobId = (int) $job['id'];
        $version = trim((string) ($job['version_label'] ?? ''));
        if ($version === '') {
            throw new \RuntimeException('version_label required');
        }

        $repoPath = FleetSettings::repoPath();
        $goBin = FleetSettings::goBinary();
        $gitRef = trim((string) ($job['git_ref'] ?? 'main'));
        $logDir = BuildJobStore::jobLogDir($jobId);
        $artifactDir = FleetSettings::artifactRoot() . '/' . $version;
        $binaryName = 'ms365-backup-worker';
        $buildOut = $logDir . '/ms365-backup-worker';
        $publishedPath = $artifactDir . '/' . $binaryName;

        $runner = new ProcRunner();
        $runner->addSecret(Ms365EngineConfig::workerToken());

        $stepRows = BuildJobStore::steps($jobId);
        if ($stepRows === []) {
            throw new \RuntimeException('Build steps not initialized for job #' . $jobId);
        }

        $finalStatus = 'succeeded';
        $errMsg = null;
        $sha256 = '';
        $releaseId = null;
        $ranStep = false;

        foreach ($stepRows as $row) {
            $key = (string) $row['step_key'];
            if ($row['status'] === 'skipped') {
                continue;
            }
            $ranStep = true;
            $logPath = $logDir . '/' . $key . '.log';
            BuildJobStore::updateJob($jobId, ['current_step' => $key]);
            BuildJobStore::updateStep($jobId, $key, [
                'status' => 'running',
                'started_at' => time(),
                'log_path' => $logPath,
            ]);

            $rc = -1;
            $summary = '';
            try {
                switch ($key) {
                    case 'validate':
                        if (!is_dir($repoPath)) {
                            throw new \RuntimeException('Repo path not found: ' . $repoPath);
                        }
                        if (!is_executable($goBin) && !is_file($goBin)) {
                            throw new \RuntimeException('Go binary not found: ' . $goBin);
                        }
                        $rc = 0;
                        $summary = 'ok';
                        break;
                    case 'git_sync':
                        $rc = $runner->run(
                            ['git', 'fetch', '--all', '--prune'],
                            $logPath,
                            $repoPath
                        );
                        if ($rc === 0) {
                            $rc = $runner->run(
                                ['git', 'checkout', $gitRef],
                                $logPath,
                                $repoPath
                            );
                        }
                        if ($rc === 0) {
                            $rc = $runner->run(
                                ['git', 'pull', '--ff-only'],
                                $logPath,
                                $repoPath
                            );
                        }
                        $summary = $rc === 0 ? 'synced ' . $gitRef : 'git failed';
                        break;
                    case 'go_test':
                        $rc = $runner->run(
                            [$goBin, 'test', './...'],
                            $logPath,
                            $repoPath,
                            self::goEnv($repoPath)
                        );
                        $summary = $rc === 0 ? 'tests passed' : 'tests failed';
                        break;
                    case 'go_build':
                        $ldflags = '-X github.com/eazybackup/ms365-backup-worker/internal/version.Version=' . $version;
                        $rc = $runner->run(
                            [$goBin, 'build', '-ldflags', $ldflags, '-o', $buildOut, './cmd/worker'],
                            $logPath,
                            $repoPath,
                            self::goEnv($repoPath)
                        );
                        $summary = $rc === 0 ? 'built' : 'build failed';
                        break;
                    case 'checksum':
                        if (!is_file($buildOut)) {
                            throw new \RuntimeException('Build output missing');
                        }
                        $sha256 = hash_file('sha256', $buildOut);
                        $rc = 0;
                        $summary = substr($sha256, 0, 16) . '...';
                        break;
                    case 'publish':
                        if ($sha256 === '' && is_file($buildOut)) {
                            $sha256 = hash_file('sha256', $buildOut);
                        }
                        $existingRelease = ReleaseRepository::getByVersion($version);
                        if ($existingRelease !== null) {
                            throw new \RuntimeException(sprintf(
                                'Version %s is already published (release #%d)',
                                $version,
                                (int) $existingRelease['id']
                            ));
                        }
                        if (!is_dir($artifactDir)) {
                            @mkdir($artifactDir, 0755, true);
                        }
                        if (!@rename($buildOut, $publishedPath)) {
                            if (!@copy($buildOut, $publishedPath)) {
                                throw new \RuntimeException('Failed to publish artifact');
                            }
                            @unlink($buildOut);
                        }
                        @chmod($artifactDir, 0755);
                        @chmod($publishedPath, 0755);
                        $releaseId = ReleaseRepository::create([
                            'version' => $version,
                            'git_ref' => $gitRef,
                            'sha256' => $sha256,
                            'artifact_path' => $publishedPath,
                            'artifact_size' => (int) filesize($publishedPath),
                            'build_job_id' => $jobId,
                            'created_by_admin_id' => $job['created_by_admin_id'] ?? null,
                        ]);
                        ReleaseSyncService::autoPublishAfterBuild($releaseId);
                        $browseSync = BrowseBinaryInstaller::syncFromRelease($releaseId);
                        if (!$browseSync['ok']) {
                            logActivity('MS365 browse binary sync failed after build publish: ' . ($browseSync['error'] ?: 'unknown'));
                        }
                        $rc = 0;
                        $summary = 'release #' . $releaseId;
                        if ($browseSync['ok']) {
                            $summary .= $browseSync['skipped'] ? '; browse unchanged' : '; browse synced';
                        } else {
                            $summary .= '; browse sync failed';
                        }
                        break;
                    default:
                        throw new \RuntimeException('Unknown step: ' . $key);
                }
            } catch (\Throwable $e) {
                $rc = 1;
                $summary = $e->getMessage();
                file_put_contents($logPath, "\n[error] " . $e->getMessage() . "\n", FILE_APPEND);
            }

            BuildJobStore::updateStep($jobId, $key, [
                'status' => $rc === 0 ? 'succeeded' : 'failed',
                'exit_code' => $rc,
                'summary' => mb_substr($summary, 0, 500),
                'ended_at' => time(),
            ]);

            if ($rc !== 0) {
                $finalStatus = 'failed';
                $errMsg = $summary;
                break;
            }
        }

        if ($finalStatus === 'succeeded' && (!$ranStep || $releaseId === null)) {
            $finalStatus = 'failed';
            $errMsg = $ranStep
                ? 'Build finished without publishing a release'
                : 'Build finished without running any steps';
        }

        BuildJobStore::updateJob($jobId, [
            'status' => $finalStatus,
            'error_message' => $errMsg,
            'release_id' => $releaseId,
            'ended_at' => time(),
        ]);

        if ($finalStatus === 'succeeded') {
            FleetAuditLog::write('build_succeeded', 'Built worker ' . $version, 'release', (string) $releaseId);
        } else {
            FleetAuditLog::write('build_failed', 'Build failed: ' . (string) $errMsg, 'build_job', (string) $jobId);
            throw new \RuntimeException((string) $errMsg);
        }
    }

    /** @return array<string, string> */
    private static function goEnv(string $repoPath): array
    {
        $parent = dirname($repoPath);
        $env = self::inheritProcessEnv();

        foreach ([
            'GOCACHE' => $parent . '/.gocache',
            'GOMODCACHE' => $parent . '/.gomodcache',
            'GOTMPDIR' => $parent . '/.gotmp',
            'GOPATH' => $parent . '/.gopath',
        ] as $key => $path) {
            @mkdir($path, 0775, true);
            $env[$key] = $path;
        }

        if (!isset($env['HOME']) || trim((string) $env['HOME']) === '') {
            $env['HOME'] = $parent;
        }
        // Avoid auto-downloading a newer Go toolchain during WHMCS-triggered builds.
        $env['GOTOOLCHAIN'] = 'local';

        return $env;
    }

    /** @return array<string, string> */
    private static function inheritProcessEnv(): array
    {
        $env = [];
        foreach (['HOME', 'USER', 'LOGNAME', 'PATH', 'LANG', 'LC_ALL', 'GOPROXY', 'GOSUMDB', 'GOPRIVATE', 'GOTOOLCHAIN'] as $key) {
            $val = getenv($key);
            if ($val !== false && $val !== '') {
                $env[$key] = $val;
            }
        }
        if (!isset($env['PATH']) || trim($env['PATH']) === '') {
            $env['PATH'] = '/usr/local/bin:/usr/local/go/bin:/usr/bin:/bin';
        }

        return $env;
    }
}
