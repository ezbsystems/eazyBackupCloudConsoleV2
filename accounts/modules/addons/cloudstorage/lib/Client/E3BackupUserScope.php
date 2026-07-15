<?php

declare(strict_types=1);

namespace WHMCS\Module\Addon\CloudStorage\Client;

use Illuminate\Database\Query\Builder;
use WHMCS\Database\Capsule;

/**
 * Shared scoping helpers for s3_backup_users (active vs soft-deleted).
 */
final class E3BackupUserScope
{
    public static function hasDeletedAtColumn(): bool
    {
        try {
            return Capsule::schema()->hasTable('s3_backup_users')
                && Capsule::schema()->hasColumn('s3_backup_users', 'deleted_at');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Self-heal when module upgrade was skipped but code expects deleted_at.
     */
    public static function ensureDeletedAtColumn(): bool
    {
        if (self::hasDeletedAtColumn()) {
            return true;
        }

        try {
            if (!Capsule::schema()->hasTable('s3_backup_users')) {
                return false;
            }

            Capsule::schema()->table('s3_backup_users', function ($table) {
                $table->timestamp('deleted_at')->nullable();
            });

            if (function_exists('logModuleCall')) {
                logModuleCall('cloudstorage', 'ensure_s3_backup_users_deleted_at', [], 'Added deleted_at on s3_backup_users', [], []);
            }

            return self::hasDeletedAtColumn();
        } catch (\Throwable $e) {
            if (function_exists('logModuleCall')) {
                logModuleCall('cloudstorage', 'ensure_s3_backup_users_deleted_at_fail', [], $e->getMessage(), [], []);
            }

            return false;
        }
    }

    /**
     * Username stored after soft-delete to free the original name for reuse.
     */
    public static function deletedUsername(string $originalUsername, int $backupUserId): string
    {
        $suffix = '__deleted_' . $backupUserId;
        $maxLen = 191;
        $base = trim($originalUsername);
        if ($base === '') {
            $base = 'user';
        }

        if (strlen($base) + strlen($suffix) > $maxLen) {
            $base = substr($base, 0, max(1, $maxLen - strlen($suffix)));
        }

        return $base . $suffix;
    }

    /**
     * @param object|null $user Row with optional deleted_at and status.
     */
    public static function isDeletedUser(?object $user): bool
    {
        if ($user === null) {
            return true;
        }

        if (property_exists($user, 'deleted_at') && !empty($user->deleted_at)) {
            return true;
        }

        return false;
    }

    /**
     * Exclude soft-deleted backup users from customer-facing listings.
     *
     * @param Builder $query Query on s3_backup_users (alias u optional).
     */
    public static function applyNotDeletedScope(Builder $query, string $tableAlias = 'u'): void
    {
        if (!self::hasDeletedAtColumn()) {
            return;
        }

        $column = $tableAlias !== '' ? $tableAlias . '.deleted_at' : 'deleted_at';
        $query->whereNull($column);
    }

    /**
     * Active, non-deleted backup users only.
     */
    public static function applyActiveScope(Builder $query, string $tableAlias = 'u'): void
    {
        $statusColumn = $tableAlias !== '' ? $tableAlias . '.status' : 'status';
        $query->where($statusColumn, 'active');
        self::applyNotDeletedScope($query, $tableAlias);
    }

    public static function isSchedulable(int $clientId, int $backupUserId): bool
    {
        if ($clientId <= 0 || $backupUserId <= 0) {
            return false;
        }

        try {
            if (!Capsule::schema()->hasTable('s3_backup_users')) {
                return false;
            }

            $query = Capsule::table('s3_backup_users')
                ->where('id', $backupUserId)
                ->where('client_id', $clientId)
                ->where('status', 'active');

            self::applyNotDeletedScope($query, '');

            return $query->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function validateDeletePhrase(string $username, string $confirmPhrase): bool
    {
        $expected = 'DELETE ' . trim($username);

        return strcasecmp(trim($confirmPhrase), $expected) === 0;
    }

    public static function validateBulkDeletePhrase(string $confirmPhrase): bool
    {
        return strcasecmp(trim($confirmPhrase), 'DELETE') === 0;
    }
}
