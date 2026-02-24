<?php

namespace WHMCS\Module\Addon\CloudStorage\Client;

/**
 * Routes retention policy execution between cloud object-layout path and
 * Kopia repo-native path. Ensures applyRetentionPolicy() is never used for
 * Local Agent or Kopia-family jobs.
 */
class KopiaRetentionRoutingService
{
    /** @var string[] Cloud source types eligible for object-prefix retention */
    private const CLOUD_SOURCE_TYPES = [
        'aws',
        's3_compatible',
        'sftp',
        'google_drive',
        'dropbox',
        'smb',
        'nas',
    ];

    /** @var string[] Engines that use Kopia/repo-native retention (excluded from object delete) */
    private const KOPIA_FAMILY_ENGINES = [
        'kopia',
        'disk_image',
        'hyperv',
    ];

    /**
     * Returns true if the job is eligible for cloud object retention
     * (applyRetentionPolicy / object-prefix deletion). Returns false for
     * local_agent and Kopia-family engines.
     *
     * @param array $job Job row (source_type, engine, etc.)
     * @return bool
     */
    public static function isCloudObjectRetentionJob(array $job): bool
    {
        $sourceType = strtolower(trim((string) ($job['source_type'] ?? '')));
        $engine = strtolower(trim((string) ($job['engine'] ?? 'sync')));

        if ($sourceType === 'local_agent') {
            return false;
        }

        if (in_array($engine, self::KOPIA_FAMILY_ENGINES, true)) {
            return false;
        }

        return in_array($sourceType, self::CLOUD_SOURCE_TYPES, true);
    }

    /**
     * Cloud source types eligible for object-prefix retention (for query filtering).
     *
     * @return string[]
     */
    public static function getCloudSourceTypes(): array
    {
        return self::CLOUD_SOURCE_TYPES;
    }

    /**
     * Kopia-family engines excluded from object-prefix retention (for query filtering).
     *
     * @return string[]
     */
    public static function getKopiaFamilyEngines(): array
    {
        return self::KOPIA_FAMILY_ENGINES;
    }
}
