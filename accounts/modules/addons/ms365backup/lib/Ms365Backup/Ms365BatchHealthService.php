<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\Ms365BatchLiveService;

require_once dirname(__DIR__, 3) . '/cloudstorage/lib/Ms365BackupBootstrap.php';

/**
 * Batch-level health summary for admin Jobs list and Live Run views.
 */
final class Ms365BatchHealthService
{
    /** Free space at or above disk watermark — healthy for admit-reject wedge detection. */
    private const HEALTHY_DISK_FREE_MIB = 4096;

    /**
     * @return array{
     *   owner_worker: ?array{
     *     hostname: string,
     *     node_id: string,
     *     disk_critical: bool,
     *     reserved_disk_mib: ?int,
     *     disk_free_mib: ?int,
     *     claim_admit_rejects: int
     *   },
     *   wedged_worker: bool,
     *   stalled_workload_count: int,
     *   health_warning: ?string
     * }
     */
    public static function summarizeForBatch(string $batchRunId): array
    {
        if (!self::isUuid($batchRunId) || !Ms365BatchClaimRepository::tableReady()) {
            return self::emptySummary();
        }

        $claim = Capsule::table('ms365_batch_claims')
            ->where('batch_run_id', $batchRunId)
            ->first(['worker_node_id']);
        $nodeId = trim((string) ($claim->worker_node_id ?? ''));
        $ownerWorker = $nodeId !== '' ? self::ownerWorkerFromNode($nodeId) : null;
        $stalledCount = self::countStalledRunningChildren($batchRunId);
        $wedged = self::isWorkerWedged($ownerWorker);

        return [
            'owner_worker' => $ownerWorker,
            'wedged_worker' => $wedged,
            'stalled_workload_count' => $stalledCount,
            'health_warning' => self::formatHealthWarning($wedged, $ownerWorker, $stalledCount),
        ];
    }

    /**
     * @param list<string> $batchRunIds
     * @return array<string, array{wedged_worker: bool, stalled_workload_count: int, health_warning: ?string}>
     */
    public static function summarizeForBatches(array $batchRunIds): array
    {
        $batchRunIds = array_values(array_unique(array_filter($batchRunIds, static fn (string $id): bool => self::isUuid($id))));
        $out = [];
        foreach ($batchRunIds as $runId) {
            $out[$runId] = [
                'wedged_worker' => false,
                'stalled_workload_count' => 0,
                'health_warning' => null,
            ];
        }
        if ($batchRunIds === [] || !Ms365BatchClaimRepository::tableReady()) {
            return $out;
        }

        $claimsByBatch = [];
        $nodeIds = [];
        foreach (Capsule::table('ms365_batch_claims')
            ->whereIn('batch_run_id', $batchRunIds)
            ->get(['batch_run_id', 'worker_node_id']) as $row) {
            $batchId = (string) ($row->batch_run_id ?? '');
            $nodeId = trim((string) ($row->worker_node_id ?? ''));
            if ($batchId === '') {
                continue;
            }
            $claimsByBatch[$batchId] = $nodeId;
            if ($nodeId !== '') {
                $nodeIds[$nodeId] = true;
            }
        }

        $nodesById = self::loadOwnerWorkers(array_keys($nodeIds));
        $stalledByBatch = self::stalledCountsForBatches($batchRunIds);

        foreach ($batchRunIds as $runId) {
            $nodeId = $claimsByBatch[$runId] ?? '';
            $ownerWorker = $nodeId !== '' ? ($nodesById[$nodeId] ?? null) : null;
            $stalledCount = $stalledByBatch[$runId] ?? 0;
            $wedged = self::isWorkerWedged($ownerWorker);
            $out[$runId] = [
                'wedged_worker' => $wedged,
                'stalled_workload_count' => $stalledCount,
                'health_warning' => self::formatHealthWarning($wedged, $ownerWorker, $stalledCount),
            ];
        }

        return $out;
    }

    /**
     * @param ?array{
     *   disk_critical?: bool|int,
     *   claim_admit_rejects?: int,
     *   disk_free_mib?: ?int
     * } $ownerWorker
     */
    public static function isWorkerWedged(?array $ownerWorker): bool
    {
        if ($ownerWorker === null) {
            return false;
        }
        if (!empty($ownerWorker['disk_critical'])) {
            return true;
        }
        $rejects = (int) ($ownerWorker['claim_admit_rejects'] ?? 0);
        if ($rejects <= 0) {
            return false;
        }
        $free = $ownerWorker['disk_free_mib'] ?? null;
        if ($free === null) {
            return false;
        }

        return (int) $free >= self::HEALTHY_DISK_FREE_MIB;
    }

    public static function countStalledRunningChildren(string $batchRunId): int
    {
        if (!self::isUuid($batchRunId)) {
            return 0;
        }

        $counts = self::stalledCountsForBatches([$batchRunId]);

        return (int) ($counts[$batchRunId] ?? 0);
    }

    /**
     * @param ?array{disk_critical?: bool|int} $ownerWorker
     */
    public static function formatHealthWarning(bool $wedged, ?array $ownerWorker, int $stalledCount): ?string
    {
        $parts = [];
        if ($wedged) {
            if (!empty($ownerWorker['disk_critical'])) {
                $parts[] = 'Owner worker disk pressure latch';
            } else {
                $parts[] = 'Owner worker rejecting admits despite healthy disk free space';
            }
        }
        if ($stalledCount > 0) {
            $parts[] = $stalledCount . ' workload' . ($stalledCount === 1 ? '' : 's') . ' with no progress';
        }

        return $parts === [] ? null : implode('; ', $parts);
    }

    /**
     * @return array{
     *   owner_worker: null,
     *   wedged_worker: false,
     *   stalled_workload_count: 0,
     *   health_warning: null
     * }
     */
    private static function emptySummary(): array
    {
        return [
            'owner_worker' => null,
            'wedged_worker' => false,
            'stalled_workload_count' => 0,
            'health_warning' => null,
        ];
    }

    /** @return ?array{hostname: string, node_id: string, disk_critical: bool, reserved_disk_mib: ?int, disk_free_mib: ?int, claim_admit_rejects: int} */
    private static function ownerWorkerFromNode(string $nodeId): ?array
    {
        $nodes = self::loadOwnerWorkers([$nodeId]);

        return $nodes[$nodeId] ?? null;
    }

    /**
     * @param list<string> $nodeIds
     * @return array<string, array{hostname: string, node_id: string, disk_critical: bool, reserved_disk_mib: ?int, disk_free_mib: ?int, claim_admit_rejects: int}>
     */
    private static function loadOwnerWorkers(array $nodeIds): array
    {
        $nodeIds = array_values(array_unique(array_filter($nodeIds, static fn (string $id): bool => $id !== '')));
        if ($nodeIds === [] || !Capsule::schema()->hasTable('ms365_worker_nodes')) {
            return [];
        }

        $select = ['node_id', 'hostname', 'claim_admit_rejects', 'disk_free_mib'];
        if (Capsule::schema()->hasColumn('ms365_worker_nodes', 'disk_critical')) {
            $select[] = 'disk_critical';
        }
        if (Capsule::schema()->hasColumn('ms365_worker_nodes', 'reserved_disk_mib')) {
            $select[] = 'reserved_disk_mib';
        }

        $out = [];
        foreach (Capsule::table('ms365_worker_nodes')->whereIn('node_id', $nodeIds)->get($select) as $row) {
            $nodeId = (string) ($row->node_id ?? '');
            if ($nodeId === '') {
                continue;
            }
            $reserved = isset($row->reserved_disk_mib) ? (int) $row->reserved_disk_mib : null;
            $out[$nodeId] = [
                'hostname' => (string) ($row->hostname ?? ''),
                'node_id' => $nodeId,
                'disk_critical' => !empty($row->disk_critical),
                'reserved_disk_mib' => $reserved,
                'disk_free_mib' => isset($row->disk_free_mib) ? (int) $row->disk_free_mib : null,
                'claim_admit_rejects' => (int) ($row->claim_admit_rejects ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @param list<string> $batchRunIds
     * @return array<string, int>
     */
    private static function stalledCountsForBatches(array $batchRunIds): array
    {
        $out = [];
        foreach ($batchRunIds as $runId) {
            $out[$runId] = 0;
        }
        if ($batchRunIds === []) {
            return $out;
        }

        $threshold = time() - Ms365BatchLiveService::WORKLOAD_ACTIVE_PROGRESS_SECONDS;

        if (Capsule::schema()->hasTable('ms365_backup_runs')) {
            // Floor with started_at so a fresh attempt is not counted stalled from a
            // prior attempt's last_progress_at (same rule as progressFreshnessAt).
            $freshnessExpr = Capsule::schema()->hasColumn('ms365_backup_runs', 'last_progress_at')
                ? 'GREATEST(COALESCE(NULLIF(last_progress_at, 0), 0), COALESCE(NULLIF(started_at, 0), 0), COALESCE(updated_at, 0))'
                : 'GREATEST(COALESCE(NULLIF(started_at, 0), 0), COALESCE(updated_at, 0))';
            foreach (Capsule::table('ms365_backup_runs')
                ->select([
                    'e3_batch_run_id',
                    Capsule::raw('COUNT(*) as stalled'),
                ])
                ->whereIn('e3_batch_run_id', $batchRunIds)
                ->whereIn('status', ['running', 'starting'])
                ->whereRaw($freshnessExpr . ' <= ?', [$threshold])
                ->groupBy('e3_batch_run_id')
                ->get() as $row) {
                $batchId = (string) ($row->e3_batch_run_id ?? '');
                if ($batchId !== '') {
                    $out[$batchId] = ($out[$batchId] ?? 0) + (int) ($row->stalled ?? 0);
                }
            }
        }

        if (Capsule::schema()->hasTable('ms365_restore_runs')
            && Capsule::schema()->hasColumn('ms365_restore_runs', 'e3_batch_run_id')) {
            $restoreFreshness = Capsule::schema()->hasColumn('ms365_restore_runs', 'last_progress_at')
                ? 'COALESCE(NULLIF(last_progress_at, 0), updated_at)'
                : 'updated_at';
            foreach (Capsule::table('ms365_restore_runs')
                ->select([
                    'e3_batch_run_id',
                    Capsule::raw('COUNT(*) as stalled'),
                ])
                ->whereIn('e3_batch_run_id', $batchRunIds)
                ->whereIn('status', ['running', 'starting'])
                ->whereRaw($restoreFreshness . ' <= ?', [$threshold])
                ->groupBy('e3_batch_run_id')
                ->get() as $row) {
                $batchId = (string) ($row->e3_batch_run_id ?? '');
                if ($batchId !== '') {
                    $out[$batchId] = ($out[$batchId] ?? 0) + (int) ($row->stalled ?? 0);
                }
            }
        }

        return $out;
    }

    private static function isUuid(string $id): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id) === 1;
    }
}
