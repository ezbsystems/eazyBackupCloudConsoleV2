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
     *   description: string,
     *   organization: bool,
     *   whole_org: bool,
     *   backup_options: array<string, int>,
     *   member_backup_options: array<string, int>,
     *   local_timezone: string
     * }
     */
    public static function parseProfile(array $profile): array
    {
        $sources = $profile['Sources'] ?? null;
        if (!is_array($sources) || $sources === []) {
            throw new \InvalidArgumentException('Comet profile has no Sources.');
        }

        $sourceGuid = '';
        $source = null;
        foreach ($sources as $guid => $row) {
            if (!is_array($row)) {
                continue;
            }
            if ((string) ($row['Engine'] ?? '') !== self::ENGINE) {
                continue;
            }
            $sourceGuid = (string) $guid;
            $source = $row;
            break;
        }

        if ($source === null) {
            throw new \InvalidArgumentException(
                'Comet profile has no Protected Item with Engine ' . self::ENGINE
            );
        }

        $props = is_array($source['EngineProps'] ?? null) ? $source['EngineProps'] : [];
        $raw = $props['CUSTOM_SETTINGV2'] ?? null;
        $settings = self::decodeCustomSetting($raw);

        return [
            'source_guid' => $sourceGuid,
            'description' => (string) ($source['Description'] ?? ''),
            'organization' => (bool) ($settings['Organization'] ?? false),
            'whole_org' => (bool) ($settings['WholeOrg'] ?? false),
            'backup_options' => self::normalizeOptionMap($settings['BackupOptions'] ?? []),
            'member_backup_options' => self::normalizeOptionMap($settings['MemberBackupOptions'] ?? []),
            'local_timezone' => (string) ($profile['LocalTimezone'] ?? ''),
        ];
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
