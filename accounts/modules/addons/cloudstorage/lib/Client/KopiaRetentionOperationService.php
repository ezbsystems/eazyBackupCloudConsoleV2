<?php
declare(strict_types=1);

namespace WHMCS\Module\Addon\CloudStorage\Client;

use Illuminate\Database\QueryException;
use WHMCS\Database\Capsule;

/**
 * Enqueues and manages repo operations in s3_kopia_repo_operations.
 * Deduplicates by operation_token using DB unique constraint (insert-first, atomic).
 */
class KopiaRetentionOperationService
{
    /**
     * Enqueue a repo operation. Returns duplicate if operation_token already exists.
     * Uses insert-first with catch on duplicate key; non-duplicate exceptions surface.
     *
     * @param int $repoId
     * @param string $opType e.g. retention_apply, maintenance_quick, maintenance_full
     * @param array $payload
     * @param string $token unique idempotency token
     * @return array{status: string, operation_id?: int}
     */
    public static function enqueue(int $repoId, string $opType, array $payload, string $token): array
    {
        $now = date('Y-m-d H:i:s');
        try {
            $id = Capsule::table('s3_kopia_repo_operations')->insertGetId([
                'repo_id' => $repoId,
                'op_type' => $opType,
                'status' => 'queued',
                'operation_token' => $token,
                'payload_json' => json_encode($payload),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            return ['status' => 'success', 'operation_id' => (int) $id];
        } catch (QueryException $e) {
            if (self::isDuplicateKeyException($e)) {
                $existing = Capsule::table('s3_kopia_repo_operations')->where('operation_token', $token)->first();
                if ($existing) {
                    return ['status' => 'duplicate', 'operation_id' => (int) $existing->id];
                }
            }
            throw $e;
        }
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
}
