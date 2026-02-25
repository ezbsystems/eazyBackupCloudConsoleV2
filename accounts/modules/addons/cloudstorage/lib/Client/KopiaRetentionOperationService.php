<?php
declare(strict_types=1);

namespace WHMCS\Module\Addon\CloudStorage\Client;

use Illuminate\Database\QueryException;

// Load shared helper (used by this service and KopiaRetentionLockService)
require_once __DIR__ . '/KopiaRetentionDbHelper.php';
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
            if (KopiaRetentionDbHelper::isDuplicateKeyException($e)) {
                $existing = Capsule::table('s3_kopia_repo_operations')->where('operation_token', $token)->first();
                if ($existing) {
                    return ['status' => 'duplicate', 'operation_id' => (int) $existing->id];
                }
            }
            throw $e;
        }
    }
}
