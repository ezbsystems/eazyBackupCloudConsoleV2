<?php
declare(strict_types=1);

namespace Ms365Backup\Fleet;

/**
 * Keeps ms365-backup-worker browse CLI on the WHMCS host in sync with the latest fleet release.
 */
final class BrowseBinaryInstaller
{
    public static function syncFromLatestRelease(): bool
    {
        $latest = ReleaseRepository::latest();
        if ($latest === null) {
            return false;
        }
        $source = trim((string) ($latest['artifact_path'] ?? ''));
        if ($source === '' || !is_file($source) || !is_readable($source)) {
            return false;
        }
        $dest = rtrim(FleetSettings::repoPath(), '/') . '/ms365-backup-worker';
        $dir = dirname($dest);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return false;
        }
        if (@copy($source, $dest) || self::identicalExisting($source, $dest)) {
            @chmod($dest, 0755);
            return is_executable($dest);
        }

        return false;
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
}
