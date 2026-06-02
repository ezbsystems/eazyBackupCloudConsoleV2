<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * Fallback calendar inventory: partition GET /events by createdDateTime (year → hour).
 */
final class CalendarPartitionScanner
{
    private const GRANULARITY_YEAR = 'year';
    private const GRANULARITY_MONTH = 'month';
    private const GRANULARITY_DAY = 'day';
    private const GRANULARITY_HOUR = 'hour';

    public function __construct(
        private readonly GraphClient $graph,
        private readonly ProgressLogger $logger,
        private readonly ?RunCancellation $cancellation = null,
    ) {
    }

    /**
     * @param array<string, true> $seenEventIds
     * @return array{complete: bool, partitions: list<array<string, mixed>>, events_upserted: int}
     */
    public function scan(
        string $userId,
        string $calendarId,
        string $calName,
        string $path,
        CalendarEventStore $store,
        array &$seenEventIds,
    ): array {
        $rangeStart = CalendarInventoryRanges::rangeStart();
        $rangeEnd = CalendarInventoryRanges::rangeEnd();

        $this->logger->info("Calendar fallback partition scan: {$calName}", [
            'range_start' => $rangeStart->format('c'),
            'range_end' => $rangeEnd->format('c'),
        ]);

        $partitions = [];
        $eventsUpserted = 0;
        $allComplete = true;

        foreach (CalendarInventoryRanges::yearPartitions() as [$start, $end]) {
            $this->cancellation?->check();
            $result = $this->scanRange(
                $path,
                $calName,
                $start,
                $end,
                self::GRANULARITY_YEAR,
                $store,
                $seenEventIds,
            );
            $partitions[] = $result;
            $eventsUpserted += (int) ($result['eventsUpserted'] ?? 0);
            if (($result['status'] ?? '') !== 'complete') {
                $allComplete = false;
            }
        }

        return [
            'complete' => $allComplete,
            'partitions' => $partitions,
            'events_upserted' => $eventsUpserted,
        ];
    }

    /**
     * @param array<string, true> $seenEventIds
     * @return array<string, mixed>
     */
    private function scanRange(
        string $path,
        string $calName,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        string $granularity,
        CalendarEventStore $store,
        array &$seenEventIds,
    ): array {
        $label = $granularity . ':' . $start->format('Y-m-d\TH:i:s\Z') . '..' . $end->format('Y-m-d\TH:i:s\Z');
        $filter = CalendarGraphFields::createdDateTimeFilter($start, $end);
        $query = [
            '$select' => CalendarGraphFields::LIST_SELECT,
            '$top' => CalendarGraphFields::PARTITION_PAGE_SIZE,
            '$filter' => $filter,
            '$orderby' => 'createdDateTime',
        ];
        $monitor = PaginationMonitor::forCalendarPartitionScan(
            $this->logger,
            'calendar.partition:' . $calName . ':' . $label,
        );

        $upserted = 0;
        $pages = 0;

        try {
            $outcome = new PaginationOutcome();
            foreach ($this->graph->paginate($path, $query, CalendarGraphFields::PREFER_IMMUTABLE, $monitor, $outcome) as $event) {
                $this->cancellation?->check();
                $r = $store->upsertFromListItem($event, $seenEventIds);
                if ($r['stored']) {
                    $upserted++;
                }
            }
            $pages = $outcome->pages;
            if ($outcome->isCleanCompletion()) {
                return [
                    'label' => $label,
                    'start' => $start->format('c'),
                    'end' => $end->format('c'),
                    'granularity' => $granularity,
                    'status' => 'complete',
                    'eventsUpserted' => $upserted,
                    'pages' => $pages,
                ];
            }
            // DetectDuplicateOnly should not be used for partitions; treat as failure to subdivide.
            return $this->subdivideOrFail($path, $calName, $start, $end, $granularity, $store, $seenEventIds, $label, $upserted, $pages);
        } catch (GraphPaginationException $e) {
            $this->logger->warn("Partition pagination error, subdividing: {$label}", [
                'error' => $e->getMessage(),
            ]);
            return $this->subdivideOrFail($path, $calName, $start, $end, $granularity, $store, $seenEventIds, $label, $upserted, $pages);
        } catch (\Throwable $e) {
            $this->logger->error("Partition failed: {$label}", ['error' => $e->getMessage()]);
            return [
                'label' => $label,
                'start' => $start->format('c'),
                'end' => $end->format('c'),
                'granularity' => $granularity,
                'status' => 'failed',
                'failureReason' => $e->getMessage(),
                'eventsUpserted' => $upserted,
                'pages' => $pages,
            ];
        }
    }

    /**
     * @param array<string, true> $seenEventIds
     * @return array<string, mixed>
     */
    private function subdivideOrFail(
        string $path,
        string $calName,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        string $granularity,
        CalendarEventStore $store,
        array &$seenEventIds,
        string $label,
        int $upsertedSoFar,
        int $pagesSoFar,
    ): array {
        $childGranularity = match ($granularity) {
            self::GRANULARITY_YEAR => self::GRANULARITY_MONTH,
            self::GRANULARITY_MONTH => self::GRANULARITY_DAY,
            self::GRANULARITY_DAY => self::GRANULARITY_HOUR,
            default => null,
        };

        if ($childGranularity === null) {
            $this->logger->error("Partition failed at hour granularity: {$label}");
            return [
                'label' => $label,
                'start' => $start->format('c'),
                'end' => $end->format('c'),
                'granularity' => $granularity,
                'status' => 'failed',
                'failureReason' => 'Pagination loop persisted at minimum partition granularity (hour)',
                'eventsUpserted' => $upsertedSoFar,
                'pages' => $pagesSoFar,
            ];
        }

        $children = $this->splitRange($start, $end, $childGranularity);
        $childResults = [];
        $totalUpserted = $upsertedSoFar;
        foreach ($children as [$childStart, $childEnd]) {
            $this->cancellation?->check();
            $childResult = $this->scanRange(
                $path,
                $calName,
                $childStart,
                $childEnd,
                $childGranularity,
                $store,
                $seenEventIds,
            );
            $childResults[] = $childResult;
            $totalUpserted += (int) ($childResult['eventsUpserted'] ?? 0);
        }

        $allComplete = true;
        foreach ($childResults as $cr) {
            if (($cr['status'] ?? '') !== 'complete') {
                $allComplete = false;
                break;
            }
        }

        return [
            'label' => $label,
            'start' => $start->format('c'),
            'end' => $end->format('c'),
            'granularity' => $granularity,
            'status' => $allComplete ? 'complete' : 'failed',
            'failureReason' => $allComplete ? null : 'One or more child partitions failed',
            'eventsUpserted' => $totalUpserted,
            'pages' => $pagesSoFar,
            'children' => $childResults,
        ];
    }

    /**
     * @return list<array{0: \DateTimeImmutable, 1: \DateTimeImmutable}>
     */
    private function splitRange(\DateTimeImmutable $start, \DateTimeImmutable $end, string $granularity): array
    {
        $ranges = [];
        $cursor = $start;
        while ($cursor < $end) {
            $next = match ($granularity) {
                self::GRANULARITY_YEAR => $cursor->modify('+1 year'),
                self::GRANULARITY_MONTH => $cursor->modify('+1 month'),
                self::GRANULARITY_DAY => $cursor->modify('+1 day'),
                self::GRANULARITY_HOUR => $cursor->modify('+1 hour'),
                default => $end,
            };
            if ($next > $end) {
                $next = $end;
            }
            $ranges[] = [$cursor, $next];
            $cursor = $next;
        }
        return $ranges;
    }
}
