<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * Upserts calendar event JSON on disk. Never tombstones/deletes on incomplete scans.
 */
final class CalendarEventStore
{
    /** @var array<string, list<string>> */
    private array $seriesExceptions = [];

    public function __construct(
        private readonly StorageLayout $storage,
        private readonly string $userId,
        private readonly string $calendarId,
        private readonly string $runId,
    ) {
    }

    /**
     * @param array<string, true> $seenEventIds
     * @return array{stored: bool, skipped_occurrence: bool}
     */
    public function upsertFromListItem(array $event, array &$seenEventIds): array
    {
        $type = (string) ($event['type'] ?? 'singleInstance');
        if ($type === 'occurrence') {
            return ['stored' => false, 'skipped_occurrence' => true];
        }

        $immutableId = (string) ($event['id'] ?? '');
        if ($immutableId === '') {
            return ['stored' => false, 'skipped_occurrence' => false];
        }
        if (isset($seenEventIds[$immutableId])) {
            return ['stored' => false, 'skipped_occurrence' => false];
        }
        $seenEventIds[$immutableId] = true;

        $envelope = $this->buildEventEnvelope($event);
        $this->storage->writeJson(
            $this->storage->calendarEventFilePath($this->userId, $this->calendarId, $immutableId),
            $envelope,
        );

        if ($type === 'seriesMaster') {
            $this->writeSeriesSidecar($immutableId, $event, []);
        } elseif ($type === 'exception') {
            $masterId = (string) ($event['seriesMasterId'] ?? '');
            if ($masterId !== '') {
                $this->seriesExceptions[$masterId][] = $immutableId;
            }
        }

        return ['stored' => true, 'skipped_occurrence' => false];
    }

    /** @param array<string, mixed> $enrichedEvent */
    public function upsertEnrichedMaster(array $enrichedEvent): void
    {
        $immutableId = (string) ($enrichedEvent['id'] ?? '');
        if ($immutableId === '') {
            return;
        }
        $path = $this->storage->calendarEventFilePath($this->userId, $this->calendarId, $immutableId);
        $existing = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        if (!is_array($existing)) {
            $existing = $this->buildEventEnvelope($enrichedEvent);
        } else {
            $priorRaw = is_array($existing['rawGraphJson'] ?? null) ? $existing['rawGraphJson'] : [];
            $existing['rawGraphJson'] = array_merge($priorRaw, $enrichedEvent);
            foreach (['createdDateTime', 'lastModifiedDateTime'] as $field) {
                if (empty($existing['rawGraphJson'][$field]) && !empty($priorRaw[$field])) {
                    $existing['rawGraphJson'][$field] = $priorRaw[$field];
                }
            }
            $existing['changeKey'] = $enrichedEvent['changeKey'] ?? $existing['changeKey'] ?? '';
            $existing['@odata.etag'] = $enrichedEvent['@odata.etag'] ?? $existing['@odata.etag'] ?? null;
            $existing['normalizedHash'] = $this->computeNormalizedHash($existing['rawGraphJson']);
            $existing['backedUpAt'] = gmdate('c');
            $existing['runId'] = $this->runId;
        }
        $this->storage->writeJson($path, $existing);
    }

    /**
     * @param list<array<string, mixed>> $attachments
     */
    public function attachToEvent(string $immutableEventId, array $attachments): void
    {
        $path = $this->storage->calendarEventFilePath($this->userId, $this->calendarId, $immutableEventId);
        if (!is_file($path)) {
            return;
        }
        $existing = json_decode((string) file_get_contents($path), true);
        if (!is_array($existing)) {
            return;
        }
        $existing['attachments'] = $attachments;
        $existing['backedUpAt'] = gmdate('c');
        $this->storage->writeJson($path, $existing);
    }

    public function mergeSeriesExceptionLinks(): void
    {
        foreach ($this->seriesExceptions as $masterId => $exceptionIds) {
            $seriesFile = $this->storage->calendarSeriesFilePath($this->userId, $this->calendarId, $masterId);
            if (!is_file($seriesFile)) {
                continue;
            }
            $existing = json_decode((string) file_get_contents($seriesFile), true);
            if (!is_array($existing)) {
                continue;
            }
            $existing['exceptionOccurrenceIds'] = array_values(array_unique(array_merge(
                $existing['exceptionOccurrenceIds'] ?? [],
                $exceptionIds,
            )));
            $this->storage->writeJson($seriesFile, $existing);
        }
    }

    /**
     * @param array<string, mixed> $seriesPayload
     */
    public function writeSeriesSidecar(string $masterId, array $rawGraphJson, array $seriesPayload): void
    {
        $payload = array_merge([
            'seriesMasterId' => $masterId,
            'mailboxId' => $this->userId,
            'calendarId' => $this->calendarId,
            'recurrence' => $rawGraphJson['recurrence'] ?? null,
            'cancelledOccurrences' => $rawGraphJson['cancelledOccurrences'] ?? [],
            'exceptionOccurrences' => $rawGraphJson['exceptionOccurrences'] ?? null,
            'exceptionOccurrenceIds' => [],
            'rawGraphJson' => $rawGraphJson,
        ], $seriesPayload);
        $this->storage->writeJson(
            $this->storage->calendarSeriesFilePath($this->userId, $this->calendarId, $masterId),
            $payload,
        );
    }

    public function countStoredEvents(): int
    {
        $dir = $this->storage->calendarEventsDir($this->userId, $this->calendarId);
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

    /**
     * @param array<string, mixed> $state
     */
    public function writeBackupState(array $state): void
    {
        $this->storage->writeJson(
            $this->storage->calendarBackupStatePath($this->userId, $this->calendarId),
            $state,
        );
    }

    /** @param array<string, mixed> $event */
    private function buildEventEnvelope(array $event): array
    {
        return [
            'mailboxId' => $this->userId,
            'calendarId' => $this->calendarId,
            'immutableEventId' => $event['id'] ?? '',
            'type' => $event['type'] ?? '',
            'changeKey' => $event['changeKey'] ?? '',
            '@odata.etag' => $event['@odata.etag'] ?? null,
            'normalizedHash' => $this->computeNormalizedHash($event),
            'rawGraphJson' => $event,
            'attachments' => null,
            'backedUpAt' => gmdate('c'),
            'runId' => $this->runId,
        ];
    }

    /** @param array<string, mixed> $event */
    private function computeNormalizedHash(array $event): string
    {
        $canonical = [
            'id' => $event['id'] ?? '',
            'changeKey' => $event['changeKey'] ?? '',
            'iCalUId' => $event['iCalUId'] ?? '',
            'type' => $event['type'] ?? '',
            'seriesMasterId' => $event['seriesMasterId'] ?? null,
            'start' => $event['start'] ?? null,
            'end' => $event['end'] ?? null,
        ];
        return hash('sha256', json_encode($canonical, JSON_UNESCAPED_SLASHES));
    }
}
