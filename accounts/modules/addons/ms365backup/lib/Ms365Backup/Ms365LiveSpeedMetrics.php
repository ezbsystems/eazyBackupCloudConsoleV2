<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * Phase-aware live throughput for MS365 batch parent runs (EMA-smoothed).
 */
final class Ms365LiveSpeedMetrics
{
    public const STALE_SECONDS = 30;
    public const EMA_ALPHA = 0.3;

    public const KIND_ITEMS = 'items';
    public const KIND_GRAPH_REQUESTS = 'graph_requests';
    public const KIND_UPLOAD = 'upload';
    public const KIND_HASH = 'hash';
    public const KIND_NONE = 'none';

    /**
     * @param array<string, mixed> $statsJson
     * @param array<string, mixed> $agg
     * @return array{
     *   stats_json: array<string, mixed>,
     *   speed_bytes_per_sec: ?int,
     *   speed_metric_kind: string,
     *   speed_metric_label: string,
     *   speed_updated_at: ?int,
     *   items_per_sec: ?int,
     *   graph_requests_per_sec: ?int,
     *   eta_seconds: ?int
     * }
     */
    public static function update(array $statsJson, array $agg, int $now): array
    {
        $dominantPhase = (string) ($agg['dominant_phase'] ?? '');
        $byteStatsComparable = !empty($agg['byte_stats_comparable']);

        $lastTs = (int) ($statsJson['ms365_last_ts'] ?? 0);
        $lastProcessed = (int) ($statsJson['ms365_last_bytes_processed'] ?? 0);
        $lastTransferred = (int) ($statsJson['ms365_last_bytes'] ?? 0);
        $lastItems = (int) ($statsJson['ms365_last_items'] ?? 0);
        $lastGraphRequests = (int) ($statsJson['ms365_speed_last_graph_requests'] ?? 0);

        $currentProcessed = (int) ($agg['bytes_processed'] ?? 0);
        $currentTransferred = (int) ($agg['bytes_transferred'] ?? 0);
        $currentItems = (int) ($agg['objects_transferred'] ?? 0);
        $currentGraphRequests = (int) ($agg['graph_requests_total'] ?? 0);
        $bytesTotal = (int) ($agg['bytes_total'] ?? 0);

        $selection = self::selectKindAndInstant(
            $dominantPhase,
            $byteStatsComparable,
            $lastTs,
            $now,
            $lastProcessed,
            $currentProcessed,
            $lastTransferred,
            $currentTransferred,
            $lastItems,
            $currentItems,
            $lastGraphRequests,
            $currentGraphRequests,
        );

        $kind = $selection['kind'];
        $instant = $selection['instant'];
        $prevKind = (string) ($statsJson['ms365_speed_metric_kind'] ?? self::KIND_NONE);
        $prevEma = isset($statsJson['ms365_speed_ema']) ? (int) $statsJson['ms365_speed_ema'] : null;

        $speedUpdatedAt = isset($statsJson['ms365_speed_updated_at'])
            ? (int) $statsJson['ms365_speed_updated_at']
            : null;

        if ($instant === null || $instant <= 0 || $kind === self::KIND_NONE) {
            $statsJson['ms365_speed_ema'] = null;
            $statsJson['ms365_speed_metric_kind'] = self::KIND_NONE;
            $statsJson['ms365_items_per_sec'] = null;
            $statsJson['ms365_graph_requests_per_sec'] = null;

            self::persistSnapshotCounters(
                $statsJson,
                $currentProcessed,
                $currentTransferred,
                $currentItems,
                $currentGraphRequests,
                $now,
            );

            return [
                'stats_json' => $statsJson,
                'speed_bytes_per_sec' => null,
                'speed_metric_kind' => self::KIND_NONE,
                'speed_metric_label' => self::labelForKind(self::KIND_NONE),
                'speed_updated_at' => $speedUpdatedAt,
                'items_per_sec' => null,
                'graph_requests_per_sec' => null,
                'eta_seconds' => null,
            ];
        }

        if ($prevKind !== $kind || $prevEma === null || $prevEma <= 0) {
            $ema = $instant;
        } else {
            $ema = self::applyEma($prevEma, $instant);
        }

        $statsJson['ms365_speed_ema'] = $ema;
        $statsJson['ms365_speed_metric_kind'] = $kind;
        $statsJson['ms365_speed_updated_at'] = $now;
        $speedUpdatedAt = $now;

        $speedBytesPerSec = null;
        $itemsPerSec = null;
        $graphRequestsPerSec = null;
        $etaSeconds = null;

        if ($kind === self::KIND_UPLOAD || $kind === self::KIND_HASH) {
            $speedBytesPerSec = $ema;
            $statsJson['ms365_items_per_sec'] = null;
            $statsJson['ms365_graph_requests_per_sec'] = null;
            $remainingBytes = max(0, $bytesTotal - ($kind === self::KIND_UPLOAD ? $currentTransferred : $currentProcessed));
            if ($ema > 0 && $remainingBytes > 0) {
                $etaSeconds = (int) ceil($remainingBytes / $ema);
            }
        } elseif ($kind === self::KIND_ITEMS) {
            $itemsPerSec = $ema;
            $statsJson['ms365_items_per_sec'] = $ema;
            $statsJson['ms365_graph_requests_per_sec'] = null;
        } elseif ($kind === self::KIND_GRAPH_REQUESTS) {
            $graphRequestsPerSec = $ema;
            $statsJson['ms365_graph_requests_per_sec'] = $ema;
            $statsJson['ms365_items_per_sec'] = null;
        }

        self::persistSnapshotCounters(
            $statsJson,
            $currentProcessed,
            $currentTransferred,
            $currentItems,
            $currentGraphRequests,
            $now,
        );

        return [
            'stats_json' => $statsJson,
            'speed_bytes_per_sec' => $speedBytesPerSec,
            'speed_metric_kind' => $kind,
            'speed_metric_label' => self::labelForKind($kind),
            'speed_updated_at' => $speedUpdatedAt,
            'items_per_sec' => $itemsPerSec,
            'graph_requests_per_sec' => $graphRequestsPerSec,
            'eta_seconds' => $etaSeconds,
        ];
    }

    public static function labelForKind(string $kind): string
    {
        return match ($kind) {
            self::KIND_ITEMS => 'Items/s',
            self::KIND_GRAPH_REQUESTS => 'Graph requests/s',
            self::KIND_UPLOAD => 'Upload speed',
            self::KIND_HASH => 'Hash speed',
            default => 'Speed',
        };
    }

    public static function hintForKind(string $kind): string
    {
        return match ($kind) {
            self::KIND_ITEMS => 'Items enumerated from Microsoft 365',
            self::KIND_GRAPH_REQUESTS => 'Microsoft Graph API activity',
            self::KIND_UPLOAD => 'Data sent to cloud storage',
            self::KIND_HASH => 'Data hashed before deduplication',
            default => '',
        };
    }

    public static function applyEma(int $prevEma, int $instant): int
    {
        return (int) round(self::EMA_ALPHA * $instant + (1 - self::EMA_ALPHA) * $prevEma);
    }

    /**
     * @return array{kind: string, instant: ?int}
     */
    private static function selectKindAndInstant(
        string $dominantPhase,
        bool $byteStatsComparable,
        int $lastTs,
        int $now,
        int $lastProcessed,
        int $currentProcessed,
        int $lastTransferred,
        int $currentTransferred,
        int $lastItems,
        int $currentItems,
        int $lastGraphRequests,
        int $currentGraphRequests,
    ): array {
        if ($lastTs <= 0 || $now <= $lastTs) {
            return ['kind' => self::KIND_NONE, 'instant' => null];
        }

        $elapsed = max(1, $now - $lastTs);

        if (Ms365BatchRunRepository::isGraphBoundPhase($dominantPhase)) {
            if ($currentItems > $lastItems) {
                return [
                    'kind' => self::KIND_ITEMS,
                    'instant' => (int) round(($currentItems - $lastItems) / $elapsed),
                ];
            }
            if ($currentGraphRequests > $lastGraphRequests) {
                return [
                    'kind' => self::KIND_GRAPH_REQUESTS,
                    'instant' => (int) round(($currentGraphRequests - $lastGraphRequests) / $elapsed),
                ];
            }

            return ['kind' => self::KIND_NONE, 'instant' => null];
        }

        if (Ms365BatchRunRepository::isUploadLikePhase($dominantPhase)) {
            if ($currentTransferred > $lastTransferred) {
                return [
                    'kind' => self::KIND_UPLOAD,
                    'instant' => (int) round(($currentTransferred - $lastTransferred) / $elapsed),
                ];
            }
            if ($currentProcessed > $lastProcessed) {
                return [
                    'kind' => self::KIND_HASH,
                    'instant' => (int) round(($currentProcessed - $lastProcessed) / $elapsed),
                ];
            }

            return ['kind' => self::KIND_NONE, 'instant' => null];
        }

        if ($byteStatsComparable) {
            if ($currentTransferred > $lastTransferred) {
                return [
                    'kind' => self::KIND_UPLOAD,
                    'instant' => (int) round(($currentTransferred - $lastTransferred) / $elapsed),
                ];
            }
            if ($currentProcessed > $lastProcessed) {
                return [
                    'kind' => self::KIND_HASH,
                    'instant' => (int) round(($currentProcessed - $lastProcessed) / $elapsed),
                ];
            }
        }

        return ['kind' => self::KIND_NONE, 'instant' => null];
    }

    /** @param array<string, mixed> $statsJson */
    private static function persistSnapshotCounters(
        array &$statsJson,
        int $currentProcessed,
        int $currentTransferred,
        int $currentItems,
        int $currentGraphRequests,
        int $now,
    ): void {
        $statsJson['ms365_last_bytes_processed'] = $currentProcessed;
        $statsJson['ms365_last_bytes'] = $currentTransferred;
        $statsJson['ms365_last_items'] = $currentItems;
        $statsJson['ms365_speed_last_graph_requests'] = $currentGraphRequests;
        $statsJson['ms365_last_ts'] = $now;
    }
}
