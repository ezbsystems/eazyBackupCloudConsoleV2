<?php
declare(strict_types=1);

namespace Ms365Backup\Fleet;

/**
 * Keeps ms365-backup-worker browse CLI on the WHMCS host in sync with fleet releases.
 */
final class BrowseBinaryInstaller
{
    /**
     * @return array{ok: bool, skipped: bool, version: string, sha256: string, dest: string, error: string, release_id: int}
     */
    public static function syncFromRelease(int $releaseId, bool $audit = true): array
    {
        $release = ReleaseRepository::get($releaseId);
        if ($release === null) {
            $result = self::result(false, false, '', '', self::destPath(), 'Release not found', $releaseId);
            if ($audit) {
                self::audit($releaseId, $result);
            }

            return $result;
        }

        $version = (string) ($release['version'] ?? '');
        $expectedSha = (string) ($release['sha256'] ?? '');
        $source = trim((string) ($release['artifact_path'] ?? ''));
        $dest = self::destPath();

        if ($source === '' || !is_file($source) || !is_readable($source)) {
            $ok = is_executable($dest);
            $result = self::result(
                $ok,
                false,
                $version,
                $expectedSha,
                $dest,
                $ok ? '' : 'Release artifact missing or unreadable and browse binary not installed',
                $releaseId
            );
            if ($audit) {
                self::audit($releaseId, $result);
            }

            return $result;
        }

        $dir = dirname($dest);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            $result = self::result(false, false, $version, $expectedSha, $dest, 'Cannot create browse binary directory', $releaseId);
            if ($audit) {
                self::audit($releaseId, $result);
            }

            return $result;
        }

        if (self::identicalExisting($source, $dest)) {
            @chmod($dest, 0755);
            $installedSha = is_file($dest) ? (hash_file('sha256', $dest) ?: '') : $expectedSha;
            $result = self::result(
                is_executable($dest),
                true,
                $version,
                $installedSha !== '' ? $installedSha : $expectedSha,
                $dest,
                is_executable($dest) ? '' : 'Browse binary exists but is not executable',
                $releaseId
            );
            if ($audit) {
                self::audit($releaseId, $result);
            }

            return $result;
        }

        if (!self::installArtifact($source, $dest)) {
            $result = self::result(false, false, $version, $expectedSha, $dest, 'Failed to copy browse binary', $releaseId);
            if ($audit) {
                self::audit($releaseId, $result);
            }

            return $result;
        }

        @chmod($dest, 0755);
        $installedSha = hash_file('sha256', $dest) ?: $expectedSha;
        $result = self::result(
            is_executable($dest),
            false,
            $version,
            $installedSha,
            $dest,
            is_executable($dest) ? '' : 'Browse binary copied but is not executable',
            $releaseId
        );
        if ($audit) {
            self::audit($releaseId, $result);
        }

        return $result;
    }

    /**
     * @return array{ok: bool, skipped: bool, version: string, sha256: string, dest: string, error: string, release_id: int}
     */
    public static function syncFromLatestRelease(): array
    {
        $latest = ReleaseRepository::latest();
        if ($latest === null) {
            return self::result(false, false, '', '', self::destPath(), 'No releases published', 0);
        }

        return self::syncFromRelease((int) ($latest['id'] ?? 0));
    }

    /**
     * @return array{ok: bool, skipped: bool, version: string, sha256: string, dest: string, error: string, release_id: int}
     */
    public static function syncFromFleetTarget(): array
    {
        $state = FleetStateRepository::get();
        $releaseId = (int) ($state['target_release_id'] ?? 0);
        if ($releaseId > 0) {
            return self::syncFromRelease($releaseId);
        }

        return self::syncFromLatestRelease();
    }

    /**
     * @return array{status: string, installed_version: ?string, installed_sha256: ?string, target_version: ?string, target_sha256: ?string, target_release_id: ?int, dest: string, executable: bool}
     */
    public static function status(?int $releaseId = null): array
    {
        $dest = self::destPath();
        $targetRelease = null;
        if ($releaseId !== null && $releaseId > 0) {
            $targetRelease = ReleaseRepository::get($releaseId);
        } else {
            $state = FleetStateRepository::get();
            $targetId = (int) ($state['target_release_id'] ?? 0);
            if ($targetId > 0) {
                $targetRelease = ReleaseRepository::get($targetId);
            }
            if ($targetRelease === null) {
                $targetRelease = ReleaseRepository::latest();
            }
        }

        $targetVersion = $targetRelease !== null ? (string) ($targetRelease['version'] ?? '') : null;
        $targetSha = $targetRelease !== null ? (string) ($targetRelease['sha256'] ?? '') : null;
        $targetReleaseId = $targetRelease !== null ? (int) ($targetRelease['id'] ?? 0) : null;
        $executable = is_executable($dest);
        $installedSha = is_file($dest) ? (hash_file('sha256', $dest) ?: null) : null;
        $installedVersion = self::resolveInstalledVersion($installedSha, $targetRelease);

        $status = 'missing';
        if ($executable && $installedSha !== null && $targetSha !== null && hash_equals($targetSha, $installedSha)) {
            $status = 'synced';
        } elseif ($executable && $installedSha !== null) {
            $status = 'out_of_date';
        } elseif ($executable) {
            $status = 'out_of_date';
        }

        return [
            'status' => $status,
            'installed_version' => $installedVersion,
            'installed_sha256' => $installedSha,
            'target_version' => $targetVersion !== '' ? $targetVersion : null,
            'target_sha256' => $targetSha !== '' ? $targetSha : null,
            'target_release_id' => $targetReleaseId > 0 ? $targetReleaseId : null,
            'dest' => $dest,
            'executable' => $executable,
        ];
    }

    /**
     * @return array{ok: bool, skipped: bool, version: string, sha256: string, dest: string, error: string, release_id: int, reconciled: bool}
     */
    public static function reconcileIfNeeded(): array
    {
        $current = self::status();
        if ($current['status'] === 'synced') {
            return self::result(
                true,
                true,
                (string) ($current['installed_version'] ?? ''),
                (string) ($current['installed_sha256'] ?? ''),
                (string) $current['dest'],
                '',
                (int) ($current['target_release_id'] ?? 0)
            ) + ['reconciled' => false];
        }

        $sync = self::syncFromFleetTarget();

        return $sync + ['reconciled' => true];
    }

    private static function destPath(): string
    {
        return rtrim(FleetSettings::repoPath(), '/') . '/ms365-backup-worker';
    }

    private static function installArtifact(string $source, string $dest): bool
    {
        if (@copy($source, $dest)) {
            return true;
        }

        return self::identicalExisting($source, $dest);
    }

    public static function installedMatchesArtifact(string $source, string $dest): bool
    {
        return self::identicalExisting($source, $dest);
    }

    private static function identicalExisting(string $source, string $dest): bool
    {
        if (!is_file($dest)) {
            return false;
        }
        $srcHash = hash_file('sha256', $source);
        $dstHash = hash_file('sha256', $dest);

        return $srcHash !== false && $srcHash === $dstHash;
    }

    /**
     * @param array{ok: bool, skipped: bool, version: string, sha256: string, dest: string, error: string, release_id: int} $result
     */
    private static function audit(int $releaseId, array $result): void
    {
        $version = $result['version'] !== '' ? $result['version'] : 'unknown';
        $message = 'Browse binary v' . $version;
        if ($result['skipped']) {
            $message .= ' (unchanged)';
        }
        if (!$result['ok'] && $result['error'] !== '') {
            $message .= ': ' . $result['error'];
        }

        FleetAuditLog::write(
            $result['ok'] ? 'browse_binary_synced' : 'browse_binary_sync_failed',
            $message,
            'release',
            (string) ($releaseId > 0 ? $releaseId : ($result['release_id'] ?? 0)),
            [
                'dest' => $result['dest'],
                'sha256' => $result['sha256'],
                'skipped' => $result['skipped'],
                'error' => $result['error'],
            ]
        );
    }

    /**
     * @param array<string, mixed>|null $targetRelease
     */
    private static function resolveInstalledVersion(?string $installedSha, ?array $targetRelease): ?string
    {
        if ($installedSha === null) {
            return null;
        }
        if ($targetRelease !== null && hash_equals((string) ($targetRelease['sha256'] ?? ''), $installedSha)) {
            return (string) ($targetRelease['version'] ?? null) ?: null;
        }

        return null;
    }

    /**
     * @return array{ok: bool, skipped: bool, version: string, sha256: string, dest: string, error: string, release_id: int}
     */
    private static function result(
        bool $ok,
        bool $skipped,
        string $version,
        string $sha256,
        string $dest,
        string $error,
        int $releaseId = 0,
    ): array {
        return [
            'ok' => $ok,
            'skipped' => $skipped,
            'version' => $version,
            'sha256' => $sha256,
            'dest' => $dest,
            'error' => $error,
            'release_id' => $releaseId,
        ];
    }
}
