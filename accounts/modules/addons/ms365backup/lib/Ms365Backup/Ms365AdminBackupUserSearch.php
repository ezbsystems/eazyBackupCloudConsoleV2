<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;

/**
 * Admin typeahead for e3 backup users (tenant export tooling).
 */
final class Ms365AdminBackupUserSearch
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function search(string $query, int $limit = 15): array
    {
        $query = trim($query);
        if ($query === '' || strlen($query) < 2) {
            return [];
        }
        if (!Capsule::schema()->hasTable('s3_backup_users')) {
            return [];
        }

        $limit = max(1, min(50, $limit));
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $query) . '%';
        $numericId = ctype_digit($query) ? (int) $query : null;

        $cols = ['u.id', 'u.client_id', 'u.username', 'u.status', 'u.backup_type', 'u.created_at'];
        if (Capsule::schema()->hasColumn('s3_backup_users', 'public_id')) {
            $cols[] = 'u.public_id';
        }

        $q = Capsule::table('s3_backup_users as u')
            ->leftJoin('tblclients as c', 'c.id', '=', 'u.client_id')
            ->select(array_merge($cols, [
                'c.firstname',
                'c.lastname',
                'c.companyname',
                'c.email',
                'c.status as client_status',
            ]))
            ->orderByDesc('u.id')
            ->limit($limit);

        $q->where(function ($builder) use ($like, $numericId) {
            $builder->where('u.username', 'like', $like)
                ->orWhere('c.email', 'like', $like)
                ->orWhere('c.firstname', 'like', $like)
                ->orWhere('c.lastname', 'like', $like)
                ->orWhere('c.companyname', 'like', $like)
                ->orWhereRaw('CONCAT(c.firstname, " ", c.lastname) LIKE ?', [$like]);
            if ($numericId !== null) {
                $builder->orWhere('u.id', '=', $numericId)
                    ->orWhere('u.client_id', '=', $numericId);
            }
            if (Capsule::schema()->hasColumn('s3_backup_users', 'public_id')) {
                $builder->orWhere('u.public_id', 'like', $like);
            }
        });

        $rows = $q->get();
        $out = [];
        foreach ($rows as $row) {
            $row = (array) $row;
            $clientId = (int) ($row['client_id'] ?? 0);
            $backupUserId = (int) ($row['id'] ?? 0);
            $tenant = $clientId > 0 && $backupUserId > 0
                ? TenantRecordRepository::getForBackupUser($clientId, $backupUserId)
                : null;

            $fullName = trim(((string) ($row['firstname'] ?? '')) . ' ' . ((string) ($row['lastname'] ?? '')));
            $out[] = [
                'backup_user_id' => $backupUserId,
                'username' => (string) ($row['username'] ?? ''),
                'public_id' => isset($row['public_id']) ? (string) $row['public_id'] : null,
                'status' => (string) ($row['status'] ?? ''),
                'backup_type' => (string) ($row['backup_type'] ?? ''),
                'client_id' => $clientId,
                'client_name' => $fullName !== '' ? $fullName : null,
                'client_company' => (string) ($row['companyname'] ?? ''),
                'client_email' => (string) ($row['email'] ?? ''),
                'client_status' => (string) ($row['client_status'] ?? ''),
                'connection_status' => $tenant !== null ? (string) ($tenant['connection_status'] ?? '') : 'none',
                'connection_auth_mode' => $tenant !== null ? (string) ($tenant['connection_auth_mode'] ?? '') : 'none',
                'azure_tenant_id' => $tenant !== null ? (string) ($tenant['azure_tenant_id'] ?? '') : '',
            ];
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    public static function getBackupUser(int $backupUserId): ?array
    {
        if ($backupUserId <= 0 || !Capsule::schema()->hasTable('s3_backup_users')) {
            return null;
        }

        $cols = ['id', 'client_id', 'username', 'status', 'backup_type', 'created_at'];
        if (Capsule::schema()->hasColumn('s3_backup_users', 'public_id')) {
            $cols[] = 'public_id';
        }

        $row = Capsule::table('s3_backup_users')
            ->where('id', $backupUserId)
            ->select($cols)
            ->first();

        return $row !== null ? (array) $row : null;
    }
}
