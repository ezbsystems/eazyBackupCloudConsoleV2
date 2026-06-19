<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * Time-range partitions for large SharePoint lists (createdDateTime half-open intervals).
 */
final class ListShardRangeHelper
{
    public const DEFAULT_START = '2010-01-01T00:00:00Z';

    /**
     * @return list<array{start: string, end: string}>
     */
    public static function rangesForItemCount(int $itemCount, int $targetItems, int $maxShards): array
    {
        if ($itemCount <= 0 || $targetItems <= 0) {
            return [];
        }
        $parts = (int) ceil($itemCount / $targetItems);
        $parts = max(2, $parts);
        $parts = min($parts, max(2, $maxShards));

        return self::equalTimeRanges(self::DEFAULT_START, gmdate('Y-m-d\TH:i:s\Z'), $parts);
    }

    /**
     * @return list<array{start: string, end: string}>
     */
    public static function equalTimeRanges(string $startISO, string $endISO, int $parts): array
    {
        $parts = max(1, $parts);
        $start = strtotime($startISO);
        $end = strtotime($endISO);
        if ($start === false || $end === false || $end <= $start) {
            return [['start' => $startISO, 'end' => $endISO]];
        }

        $span = $end - $start;
        $step = (int) floor($span / $parts);
        if ($step < 1) {
            $step = 1;
        }

        $ranges = [];
        $cursor = $start;
        for ($i = 0; $i < $parts; $i++) {
            $next = ($i === $parts - 1) ? $end : min($end, $cursor + $step);
            $ranges[] = [
                'start' => gmdate('Y-m-d\TH:i:s\Z', $cursor),
                'end' => gmdate('Y-m-d\TH:i:s\Z', $next),
            ];
            $cursor = $next;
            if ($cursor >= $end) {
                break;
            }
        }

        return $ranges;
    }

    public static function segmentForRange(string $start, string $end): string
    {
        return $start . '/' . $end;
    }

    /**
     * @return array{start: string, end: string}|null
     */
    public static function parseSegment(string $segment): ?array
    {
        $segment = trim($segment);
        if ($segment === '' || !str_contains($segment, '/')) {
            return null;
        }
        [$start, $end] = explode('/', $segment, 2);
        $start = trim($start);
        $end = trim($end);
        if ($start === '' || $end === '') {
            return null;
        }

        return ['start' => $start, 'end' => $end];
    }
}
