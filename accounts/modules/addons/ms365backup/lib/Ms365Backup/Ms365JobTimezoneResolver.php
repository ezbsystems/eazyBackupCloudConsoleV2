<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;

/**
 * Resolves IANA timezone for MS365 backup jobs.
 */
final class Ms365JobTimezoneResolver
{
    public const PLATFORM_DEFAULT = 'America/Toronto';

    public static function resolveForClient(int $clientId, ?string $requestedTz): string
    {
        $validated = self::validateTimezone($requestedTz);
        if ($validated !== null) {
            return $validated;
        }

        $clientDefault = self::clientDefaultTimezone($clientId);
        if ($clientDefault !== null) {
            return $clientDefault;
        }

        return self::PLATFORM_DEFAULT;
    }

    /**
     * Preserve existing job timezone on edit unless the client posts a new one.
     */
    public static function resolveForUpdate(int $clientId, object $job, ?string $requestedTz): string
    {
        $validated = self::validateTimezone($requestedTz);
        if ($validated !== null) {
            return $validated;
        }

        $existing = self::validateTimezone(trim((string) ($job->timezone ?? '')));
        if ($existing !== null) {
            return $existing;
        }

        return self::resolveForClient($clientId, null);
    }

    public static function validateTimezone(?string $timezone): ?string
    {
        $timezone = trim((string) $timezone);
        if ($timezone === '') {
            return null;
        }
        try {
            new \DateTimeZone($timezone);
        } catch (\Throwable $e) {
            return null;
        }

        return $timezone;
    }

    private static function clientDefaultTimezone(int $clientId): ?string
    {
        if ($clientId <= 0) {
            return null;
        }
        try {
            if (!Capsule::schema()->hasTable('s3_cloudbackup_settings')) {
                return null;
            }
            $tz = trim((string) Capsule::table('s3_cloudbackup_settings')
                ->where('client_id', $clientId)
                ->value('default_timezone'));
        } catch (\Throwable $e) {
            return null;
        }

        return self::validateTimezone($tz);
    }
}
