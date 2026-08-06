<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

use WHMCS\Database\Capsule;

/**
 * Client-area download catalog for e3 local backup agent artifacts.
 *
 * Reads the latest published rows from s3_agent_releases (maintained by Agent
 * Builds Publish) and returns stable URLs + version metadata for the download
 * drawer and enrollment token install commands.
 */
class AgentDownloadCatalog
{
    /** @var array<string, string> Catalog key => latest alias filename */
    public const ARTIFACT_FILENAMES = [
        'windows'          => 'e3-backup-agent-setup.exe',
        'linux_install_sh' => 'e3-backup-agent-linux-install.sh',
        'linux_deb'        => 'e3-backup-agent-linux.deb',
        'linux_binary'     => 'e3-backup-agent-linux',
    ];

    /**
     * Return download metadata for every known client artifact.
     *
     * @return array<string, array{url: string, filename: string, version_label: string|null, size_bytes: int|null, size_label: string|null}>
     */
    public static function forClientArea(): array
    {
        $releases = self::loadLatestReleases();
        $out = [];

        foreach (self::ARTIFACT_FILENAMES as $key => $filename) {
            $row = $releases[$filename] ?? null;
            $url = self::resolveUrl($row, $filename);
            $sizeBytes = $row && isset($row->size_bytes) ? (int) $row->size_bytes : null;

            $out[$key] = [
                'url'            => $url,
                'filename'       => $filename,
                'version_label'  => $row && !empty($row->version_label) ? (string) $row->version_label : null,
                'size_bytes'     => $sizeBytes > 0 ? $sizeBytes : null,
                'size_label'     => $sizeBytes > 0 ? self::formatSize($sizeBytes) : null,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, object> artifact_filename => release row
     */
    private static function loadLatestReleases(): array
    {
        $filenames = array_values(self::ARTIFACT_FILENAMES);
        if (!Capsule::schema()->hasTable('s3_agent_releases')) {
            return [];
        }

        try {
            $rows = Capsule::table('s3_agent_releases')
                ->where('is_latest', 1)
                ->whereIn('artifact_filename', $filenames)
                ->orderByDesc('id')
                ->get();
        } catch (\Throwable $e) {
            return [];
        }

        $byFilename = [];
        foreach ($rows as $row) {
            $name = (string) ($row->artifact_filename ?? '');
            if ($name !== '' && !isset($byFilename[$name])) {
                $byFilename[$name] = $row;
            }
        }

        return $byFilename;
    }

    private static function resolveUrl($row, string $filename): string
    {
        if ($row && !empty($row->download_url)) {
            return (string) $row->download_url;
        }

        return '/client_installer/' . $filename;
    }

    private static function formatSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        if ($bytes < 1024 * 1024 * 1024) {
            return round($bytes / (1024 * 1024), 1) . ' MB';
        }

        return round($bytes / (1024 * 1024 * 1024), 2) . ' GB';
    }
}
