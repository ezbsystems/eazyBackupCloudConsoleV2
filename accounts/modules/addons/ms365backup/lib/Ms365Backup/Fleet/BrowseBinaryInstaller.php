<?php
declare(strict_types=1);

namespace Ms365Backup\Fleet;

/**
 * Keeps ms365-backup-worker browse CLI on the WHMCS host in sync with fleet releases.
 */
final class BrowseBinaryInstaller
{
    /**
     * @return array{ok: bool, skipped: bool, version: string, sha256: string, dest: string, error: string, hint: string, release_id: int, diagnostics?: array<string, mixed>}
     */
    public static function syncFromRelease(int $releaseId, bool $audit = true): array
    {
        $release = ReleaseRepository::get($releaseId);
        if ($release === null) {
            return self::finish($releaseId, self::result(
                false,
                false,
                '',
                '',
                self::destPath(),
                'Release not found',
                'Verify release id #' . $releaseId . ' exists in ms365_worker_releases',
                $releaseId
            ), $audit);
        }

        $version = (string) ($release['version'] ?? '');
        $expectedSha = (string) ($release['sha256'] ?? '');
        $source = trim((string) ($release['artifact_path'] ?? ''));
        $dest = self::destPath();

        if ($source === '' || !is_file($source) || !is_readable($source)) {
            $ok = is_executable($dest);
            $diagnostics = self::pathDiagnostics($dest);

            return self::finish($releaseId, self::result(
                $ok,
                false,
                $version,
                $expectedSha,
                $dest,
                $ok ? '' : 'Release artifact missing or unreadable and browse binary not installed',
                $ok ? '' : 'Restore artifact at ' . ($source !== '' ? $source : '(empty path)') . ' or run release sync cron',
                $releaseId,
                $diagnostics
            ), $audit);
        }

        $dir = dirname($dest);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            $diagnostics = self::pathDiagnostics($dest);

            return self::finish($releaseId, self::result(
                false,
                false,
                $version,
                $expectedSha,
                $dest,
                'Cannot create browse binary directory',
                self::chownHint($dir),
                $releaseId,
                $diagnostics
            ), $audit);
        }

        if (self::identicalExisting($source, $dest)) {
            @chmod($dest, 0755);
            self::ensureWebOwnership($dest);
            $installedSha = is_file($dest) ? (hash_file('sha256', $dest) ?: '') : $expectedSha;

            return self::finish($releaseId, self::result(
                is_executable($dest),
                true,
                $version,
                $installedSha !== '' ? $installedSha : $expectedSha,
                $dest,
                is_executable($dest) ? '' : 'Browse binary exists but is not executable',
                is_executable($dest) ? '' : 'Run: chmod 755 ' . $dest,
                $releaseId
            ), $audit);
        }

        $install = self::installArtifact($source, $dest);
        if (!$install['ok']) {
            return self::finish($releaseId, self::result(
                false,
                false,
                $version,
                $expectedSha,
                $dest,
                $install['error'],
                $install['hint'],
                $releaseId,
                $install['diagnostics']
            ), $audit);
        }

        @chmod($dest, 0755);
        self::ensureWebOwnership($dest);
        $installedSha = hash_file('sha256', $dest) ?: $expectedSha;

        return self::finish($releaseId, self::result(
            is_executable($dest),
            false,
            $version,
            $installedSha,
            $dest,
            is_executable($dest) ? '' : 'Browse binary copied but is not executable',
            is_executable($dest) ? '' : 'Run: chmod 755 ' . $dest,
            $releaseId
        ), $audit);
    }

    /**
     * @return array{ok: bool, skipped: bool, version: string, sha256: string, dest: string, error: string, hint: string, release_id: int, diagnostics?: array<string, mixed>}
     */
    public static function syncFromLatestRelease(): array
    {
        $latest = ReleaseRepository::latest();
        if ($latest === null) {
            return self::result(false, false, '', '', self::destPath(), 'No releases published', 'Publish a worker release first', 0);
        }

        return self::syncFromRelease((int) ($latest['id'] ?? 0));
    }

    /**
     * @return array{ok: bool, skipped: bool, version: string, sha256: string, dest: string, error: string, hint: string, release_id: int, diagnostics?: array<string, mixed>}
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
     * @return array{status: string, installed_version: ?string, installed_sha256: ?string, target_version: ?string, target_sha256: ?string, target_release_id: ?int, dest: string, executable: bool, diagnostics?: array<string, mixed>}
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

        $out = [
            'status' => $status,
            'installed_version' => $installedVersion,
            'installed_sha256' => $installedSha,
            'target_version' => $targetVersion !== '' ? $targetVersion : null,
            'target_sha256' => $targetSha !== '' ? $targetSha : null,
            'target_release_id' => $targetReleaseId > 0 ? $targetReleaseId : null,
            'dest' => $dest,
            'executable' => $executable,
        ];
        if ($status !== 'synced') {
            $out['diagnostics'] = self::pathDiagnostics($dest);
            $out['hint'] = self::chownHint(dirname($dest));
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public static function pathDiagnostics(string $dest): array
    {
        $dest = trim($dest);
        $dir = $dest !== '' ? dirname($dest) : FleetSettings::repoPath();
        $phpUser = self::phpUser();

        return [
            'dest' => $dest,
            'dest_exists' => $dest !== '' && is_file($dest),
            'dest_writable' => $dest !== '' && is_file($dest) && is_writable($dest),
            'dest_owner' => $dest !== '' && is_file($dest) ? self::pathOwner($dest) : null,
            'dest_mode' => $dest !== '' && is_file($dest) ? substr(sprintf('%o', fileperms($dest)), -4) : null,
            'dir' => $dir,
            'dir_exists' => is_dir($dir),
            'dir_writable' => is_dir($dir) && is_writable($dir),
            'dir_owner' => is_dir($dir) ? self::pathOwner($dir) : null,
            'php_user' => $phpUser,
            'can_install' => self::canInstallTo($dest),
        ];
    }

    /**
     * @return array{ok: bool, skipped: bool, version: string, sha256: string, dest: string, error: string, hint: string, release_id: int, reconciled: bool, diagnostics?: array<string, mixed>}
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
                '',
                (int) ($current['target_release_id'] ?? 0)
            ) + ['reconciled' => false];
        }

        $sync = self::syncFromFleetTarget();

        return $sync + ['reconciled' => true];
    }

    public static function installedMatchesArtifact(string $source, string $dest): bool
    {
        return self::identicalExisting($source, $dest);
    }

    public static function repoPath(): string
    {
        return rtrim(FleetSettings::repoPath(), '/');
    }

    private static function destPath(): string
    {
        return self::repoPath() . '/ms365-backup-worker';
    }

    /**
     * @return array{ok: bool, error: string, hint: string, diagnostics: array<string, mixed>}
     */
    private static function installArtifact(string $source, string $dest): array
    {
        $diagnostics = self::pathDiagnostics($dest);
        if (!self::canInstallTo($dest)) {
            $error = 'Cannot write browse binary';
            if (is_file($dest) && !is_writable($dest)) {
                $error = 'Destination exists but is not writable by PHP user ' . self::phpUser();
            } elseif (!is_writable(dirname($dest))) {
                $error = 'Browse binary directory is not writable by PHP user ' . self::phpUser();
            }

            return [
                'ok' => false,
                'error' => $error,
                'hint' => self::chownHint(dirname($dest)),
                'diagnostics' => $diagnostics,
            ];
        }

        // Stage beside the destination, then rename into place. In-place copy() fails with
        // ETXTBSY when restore browse is running the current binary.
        $staging = self::stagingPath($dest);
        @unlink($staging);

        if (!@copy($source, $staging)) {
            $last = error_get_last();
            $copyDetail = is_array($last) ? (string) ($last['message'] ?? '') : '';
            @unlink($staging);

            return [
                'ok' => false,
                'error' => 'Failed to stage browse binary' . ($copyDetail !== '' ? ': ' . $copyDetail : ''),
                'hint' => self::installFailureHint($copyDetail, dirname($dest)),
                'diagnostics' => $diagnostics,
            ];
        }

        @chmod($staging, 0755);
        self::ensureWebOwnership($staging);

        if (!@rename($staging, $dest)) {
            $last = error_get_last();
            $renameDetail = is_array($last) ? (string) ($last['message'] ?? '') : '';
            @unlink($staging);

            return [
                'ok' => false,
                'error' => 'Failed to activate browse binary' . ($renameDetail !== '' ? ': ' . $renameDetail : ''),
                'hint' => self::installFailureHint($renameDetail, dirname($dest)),
                'diagnostics' => $diagnostics,
            ];
        }

        return ['ok' => true, 'error' => '', 'hint' => '', 'diagnostics' => $diagnostics];
    }

    private static function stagingPath(string $dest): string
    {
        return $dest . '.new';
    }

    private static function installFailureHint(string $detail, string $dir): string
    {
        $lower = strtolower($detail);
        if (str_contains($lower, 'text file busy') || str_contains($lower, 'device or resource busy')) {
            return 'A browse worker may still be running — check: pgrep -af ms365-backup-worker';
        }
        if (str_contains($lower, 'permission denied') || str_contains($lower, 'not writable')) {
            return self::chownHint($dir);
        }

        return self::chownHint($dir);
    }

    private static function canInstallTo(string $dest): bool
    {
        $dir = dirname($dest);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return false;
        }
        if (is_file($dest)) {
            return is_writable($dest);
        }

        return is_writable($dir);
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
     * @param array{ok: bool, skipped: bool, version: string, sha256: string, dest: string, error: string, hint: string, release_id: int, diagnostics?: array<string, mixed>} $result
     * @return array{ok: bool, skipped: bool, version: string, sha256: string, dest: string, error: string, hint: string, release_id: int, diagnostics?: array<string, mixed>}
     */
    private static function finish(int $releaseId, array $result, bool $audit): array
    {
        if ($audit) {
            self::audit($releaseId, $result);
        }

        return $result;
    }

    /**
     * @param array{ok: bool, skipped: bool, version: string, sha256: string, dest: string, error: string, hint: string, release_id: int, diagnostics?: array<string, mixed>} $result
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
        if (!$result['ok'] && ($result['hint'] ?? '') !== '') {
            $message .= ' — ' . $result['hint'];
        }

        $context = [
            'dest' => $result['dest'],
            'sha256' => $result['sha256'],
            'skipped' => $result['skipped'],
            'error' => $result['error'],
            'hint' => $result['hint'] ?? '',
            'php_user' => self::phpUser(),
        ];
        if (isset($result['diagnostics']) && is_array($result['diagnostics'])) {
            $context['diagnostics'] = $result['diagnostics'];
        }

        FleetAuditLog::write(
            $result['ok'] ? 'browse_binary_synced' : 'browse_binary_sync_failed',
            $message,
            'release',
            (string) ($releaseId > 0 ? $releaseId : ($result['release_id'] ?? 0)),
            $context
        );
    }

    private static function pathOwner(string $path): ?string
    {
        if (!is_file($path) && !is_dir($path)) {
            return null;
        }
        $uid = @fileowner($path);
        if ($uid === false) {
            return null;
        }
        if (function_exists('posix_getpwuid')) {
            $pw = posix_getpwuid((int) $uid);

            return is_array($pw) ? (string) ($pw['name'] ?? $uid) : (string) $uid;
        }

        return 'uid:' . $uid;
    }

    private static function phpUser(): string
    {
        $user = get_current_user();
        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $pw = posix_getpwuid(posix_geteuid());
            if (is_array($pw) && !empty($pw['name'])) {
                return (string) $pw['name'];
            }
        }

        return $user !== '' ? $user : 'unknown';
    }

    private static function chownHint(string $dir): string
    {
        $webUser = self::webUser();

        return 'chown -R ' . $webUser . ':' . $webUser . ' ' . $dir
            . ' (PHP runs as ' . self::phpUser() . ')';
    }

    private static function webUser(): string
    {
        return 'www-data';
    }

    /** Root-owned browse binaries block WHMCS (www-data) from future fleet syncs. */
    private static function ensureWebOwnership(string $path): void
    {
        if (!is_file($path) || !function_exists('posix_getpwnam') || !function_exists('posix_getgrnam')) {
            return;
        }
        $webUser = self::webUser();
        $pw = posix_getpwnam($webUser);
        $gr = posix_getgrnam($webUser);
        if (!is_array($pw) || !is_array($gr)) {
            return;
        }
        $uid = (int) ($pw['uid'] ?? -1);
        $gid = (int) ($gr['gid'] ?? -1);
        if ($uid < 0 || $gid < 0) {
            return;
        }
        $owner = @fileowner($path);
        if ($owner === false || (int) $owner === $uid) {
            return;
        }
        @chown($path, $uid);
        @chgrp($path, $gid);
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
     * @param array<string, mixed> $diagnostics
     * @return array{ok: bool, skipped: bool, version: string, sha256: string, dest: string, error: string, hint: string, release_id: int, diagnostics?: array<string, mixed>}
     */
    private static function result(
        bool $ok,
        bool $skipped,
        string $version,
        string $sha256,
        string $dest,
        string $error,
        string $hint,
        int $releaseId = 0,
        array $diagnostics = [],
    ): array {
        $out = [
            'ok' => $ok,
            'skipped' => $skipped,
            'version' => $version,
            'sha256' => $sha256,
            'dest' => $dest,
            'error' => $error,
            'hint' => $hint,
            'release_id' => $releaseId,
        ];
        if ($diagnostics !== []) {
            $out['diagnostics'] = $diagnostics;
        }

        return $out;
    }
}
