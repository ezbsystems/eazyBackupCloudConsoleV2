<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;

/**
 * Resolves s3_backup_users scope for MS365 customer APIs.
 */
final class BackupUserResolver
{
    /**
     * @return array{id: int, client_id: int, username: string, public_id: string}
     */
    public static function resolveForClient(int $clientId, string $userIdRaw): array
    {
        if ($clientId <= 0 || trim($userIdRaw) === '') {
            throw new \RuntimeException('Backup user is required.');
        }

        $hasPublicId = Capsule::schema()->hasColumn('s3_backup_users', 'public_id');
        $query = Capsule::table('s3_backup_users')->where('client_id', $clientId);
        if ($hasPublicId && !ctype_digit(trim($userIdRaw))) {
            $query->where('public_id', trim($userIdRaw));
        } else {
            $query->where('id', (int) $userIdRaw);
        }

        $row = $query->first(['id', 'client_id', 'username', 'public_id']);
        if ($row === null) {
            throw new \RuntimeException('Backup user not found.');
        }

        return [
            'id' => (int) $row->id,
            'client_id' => (int) $row->client_id,
            'username' => (string) ($row->username ?? ''),
            'public_id' => (string) ($row->public_id ?? (string) $row->id),
        ];
    }

    /**
     * @return array{id: int, client_id: int, username: string, public_id: string}
     */
    public static function resolveByIdForClient(int $clientId, int $backupUserId): array
    {
        if ($clientId <= 0 || $backupUserId <= 0) {
            throw new \RuntimeException('Backup user is required.');
        }

        $hasPublicId = Capsule::schema()->hasColumn('s3_backup_users', 'public_id');
        $cols = ['id', 'client_id', 'username'];
        if ($hasPublicId) {
            $cols[] = 'public_id';
        }

        $row = Capsule::table('s3_backup_users')
            ->where('client_id', $clientId)
            ->where('id', $backupUserId)
            ->first($cols);

        if ($row === null) {
            throw new \RuntimeException('Backup user not found.');
        }

        return [
            'id' => (int) $row->id,
            'client_id' => (int) $row->client_id,
            'username' => (string) ($row->username ?? ''),
            'public_id' => (string) ($row->public_id ?? (string) $row->id),
        ];
    }
}
