<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * Per-calendar inventory: normal /events pass, then optional createdDateTime partition fallback.
 */
final class CalendarInventoryScanner
{
    public function __construct(
        private readonly GraphClient $graph,
        private readonly CalendarPartitionScanner $partitionScanner,
        private readonly ProgressLogger $logger,
        private readonly ?RunCancellation $cancellation = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function scanCalendar(
        string $userId,
        string $calendarId,
        string $calName,
        CalendarEventStore $store,
        string $runId,
    ): array {
        $path = "users/{$userId}/calendars/{$calendarId}/events";
        /** @var array<string, true> $seenEventIds */
        $seenEventIds = [];
        $stats = [
            'events_stored' => 0,
            'series_stored' => 0,
            'skipped_occurrences' => 0,
        ];

        $this->logger->info("Calendar inventory normal pass: {$calName}", ['calendar_id' => $calendarId]);

        $normalOutcome = new PaginationOutcome();
        $normalMonitor = PaginationMonitor::forCalendarNormalScan(
            $this->logger,
            'calendar.events.normal:' . $calName,
        );
        $query = [
            '$select' => CalendarGraphFields::LIST_SELECT,
            '$top' => CalendarGraphFields::NORMAL_PAGE_SIZE,
        ];

        try {
            foreach ($this->graph->paginate($path, $query, CalendarGraphFields::PREFER_IMMUTABLE, $normalMonitor, $normalOutcome) as $event) {
                $this->cancellation?->check();
                $r = $store->upsertFromListItem($event, $seenEventIds);
                if ($r['skipped_occurrence']) {
                    $stats['skipped_occurrences']++;
                }
                if ($r['stored']) {
                    $stats['events_stored']++;
                    if (($event['type'] ?? '') === 'seriesMaster') {
                        $stats['series_stored']++;
                    }
                }
            }
        } catch (GraphPaginationException $e) {
            $this->logger->error("Calendar normal pass failed: {$calName}", ['error' => $e->getMessage()]);
            $store->writeBackupState($this->incompleteState($runId, 'normal', $e->getMessage(), [], $store->countStoredEvents()));
            return $this->result(false, 'normal', $stats, [], $e->getMessage());
        }

        $scanMode = 'normal';
        $partitions = [];

        if ($normalOutcome->needsFallbackInventory()) {
            $this->logger->warn("Calendar normal pass incomplete, starting partition fallback: {$calName}", [
                'provisional_events' => $stats['events_stored'],
                'pages' => $normalOutcome->pages,
            ]);
            $scanMode = 'fallback';
            $partitionResult = $this->partitionScanner->scan(
                $userId,
                $calendarId,
                $calName,
                $path,
                $store,
                $seenEventIds,
            );
            $partitions = $partitionResult['partitions'];
            if (!$partitionResult['complete']) {
                $store->mergeSeriesExceptionLinks();
                $store->writeBackupState($this->incompleteState(
                    $runId,
                    'fallback',
                    'One or more createdDateTime partitions failed',
                    $partitions,
                    $store->countStoredEvents(),
                ));
                return $this->result(false, 'fallback', $stats, $partitions, 'Partition scan incomplete');
            }
            $stats['events_stored'] = $store->countStoredEvents();
        } elseif (!$normalOutcome->isCleanCompletion()) {
            $store->writeBackupState($this->incompleteState(
                $runId,
                'normal',
                'Unexpected pagination end state',
                [],
                $store->countStoredEvents(),
            ));
            return $this->result(false, 'normal', $stats, [], 'Unexpected pagination end state');
        }

        $store->mergeSeriesExceptionLinks();
        $eventCount = $store->countStoredEvents();
        $store->writeBackupState([
            'complete' => true,
            'completedAt' => gmdate('c'),
            'lastRunId' => $runId,
            'scanMode' => $scanMode,
            'partitions' => $partitions,
            'eventCount' => $eventCount,
        ]);

        return $this->result(true, $scanMode, $stats, $partitions, null);
    }

    /**
     * @param list<array<string, mixed>> $partitions
     * @return array<string, mixed>
     */
    private function incompleteState(
        string $runId,
        string $scanMode,
        string $failureReason,
        array $partitions,
        int $eventCount,
    ): array {
        return [
            'complete' => false,
            'completedAt' => null,
            'lastRunId' => $runId,
            'scanMode' => $scanMode,
            'failureReason' => $failureReason,
            'partitions' => $partitions,
            'eventCount' => $eventCount,
        ];
    }

    /**
     * @param list<array<string, mixed>> $partitions
     * @return array<string, mixed>
     */
    private function result(
        bool $complete,
        string $scanMode,
        array $stats,
        array $partitions,
        ?string $failureReason,
    ): array {
        return array_merge($stats, [
            'complete' => $complete,
            'scanMode' => $scanMode,
            'partitions' => $partitions,
            'failureReason' => $failureReason,
        ]);
    }
}
