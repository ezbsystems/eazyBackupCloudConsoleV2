<?php

declare(strict_types=1);

namespace EazyBackup\Billing;

use InvalidArgumentException;

class AnnualCycleWindow
{
    /**
     * Compute cycle_start and cycle_end from next due date (Y-m-d).
     * cycle_end = nextDueYmd, cycle_start = day after (end - 1 year).
     * Uses UTC for timezone-safe date math.
     *
     * @return array{cycle_start: string, cycle_end: string}
     * @throws InvalidArgumentException if input is not valid Y-m-d
     */
    public static function fromNextDueDate(string $nextDueYmd): array
    {
        $tz = new \DateTimeZone('UTC');
        $cycleEnd = \DateTimeImmutable::createFromFormat('!Y-m-d', $nextDueYmd, $tz);
        if ($cycleEnd === false || $cycleEnd->format('Y-m-d') !== $nextDueYmd) {
            throw new InvalidArgumentException('Invalid next due date (expected Y-m-d): ' . $nextDueYmd);
        }

        $prevCycleEnd = $cycleEnd->modify('-1 year');
        $cycleStart = $prevCycleEnd->modify('+1 day');

        return [
            'cycle_start' => $cycleStart->format('Y-m-d'),
            'cycle_end' => $cycleEnd->format('Y-m-d'),
        ];
    }
}
