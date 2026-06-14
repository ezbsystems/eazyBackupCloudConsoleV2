<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * Maps customer backup presets to resource IDs and default scope.
 */
final class CustomerPresetCatalog
{
    public const PRESET_USER_MAIL_CALENDAR = 'user_mail_calendar';
    public const PRESET_COLLABORATION = 'collaboration';
    public const PRESET_FULL = 'full';

    /** @return list<string> */
    public static function validPresetIds(): array
    {
        return [
            self::PRESET_USER_MAIL_CALENDAR,
            self::PRESET_COLLABORATION,
            self::PRESET_FULL,
        ];
    }

    /**
     * @param array<string, mixed> $inventory
     * @return array{selected_ids: list<string>, scope: BackupScope}
     */
    public static function resolve(string $preset, array $inventory): array
    {
        if (!in_array($preset, self::validPresetIds(), true)) {
            throw new \RuntimeException('Unknown backup preset: ' . $preset);
        }

        $resources = is_array($inventory['resources'] ?? null) ? $inventory['resources'] : [];

        return match ($preset) {
            self::PRESET_USER_MAIL_CALENDAR => [
                'selected_ids' => self::resourceIdsByTypes($resources, [
                    TenantResource::TYPE_USER,
                    TenantResource::TYPE_MAILBOX,
                ]),
                'scope' => new BackupScope([
                    BackupScope::MAIL => true,
                    BackupScope::CALENDAR => true,
                ]),
            ],
            self::PRESET_COLLABORATION => [
                'selected_ids' => self::resourceIdsByTypes($resources, [
                    TenantResource::TYPE_SHAREPOINT_SITE,
                    TenantResource::TYPE_TEAM,
                    TenantResource::TYPE_M365_GROUP,
                ]),
                'scope' => new BackupScope([
                    BackupScope::FILES => true,
                    BackupScope::LISTS => true,
                    BackupScope::TEAMS_METADATA => true,
                    BackupScope::TEAMS_MESSAGES => true,
                    BackupScope::MAIL => true,
                    BackupScope::CALENDAR => true,
                ]),
            ],
            self::PRESET_FULL => [
                'selected_ids' => self::allBackupableResourceIds($resources),
                'scope' => new BackupScope([
                    BackupScope::MAIL => true,
                    BackupScope::CALENDAR => true,
                    BackupScope::CONTACTS => true,
                    BackupScope::TASKS => true,
                    BackupScope::ONEDRIVE => true,
                    BackupScope::FILES => true,
                    BackupScope::LISTS => true,
                    BackupScope::TEAMS_METADATA => true,
                    BackupScope::TEAMS_MESSAGES => true,
                    BackupScope::PLANNER => true,
                    BackupScope::ONENOTE => true,
                ]),
            ],
        };
    }

    /**
     * @param list<array<string, mixed>> $resources
     * @param list<string> $types
     * @return list<string>
     */
    private static function resourceIdsByTypes(array $resources, array $types): array
    {
        $ids = [];
        foreach ($resources as $resource) {
            if (!is_array($resource)) {
                continue;
            }
            $type = (string) ($resource['resource_type'] ?? '');
            if (!in_array($type, $types, true)) {
                continue;
            }
            $id = (string) ($resource['id'] ?? '');
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param list<array<string, mixed>> $resources
     * @return list<string>
     */
    private static function allBackupableResourceIds(array $resources): array
    {
        $skip = [
            TenantResource::TYPE_TEAM_CHANNEL,
        ];
        $ids = [];
        foreach ($resources as $resource) {
            if (!is_array($resource)) {
                continue;
            }
            $type = (string) ($resource['resource_type'] ?? '');
            if ($type === '' || in_array($type, $skip, true)) {
                continue;
            }
            $id = (string) ($resource['id'] ?? '');
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
}
