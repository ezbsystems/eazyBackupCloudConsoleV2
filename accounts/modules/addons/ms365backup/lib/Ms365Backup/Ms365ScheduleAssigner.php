<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * Assigns backup run times in the evening window (19:00–23:59) with minutes 20–40.
 */
final class Ms365ScheduleAssigner
{
    public const FREQUENCY_ONCE_DAILY = 'once_daily';
    public const FREQUENCY_TWICE_DAILY = 'twice_daily';

    /** @return list<array{hour: int, minute: int}> */
    public static function assignSlots(string $frequency): array
    {
        $frequency = strtolower(trim($frequency));
        if ($frequency === self::FREQUENCY_TWICE_DAILY) {
            return [
                self::randomSlot(19, 21),
                self::randomSlot(22, 23),
            ];
        }

        return [self::randomSlot(19, 23)];
    }

    /**
     * @return array{schedule_frequency: string, schedule_slots: list<array{hour: int, minute: int}>, timezone: string}
     */
    public static function buildSchedulePayload(string $frequency, ?string $timezone = null): array
    {
        $timezone = Ms365JobTimezoneResolver::validateTimezone($timezone)
            ?? Ms365JobTimezoneResolver::PLATFORM_DEFAULT;

        return [
            'schedule_frequency' => $frequency === self::FREQUENCY_TWICE_DAILY
                ? self::FREQUENCY_TWICE_DAILY
                : self::FREQUENCY_ONCE_DAILY,
            'schedule_slots' => self::assignSlots($frequency),
            'timezone' => $timezone,
        ];
    }

    /** @return array{hour: int, minute: int} */
    private static function randomSlot(int $hourMin, int $hourMax): array
    {
        $hour = random_int($hourMin, $hourMax);
        $minute = random_int(20, 40);

        return ['hour' => $hour, 'minute' => $minute];
    }

    /**
     * Whether a job is due to run at the given instant in the job's timezone.
     *
     * @param array<string, mixed> $scheduleJson decoded schedule_json from job row
     */
    public static function isDueNow(array $scheduleJson, ?\DateTimeImmutable $now = null, ?string $jobTimezone = null): bool
    {
        $slots = $scheduleJson['schedule_slots'] ?? [];
        if (!is_array($slots) || $slots === []) {
            return false;
        }

        $localNow = self::localNow($scheduleJson, $now, $jobTimezone);
        $currentHour = (int) $localNow->format('G');
        $currentMinute = (int) $localNow->format('i');

        foreach ($slots as $slot) {
            if (!is_array($slot)) {
                continue;
            }
            $h = (int) ($slot['hour'] ?? -1);
            $m = (int) ($slot['minute'] ?? -1);
            if ($h === $currentHour && $m === $currentMinute) {
                return true;
            }
        }

        return false;
    }

    /**
     * Minute dedup key in the job's local timezone (Y-m-d-H-i).
     *
     * @param array<string, mixed> $scheduleJson
     */
    public static function localMinuteKey(array $scheduleJson, ?\DateTimeImmutable $now = null, ?string $jobTimezone = null): string
    {
        return self::localNow($scheduleJson, $now, $jobTimezone)->format('Y-m-d-H-i');
    }

    /**
     * Next scheduled slot as UTC epoch milliseconds (for UI display).
     *
     * @param array<string, mixed> $scheduleJson
     */
    public static function nextRunEpochMs(array $scheduleJson, ?\DateTimeImmutable $now = null, ?string $jobTimezone = null): ?int
    {
        $slots = $scheduleJson['schedule_slots'] ?? [];
        if (!is_array($slots) || $slots === []) {
            return null;
        }

        $localNow = self::localNow($scheduleJson, $now, $jobTimezone);
        $candidates = [];

        foreach ($slots as $slot) {
            if (!is_array($slot)) {
                continue;
            }
            $hour = (int) ($slot['hour'] ?? -1);
            $minute = (int) ($slot['minute'] ?? -1);
            if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
                continue;
            }

            $candidate = $localNow->setTime($hour, $minute, 0);
            if ($candidate <= $localNow) {
                $candidate = $candidate->modify('+1 day');
            }
            $candidates[] = $candidate;
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, static fn (\DateTimeImmutable $a, \DateTimeImmutable $b): int => $a <=> $b);

        return $candidates[0]->getTimestamp() * 1000;
    }

    /**
     * @param array<string, mixed> $scheduleJson
     */
    public static function resolveTimezone(array $scheduleJson, ?string $jobTimezone = null): string
    {
        $fromJson = Ms365JobTimezoneResolver::validateTimezone((string) ($scheduleJson['timezone'] ?? ''));
        if ($fromJson !== null) {
            return $fromJson;
        }

        $fromJob = Ms365JobTimezoneResolver::validateTimezone($jobTimezone);
        if ($fromJob !== null) {
            return $fromJob;
        }

        return Ms365JobTimezoneResolver::PLATFORM_DEFAULT;
    }

    /**
     * @param array<string, mixed> $scheduleJson
     */
    private static function localNow(array $scheduleJson, ?\DateTimeImmutable $now, ?string $jobTimezone): \DateTimeImmutable
    {
        $tz = new \DateTimeZone(self::resolveTimezone($scheduleJson, $jobTimezone));
        $utc = new \DateTimeZone('UTC');
        $instant = $now ?? new \DateTimeImmutable('now', $utc);

        return $instant->setTimezone($tz);
    }
}
