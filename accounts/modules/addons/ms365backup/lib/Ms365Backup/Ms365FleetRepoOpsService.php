<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\KopiaRetentionOperationService;

final class Ms365FleetRepoOpsService
{
    private const ACTIVE_LIMIT = 25;
    private const RECENT_LIMIT = 50;
    private const REPO_LIMIT = 100;

    /** @return array{active: list<array<string,mixed>>, recent: list<array<string,mixed>>, repos: list<array<string,mixed>>} */
    public static function listForFleet(): array
    {
        if (!Capsule::schema()->hasTable('s3_kopia_repo_operations')
            || !Capsule::schema()->hasTable('s3_kopia_repos')) {
            return ['active' => [], 'recent' => [], 'repos' => []];
        }

        $base = Capsule::table('s3_kopia_repo_operations as op')
            ->join('s3_kopia_repos as r', 'r.id', '=', 'op.repo_id')
            ->where('r.repository_id', 'like', 'ms365:%');

        $select = [
            'op.id', 'op.repo_id', 'op.op_type', 'op.status', 'op.attempt_count',
            'op.created_at', 'op.updated_at', 'op.result_json',
            'r.repository_id', 'r.client_id',
        ];
        if (Capsule::schema()->hasColumn('s3_kopia_repo_operations', 'claimed_by_node_id')) {
            $select[] = 'op.claimed_by_node_id';
        }

        $activeRows = (clone $base)
            ->whereIn('op.status', ['queued', 'running'])
            ->orderByRaw("FIELD(op.status, 'running', 'queued')")
            ->orderBy('op.created_at')
            ->limit(self::ACTIVE_LIMIT)
            ->get($select);

        $recentRows = (clone $base)
            ->orderByDesc('op.id')
            ->limit(self::RECENT_LIMIT)
            ->get($select);

        $repos = Capsule::table('s3_kopia_repos')
            ->where('status', 'active')
            ->where('repository_id', 'like', 'ms365:%')
            ->orderByDesc('id')
            ->limit(self::REPO_LIMIT)
            ->get(['id', 'repository_id', 'client_id']);

        return [
            'active' => array_map([self::class, 'mapOpRow'], $activeRows->all()),
            'recent' => array_map([self::class, 'mapOpRow'], $recentRows->all()),
            'repos' => array_map(static fn ($r) => [
                'id' => (int) $r->id,
                'repository_id' => (string) $r->repository_id,
                'client_id' => (int) ($r->client_id ?? 0),
            ], $repos->all()),
        ];
    }

    /** @return array{ok: bool, status?: string, operation_id?: int, message?: string, error?: string} */
    public static function enqueue(int $repoId, string $opType): array
    {
        $allowed = ['maintenance_quick', 'maintenance_full', 'retention_apply'];
        if ($repoId <= 0 || !in_array($opType, $allowed, true)) {
            return ['ok' => false, 'error' => 'repo_id and valid op_type required'];
        }
        if (!Capsule::schema()->hasTable('s3_kopia_repos')
            || !Capsule::schema()->hasTable('s3_kopia_repo_operations')) {
            return ['ok' => false, 'error' => 'repo operations schema unavailable'];
        }

        $repo = Capsule::table('s3_kopia_repos')->where('id', $repoId)->first();
        if ($repo === null) {
            return ['ok' => false, 'error' => 'repo not found'];
        }
        $repositoryId = (string) ($repo->repository_id ?? '');
        if (!str_starts_with($repositoryId, 'ms365:')) {
            return ['ok' => false, 'error' => 'repo is not an MS365 repository'];
        }

        $active = Capsule::table('s3_kopia_repo_operations')
            ->where('repo_id', $repoId)
            ->whereIn('status', ['queued', 'running'])
            ->orderByDesc('id')
            ->first(['id', 'op_type', 'status']);
        if ($active !== null) {
            $existingId = (int) $active->id;

            return [
                'ok' => false,
                'error' => 'Repo already has an active operation (#' . $existingId . ', '
                    . (string) $active->op_type . ', ' . (string) $active->status . ')',
                'operation_id' => $existingId,
            ];
        }

        $payload = ['repo_id' => $repoId, 'engine' => 'ms365', 'reason' => 'fleet_dashboard_enqueue'];
        $prior = Capsule::table('s3_kopia_repo_operations')
            ->where('repo_id', $repoId)
            ->orderByDesc('id')
            ->limit(20)
            ->get(['payload_json']);
        foreach ($prior as $row) {
            $decoded = json_decode((string) ($row->payload_json ?? ''), true);
            if (!is_array($decoded)) {
                continue;
            }
            $jobId = trim((string) ($decoded['e3_job_id'] ?? ''));
            if ($jobId !== '') {
                $payload['e3_job_id'] = $jobId;
                break;
            }
        }

        $token = sprintf(
            'ms365-fleet-%s-%d-%s-%s',
            $opType,
            $repoId,
            gmdate('YmdHis'),
            bin2hex(random_bytes(2))
        );

        $result = KopiaRetentionOperationService::enqueue($repoId, $opType, $payload, $token);
        $status = (string) ($result['status'] ?? 'error');
        if (!in_array($status, ['success', 'duplicate'], true)) {
            return ['ok' => false, 'error' => 'enqueue failed', 'status' => $status];
        }

        return [
            'ok' => true,
            'status' => $status,
            'operation_id' => (int) ($result['operation_id'] ?? 0),
            'message' => $status === 'duplicate'
                ? 'Operation already queued (duplicate token)'
                : 'Enqueued operation #' . (int) ($result['operation_id'] ?? 0),
        ];
    }

    /** @param object $row */
    private static function mapOpRow(object $row): array
    {
        $result = [];
        if (!empty($row->result_json)) {
            $decoded = json_decode((string) $row->result_json, true);
            if (is_array($decoded)) {
                $result = $decoded;
            }
        }
        $created = (string) ($row->created_at ?? '');
        $updated = (string) ($row->updated_at ?? '');
        $duration = null;
        $start = $created !== '' ? strtotime($created) : false;
        $end = $updated !== '' ? strtotime($updated) : false;
        if ($start !== false && $end !== false && $end >= $start) {
            $duration = $end - $start;
        }

        return [
            'id' => (int) $row->id,
            'repo_id' => (int) $row->repo_id,
            'repository_id' => (string) ($row->repository_id ?? ''),
            'op_type' => (string) ($row->op_type ?? ''),
            'status' => (string) ($row->status ?? ''),
            'attempt_count' => (int) ($row->attempt_count ?? 0),
            'claimed_by_node_id' => isset($row->claimed_by_node_id) ? (string) $row->claimed_by_node_id : '',
            'phase' => (string) ($result['phase'] ?? ''),
            'effective_mode' => (string) ($result['effective_mode'] ?? ''),
            'index_blobs_before' => isset($result['index_blobs_before']) ? (int) $result['index_blobs_before'] : null,
            'index_blobs_after' => isset($result['index_blobs_after']) ? (int) $result['index_blobs_after'] : null,
            'escalated' => !empty($result['escalated']),
            'skipped' => !empty($result['skipped']),
            'duration_seconds' => $duration,
            'created_at' => $created,
            'updated_at' => $updated,
            'error' => (string) ($result['error'] ?? ''),
        ];
    }
}
