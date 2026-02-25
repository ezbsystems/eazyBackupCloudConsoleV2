<?php

namespace WHMCS\Module\Addon\CloudStorage\Admin;

use WHMCS\Database\Capsule;

class CloudBackupAdminController {

    private static function getModuleSetting(string $key, $default = null)
    {
        try {
            $val = Capsule::table('tbladdonmodules')
                ->where('module', 'cloudstorage')
                ->where('setting', $key)
                ->value('value');
            return ($val !== null && $val !== '') ? $val : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    private static function getOnlineThresholdSeconds(): int
    {
        $seconds = (int) self::getModuleSetting('cloudbackup_agent_online_threshold_seconds', 180);
        return $seconds > 0 ? $seconds : 180;
    }

    private static function buildAgentsBaseQuery(array $filters = [])
    {
        $hasTenants = Capsule::schema()->hasTable('s3_backup_tenants');
        $hasAgentVersion = Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'agent_version');
        $hasAgentOs = Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'agent_os');
        $hasAgentArch = Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'agent_arch');
        $hasAgentBuild = Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'agent_build');
        $hasMetadataUpdatedAt = Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'metadata_updated_at');
        $query = Capsule::table('s3_cloudbackup_agents as a')
            ->join('tblclients as c', 'a.client_id', '=', 'c.id')
            ->select([
                'a.id',
                'a.client_id',
                'a.hostname',
                'a.device_id',
                'a.device_name',
                'a.install_id',
                'a.status',
                'a.agent_type',
                'a.tenant_id',
                'a.tenant_user_id',
                'a.last_seen_at',
                'a.created_at',
                'a.updated_at',
                'a.volumes_updated_at',
                'c.firstname',
                'c.lastname',
                'c.email',
                Capsule::raw('TIMESTAMPDIFF(SECOND, a.last_seen_at, NOW()) as seconds_since_seen'),
                Capsule::raw("(SELECT r.id FROM s3_cloudbackup_runs r WHERE r.agent_id = a.id ORDER BY r.created_at DESC LIMIT 1) as latest_run_id"),
                Capsule::raw("(SELECT r.status FROM s3_cloudbackup_runs r WHERE r.agent_id = a.id ORDER BY r.created_at DESC LIMIT 1) as latest_run_status"),
                Capsule::raw("(SELECT r.id FROM s3_cloudbackup_runs r WHERE r.agent_id = a.id AND r.status IN ('queued','starting','running') ORDER BY r.created_at DESC LIMIT 1) as active_run_id"),
            ]);

        $query->addSelect($hasAgentVersion ? 'a.agent_version' : Capsule::raw('NULL as agent_version'));
        $query->addSelect($hasAgentOs ? 'a.agent_os' : Capsule::raw('NULL as agent_os'));
        $query->addSelect($hasAgentArch ? 'a.agent_arch' : Capsule::raw('NULL as agent_arch'));
        $query->addSelect($hasAgentBuild ? 'a.agent_build' : Capsule::raw('NULL as agent_build'));
        $query->addSelect($hasMetadataUpdatedAt ? 'a.metadata_updated_at' : Capsule::raw('NULL as metadata_updated_at'));

        if ($hasTenants) {
            $query->leftJoin('s3_backup_tenants as t', 'a.tenant_id', '=', 't.id')
                ->addSelect('t.name as tenant_name');
        } else {
            $query->addSelect(Capsule::raw('NULL as tenant_name'));
        }

        if (!empty($filters['client_id'])) {
            $query->where('a.client_id', (int) $filters['client_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('a.status', $filters['status']);
        }

        if (!empty($filters['agent_type'])) {
            $query->where('a.agent_type', $filters['agent_type']);
        }

        if (isset($filters['tenant_id']) && $filters['tenant_id'] !== '') {
            if ($filters['tenant_id'] === 'direct') {
                $query->whereNull('a.tenant_id');
            } else {
                $query->where('a.tenant_id', (int) $filters['tenant_id']);
            }
        }

        if (isset($filters['online_status']) && $filters['online_status'] !== '') {
            $threshold = self::getOnlineThresholdSeconds();
            if ($filters['online_status'] === 'never') {
                $query->whereNull('a.last_seen_at');
            } elseif ($filters['online_status'] === 'online') {
                $query->whereNotNull('a.last_seen_at')
                    ->whereRaw('TIMESTAMPDIFF(SECOND, a.last_seen_at, NOW()) <= ?', [$threshold]);
            } elseif ($filters['online_status'] === 'offline') {
                $query->whereNotNull('a.last_seen_at')
                    ->whereRaw('TIMESTAMPDIFF(SECOND, a.last_seen_at, NOW()) > ?', [$threshold]);
            }
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $query->where(function ($inner) use ($q) {
                $like = '%' . $q . '%';
                $inner->where('a.hostname', 'LIKE', $like)
                    ->orWhere('a.device_name', 'LIKE', $like)
                    ->orWhere('a.device_id', 'LIKE', $like)
                    ->orWhere('a.install_id', 'LIKE', $like)
                    ->orWhere('c.firstname', 'LIKE', $like)
                    ->orWhere('c.lastname', 'LIKE', $like)
                    ->orWhere('c.email', 'LIKE', $like);
                if (ctype_digit($q)) {
                    $inner->orWhere('a.id', (int) $q);
                }
            });
        }

        return $query;
    }

    public static function getAllAgents(array $filters = [], array $sort = [], int $limit = 50, int $offset = 0): array
    {
        try {
            $limit = max(1, min(200, $limit));
            $offset = max(0, $offset);

            $query = self::buildAgentsBaseQuery($filters);
            $sortField = (string) ($sort['field'] ?? 'created_at');
            $sortDir = strtolower((string) ($sort['dir'] ?? 'desc'));
            if (!in_array($sortDir, ['asc', 'desc'], true)) {
                $sortDir = 'desc';
            }

            $allowedSorts = [
                'id' => 'a.id',
                'hostname' => 'a.hostname',
                'device_id' => 'a.device_id',
                'device_name' => 'a.device_name',
                'tenant' => 'tenant_name',
                'agent_type' => 'a.agent_type',
                'status' => 'a.status',
                'last_seen_at' => 'a.last_seen_at',
                'created_at' => 'a.created_at',
                'client_name' => 'c.firstname',
                'client_email' => 'c.email',
                'online_status' => 'seconds_since_seen',
            ];
            $sortColumn = $allowedSorts[$sortField] ?? 'a.created_at';

            $query->orderBy($sortColumn, $sortDir)
                ->orderBy('a.id', 'desc')
                ->limit($limit)
                ->offset($offset);

            $rows = $query->get();
            $threshold = self::getOnlineThresholdSeconds();
            foreach ($rows as $row) {
                $secs = isset($row->seconds_since_seen) ? (int) $row->seconds_since_seen : null;
                if (empty($row->last_seen_at)) {
                    $row->online_status = 'never';
                } elseif ($secs !== null && $secs <= $threshold) {
                    $row->online_status = 'online';
                } else {
                    $row->online_status = 'offline';
                }
                $row->online_threshold_seconds = $threshold;
                $row->client_name = trim(($row->firstname ?? '') . ' ' . ($row->lastname ?? ''));
            }

            return array_map(function ($item) {
                return (array) $item;
            }, $rows->toArray());
        } catch (\Throwable $e) {
            logModuleCall('cloudstorage', 'getAllAgents', ['filters' => $filters, 'sort' => $sort, 'limit' => $limit, 'offset' => $offset], $e->getMessage());
            return [];
        }
    }

    public static function countAllAgents(array $filters = []): int
    {
        try {
            $query = Capsule::table('s3_cloudbackup_agents as a')
                ->join('tblclients as c', 'a.client_id', '=', 'c.id');
            if (Capsule::schema()->hasTable('s3_backup_tenants')) {
                $query->leftJoin('s3_backup_tenants as t', 'a.tenant_id', '=', 't.id');
            }

            if (!empty($filters['client_id'])) {
                $query->where('a.client_id', (int) $filters['client_id']);
            }
            if (!empty($filters['status'])) {
                $query->where('a.status', $filters['status']);
            }
            if (!empty($filters['agent_type'])) {
                $query->where('a.agent_type', $filters['agent_type']);
            }
            if (isset($filters['tenant_id']) && $filters['tenant_id'] !== '') {
                if ($filters['tenant_id'] === 'direct') {
                    $query->whereNull('a.tenant_id');
                } else {
                    $query->where('a.tenant_id', (int) $filters['tenant_id']);
                }
            }
            if (isset($filters['online_status']) && $filters['online_status'] !== '') {
                $threshold = self::getOnlineThresholdSeconds();
                if ($filters['online_status'] === 'never') {
                    $query->whereNull('a.last_seen_at');
                } elseif ($filters['online_status'] === 'online') {
                    $query->whereNotNull('a.last_seen_at')
                        ->whereRaw('TIMESTAMPDIFF(SECOND, a.last_seen_at, NOW()) <= ?', [$threshold]);
                } elseif ($filters['online_status'] === 'offline') {
                    $query->whereNotNull('a.last_seen_at')
                        ->whereRaw('TIMESTAMPDIFF(SECOND, a.last_seen_at, NOW()) > ?', [$threshold]);
                }
            }

            $q = trim((string) ($filters['q'] ?? ''));
            if ($q !== '') {
                $query->where(function ($inner) use ($q) {
                    $like = '%' . $q . '%';
                    $inner->where('a.hostname', 'LIKE', $like)
                        ->orWhere('a.device_name', 'LIKE', $like)
                        ->orWhere('a.device_id', 'LIKE', $like)
                        ->orWhere('a.install_id', 'LIKE', $like)
                        ->orWhere('c.firstname', 'LIKE', $like)
                        ->orWhere('c.lastname', 'LIKE', $like)
                        ->orWhere('c.email', 'LIKE', $like);
                    if (ctype_digit($q)) {
                        $inner->orWhere('a.id', (int) $q);
                    }
                });
            }

            return (int) $query->count('a.id');
        } catch (\Throwable $e) {
            logModuleCall('cloudstorage', 'countAllAgents', ['filters' => $filters], $e->getMessage());
            return 0;
        }
    }

    /**
     * Get all jobs across all clients
     *
     * @param array $filters
     * @return array
     */
    public static function getAllJobs($filters = [])
    {
        try {
            $query = Capsule::table('s3_cloudbackup_jobs')
                ->join('tblclients', 's3_cloudbackup_jobs.client_id', '=', 'tblclients.id')
                ->select(
                    's3_cloudbackup_jobs.*',
                    'tblclients.firstname',
                    'tblclients.lastname',
                    'tblclients.email'
                );

            if (isset($filters['client_id']) && $filters['client_id']) {
                $query->where('s3_cloudbackup_jobs.client_id', $filters['client_id']);
            }

            if (isset($filters['status']) && $filters['status']) {
                $query->where('s3_cloudbackup_jobs.status', $filters['status']);
            }

            if (isset($filters['source_type']) && $filters['source_type']) {
                $query->where('s3_cloudbackup_jobs.source_type', $filters['source_type']);
            }

            if (isset($filters['job_name']) && $filters['job_name']) {
                $query->where('s3_cloudbackup_jobs.name', 'LIKE', '%' . $filters['job_name'] . '%');
            }

            $results = $query->orderBy('s3_cloudbackup_jobs.created_at', 'desc')->get();
            // Convert stdClass objects to arrays for Smarty compatibility
            return array_map(function($item) {
                return (array) $item;
            }, $results->toArray());
        } catch (\Exception $e) {
            logModuleCall('cloudstorage', 'getAllJobs', $filters, $e->getMessage());
            return [];
        }
    }

    /**
     * Get all runs across all clients
     *
     * @param array $filters
     * @return array
     */
    public static function getAllRuns($filters = [])
    {
        try {
            $query = Capsule::table('s3_cloudbackup_runs')
                ->join('s3_cloudbackup_jobs', 's3_cloudbackup_runs.job_id', '=', 's3_cloudbackup_jobs.id')
                ->join('tblclients', 's3_cloudbackup_jobs.client_id', '=', 'tblclients.id')
                ->select(
                    's3_cloudbackup_runs.*',
                    's3_cloudbackup_jobs.name as job_name',
                    's3_cloudbackup_jobs.client_id',
                    'tblclients.firstname',
                    'tblclients.lastname',
                    'tblclients.email'
                );

            if (isset($filters['client_id']) && $filters['client_id']) {
                $query->where('s3_cloudbackup_jobs.client_id', $filters['client_id']);
            }

            if (isset($filters['job_id']) && $filters['job_id']) {
                $query->where('s3_cloudbackup_runs.job_id', $filters['job_id']);
            }

            if (isset($filters['agent_id']) && $filters['agent_id']) {
                $agentId = (int) $filters['agent_id'];
                $query->where(function ($q) use ($agentId) {
                    $q->where('s3_cloudbackup_runs.agent_id', $agentId)
                        ->orWhere('s3_cloudbackup_jobs.agent_id', $agentId);
                });
            }

            if (isset($filters['status']) && $filters['status']) {
                $query->where('s3_cloudbackup_runs.status', $filters['status']);
            }

            if (isset($filters['date_from']) && $filters['date_from']) {
                $query->where('s3_cloudbackup_runs.started_at', '>=', $filters['date_from']);
            }

            if (isset($filters['date_to']) && $filters['date_to']) {
                $query->where('s3_cloudbackup_runs.started_at', '<=', $filters['date_to'] . ' 23:59:59');
            }

            if (isset($filters['job_name']) && $filters['job_name']) {
                $query->where('s3_cloudbackup_jobs.name', 'LIKE', '%' . $filters['job_name'] . '%');
            }

            $results = $query->orderBy('s3_cloudbackup_runs.started_at', 'desc')->limit(500)->get();
            // Convert stdClass objects to arrays for Smarty compatibility
            return array_map(function($item) {
                return (array) $item;
            }, $results->toArray());
        } catch (\Exception $e) {
            logModuleCall('cloudstorage', 'getAllRuns', $filters, $e->getMessage());
            return [];
        }
    }

    /**
     * Get repo retention/maintenance operations for admin view
     *
     * @return array
     */
    public static function getRepoRetentionOps(): array
    {
        try {
            if (!Capsule::schema()->hasTable('s3_kopia_repo_operations')
                || !Capsule::schema()->hasTable('s3_kopia_repos')) {
                return [];
            }
            $query = Capsule::table('s3_kopia_repo_operations as op')
                ->join('s3_kopia_repos as r', 'op.repo_id', '=', 'r.id')
                ->select(
                    'op.id',
                    'op.repo_id',
                    'op.op_type',
                    'op.status',
                    'op.attempt_count',
                    'op.operation_token',
                    'op.created_at',
                    'op.updated_at',
                    'op.next_attempt_at',
                    'r.repository_id',
                    'r.client_id'
                )
                ->orderBy('op.created_at', 'desc')
                ->limit(200);
            $rows = $query->get();
            return array_map(function ($item) {
                return (array) $item;
            }, $rows->toArray());
        } catch (\Throwable $e) {
            logModuleCall('cloudstorage', 'getRepoRetentionOps', [], $e->getMessage());
            return [];
        }
    }

    /**
     * Get Kopia repos for admin enqueue dropdown
     *
     * @return array
     */
    public static function getKopiaReposForAdmin(): array
    {
        try {
            if (!Capsule::schema()->hasTable('s3_kopia_repos')) {
                return [];
            }
            $rows = Capsule::table('s3_kopia_repos')
                ->where('status', 'active')
                ->select('id', 'repository_id', 'client_id')
                ->orderBy('id', 'desc')
                ->limit(100)
                ->get();
            return array_map(function ($item) {
                return (array) $item;
            }, $rows->toArray());
        } catch (\Throwable $e) {
            logModuleCall('cloudstorage', 'getKopiaReposForAdmin', [], $e->getMessage());
            return [];
        }
    }

    /**
     * Force cancel a run
     *
     * @param int $runId
     * @return array
     */
    public static function forceCancelRun($runId)
    {
        try {
            Capsule::table('s3_cloudbackup_runs')
                ->where('id', $runId)
                ->update([
                    'cancel_requested' => 1,
                ]);

            return ['status' => 'success'];
        } catch (\Exception $e) {
            logModuleCall('cloudstorage', 'forceCancelRun', ['run_id' => $runId], $e->getMessage());
            return ['status' => 'fail', 'message' => $e->getMessage()];
        }
    }
}

