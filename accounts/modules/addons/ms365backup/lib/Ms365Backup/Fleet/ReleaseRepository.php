<?php
declare(strict_types=1);

namespace Ms365Backup\Fleet;

use WHMCS\Database\Capsule;

final class ReleaseRepository
{
    public static function create(array $data): int
    {
        $now = time();

        return (int) Capsule::table('ms365_worker_releases')->insertGetId([
            'version' => (string) $data['version'],
            'git_ref' => (string) ($data['git_ref'] ?? ''),
            'sha256' => (string) $data['sha256'],
            'artifact_path' => (string) $data['artifact_path'],
            'artifact_size' => (int) ($data['artifact_size'] ?? 0),
            'build_job_id' => isset($data['build_job_id']) ? (int) $data['build_job_id'] : null,
            'created_by_admin_id' => isset($data['created_by_admin_id']) ? (int) $data['created_by_admin_id'] : null,
            'notes' => $data['notes'] ?? null,
            'created_at' => $now,
        ]);
    }

    public static function get(int $id): ?array
    {
        $row = Capsule::table('ms365_worker_releases')->where('id', $id)->first();

        return $row ? (array) $row : null;
    }

    public static function getByVersion(string $version): ?array
    {
        $row = Capsule::table('ms365_worker_releases')->where('version', $version)->first();

        return $row ? (array) $row : null;
    }

    public static function latest(): ?array
    {
        $row = Capsule::table('ms365_worker_releases')->orderByDesc('id')->first();

        return $row ? (array) $row : null;
    }

    /** @return list<array<string, mixed>> */
    public static function listRecent(int $limit = 25): array
    {
        return Capsule::table('ms365_worker_releases')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(static fn ($r) => (array) $r)
            ->all();
    }

    public static function compareVersions(string $a, string $b): int
    {
        return version_compare($a, $b);
    }

    public static function validateVersionLabel(string $version): void
    {
        $version = trim($version);
        if ($version === '' || !preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            throw new \RuntimeException('version_label must be three-part semver (e.g. 0.1.18), not ' . ($version === '' ? 'empty' : $version));
        }
    }

    public static function assertVersionAvailable(string $version): void
    {
        self::validateVersionLabel($version);
        $existing = self::getByVersion($version);
        if ($existing === null) {
            return;
        }
        $next = self::suggestNextVersion();
        throw new \RuntimeException(sprintf(
            'Version %s is already published (release #%d). Use the next version (e.g. %s) or delete the existing release first.',
            $version,
            (int) $existing['id'],
            $next
        ));
    }

    /** Suggest next patch from latest release (0.1.17 -> 0.1.18). */
    public static function suggestNextVersion(): string
    {
        $latest = self::latest();
        if ($latest === null || trim((string) ($latest['version'] ?? '')) === '') {
            return '0.1.1';
        }
        $parts = explode('.', (string) $latest['version']);
        if (count($parts) !== 3) {
            return (string) $latest['version'];
        }
        $parts[2] = (string) ((int) $parts[2] + 1);

        return implode('.', $parts);
    }

    public static function nodeMatchesTarget(string $nodeVersion, string $targetVersion): bool
    {
        if ($targetVersion === '' || $nodeVersion === '') {
            return false;
        }

        return self::compareVersions($nodeVersion, $targetVersion) === 0;
    }

    public static function nodeAheadOfTarget(string $nodeVersion, string $targetVersion): bool
    {
        if ($targetVersion === '' || $nodeVersion === '') {
            return false;
        }

        return self::compareVersions($nodeVersion, $targetVersion) > 0;
    }

    public static function nodeNeedsUpdate(string $nodeVersion, string $targetVersion): bool
    {
        if ($targetVersion === '') {
            return false;
        }
        if ($nodeVersion === '') {
            return true;
        }

        return self::compareVersions($nodeVersion, $targetVersion) < 0;
    }
}
