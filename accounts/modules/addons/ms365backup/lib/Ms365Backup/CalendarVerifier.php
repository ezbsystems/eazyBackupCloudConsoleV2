<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * Compares Graph $count per createdDateTime year partition to on-disk event files.
 *
 * Graph does not support type ne 'occurrence' on calendar /events. We compare
 * seriesMaster + singleInstance counts to the same types on disk; exceptions on
 * disk are reported separately (Graph type eq 'exception' is not filterable).
 */
final class CalendarVerifier
{
    /** @var array<string, string> */
    private const COUNT_HEADERS = [
        'ConsistencyLevel' => 'eventual',
        'Prefer' => 'IdType="ImmutableId"',
    ];

    public function __construct(
        private readonly GraphClient $graph,
        private readonly StorageLayout $storage,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function verify(string $userId, string $calendarId, ?string $calendarName = null): array
    {
        $path = "users/{$userId}/calendars/{$calendarId}/events";
        $eventsDir = $this->storage->calendarEventsDir($userId, $calendarId);
        $statePath = $this->storage->calendarBackupStatePath($userId, $calendarId);
        $label = $calendarName ?? $calendarId;

        $diskIndex = $this->indexDiskEvents($eventsDir);
        $partitionResults = [];
        $gaps = [];
        $graphPartitionSum = 0;
        $graphErrors = [];

        foreach (CalendarInventoryRanges::yearPartitions() as [$start, $end]) {
            $yearLabel = $start->format('Y') . ($end->format('Y') !== $start->format('Y') ? '-' . $end->format('Y') : '');
            $graphSm = $this->countGraphTyped($path, $start, $end, 'seriesMaster', $graphErrors);
            $graphSi = $this->countGraphTyped($path, $start, $end, 'singleInstance', $graphErrors);
            $graphCount = ($graphSm >= 0 && $graphSi >= 0) ? $graphSm + $graphSi : -1;
            $diskSmSi = $this->countDiskInRangeByTypes($diskIndex, $start, $end, ['seriesMaster', 'singleInstance']);
            $diskExceptions = $this->countDiskInRangeByTypes($diskIndex, $start, $end, ['exception']);
            $diskCount = $diskSmSi + $diskExceptions;

            if ($graphCount >= 0) {
                $graphPartitionSum += $graphCount;
            }

            $delta = $graphCount >= 0 ? $diskSmSi - $graphCount : null;
            $ok = $graphCount >= 0 && $delta === 0;

            $row = [
                'partition' => $yearLabel,
                'start' => $start->format('c'),
                'end' => $end->format('c'),
                'graph_series_master' => $graphSm,
                'graph_single_instance' => $graphSi,
                'graph_count' => $graphCount,
                'disk_series_master_single' => $diskSmSi,
                'disk_exceptions' => $diskExceptions,
                'disk_count' => $diskCount,
                'delta_disk_sm_si_minus_graph' => $delta,
                'ok' => $ok,
            ];
            $partitionResults[] = $row;

            if ($graphCount < 0) {
                $gaps[] = [
                    'partition' => $yearLabel,
                    'issue' => 'graph_count_unavailable',
                ];
            } elseif ($delta < 0) {
                $gaps[] = [
                    'partition' => $yearLabel,
                    'issue' => 'disk_below_graph',
                    'graph_count' => $graphCount,
                    'disk_series_master_single' => $diskSmSi,
                    'delta' => $delta,
                ];
            } elseif ($delta > 0 && $diskExceptions === 0) {
                $gaps[] = [
                    'partition' => $yearLabel,
                    'issue' => 'disk_exceeds_graph_without_exceptions',
                    'graph_count' => $graphCount,
                    'disk_series_master_single' => $diskSmSi,
                    'delta' => $delta,
                ];
            }
        }

        $graphSmTotal = $this->countGraphTyped(
            $path,
            CalendarInventoryRanges::rangeStart(),
            CalendarInventoryRanges::rangeEnd(),
            'seriesMaster',
            $graphErrors,
        );
        $graphSiTotal = $this->countGraphTyped(
            $path,
            CalendarInventoryRanges::rangeStart(),
            CalendarInventoryRanges::rangeEnd(),
            'singleInstance',
            $graphErrors,
        );
        $graphTotal = ($graphSmTotal >= 0 && $graphSiTotal >= 0) ? $graphSmTotal + $graphSiTotal : -1;
        $diskSmSiTotal = $this->countDiskInRangeByTypes(
            $diskIndex,
            CalendarInventoryRanges::rangeStart(),
            CalendarInventoryRanges::rangeEnd(),
            ['seriesMaster', 'singleInstance'],
        );
        $diskExceptionsTotal = $this->countDiskInRangeByTypes(
            $diskIndex,
            CalendarInventoryRanges::rangeStart(),
            CalendarInventoryRanges::rangeEnd(),
            ['exception'],
        );
        $diskTotal = $diskIndex['in_inventory_range'];

        $totalDelta = $graphTotal >= 0 ? $diskSmSiTotal - $graphTotal : null;
        if ($graphTotal >= 0 && $totalDelta < 0) {
            $gaps[] = [
                'partition' => 'total',
                'issue' => 'disk_below_graph',
                'graph_count' => $graphTotal,
                'disk_series_master_single' => $diskSmSiTotal,
                'delta' => $totalDelta,
            ];
        }

        $backupState = is_file($statePath)
            ? json_decode((string) file_get_contents($statePath), true)
            : null;

        return [
            'calendar' => $label,
            'calendar_id' => $calendarId,
            'user_id' => $userId,
            'events_dir' => $eventsDir,
            'disk_file_count' => $diskIndex['total_files'],
            'disk_in_inventory_range' => $diskTotal,
            'disk_series_master_single' => $diskSmSiTotal,
            'disk_exceptions' => $diskExceptionsTotal,
            'disk_missing_createdDateTime' => $diskIndex['missing_createdDateTime'],
            'disk_outside_inventory_range' => $diskIndex['outside_range'],
            'disk_occurrence_files' => $diskIndex['occurrence_files'],
            'graph_series_master_total' => $graphSmTotal,
            'graph_single_instance_total' => $graphSiTotal,
            'graph_total_series_master_plus_single' => $graphTotal,
            'graph_partition_sum' => $graphPartitionSum,
            'graph_errors' => array_values(array_unique($graphErrors)),
            'backup_state' => is_array($backupState) ? [
                'complete' => $backupState['complete'] ?? null,
                'scanMode' => $backupState['scanMode'] ?? null,
                'eventCount' => $backupState['eventCount'] ?? null,
            ] : null,
            'backup_state_event_count_delta' => is_array($backupState) && isset($backupState['eventCount'])
                ? $diskIndex['total_files'] - (int) $backupState['eventCount']
                : null,
            'note' => 'Graph cannot $filter type=exception or type ne occurrence on calendar /events; '
                . 'compares seriesMaster+singleInstance counts. Exceptions on disk are listed separately.',
            'partitions' => $partitionResults,
            'gaps' => $gaps,
            'ok' => $gaps === [],
        ];
    }

    /**
     * @param list<string> $graphErrors
     */
    private function countGraphTyped(
        string $path,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        string $eventType,
        array &$graphErrors,
    ): int {
        $filter = CalendarInventoryRanges::inventoryEventFilterWithType($start, $end, $eventType);
        try {
            $data = $this->graph->get($path, [
                '$filter' => $filter,
                '$count' => 'true',
                '$top' => '1',
                '$select' => 'id',
            ], self::COUNT_HEADERS);

            if (array_key_exists('@odata.count', $data)) {
                return (int) $data['@odata.count'];
            }
            $graphErrors[] = "Missing @odata.count for type {$eventType}";
            return -1;
        } catch (\Throwable $e) {
            $graphErrors[] = $eventType . ': ' . $e->getMessage();
            return -1;
        }
    }

    /**
     * @return array{
     *   by_id: array<string, array{created: ?\DateTimeImmutable, type: string}>,
     *   total_files: int,
     *   in_inventory_range: int,
     *   outside_range: int,
     *   missing_createdDateTime: int,
     *   occurrence_files: int
     * }
     */
    private function indexDiskEvents(string $eventsDir): array
    {
        $index = [
            'by_id' => [],
            'total_files' => 0,
            'in_inventory_range' => 0,
            'outside_range' => 0,
            'missing_createdDateTime' => 0,
            'occurrence_files' => 0,
        ];
        if (!is_dir($eventsDir)) {
            return $index;
        }

        $rangeStart = CalendarInventoryRanges::rangeStart();
        $rangeEnd = CalendarInventoryRanges::rangeEnd();

        foreach (scandir($eventsDir) ?: [] as $file) {
            if (!str_ends_with($file, '.json')) {
                continue;
            }
            $index['total_files']++;
            $raw = json_decode((string) file_get_contents($eventsDir . '/' . $file), true);
            if (!is_array($raw)) {
                continue;
            }
            $event = is_array($raw['rawGraphJson'] ?? null) ? $raw['rawGraphJson'] : $raw;
            $type = (string) ($event['type'] ?? $raw['type'] ?? '');
            if ($type === 'occurrence') {
                $index['occurrence_files']++;
                continue;
            }
            $created = $this->parseInventoryDateTime($event);
            if ($created === null) {
                $index['missing_createdDateTime']++;
                $id = (string) ($event['id'] ?? $raw['immutableEventId'] ?? '');
                if ($id !== '') {
                    $index['by_id'][$id] = ['created' => null, 'type' => $type];
                }
                continue;
            }
            if ($created < $rangeStart || $created >= $rangeEnd) {
                $index['outside_range']++;
                continue;
            }
            $index['in_inventory_range']++;
            $id = (string) ($event['id'] ?? $raw['immutableEventId'] ?? '');
            if ($id !== '') {
                $index['by_id'][$id] = ['created' => $created, 'type' => $type];
            }
        }

        return $index;
    }

    /**
     * @param array<string, mixed> $diskIndex
     * @param list<string> $types
     */
    private function countDiskInRangeByTypes(
        array $diskIndex,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        array $types,
    ): int {
        $count = 0;
        foreach ($diskIndex['by_id'] as $meta) {
            if (!in_array($meta['type'], $types, true)) {
                continue;
            }
            $created = $meta['created'];
            if ($created !== null && $created >= $start && $created < $end) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Partition bucketing uses createdDateTime, then lastModifiedDateTime.
     *
     * @param array<string, mixed> $event
     */
    private function parseInventoryDateTime(array $event): ?\DateTimeImmutable
    {
        foreach (['createdDateTime', 'lastModifiedDateTime'] as $field) {
            $raw = $event[$field] ?? null;
            if (!is_string($raw) || $raw === '') {
                continue;
            }
            try {
                return new \DateTimeImmutable($raw);
            } catch (\Throwable) {
                continue;
            }
        }
        $start = $event['start'] ?? null;
        if (is_array($start) && !empty($start['dateTime']) && is_string($start['dateTime'])) {
            try {
                return new \DateTimeImmutable($start['dateTime']);
            } catch (\Throwable) {
            }
        }
        return null;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function formatCliReport(array $report): string
    {
        $lines = [];
        $lines[] = 'Calendar verify: ' . ($report['calendar'] ?? '');
        $lines[] = '  ' . ($report['note'] ?? '');
        $lines[] = '  Disk files: ' . ($report['disk_file_count'] ?? 0)
            . ' (in range: ' . ($report['disk_in_inventory_range'] ?? 0)
            . ', sm+si: ' . ($report['disk_series_master_single'] ?? 0)
            . ', exceptions: ' . ($report['disk_exceptions'] ?? 0) . ')';
        if (($report['disk_missing_createdDateTime'] ?? 0) > 0) {
            $lines[] = '  Warning: ' . $report['disk_missing_createdDateTime']
                . ' file(s) missing createdDateTime (excluded from partition match)';
        }
        $state = $report['backup_state'] ?? null;
        if (is_array($state)) {
            $lines[] = '  backup_state: complete=' . json_encode($state['complete'])
                . ' scanMode=' . ($state['scanMode'] ?? '')
                . ' eventCount=' . ($state['eventCount'] ?? '');
        }
        $gt = $report['graph_total_series_master_plus_single'] ?? -1;
        $lines[] = '  Graph total (seriesMaster+singleInstance): ' . ($gt >= 0 ? (string) $gt : 'n/a');
        $lines[] = '';
        $lines[] = sprintf(
            '  %-10s %5s %5s %5s %5s %5s %6s %s',
            'Year',
            'G_sm',
            'G_si',
            'D_sm',
            'D_x',
            'Delta',
            'OK',
            '',
        );
        foreach ($report['partitions'] ?? [] as $row) {
            $delta = $row['delta_disk_sm_si_minus_graph'];
            $lines[] = sprintf(
                '  %-10s %5s %5s %5d %5d %5s %6s',
                $row['partition'] ?? '',
                ($row['graph_series_master'] ?? -1) >= 0 ? (string) $row['graph_series_master'] : 'n/a',
                ($row['graph_single_instance'] ?? -1) >= 0 ? (string) $row['graph_single_instance'] : 'n/a',
                $row['disk_series_master_single'] ?? 0,
                $row['disk_exceptions'] ?? 0,
                $delta === null ? 'n/a' : (string) $delta,
                !empty($row['ok']) ? 'yes' : 'NO',
            );
        }
        if (($report['gaps'] ?? []) !== []) {
            $lines[] = '';
            $lines[] = 'Gaps:';
            foreach ($report['gaps'] as $gap) {
                $lines[] = '  - ' . json_encode($gap, JSON_UNESCAPED_SLASHES);
            }
        } else {
            $lines[] = '';
            $lines[] = 'OK: all year partitions match Graph $count for seriesMaster+singleInstance.';
        }
        return implode("\n", $lines);
    }

    /**
     * Compact summary for manifest.json and admin UI (omits per-year partition rows).
     *
     * @param array<string, mixed> $report
     * @return array<string, mixed>
     */
    public static function summarizeForManifest(array $report): array
    {
        $gaps = is_array($report['gaps'] ?? null) ? $report['gaps'] : [];
        return [
            'ok' => (bool) ($report['ok'] ?? false),
            'gap_count' => count($gaps),
            'gaps' => array_slice($gaps, 0, 25),
            'graph_total' => $report['graph_total_series_master_plus_single'] ?? null,
            'disk_series_master_single' => $report['disk_series_master_single'] ?? null,
            'disk_exceptions' => $report['disk_exceptions'] ?? null,
            'disk_file_count' => $report['disk_file_count'] ?? null,
            'disk_missing_createdDateTime' => $report['disk_missing_createdDateTime'] ?? 0,
            'total_delta' => ($report['graph_total_series_master_plus_single'] ?? -1) >= 0
                ? ($report['disk_series_master_single'] ?? 0) - (int) $report['graph_total_series_master_plus_single']
                : null,
        ];
    }
}
