<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\E3BackupUserLifecycleService;
use WHMCS\Module\Addon\CloudStorage\Client\E3BackupUserScope;
use WHMCS\Module\Addon\CloudStorage\Provision\E3BackupUserProductBootstrap;
use WHMCS\Module\Addon\CloudStorage\Provision\Provisioner;

require_once dirname(__DIR__, 3) . '/cloudstorage/lib/Client/E3BackupUserLifecycleService.php';
require_once dirname(__DIR__, 3) . '/cloudstorage/lib/Client/E3BackupUserScope.php';
require_once dirname(__DIR__, 3) . '/cloudstorage/lib/Provision/E3BackupUserProductBootstrap.php';
require_once dirname(__DIR__, 3) . '/cloudstorage/lib/Provision/Provisioner.php';

/**
 * Admin search, listing, and enriched preview for e3 Backup User deprovision.
 */
final class Ms365AdminDeprovisionService
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function searchClients(string $query, int $limit = 15): array
    {
        $query = trim($query);
        if ($query === '' || strlen($query) < 2) {
            return [];
        }

        $limit = max(1, min(50, $limit));
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $query) . '%';
        $numericId = ctype_digit($query) ? (int) $query : null;

        try {
            $base = Capsule::table('tblclients')
                ->select('id', 'firstname', 'lastname', 'companyname', 'email', 'status')
                ->orderBy('id', 'desc')
                ->limit($limit);

            $base->where(function ($q) use ($like, $numericId) {
                $q->where('email', 'like', $like)
                    ->orWhere('firstname', 'like', $like)
                    ->orWhere('lastname', 'like', $like)
                    ->orWhere('companyname', 'like', $like)
                    ->orWhereRaw('CONCAT(firstname, " ", lastname) LIKE ?', [$like]);
                if ($numericId !== null) {
                    $q->orWhere('id', '=', $numericId);
                }
            });

            $clients = $base->get();
        } catch (\Throwable $e) {
            return [];
        }

        $out = [];
        foreach ($clients as $client) {
            $arr = (array) $client;
            $clientId = (int) ($arr['id'] ?? 0);
            if ($clientId <= 0) {
                continue;
            }
            $out[] = [
                'client_id' => $clientId,
                'client_name' => self::formatClientName($arr),
                'email' => (string) ($arr['email'] ?? ''),
                'company' => (string) ($arr['companyname'] ?? ''),
                'status' => (string) ($arr['status'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * @return array{client: array<string, mixed>, users: list<array<string, mixed>>}
     */
    public static function listBackupUsersForClient(int $clientId): array
    {
        $client = self::loadClient($clientId);
        if ($client === null) {
            throw new \RuntimeException('Client not found.');
        }

        return [
            'client' => $client,
            'users' => self::backupUsersForClient($clientId),
        ];
    }

    /**
     * @return array{client: array<string, mixed>, users: list<array<string, mixed>>}
     */
    public static function resolveByServiceId(int $serviceId): array
    {
        if ($serviceId <= 0) {
            throw new \RuntimeException('service_id required.');
        }

        $backupUserPid = E3BackupUserProductBootstrap::getPid();
        if ($backupUserPid <= 0) {
            throw new \RuntimeException('e3 Backup User product is not configured.');
        }

        $hosting = Capsule::table('tblhosting')
            ->where('id', $serviceId)
            ->first(['id', 'userid', 'username', 'packageid', 'domainstatus']);

        if ($hosting === null) {
            throw new \RuntimeException('WHMCS service not found.');
        }

        $packageId = (int) ($hosting->packageid ?? 0);
        if ($packageId !== $backupUserPid) {
            throw new \RuntimeException(
                'Service ' . $serviceId . ' is not an e3 Backup User product (packageid=' . $packageId . ').'
            );
        }

        $clientId = (int) ($hosting->userid ?? 0);
        $client = self::loadClient($clientId);
        if ($client === null) {
            throw new \RuntimeException('Client not found for service.');
        }

        $backupUser = self::resolveBackupUserForService($clientId, $serviceId, (string) ($hosting->username ?? ''));
        if ($backupUser === null) {
            throw new \RuntimeException('No matching s3_backup_users row for service ' . $serviceId . '.');
        }

        $userRow = self::enrichBackupUserRow($backupUser, $clientId);
        $userRow['whmcs_service_id'] = $serviceId;
        $userRow['whmcs_service_status'] = (string) ($hosting->domainstatus ?? '');

        return [
            'client' => $client,
            'users' => [$userRow],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildPreview(int $backupUserId): array
    {
        if ($backupUserId <= 0) {
            throw new \RuntimeException('backup_user_id required.');
        }

        $userRow = self::loadBackupUserRow($backupUserId);
        if ($userRow === null) {
            throw new \RuntimeException('Backup user not found.');
        }

        $clientId = (int) ($userRow['client_id'] ?? 0);
        $client = self::loadClient($clientId);
        if ($client === null) {
            throw new \RuntimeException('Client not found.');
        }

        $lifecyclePreview = E3BackupUserLifecycleService::preview($clientId, $backupUserId);
        if (($lifecyclePreview['status'] ?? '') !== 'success') {
            throw new \RuntimeException((string) ($lifecyclePreview['message'] ?? 'Preview failed.'));
        }

        $serviceInfo = self::resolveWhmcsServiceForUser($clientId, $userRow);
        $jobs = self::jobsForUser($clientId, $backupUserId);
        $vaults = self::vaultsForUser($clientId, $backupUserId);
        $objectStorage = self::objectStorageServiceForClient($clientId);
        $graceDays = self::vaultRecycleGraceDays();

        return [
            'backup_user_id' => $backupUserId,
            'client' => $client,
            'user' => self::enrichBackupUserRow($userRow, $clientId),
            'whmcs_service' => $serviceInfo,
            'confirm_phrase' => (string) ($lifecyclePreview['confirm_phrase'] ?? ''),
            'impact' => $lifecyclePreview['impact'] ?? [],
            'jobs' => $jobs,
            'vaults' => $vaults,
            'vault_recycle_grace_days' => $graceDays,
            'ms365_connected' => !empty(($lifecyclePreview['impact'] ?? [])['ms365_connected']),
            'will_cancel' => [
                'product' => E3BackupUserProductBootstrap::PRODUCT_NAME,
                'service_id' => (int) ($serviceInfo['service_id'] ?? 0),
                'service_status' => (string) ($serviceInfo['status'] ?? ''),
            ],
            'will_not_touch' => [
                'object_storage_product' => 'e3 Object Storage / Cloud Storage',
                'object_storage_service_id' => (int) ($objectStorage['service_id'] ?? 0),
                'object_storage_service_status' => (string) ($objectStorage['status'] ?? ''),
                'note' => 'Customer RGW user and buckets are not deprovisioned by this workflow.',
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function backupUsersForClient(int $clientId): array
    {
        if ($clientId <= 0 || !Capsule::schema()->hasTable('s3_backup_users')) {
            return [];
        }

        $query = Capsule::table('s3_backup_users as u')
            ->where('u.client_id', $clientId)
            ->select([
                'u.id as backup_user_id',
                'u.client_id',
                'u.username',
                'u.status as user_status',
            ]);

        if (Capsule::schema()->hasColumn('s3_backup_users', 'backup_type')) {
            $query->addSelect('u.backup_type');
        }
        if (Capsule::schema()->hasColumn('s3_backup_users', 'whmcs_service_id')) {
            $query->addSelect('u.whmcs_service_id');
        }

        E3BackupUserScope::applyNotDeletedScope($query, 'u');

        $rows = $query->orderBy('u.username')->get();
        $out = [];
        foreach ($rows as $row) {
            $out[] = self::enrichBackupUserRow((array) $row, $clientId);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function enrichBackupUserRow(array $row, int $clientId): array
    {
        $backupUserId = (int) ($row['backup_user_id'] ?? $row['id'] ?? 0);
        $username = (string) ($row['username'] ?? '');
        $serviceInfo = self::resolveWhmcsServiceForUser($clientId, $row);
        $impact = E3BackupUserLifecycleService::impactCounts($clientId, $backupUserId);

        return [
            'backup_user_id' => $backupUserId,
            'client_id' => $clientId,
            'username' => $username,
            'status' => self::resolveUserStatus($row),
            'backup_type' => (string) ($row['backup_type'] ?? 'both'),
            'whmcs_service_id' => (int) ($serviceInfo['service_id'] ?? 0),
            'whmcs_service_status' => (string) ($serviceInfo['status'] ?? ''),
            'job_count' => (int) ($impact['jobs'] ?? 0),
            'vault_count' => (int) ($impact['vaults'] ?? 0),
            'ms365_connected' => !empty($impact['ms365_connected']),
            'agent_count' => (int) ($impact['agents'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array{service_id: int, status: string, username: string}
     */
    private static function resolveWhmcsServiceForUser(int $clientId, array $row): array
    {
        $empty = ['service_id' => 0, 'status' => '', 'username' => ''];
        if ($clientId <= 0) {
            return $empty;
        }

        $backupUserPid = E3BackupUserProductBootstrap::getPid();
        if ($backupUserPid <= 0) {
            return $empty;
        }

        $serviceId = (int) ($row['whmcs_service_id'] ?? 0);
        if ($serviceId > 0) {
            $hosting = Capsule::table('tblhosting')
                ->where('id', $serviceId)
                ->where('userid', $clientId)
                ->where('packageid', $backupUserPid)
                ->first(['id', 'domainstatus', 'username']);
            if ($hosting !== null) {
                return [
                    'service_id' => (int) ($hosting->id ?? 0),
                    'status' => (string) ($hosting->domainstatus ?? ''),
                    'username' => (string) ($hosting->username ?? ''),
                ];
            }
        }

        $username = trim((string) ($row['username'] ?? ''));
        if ($username === '') {
            return $empty;
        }

        $hosting = Capsule::table('tblhosting')
            ->where('userid', $clientId)
            ->where('packageid', $backupUserPid)
            ->where('username', $username)
            ->orderByDesc('id')
            ->first(['id', 'domainstatus', 'username']);

        if ($hosting === null) {
            return $empty;
        }

        return [
            'service_id' => (int) ($hosting->id ?? 0),
            'status' => (string) ($hosting->domainstatus ?? ''),
            'username' => (string) ($hosting->username ?? ''),
        ];
    }

    /**
     * @return array{service_id: int, status: string}|null
     */
    private static function objectStorageServiceForClient(int $clientId): ?array
    {
        if ($clientId <= 0) {
            return null;
        }

        $storagePid = (int) Provisioner::getSetting('pid_cloud_storage', 0);
        if ($storagePid <= 0) {
            return null;
        }

        $hosting = Capsule::table('tblhosting')
            ->where('userid', $clientId)
            ->where('packageid', $storagePid)
            ->whereNotIn('domainstatus', ['Cancelled', 'Terminated'])
            ->orderByDesc('id')
            ->first(['id', 'domainstatus']);

        if ($hosting === null) {
            return null;
        }

        return [
            'service_id' => (int) ($hosting->id ?? 0),
            'status' => (string) ($hosting->domainstatus ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function resolveBackupUserForService(int $clientId, int $serviceId, string $serviceUsername): ?array
    {
        if (!Capsule::schema()->hasTable('s3_backup_users')) {
            return null;
        }

        if (Capsule::schema()->hasColumn('s3_backup_users', 'whmcs_service_id')) {
            $row = Capsule::table('s3_backup_users')
                ->where('client_id', $clientId)
                ->where('whmcs_service_id', $serviceId)
                ->first();
            if ($row !== null) {
                return (array) $row;
            }
        }

        $username = trim($serviceUsername);
        if ($username === '') {
            return null;
        }

        $query = Capsule::table('s3_backup_users')
            ->where('client_id', $clientId)
            ->where('username', $username);
        E3BackupUserScope::applyNotDeletedScope($query);

        $row = $query->first();

        return $row !== null ? (array) $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function loadBackupUserRow(int $backupUserId): ?array
    {
        if ($backupUserId <= 0 || !Capsule::schema()->hasTable('s3_backup_users')) {
            return null;
        }

        $query = Capsule::table('s3_backup_users')
            ->where('id', $backupUserId);

        $cols = ['id as backup_user_id', 'client_id', 'username', 'status as user_status'];
        if (Capsule::schema()->hasColumn('s3_backup_users', 'backup_type')) {
            $cols[] = 'backup_type';
        }
        if (Capsule::schema()->hasColumn('s3_backup_users', 'whmcs_service_id')) {
            $cols[] = 'whmcs_service_id';
        }

        $row = $query->first($cols);

        return $row !== null ? (array) $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function loadClient(int $clientId): ?array
    {
        if ($clientId <= 0) {
            return null;
        }

        $row = Capsule::table('tblclients')
            ->where('id', $clientId)
            ->first(['id', 'firstname', 'lastname', 'companyname', 'email', 'status']);

        if ($row === null) {
            return null;
        }

        $arr = (array) $row;

        return [
            'client_id' => (int) ($arr['id'] ?? 0),
            'client_name' => self::formatClientName($arr),
            'email' => (string) ($arr['email'] ?? ''),
            'company' => (string) ($arr['companyname'] ?? ''),
            'status' => (string) ($arr['status'] ?? ''),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function jobsForUser(int $clientId, int $backupUserId): array
    {
        if ($clientId <= 0 || $backupUserId <= 0 || !Capsule::schema()->hasTable('s3_cloudbackup_jobs')) {
            return [];
        }

        $hasJobIdPk = Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'job_id');
        $select = ['name', 'status', 'source_type', 'dest_bucket_id'];
        if ($hasJobIdPk) {
            $select[] = Capsule::raw('BIN_TO_UUID(job_id) as job_id');
        } else {
            $select[] = 'id as job_id';
        }

        $rows = Capsule::table('s3_cloudbackup_jobs')
            ->where('client_id', $clientId)
            ->where('backup_user_id', $backupUserId)
            ->where('status', '!=', 'deleted')
            ->orderBy('name')
            ->get($select);

        $out = [];
        foreach ($rows as $row) {
            $arr = (array) $row;
            $jobId = (string) ($arr['job_id'] ?? '');
            if ($jobId === '') {
                continue;
            }
            $out[] = [
                'job_id' => $jobId,
                'name' => (string) ($arr['name'] ?? ''),
                'status' => (string) ($arr['status'] ?? ''),
                'source_type' => (string) ($arr['source_type'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function vaultsForUser(int $clientId, int $backupUserId): array
    {
        if ($clientId <= 0 || $backupUserId <= 0 || !Capsule::schema()->hasTable('s3_cloudbackup_jobs')) {
            return [];
        }

        $jobRows = Capsule::table('s3_cloudbackup_jobs')
            ->where('client_id', $clientId)
            ->where('backup_user_id', $backupUserId)
            ->where('status', '!=', 'deleted')
            ->whereNotNull('dest_bucket_id')
            ->where('dest_bucket_id', '>', 0)
            ->get(['dest_bucket_id']);

        $bucketIds = [];
        foreach ($jobRows as $row) {
            $bid = (int) ($row->dest_bucket_id ?? 0);
            if ($bid > 0) {
                $bucketIds[$bid] = true;
            }
        }

        if ($bucketIds === []) {
            return [];
        }

        $bucketNames = [];
        $recycleStatus = [];
        if (Capsule::schema()->hasTable('s3_buckets')) {
            $cols = ['id', 'name'];
            if (Capsule::schema()->hasColumn('s3_buckets', 'recycle_status')) {
                $cols[] = 'recycle_status';
            }
            foreach (Capsule::table('s3_buckets')->whereIn('id', array_keys($bucketIds))->get($cols) as $b) {
                $bid = (int) ($b->id ?? 0);
                $bucketNames[$bid] = (string) ($b->name ?? '');
                if (isset($b->recycle_status)) {
                    $recycleStatus[$bid] = (string) ($b->recycle_status ?? '');
                }
            }
        }

        $usageMap = self::bucketUsageMap(array_keys($bucketIds));
        $out = [];
        foreach (array_keys($bucketIds) as $bid) {
            $bytes = $usageMap[$bid] ?? null;
            $out[] = [
                'bucket_id' => $bid,
                'name' => $bucketNames[$bid] ?? ('bucket-' . $bid),
                'size_display' => $bytes !== null ? self::formatBytes($bytes) : '—',
                'recycle_status' => $recycleStatus[$bid] ?? 'active',
                'is_ms365_vault' => str_starts_with($bucketNames[$bid] ?? '', 'e3ms365-'),
            ];
        }

        usort($out, static fn (array $a, array $b): int => strcmp((string) $a['name'], (string) $b['name']));

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

    private static function vaultRecycleGraceDays(): int
    {
        $raw = (int) Provisioner::getSetting('ms365_vault_recycle_grace_days', 30);

        return max(1, $raw);
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function formatClientName(array $row): string
    {
        $company = trim((string) ($row['companyname'] ?? ''));
        if ($company !== '') {
            return $company;
        }

        $first = trim((string) ($row['firstname'] ?? ''));
        $last = trim((string) ($row['lastname'] ?? ''));
        $name = trim($first . ' ' . $last);

        return $name !== '' ? $name : ('Client #' . (int) ($row['id'] ?? $row['client_id'] ?? 0));
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function resolveUserStatus(array $row): string
    {
        if (strtolower((string) ($row['user_status'] ?? $row['status'] ?? '')) === 'disabled') {
            return 'Disabled';
        }

        return 'Active';
    }

    private static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1024 ** 2) {
            return round($bytes / 1024, 1) . ' KiB';
        }
        if ($bytes < 1024 ** 3) {
            return round($bytes / (1024 ** 2), 1) . ' MiB';
        }

        return round($bytes / (1024 ** 3), 2) . ' GiB';
    }
}
