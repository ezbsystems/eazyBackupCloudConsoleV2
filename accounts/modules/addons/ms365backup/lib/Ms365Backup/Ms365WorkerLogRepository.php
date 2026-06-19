<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;

final class Ms365WorkerLogRepository
{
    private const MAX_LINES_PER_RUN = 10000;

    public static function tablesReady(): bool
    {
        return Capsule::schema()->hasTable('ms365_run_worker_assignments')
            && Capsule::schema()->hasTable('ms365_worker_log_lines');
    }

    public static function recordAssignment(string $runId, string $workerNodeId): void
    {
        if (!self::tablesReady() || $runId === '' || $workerNodeId === '') {
            return;
        }
        Capsule::table('ms365_run_worker_assignments')->insert([
            'run_id' => $runId,
            'worker_node_id' => $workerNodeId,
            'claimed_at' => time(),
            'released_at' => null,
            'release_reason' => null,
        ]);
    }

    public static function releaseAssignment(string $runId, string $reason): void
    {
        if (!self::tablesReady() || $runId === '') {
            return;
        }
        $now = time();
        Capsule::table('ms365_run_worker_assignments')
            ->where('run_id', $runId)
            ->whereNull('released_at')
            ->update([
                'released_at' => $now,
                'release_reason' => mb_substr($reason, 0, 64),
            ]);
    }

    /**
     * @param list<array{level?: string, message: string, ts?: int}> $lines
     */
    public static function insertLogLines(string $runId, string $workerNodeId, array $lines): int
    {
        if (!self::tablesReady() || $runId === '' || $workerNodeId === '' || $lines === []) {
            return 0;
        }
        $now = time();
        $inserted = 0;
        foreach ($lines as $line) {
            $message = trim((string) ($line['message'] ?? ''));
            if ($message === '') {
                continue;
            }
            $level = strtolower(trim((string) ($line['level'] ?? 'info')));
            if ($level === '') {
                $level = 'info';
            }
            $createdAt = (int) ($line['ts'] ?? $now);
            Capsule::table('ms365_worker_log_lines')->insert([
                'run_id' => $runId,
                'worker_node_id' => $workerNodeId,
                'level' => mb_substr($level, 0, 16),
                'message' => mb_substr($message, 0, 65535),
                'created_at' => $createdAt > 0 ? $createdAt : $now,
            ]);
            ++$inserted;
        }
        self::pruneRunLogs($runId);

        return $inserted;
    }

    private static function pruneRunLogs(string $runId): void
    {
        $count = (int) Capsule::table('ms365_worker_log_lines')->where('run_id', $runId)->count();
        if ($count <= self::MAX_LINES_PER_RUN) {
            return;
        }
        $excess = $count - self::MAX_LINES_PER_RUN;
        $ids = Capsule::table('ms365_worker_log_lines')
            ->where('run_id', $runId)
            ->orderBy('id')
            ->limit($excess)
            ->pluck('id')
            ->all();
        if ($ids !== []) {
            Capsule::table('ms365_worker_log_lines')->whereIn('id', $ids)->delete();
        }
    }

    /**
     * @param list<string> $childRunIds
     * @return list<array<string, mixed>>
     */
    public static function getLogLinesForRuns(array $childRunIds, int $limit = 5000): array
    {
        if (!self::tablesReady() || $childRunIds === []) {
            return [];
        }
        $childRunIds = array_values(array_unique(array_filter(array_map('strval', $childRunIds))));
        $nodes = [];
        if (Capsule::schema()->hasTable('ms365_worker_nodes')) {
            foreach (Capsule::table('ms365_worker_nodes')->get() as $n) {
                $nodes[(string) $n->node_id] = (array) $n;
            }
        }
        $rows = Capsule::table('ms365_worker_log_lines')
            ->whereIn('run_id', $childRunIds)
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();
        $out = [];
        foreach ($rows as $row) {
            $arr = (array) $row;
            $nodeId = (string) ($arr['worker_node_id'] ?? '');
            $node = $nodes[$nodeId] ?? [];
            $out[] = [
                'id' => (int) ($arr['id'] ?? 0),
                'run_id' => (string) ($arr['run_id'] ?? ''),
                'worker_node_id' => $nodeId,
                'hostname' => (string) ($node['hostname'] ?? ''),
                'proxmox_vmid' => (int) ($node['proxmox_vmid'] ?? 0),
                'level' => (string) ($arr['level'] ?? 'info'),
                'message' => (string) ($arr['message'] ?? ''),
                'created_at' => (int) ($arr['created_at'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @param list<string> $childRunIds
     * @return list<array<string, mixed>>
     */
    public static function getAssignmentsForRuns(array $childRunIds): array
    {
        if (!self::tablesReady() || $childRunIds === []) {
            return [];
        }
        $childRunIds = array_values(array_unique(array_filter(array_map('strval', $childRunIds))));
        $nodes = [];
        if (Capsule::schema()->hasTable('ms365_worker_nodes')) {
            foreach (Capsule::table('ms365_worker_nodes')->get() as $n) {
                $nodes[(string) $n->node_id] = (array) $n;
            }
        }
        $rows = Capsule::table('ms365_run_worker_assignments')
            ->whereIn('run_id', $childRunIds)
            ->orderBy('claimed_at')
            ->get();
        $out = [];
        foreach ($rows as $row) {
            $arr = (array) $row;
            $nodeId = (string) ($arr['worker_node_id'] ?? '');
            $node = $nodes[$nodeId] ?? [];
            $out[] = [
                'run_id' => (string) ($arr['run_id'] ?? ''),
                'worker_node_id' => $nodeId,
                'hostname' => (string) ($node['hostname'] ?? ''),
                'proxmox_vmid' => (int) ($node['proxmox_vmid'] ?? 0),
                'claimed_at' => (int) ($arr['claimed_at'] ?? 0),
                'released_at' => isset($arr['released_at']) ? (int) $arr['released_at'] : null,
                'release_reason' => (string) ($arr['release_reason'] ?? ''),
            ];
        }

        return $out;
    }

    public static function runRecentlyClaimedByNode(string $runId, string $workerNodeId, int $withinSeconds = 86400): bool
    {
        if (!self::tablesReady() || $runId === '' || $workerNodeId === '') {
            return false;
        }
        $cutoff = time() - max(60, $withinSeconds);

        return Capsule::table('ms365_run_worker_assignments')
            ->where('run_id', $runId)
            ->where('worker_node_id', $workerNodeId)
            ->where('claimed_at', '>=', $cutoff)
            ->exists();
    }

    public static function isRunActiveOnNode(string $runId, string $workerNodeId): bool
    {
        if (!self::tablesReady() || $runId === '' || $workerNodeId === '') {
            return false;
        }

        return Capsule::table('ms365_run_worker_assignments')
            ->where('run_id', $runId)
            ->where('worker_node_id', $workerNodeId)
            ->whereNull('released_at')
            ->exists();
    }
}
