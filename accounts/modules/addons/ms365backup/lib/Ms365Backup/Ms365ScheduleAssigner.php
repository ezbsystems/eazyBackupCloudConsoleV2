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
        return [
            'schedule_frequency' => $frequency === self::FREQUENCY_TWICE_DAILY
                ? self::FREQUENCY_TWICE_DAILY
                : self::FREQUENCY_ONCE_DAILY,
            'schedule_slots' => self::assignSlots($frequency),
            'timezone' => $timezone !== null && $timezone !== '' ? $timezone : 'America/Toronto',
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
     * Whether a job is due to run at the given local time (server TZ or job timezone).
     *
     * @param array<string, mixed> $scheduleJson decoded schedule_json from job row
     */
    public static function isDueNow(array $scheduleJson, ?\DateTimeImmutable $now = null): bool
    {
        $slots = $scheduleJson['schedule_slots'] ?? [];
        if (!is_array($slots) || $slots === []) {
            return false;
        }

        $now = $now ?? new \DateTimeImmutable('now');
        $currentHour = (int) $now->format('G');
        $currentMinute = (int) $now->format('i');

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
}
