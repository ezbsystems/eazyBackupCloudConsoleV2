<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * Fetches full series master payloads (cancelledOccurrences, exceptionOccurrences).
 */
final class SeriesMasterEnricher
{
    public function __construct(
        private readonly GraphClient $graph,
        private readonly StorageLayout $storage,
        private readonly ProgressLogger $logger,
        private readonly ?RunCancellation $cancellation = null,
    ) {
    }

    public function enrichCalendar(
        GraphMailboxOwner $owner,
        string $calendarId,
        string $calName,
        CalendarEventStore $store,
    ): int {
        $eventsDir = $this->storage->calendarEventsDir($owner, $calendarId);
        if (!is_dir($eventsDir)) {
            return 0;
        }

        $enriched = 0;
        foreach (scandir($eventsDir) ?: [] as $file) {
            if (!str_ends_with($file, '.json')) {
                continue;
            }
            $this->cancellation?->check();
            $path = $eventsDir . '/' . $file;
            $envelope = json_decode((string) file_get_contents($path), true);
            if (!is_array($envelope) || ($envelope['type'] ?? '') !== 'seriesMaster') {
                continue;
            }
            $masterId = (string) ($envelope['immutableEventId'] ?? '');
            if ($masterId === '') {
                continue;
            }
            try {
                $this->enrichOne($owner, $calendarId, $calName, $masterId, $store);
                $enriched++;
            } catch (\Throwable $e) {
                $this->logger->error("Series master enrich failed: {$calName}", [
                    'series_master_id' => $masterId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        return $enriched;
    }

    private function enrichOne(
        GraphMailboxOwner $owner,
        string $calendarId,
        string $calName,
        string $masterId,
        CalendarEventStore $store,
    ): void {
        $path = $owner->graphPath("calendars/{$calendarId}/events/{$masterId}");
        $query = [
            '$select' => CalendarGraphFields::SERIES_GET_SELECT,
            '$expand' => 'exceptionOccurrences',
        ];
        $event = $this->graph->get($path, $query, CalendarGraphFields::PREFER_IMMUTABLE);
        $store->upsertEnrichedMaster($event);
        $store->writeSeriesSidecar($masterId, $event, [
            'recurrence' => $event['recurrence'] ?? null,
            'cancelledOccurrences' => $event['cancelledOccurrences'] ?? [],
            'exceptionOccurrences' => $event['exceptionOccurrences'] ?? null,
        ]);
        $this->logger->info("Series master enriched: {$calName}", ['series_master_id' => $masterId]);
    }
}
