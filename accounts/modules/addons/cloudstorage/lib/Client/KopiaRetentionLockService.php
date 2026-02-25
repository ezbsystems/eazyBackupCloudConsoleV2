<?php
declare(strict_types=1);

namespace WHMCS\Module\Addon\CloudStorage\Client;

use Illuminate\Database\QueryException;
use WHMCS\Database\Capsule;

/**
 * Acquires, renews, and releases per-repo lease locks in s3_kopia_repo_locks.
 * Uses transaction + lockForUpdate for row-level locking; update-in-place to avoid delete+insert race.
 */
class KopiaRetentionLockService
{
    /**
     * Acquire a lock on repo_id. Uses transaction + lockForUpdate to avoid delete+insert races.
     * Same token: renew in place; expired: update in place; no row: insert (handles concurrent duplicate).
     *
     * @param int $repoId
     * @param string $lockToken
     * @param int|null $agentId
     * @param int $ttlSeconds
     * @return bool true if acquired
     */
    public static function acquire(int $repoId, string $lockToken, ?int $agentId, int $ttlSeconds = 300): bool
    {
        return Capsule::connection()->transaction(function () use ($repoId, $lockToken, $agentId, $ttlSeconds): bool {
            $now = date('Y-m-d H:i:s');
            $expiresAt = date('Y-m-d H:i:s', time() + $ttlSeconds);

            $row = Capsule::table('s3_kopia_repo_locks')->where('repo_id', $repoId)->lockForUpdate()->first();
            if ($row) {
                if ($row->lock_token === $lockToken) {
                    Capsule::table('s3_kopia_repo_locks')->where('repo_id', $repoId)->update([
                        'expires_at' => $expiresAt,
                        'updated_at' => $now,
                    ]);
                    return true;
                }
                $expiryTs = $row->expires_at ? strtotime((string) $row->expires_at) : 0;
                if ($expiryTs > 0 && $expiryTs < time()) {
                    Capsule::table('s3_kopia_repo_locks')->where('repo_id', $repoId)->update([
                        'lock_token' => $lockToken,
                        'claimed_by_agent_id' => $agentId,
                        'expires_at' => $expiresAt,
                        'updated_at' => $now,
                    ]);
                    return true;
                }
                return false;
            }

            try {
                Capsule::table('s3_kopia_repo_locks')->insert([
                    'repo_id' => $repoId,
                    'lock_token' => $lockToken,
                    'claimed_by_agent_id' => $agentId,
                    'expires_at' => $expiresAt,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                return true;
            } catch (QueryException $e) {
                if (self::isDuplicateKeyException($e)) {
                    $recheck = Capsule::table('s3_kopia_repo_locks')->where('repo_id', $repoId)->first();
                    return $recheck && $recheck->lock_token === $lockToken;
                }
                throw $e;
            }
        });
    }

    private static function isDuplicateKeyException(QueryException $e): bool
    {
        $code = $e->getCode();
        if ($code === '23000' || $code === 23000 || $code === 1062) {
            return true;
        }
        $msg = $e->getMessage();
        return str_contains($msg, 'Duplicate entry') || str_contains($msg, '1062');
    }

    /**
     * Renew lock expiry if token matches and lock exists.
     * Uses atomic update constrained by repo_id + lock_token; returns true only if affected rows > 0.
     *
     * @param int $repoId
     * @param string $lockToken
     * @param int $ttlSeconds
     * @return bool true if renewed
     */
    public static function renew(int $repoId, string $lockToken, int $ttlSeconds = 300): bool
    {
        $affected = Capsule::table('s3_kopia_repo_locks')
            ->where('repo_id', $repoId)
            ->where('lock_token', $lockToken)
            ->update([
                'expires_at' => Capsule::raw('DATE_ADD(NOW(), INTERVAL ' . (int) $ttlSeconds . ' SECOND)'),
                'updated_at' => Capsule::raw('NOW()'),
            ]);
        return $affected > 0;
    }

    /**
     * Release lock if token matches.
     * Uses atomic delete constrained by repo_id + lock_token; returns true only if deleted rows > 0.
     *
     * @param int $repoId
     * @param string $lockToken
     * @return bool true if released
     */
    public static function release(int $repoId, string $lockToken): bool
    {
        $deleted = Capsule::table('s3_kopia_repo_locks')
            ->where('repo_id', $repoId)
            ->where('lock_token', $lockToken)
            ->delete();
        return $deleted > 0;
    }
}
