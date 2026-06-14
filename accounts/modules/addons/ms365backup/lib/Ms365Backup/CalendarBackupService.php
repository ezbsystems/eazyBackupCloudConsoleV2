<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * Calendar backup via GET /calendars/{id}/events (series masters + exceptions).
 * Normal unfiltered pass; on Graph duplicate-page defect, createdDateTime partition fallback.
 *
 * @see https://github.com/microsoftgraph/msgraph-sdk-dotnet/issues/3070
 */
final class CalendarBackupService
{
    public function __construct(
        private readonly GraphClient $graph,
        private readonly StorageLayout $storage,
        private readonly ProgressLogger $logger,
        private readonly string $runId,
        private readonly ?RunCancellation $cancellation = null,
    ) {
    }

    /**
     * @return array{
     *   calendars: int,
     *   events_stored: int,
     *   series_stored: int,
     *   skipped_occurrences: int,
     *   calendars_complete: int,
     *   calendars_incomplete: list<array<string, mixed>>,
     *   calendar_results: list<array<string, mixed>>
     * }
     */
    public function backupUser(string $userId): array
    {
        return $this->backupMailbox(GraphMailboxOwner::user($userId));
    }

    /**
     * @return array{
     *   calendars: int,
     *   events_stored: int,
     *   series_stored: int,
     *   skipped_occurrences: int,
     *   calendars_complete: int,
     *   calendars_incomplete: list<array<string, mixed>>,
     *   calendar_results: list<array<string, mixed>>
     * }
     */
    public function backupMailbox(GraphMailboxOwner $owner): array
    {
        $ownerId = $owner->id();
        $this->logger->info('Starting calendar backup', [
            'mailbox_id' => $ownerId,
            'mailbox_type' => $owner->isGroup() ? 'group' : 'user',
            'endpoint' => 'calendars/{id}/events',
            'strategy' => 'normal_pass_then_createdDateTime_partitions',
        ]);

        $inventoryScanner = new CalendarInventoryScanner(
            $this->graph,
            new CalendarPartitionScanner($this->graph, $this->logger, $this->cancellation),
            $this->logger,
            $this->cancellation,
        );
        $seriesEnricher = new SeriesMasterEnricher($this->graph, $this->storage, $this->logger, $this->cancellation);
        $attachmentFetcher = new CalendarAttachmentFetcher($this->graph, $this->storage, $this->logger, $this->cancellation);

        $stats = [
            'calendars' => 0,
            'events_stored' => 0,
            'series_stored' => 0,
            'skipped_occurrences' => 0,
            'calendars_complete' => 0,
            'calendars_incomplete' => [],
            'calendar_results' => [],
        ];

        $listMonitor = PaginationMonitor::forBackup($this->logger, 'calendar.list');
        $calendars = [];
        try {
            foreach ($this->graph->paginate($owner->graphPath('calendars'), ['$top' => '50'], [], $listMonitor) as $cal) {
                $this->cancellation?->check();
                $calendars[] = $cal;
            }
        } catch (GraphApiException $e) {
            $result = ResourceAccessClassifier::classify($e);
            if ($result->skippable) {
                throw ResourceUnavailableException::fromGraph('calendar', $e);
            }
            throw $e;
        }
        $stats['calendars'] = count($calendars);
        $this->logger->info('Calendars listed', ['count' => count($calendars)]);

        foreach ($calendars as $calendar) {
            $this->cancellation?->check();
            $calendarId = (string) ($calendar['id'] ?? '');
            $calName = (string) ($calendar['name'] ?? $calendarId);
            if ($calendarId === '') {
                continue;
            }

            $store = new CalendarEventStore($this->storage, $owner, $calendarId, $this->runId);
            $result = $inventoryScanner->scanCalendar($owner, $calendarId, $calName, $store, $this->runId);

            if (!($result['complete'] ?? false)) {
                $stats['calendars_incomplete'][] = [
                    'calendar_id' => $calendarId,
                    'name' => $calName,
                    'scan_mode' => $result['scanMode'] ?? '',
                    'failure_reason' => $result['failureReason'] ?? 'Unknown',
                    'partitions' => $result['partitions'] ?? [],
                    'event_count' => $store->countStoredEvents(),
                ];
                $this->logger->error("Calendar inventory incomplete: {$calName}", [
                    'failure_reason' => $result['failureReason'] ?? '',
                ]);
                continue;
            }

            $seriesEnricher->enrichCalendar($owner, $calendarId, $calName, $store);
            $attachmentFetcher->fetchForCalendar($owner, $calendarId, $calName, $store);

            $stats['calendars_complete']++;
            $stats['events_stored'] += $store->countStoredEvents();
            $stats['series_stored'] += $this->countSeriesFiles($owner, $calendarId);
            $stats['skipped_occurrences'] += (int) ($result['skipped_occurrences'] ?? 0);
            $stats['calendar_results'][] = [
                'calendar_id' => $calendarId,
                'name' => $calName,
                'scan_mode' => $result['scanMode'] ?? 'normal',
                'complete' => true,
                'event_count' => $store->countStoredEvents(),
            ];
            $this->logger->info("Calendar backed up: {$calName}", [
                'scan_mode' => $result['scanMode'] ?? 'normal',
                'event_count' => $store->countStoredEvents(),
            ]);
        }

        $this->logger->info('Calendar backup finished', $stats);

        if ($stats['calendar_results'] !== []) {
            $stats['verify'] = $this->verifyCalendars($owner, $stats['calendar_results']);
            foreach ($stats['calendar_results'] as $i => $cr) {
                if (isset($stats['verify']['calendars'][$i])) {
                    $stats['calendar_results'][$i]['verify'] = $stats['verify']['calendars'][$i];
                }
            }
        }

        if ($stats['calendars_incomplete'] !== []) {
            $names = array_map(static fn ($c) => $c['name'] ?? $c['calendar_id'], $stats['calendars_incomplete']);
            throw new CalendarBackupIncompleteException(
                'Calendar backup incomplete for: ' . implode(', ', $names),
                $stats['calendars_incomplete'],
                $stats['calendar_results'],
            );
        }

        return $stats;
    }

    /**
     * Post-backup Graph $count cross-check per calendar (does not fail the run).
     *
     * @param list<array<string, mixed>> $calendarResults
     * @return array{ok: bool, calendars: list<array<string, mixed>>}
     */
    private function verifyCalendars(GraphMailboxOwner $owner, array $calendarResults): array
    {
        BackupRunRepository::setPhase($this->runId, 'calendar_verify');
        $verifier = new CalendarVerifier($this->graph, $this->storage);
        $verifyDir = $this->storage->runDirForMailbox($owner, $this->runId) . '/calendar_verify';

        $this->logger->info('Starting calendar verify (Graph $count vs on-disk)', [
            'calendar_count' => count($calendarResults),
        ]);

        $summaries = [];
        $allOk = true;

        foreach ($calendarResults as $cr) {
            $this->cancellation?->check();
            $calendarId = (string) ($cr['calendar_id'] ?? '');
            $calName = (string) ($cr['name'] ?? $calendarId);
            if ($calendarId === '') {
                continue;
            }

            $report = $verifier->verifyMailbox($owner, $calendarId, $calName);
            $safeCal = preg_replace('/[^a-zA-Z0-9._-]/', '_', $calendarId) ?: 'calendar';
            $this->storage->writeJson($verifyDir . '/' . $safeCal . '.json', $report);

            $summary = CalendarVerifier::summarizeForManifest($report);
            $summary['calendar_id'] = $calendarId;
            $summary['name'] = $calName;
            $summaries[] = $summary;

            if (!$summary['ok']) {
                $allOk = false;
                $this->logger->warn("Calendar verify found gaps: {$calName}", $summary);
            } else {
                $this->logger->info("Calendar verify OK: {$calName}", [
                    'graph_total' => $summary['graph_total'],
                    'disk_series_master_single' => $summary['disk_series_master_single'],
                ]);
            }
        }

        $this->logger->info('Calendar verify finished', ['ok' => $allOk, 'calendars' => count($summaries)]);

        return ['ok' => $allOk, 'calendars' => $summaries];
    }

    private function countSeriesFiles(GraphMailboxOwner $owner, string $calendarId): int
    {
        $dir = $this->storage->calendarSeriesDir($owner, $calendarId);
        if (!is_dir($dir)) {
            return 0;
        }
        $count = 0;
        foreach (scandir($dir) ?: [] as $file) {
            if (str_ends_with($file, '.json')) {
                $count++;
            }
        }
        return $count;
    }
}
