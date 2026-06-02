<?php
declare(strict_types=1);

namespace Ms365Backup;

/** Shared createdDateTime inventory windows (backup + verify). */
final class CalendarInventoryRanges
{
    public static function rangeStart(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(CalendarGraphFields::INVENTORY_START, new \DateTimeZone('UTC'));
    }

    public static function rangeEnd(): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('+1 day')->setTime(0, 0, 0);
    }

    /**
     * @return list<array{0: \DateTimeImmutable, 1: \DateTimeImmutable}>
     */
    public static function yearPartitions(): array
    {
        return self::splitByYear(self::rangeStart(), self::rangeEnd());
    }

    /**
     * @return list<array{0: \DateTimeImmutable, 1: \DateTimeImmutable}>
     */
    public static function splitByYear(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $ranges = [];
        $cursor = $start;
        while ($cursor < $end) {
            $next = $cursor->modify('+1 year');
            if ($next > $end) {
                $next = $end;
            }
            $ranges[] = [$cursor, $next];
            $cursor = $next;
        }
        return $ranges;
    }

    public static function inventoryEventFilter(\DateTimeImmutable $start, \DateTimeImmutable $end): string
    {
        return CalendarGraphFields::createdDateTimeFilter($start, $end);
    }

    public static function inventoryEventFilterWithType(
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        string $eventType,
    ): string {
        return self::inventoryEventFilter($start, $end) . " and type eq '{$eventType}'";
    }

    public static function allInventoryEventsFilter(): string
    {
        return CalendarGraphFields::createdDateTimeFilter(self::rangeStart(), self::rangeEnd());
    }
}
