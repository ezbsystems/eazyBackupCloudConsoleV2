<?php
declare(strict_types=1);

namespace Ms365Backup\Comet;

/**
 * Extracts Office 365 selection from a Comet user profile (CUSTOM_SETTINGV2).
 */
final class CometOffice365SelectionParser
{
    public const ENGINE = 'engine1/winmsofficemail';

    /**
     * @param array<string, mixed> $profile
     * @return array{
     *   source_guid: string,
     *   source_guids: list<string>,
     *   source_count: int,
     *   description: string,
     *   organization: bool,
     *   whole_org: bool,
     *   backup_options: array<string, int>,
     *   member_backup_options: array<string, int>,
     *   local_timezone: string,
     *   merged: bool
     * }
     */
    public static function parseProfile(array $profile, bool $mergeAllSources = false): array
    {
        $sources = $profile['Sources'] ?? null;
        if (!is_array($sources) || $sources === []) {
            throw new \InvalidArgumentException('Comet profile has no Sources.');
        }

        $officeSources = [];
        foreach ($sources as $guid => $row) {
            if (!is_array($row)) {
                continue;
            }
            if ((string) ($row['Engine'] ?? '') !== self::ENGINE) {
                continue;
            }
            $officeSources[(string) $guid] = $row;
            if (!$mergeAllSources) {
                break;
            }
        }

        if ($officeSources === []) {
            throw new \InvalidArgumentException(
                'Comet profile has no Protected Item with Engine ' . self::ENGINE
            );
        }

        $sourceGuids = array_keys($officeSources);
        $backupOptions = [];
        $memberBackupOptions = [];
        $organization = false;
        $wholeOrg = false;
        $descriptions = [];

        foreach ($officeSources as $guid => $source) {
            $props = is_array($source['EngineProps'] ?? null) ? $source['EngineProps'] : [];
            $settings = self::decodeCustomSetting($props['CUSTOM_SETTINGV2'] ?? null);
            $organization = $organization || (bool) ($settings['Organization'] ?? false);
            $wholeOrg = $wholeOrg || (bool) ($settings['WholeOrg'] ?? false);
            $backupOptions = self::mergeOptionMaps(
                $backupOptions,
                self::normalizeOptionMap($settings['BackupOptions'] ?? [])
            );
            $memberBackupOptions = self::mergeOptionMaps(
                $memberBackupOptions,
                self::normalizeOptionMap($settings['MemberBackupOptions'] ?? [])
            );
            $desc = trim((string) ($source['Description'] ?? ''));
            if ($desc !== '') {
                $descriptions[] = $desc;
            }
        }

        $merged = count($officeSources) > 1;
        $description = $descriptions === []
            ? ''
            : ($merged ? implode(' + ', $descriptions) : $descriptions[0]);

        return [
            'source_guid' => $sourceGuids[0],
            'source_guids' => $sourceGuids,
            'source_count' => count($sourceGuids),
            'description' => $description,
            'organization' => $organization,
            'whole_org' => $wholeOrg,
            'backup_options' => $backupOptions,
            'member_backup_options' => $memberBackupOptions,
            'local_timezone' => (string) ($profile['LocalTimezone'] ?? ''),
            'merged' => $merged,
        ];
    }

    /**
     * @param array<string, int> $a
     * @param array<string, int> $b
     * @return array<string, int>
     */
    public static function mergeOptionMaps(array $a, array $b): array
    {
        $out = $a;
        foreach ($b as $id => $mask) {
            $out[$id] = ((int) ($out[$id] ?? 0)) | (int) $mask;
        }

        return $out;
    }

    /**
     * @param mixed $raw
     * @return array<string, mixed>
     */
    private static function decodeCustomSetting(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw) || trim($raw) === '') {
            throw new \InvalidArgumentException('CUSTOM_SETTINGV2 is missing or empty.');
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new \InvalidArgumentException('CUSTOM_SETTINGV2 is not valid JSON.');
        }

        return $decoded;
    }

    /**
     * @param mixed $map
     * @return array<string, int>
     */
    private static function normalizeOptionMap(mixed $map): array
    {
        if (!is_array($map)) {
            return [];
        }
        $out = [];
        foreach ($map as $key => $value) {
            $id = trim((string) $key);
            if ($id === '') {
                continue;
            }
            $out[$id] = (int) $value;
        }

        return $out;
    }
}
