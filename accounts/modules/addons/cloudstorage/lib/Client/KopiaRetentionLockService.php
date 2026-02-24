<?php
declare(strict_types=1);

namespace WHMCS\Module\Addon\CloudStorage\Client;

use WHMCS\Database\Capsule;

/**
 * Acquires, renews, and releases per-repo lease locks in s3_kopia_repo_locks.
 */
class KopiaRetentionLockService
{
    /**
     * Acquire a lock on repo_id. If lock exists and is not expired with same token, treat as success.
     * If no lock or expired, insert/update.
     *
     * @param int $repoId
     * @param string $lockToken
     * @param int|null $agentId
     * @param int $ttlSeconds
     * @return bool true if acquired
     */
    public static function acquire(int $repoId, string $lockToken, ?int $agentId, int $ttlSeconds = 300): bool
    {
        $now = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', time() + $ttlSeconds);

        $row = Capsule::table('s3_kopia_repo_locks')->where('repo_id', $repoId)->first();
        if ($row) {
            if ($row->lock_token === $lockToken) {
                return true;
            }
            if ($row->expires_at && $row->expires_at < $now) {
                Capsule::table('s3_kopia_repo_locks')->where('repo_id', $repoId)->delete();
            } else {
                return false;
            }
        }
        Capsule::table('s3_kopia_repo_locks')->insert([
            'repo_id' => $repoId,
            'lock_token' => $lockToken,
            'claimed_by_agent_id' => $agentId,
            'expires_at' => $expiresAt,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return true;
    }

    /**
     * Renew lock expiry if token matches and lock exists.
     *
     * @param int $repoId
     * @param string $lockToken
     * @param int $ttlSeconds
     * @return bool true if renewed
     */
    public static function renew(int $repoId, string $lockToken, int $ttlSeconds = 300): bool
    {
        $row = Capsule::table('s3_kopia_repo_locks')->where('repo_id', $repoId)->first();
        if (!$row || $row->lock_token !== $lockToken) {
            return false;
        }
        $expiresAt = date('Y-m-d H:i:s', time() + $ttlSeconds);
        $now = date('Y-m-d H:i:s');
        Capsule::table('s3_kopia_repo_locks')->where('repo_id', $repoId)->update([
            'expires_at' => $expiresAt,
            'updated_at' => $now,
        ]);
        return true;
    }

    /**
     * Release lock if token matches.
     *
     * @param int $repoId
     * @param string $lockToken
     * @return bool true if released
     */
    public static function release(int $repoId, string $lockToken): bool
    {
        $row = Capsule::table('s3_kopia_repo_locks')->where('repo_id', $repoId)->first();
        if (!$row || $row->lock_token !== $lockToken) {
            return false;
        }
        Capsule::table('s3_kopia_repo_locks')->where('repo_id', $repoId)->delete();
        return true;
    }
}
