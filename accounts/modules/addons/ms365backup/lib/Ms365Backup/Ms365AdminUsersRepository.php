<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;

require_once dirname(__DIR__, 3) . '/cloudstorage/lib/Ms365BackupBootstrap.php';
require_once dirname(__DIR__, 3) . '/cloudstorage/lib/Client/E3BackupUserScope.php';

/**
 * Admin listing of MS365 backup users.
 */
final class Ms365AdminUsersRepository
{
    /** @return array{rows: list<array<string, mixed>>, total: int, page: int, per_page: int} */
    public static function listUsers(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $page = max(1, $page);
        $perPage = min(200, max(1, $perPage));

        if (!Capsule::schema()->hasTable('s3_backup_users')) {
            return ['rows' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage];
        }

        $hasJobBackupUserId = Capsule::schema()->hasTable('s3_cloudbackup_jobs')
            && Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'backup_user_id');
        $hasTenantTable = Capsule::schema()->hasTable('ms365_tenant_records')
            && Capsule::schema()->hasColumn('ms365_tenant_records', 'backup_user_id');
        $hasControls = Capsule::schema()->hasTable('ms365_admin_user_controls');

        if (!$hasJobBackupUserId && !$hasTenantTable) {
            return ['rows' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage];
        }

        $hasWhmcsServiceId = Capsule::schema()->hasColumn('s3_backup_users', 'whmcs_service_id');

        $query = Capsule::table('s3_backup_users as u')
            ->join('tblclients as c', 'u.client_id', '=', 'c.id')
            ->select([
                'u.id as backup_user_id',
                'u.client_id',
                'u.username',
                'u.status as user_status',
                'c.firstname',
                'c.lastname',
                'c.companyname',
                'c.email',
            ]);
        if ($hasWhmcsServiceId) {
            $query->addSelect('u.whmcs_service_id');
        }

        if (class_exists(\WHMCS\Module\Addon\CloudStorage\Client\E3BackupUserScope::class)) {
            \WHMCS\Module\Addon\CloudStorage\Client\E3BackupUserScope::applyNotDeletedScope($query, 'u');
        } else {
            $query->where('u.status', '!=', 'disabled');
        }

        $query->where(function ($q) use ($hasJobBackupUserId, $hasTenantTable) {
            $hasClause = false;
            if ($hasTenantTable) {
                $q->whereExists(function ($sub) {
                    $sub->select(Capsule::raw('1'))
                        ->from('ms365_tenant_records as t')
                        ->whereColumn('t.backup_user_id', 'u.id')
                        ->where('t.is_active', 1);
                });
                $hasClause = true;
            }
            if ($hasJobBackupUserId) {
                $method = $hasClause ? 'orWhereExists' : 'whereExists';
                $q->{$method}(function ($sub) {
                    $sub->select(Capsule::raw('1'))
                        ->from('s3_cloudbackup_jobs as j')
                        ->whereColumn('j.backup_user_id', 'u.id')
                        ->where('j.source_type', Ms365CustomerJobService::SOURCE_TYPE)
                        ->where('j.status', '!=', 'deleted');
                });
            }
        });

        if (!empty($filters['search'])) {
            $term = '%' . trim((string) $filters['search']) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('u.username', 'LIKE', $term)
                    ->orWhere('c.email', 'LIKE', $term)
                    ->orWhere('c.firstname', 'LIKE', $term)
                    ->orWhere('c.lastname', 'LIKE', $term)
                    ->orWhere('c.companyname', 'LIKE', $term);
            });
        }

        if (!empty($filters['status'])) {
            $statusFilter = strtolower(trim((string) $filters['status']));
            if ($statusFilter === 'suspended') {
                $query->where(function ($q) use ($hasControls) {
                    if ($hasControls) {
                        $q->whereExists(function ($sub) {
                            $sub->select(Capsule::raw('1'))
                                ->from('ms365_admin_user_controls as ac')
                                ->whereColumn('ac.backup_user_id', 'u.id')
                                ->whereNotNull('ac.admin_suspended_at');
                        });
                    }
                    $q->orWhereExists(function ($sub) {
                        $sub->select(Capsule::raw('1'))
                            ->from('tblhosting as h')
                            ->whereColumn('h.username', 'u.username')
                            ->whereColumn('h.userid', 'u.client_id')
                            ->where('h.domainstatus', 'Suspended');
                    });
                });
            } elseif ($statusFilter === 'disabled') {
                $query->where('u.status', 'disabled');
            } elseif ($statusFilter === 'active') {
                if ($hasControls) {
                    $query->whereNotExists(function ($sub) {
                        $sub->select(Capsule::raw('1'))
                            ->from('ms365_admin_user_controls as ac')
                            ->whereColumn('ac.backup_user_id', 'u.id')
                            ->whereNotNull('ac.admin_suspended_at');
                    });
                }
                $query->where('u.status', '!=', 'disabled')
                    ->whereNotExists(function ($sub) {
                        $sub->select(Capsule::raw('1'))
                            ->from('tblhosting as h')
                            ->whereColumn('h.username', 'u.username')
                            ->whereColumn('h.userid', 'u.client_id')
                            ->where('h.domainstatus', 'Suspended');
                    });
            }
        }

        $total = (int) $query->count();

        $rows = (clone $query)
            ->orderByDesc('u.id')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $backupUserIds = [];
        $billingPairs = [];
        $parsed = [];
        foreach ($rows as $row) {
            $arr = (array) $row;
            $backupUserId = (int) ($arr['backup_user_id'] ?? 0);
            $clientId = (int) ($arr['client_id'] ?? 0);
            if ($backupUserId <= 0) {
                continue;
            }
            $backupUserIds[] = $backupUserId;
            $billingPairs[$clientId . ':' . $backupUserId] = [$clientId, $backupUserId];
            $parsed[] = $arr;
        }

        $adminSuspended = self::adminSuspendedMap($backupUserIds);
        $whmcsSuspended = self::whmcsSuspendedMap($parsed);
        $billingCache = self::billingSummariesForKeys(array_values($billingPairs));
        $jobsByUser = self::ms365JobsForUsers($backupUserIds);
        $vaultsByUser = self::vaultsForUsers($backupUserIds, $jobsByUser);
        $serviceIdsByUser = self::serviceIdsForUsers($parsed);

        $out = [];
        foreach ($parsed as $arr) {
            $backupUserId = (int) ($arr['backup_user_id'] ?? 0);
            $clientId = (int) ($arr['client_id'] ?? 0);
            $billingKey = $clientId . ':' . $backupUserId;
            $billing = $billingCache[$billingKey] ?? ['protected_users' => 0, 'onedrive_overage_gib' => 0, 'trial_status' => null];
            $status = self::resolveDisplayStatus(
                $arr,
                !empty($adminSuspended[$backupUserId]),
                !empty($whmcsSuspended[$backupUserId])
            );

            $out[] = [
                'backup_user_id' => $backupUserId,
                'client_id' => $clientId,
                'client_name' => self::formatClientName($arr),
                'username' => (string) ($arr['username'] ?? ''),
                'whmcs_service_id' => (int) ($serviceIdsByUser[$backupUserId] ?? 0),
                'status' => $status,
                'admin_suspended' => !empty($adminSuspended[$backupUserId]),
                'protected_users' => (int) ($billing['protected_users'] ?? 0),
                'onedrive_overage_gib' => (int) ($billing['onedrive_overage_gib'] ?? 0),
                'trial_status' => $billing['trial_status'] ?? null,
                'vaults' => $vaultsByUser[$backupUserId] ?? [],
                'jobs' => $jobsByUser[$backupUserId] ?? [],
            ];
        }

        return ['rows' => $out, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    /** @return array<string, mixed>|null */
    public static function getUserSummary(int $backupUserId): ?array
    {
        if ($backupUserId <= 0) {
            return null;
        }

        $result = self::listUsers(['search' => (string) $backupUserId], 1, 1);
        foreach ($result['rows'] as $row) {
            if ((int) ($row['backup_user_id'] ?? 0) === $backupUserId) {
                return $row;
            }
        }

        if (!Capsule::schema()->hasTable('s3_backup_users')) {
            return null;
        }

        $row = Capsule::table('s3_backup_users as u')
            ->leftJoin('tblclients as c', 'c.id', '=', 'u.client_id')
            ->where('u.id', $backupUserId)
            ->select([
                'u.id as backup_user_id',
                'u.client_id',
                'u.username',
                'u.status as user_status',
                'c.firstname',
                'c.lastname',
                'c.companyname',
                'c.email',
            ])
            ->first();

        if ($row === null) {
            return null;
        }

        $arr = (array) $row;
        $clientId = (int) ($arr['client_id'] ?? 0);

        return [
            'backup_user_id' => $backupUserId,
            'client_id' => $clientId,
            'client_name' => self::formatClientName($arr),
            'username' => (string) ($arr['username'] ?? ''),
            'status' => self::resolveDisplayStatus(
                $arr,
                Ms365AdminUserControlsRepository::isAdminSuspended($backupUserId),
                self::isWhmcsSuspended($clientId, (string) ($arr['username'] ?? ''))
            ),
            'jobs' => self::ms365JobsForUsers([$backupUserId])[$backupUserId] ?? [],
        ];
    }

    /**
     * @param list<int> $backupUserIds
     * @return array<int, true>
     */
    private static function adminSuspendedMap(array $backupUserIds): array
    {
        $out = [];
        $backupUserIds = array_values(array_filter(array_map('intval', $backupUserIds)));
        if ($backupUserIds === [] || !Capsule::schema()->hasTable('ms365_admin_user_controls')) {
            return $out;
        }

        foreach (Capsule::table('ms365_admin_user_controls')
            ->whereIn('backup_user_id', $backupUserIds)
            ->whereNotNull('admin_suspended_at')
            ->pluck('backup_user_id') as $id) {
            $out[(int) $id] = true;
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<int, true>
     */
    private static function whmcsSuspendedMap(array $rows): array
    {
        $out = [];
        foreach ($rows as $arr) {
            $backupUserId = (int) ($arr['backup_user_id'] ?? 0);
            $clientId = (int) ($arr['client_id'] ?? 0);
            $username = (string) ($arr['username'] ?? '');
            if ($backupUserId > 0 && self::isWhmcsSuspended($clientId, $username)) {
                $out[$backupUserId] = true;
            }
        }

        return $out;
    }

    private static function isWhmcsSuspended(int $clientId, string $username): bool
    {
        if ($clientId <= 0 || trim($username) === '') {
            return false;
        }

        return Capsule::table('tblhosting')
            ->where('userid', $clientId)
            ->where('username', $username)
            ->where('domainstatus', 'Suspended')
            ->exists();
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function resolveDisplayStatus(array $row, bool $adminSuspended, bool $whmcsSuspended): string
    {
        if (strtolower((string) ($row['user_status'] ?? '')) === 'disabled') {
            return 'Disabled';
        }
        if ($adminSuspended || $whmcsSuspended) {
            return 'Suspended';
        }

        return 'Active';
    }

    /**
     * @param list<int> $backupUserIds
     * @return array<int, list<array{job_id: string, name: string}>>
     */
    private static function ms365JobsForUsers(array $backupUserIds): array
    {
        $out = [];
        $backupUserIds = array_values(array_filter(array_map('intval', $backupUserIds)));
        if ($backupUserIds === [] || !Capsule::schema()->hasTable('s3_cloudbackup_jobs')) {
            return $out;
        }

        $hasJobIdPk = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'job_id');
        $select = ['name', 'backup_user_id', 'status'];
        if ($hasJobIdPk) {
            $select[] = Capsule::raw('BIN_TO_UUID(job_id) as job_id');
        } else {
            $select[] = 'id as job_id';
        }

        $q = Capsule::table('s3_cloudbackup_jobs')
            ->whereIn('backup_user_id', $backupUserIds)
            ->where('source_type', Ms365CustomerJobService::SOURCE_TYPE)
            ->where('status', '!=', 'deleted')
            ->orderBy('name');

        $rows = $q->get($select);
        foreach ($rows as $row) {
            $arr = (array) $row;
            $uid = (int) ($arr['backup_user_id'] ?? 0);
            $jobId = (string) ($arr['job_id'] ?? '');
            if ($uid <= 0 || $jobId === '') {
                continue;
            }
            $out[$uid][] = [
                'job_id' => $jobId,
                'name' => (string) ($arr['name'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * Resolve WHMCS hosting service ids for Users-page links (clientsservices.php).
     *
     * @param list<array<string, mixed>> $rows
     * @return array<int, int> backup_user_id => tblhosting.id
     */
    private static function serviceIdsForUsers(array $rows): array
    {
        $out = [];
        $needFallback = [];
        foreach ($rows as $arr) {
            $backupUserId = (int) ($arr['backup_user_id'] ?? 0);
            $clientId = (int) ($arr['client_id'] ?? 0);
            if ($backupUserId <= 0 || $clientId <= 0) {
                continue;
            }
            $fromCol = (int) ($arr['whmcs_service_id'] ?? 0);
            if ($fromCol > 0) {
                $out[$backupUserId] = $fromCol;
                continue;
            }
            $needFallback[$backupUserId] = $clientId;
        }

        if ($needFallback === []) {
            return $out;
        }

        if (Capsule::schema()->hasTable('ms365_tenant_records')
            && Capsule::schema()->hasColumn('ms365_tenant_records', 'whmcs_service_id')) {
            $tenantRows = Capsule::table('ms365_tenant_records')
                ->whereIn('backup_user_id', array_keys($needFallback))
                ->where('is_active', 1)
                ->where('whmcs_service_id', '>', 0)
                ->orderByDesc('id')
                ->get(['backup_user_id', 'whmcs_service_id']);
            foreach ($tenantRows as $tr) {
                $uid = (int) ($tr->backup_user_id ?? 0);
                $sid = (int) ($tr->whmcs_service_id ?? 0);
                if ($uid > 0 && $sid > 0 && !isset($out[$uid])) {
                    $out[$uid] = $sid;
                    unset($needFallback[$uid]);
                }
            }
        }

        foreach ($needFallback as $backupUserId => $clientId) {
            $sid = Ms365BillingService::resolveServiceIdForBackupUser($clientId, $backupUserId);
            if ($sid > 0) {
                $out[$backupUserId] = $sid;
            }
        }

        return $out;
    }

    /**
     * @param list<int> $backupUserIds
     * @param array<int, list<array{job_id: string, name: string}>> $jobsByUser
     * @return array<int, list<array{name: string, size_gib: float|null, size_display: string}>>
     */
    private static function vaultsForUsers(array $backupUserIds, array $jobsByUser): array
    {
        $out = [];
        $backupUserIds = array_values(array_filter(array_map('intval', $backupUserIds)));
        if ($backupUserIds === [] || !Capsule::schema()->hasTable('s3_cloudbackup_jobs')) {
            return $out;
        }

        $jobRows = Capsule::table('s3_cloudbackup_jobs as j')
            ->whereIn('j.backup_user_id', $backupUserIds)
            ->where('j.source_type', Ms365CustomerJobService::SOURCE_TYPE)
            ->where('j.status', '!=', 'deleted')
            ->whereNotNull('j.dest_bucket_id')
            ->where('j.dest_bucket_id', '>', 0)
            ->get(['j.backup_user_id', 'j.dest_bucket_id']);

        $bucketIds = [];
        $bucketsByUser = [];
        foreach ($jobRows as $row) {
            $uid = (int) ($row->backup_user_id ?? 0);
            $bid = (int) ($row->dest_bucket_id ?? 0);
            if ($uid <= 0 || $bid <= 0) {
                continue;
            }
            $bucketIds[$bid] = true;
            $bucketsByUser[$uid][$bid] = true;
        }

        $bucketNames = [];
        if ($bucketIds !== [] && Capsule::schema()->hasTable('s3_buckets')) {
            foreach (Capsule::table('s3_buckets')->whereIn('id', array_keys($bucketIds))->get(['id', 'name']) as $b) {
                $bucketNames[(int) ($b->id ?? 0)] = (string) ($b->name ?? '');
            }
        }

        $usageMap = self::bucketUsageMap(array_keys($bucketIds));

        foreach ($bucketsByUser as $uid => $bucketSet) {
            foreach (array_keys($bucketSet) as $bid) {
                $bytes = $usageMap[$bid] ?? null;
                $out[$uid][] = [
                    'name' => $bucketNames[$bid] ?? ('bucket-' . $bid),
                    'size_gib' => $bytes !== null ? round($bytes / (1024 ** 3), 2) : null,
                    'size_display' => $bytes !== null ? self::formatBytes($bytes) : '—',
                ];
            }
        }

        return $out;
    }

    /**
     * @param list<int> $bucketIds
     * @return array<int, int>
     */
    private static function bucketUsageMap(array $bucketIds): array
    {
        $map = [];
        $bucketIds = array_values(array_filter(array_map('intval', $bucketIds)));
        if ($bucketIds === [] || !Capsule::schema()->hasTable('s3_bucket_stats_summary')) {
            return $map;
        }

        $rows = Capsule::table('s3_bucket_stats_summary')
            ->whereIn('bucket_id', $bucketIds)
            ->orderByDesc('created_at')
            ->get(['bucket_id', 'total_usage']);

        foreach ($rows as $row) {
            $bid = (int) ($row->bucket_id ?? 0);
            if ($bid > 0 && !isset($map[$bid])) {
                $map[$bid] = (int) ($row->total_usage ?? 0);
            }
        }

        return $map;
    }

    /**
     * @param list<array{0: int, 1: int}> $pairs
     * @return array<string, array{protected_users: int, onedrive_overage_gib: int, trial_status: string|null}>
     */
    private static function billingSummariesForKeys(array $pairs): array
    {
        $empty = ['protected_users' => 0, 'onedrive_overage_gib' => 0, 'trial_status' => null];
        $out = [];
        foreach ($pairs as $pair) {
            $out[(int) $pair[0] . ':' . (int) $pair[1]] = $empty;
        }
        if ($pairs === [] || !Capsule::schema()->hasTable('ms365_billing_usage_snapshots')) {
            return $out;
        }

        $clientIds = array_values(array_unique(array_map(static fn (array $pair): int => (int) $pair[0], $pairs)));
        $rows = Capsule::table('ms365_billing_usage_snapshots')
            ->whereIn('client_id', $clientIds)
            ->where('taken_at', '>=', date('Y-m-d', strtotime('-14 days')))
            ->orderByDesc('taken_at')
            ->get(['client_id', 'backup_user_id', 'metric', 'qty', 'service_id']);

        $seen = [];
        $serviceIds = [];
        foreach ($rows as $row) {
            $key = (int) ($row->client_id ?? 0) . ':' . (int) ($row->backup_user_id ?? 0);
            if (!isset($out[$key])) {
                continue;
            }
            $metric = (string) ($row->metric ?? '');
            $metricKey = $key . ':' . $metric;
            if (isset($seen[$metricKey])) {
                continue;
            }
            $seen[$metricKey] = true;
            if ($metric === Ms365BillingConfig::METRIC_PROTECTED_USERS) {
                $out[$key]['protected_users'] = (int) ($row->qty ?? 0);
            } elseif ($metric === Ms365BillingConfig::METRIC_ONEDRIVE_OVERAGE_GIB) {
                $out[$key]['onedrive_overage_gib'] = (int) ($row->qty ?? 0);
            }
            $serviceId = (int) ($row->service_id ?? 0);
            if ($serviceId > 0) {
                $serviceIds[$key] = $serviceId;
            }
        }

        if ($serviceIds !== [] && Capsule::schema()->hasTable('ms365_billing_trial_state')) {
            $trialRows = Capsule::table('ms365_billing_trial_state')
                ->whereIn('service_id', array_values(array_unique($serviceIds)))
                ->get(['service_id', 'status']);
            $trialByService = [];
            foreach ($trialRows as $trialRow) {
                $trialByService[(int) ($trialRow->service_id ?? 0)] = (string) ($trialRow->status ?? '');
            }
            foreach ($serviceIds as $key => $serviceId) {
                if (isset($trialByService[$serviceId]) && $trialByService[$serviceId] !== '') {
                    $out[$key]['trial_status'] = $trialByService[$serviceId];
                }
            }
        }

        return $out;
    }

    /** @param array<string, mixed> $row */
    private static function formatClientName(array $row): string
    {
        $company = trim((string) ($row['companyname'] ?? ''));
        if ($company !== '') {
            return $company;
        }
        $name = trim(((string) ($row['firstname'] ?? '')) . ' ' . ((string) ($row['lastname'] ?? '')));
        if ($name !== '') {
            return $name;
        }

        return (string) ($row['email'] ?? '');
    }

    private static function formatBytes(int $bytes): string
    {
        // Units must start at B so each /1024 advances the label correctly.
        $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB'];
        $value = (float) $bytes;
        $unit = 0;
        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            ++$unit;
        }

        if ($unit === 0) {
            return ((int) $value) . ' B';
        }

        return round($value, $unit < 3 ? 1 : 2) . ' ' . $units[$unit];
    }
}
