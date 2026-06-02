<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * Fetches event attachments when hasAttachments is true on list payload.
 */
final class CalendarAttachmentFetcher
{
    public function __construct(
        private readonly GraphClient $graph,
        private readonly StorageLayout $storage,
        private readonly ProgressLogger $logger,
        private readonly ?RunCancellation $cancellation = null,
    ) {
    }

    public function fetchForCalendar(
        string $userId,
        string $calendarId,
        string $calName,
        CalendarEventStore $store,
    ): int {
        $eventsDir = $this->storage->calendarEventsDir($userId, $calendarId);
        if (!is_dir($eventsDir)) {
            return 0;
        }

        $fetched = 0;
        foreach (scandir($eventsDir) ?: [] as $file) {
            if (!str_ends_with($file, '.json')) {
                continue;
            }
            $this->cancellation?->check();
            $path = $eventsDir . '/' . $file;
            $envelope = json_decode((string) file_get_contents($path), true);
            if (!is_array($envelope)) {
                continue;
            }
            $raw = $envelope['rawGraphJson'] ?? [];
            if (!is_array($raw) || empty($raw['hasAttachments'])) {
                continue;
            }
            $eventId = (string) ($envelope['immutableEventId'] ?? '');
            if ($eventId === '') {
                continue;
            }
            try {
                $attachments = $this->listAttachments($userId, $calendarId, $eventId, $calName);
                $store->attachToEvent($eventId, $attachments);
                $fetched++;
            } catch (\Throwable $e) {
                $this->logger->error("Attachment fetch failed: {$calName}", [
                    'event_id' => $eventId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        return $fetched;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listAttachments(string $userId, string $calendarId, string $eventId, string $calName): array
    {
        $path = "users/{$userId}/calendars/{$calendarId}/events/{$eventId}/attachments";
        $monitor = PaginationMonitor::forBackup($this->logger, 'calendar.attachments:' . $calName);
        $items = [];
        foreach ($this->graph->paginate($path, ['$top' => '50'], CalendarGraphFields::PREFER_IMMUTABLE, $monitor) as $item) {
            $items[] = $item;
        }
        return $items;
    }
}
