<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\KopiaRetentionOperationService;

/**
 * Schedules Kopia maintenance for MS365 customer repositories.
 */
final class Ms365KopiaMaintenanceService
{
    /** @return array{scheduled: int, skipped: int} */
    public static function scheduleDueMaintenance(): array
    {
        if (!Capsule::schema()->hasTable('ms365_tenant_records')
            || !Capsule::schema()->hasTable('s3_kopia_repos')
            || !Capsule::schema()->hasTable('s3_kopia_repo_operations')) {
            return ['scheduled' => 0, 'skipped' => 0];
        }

        $intervalDays = Ms365EngineConfig::kopiaMaintenanceIntervalDays();
        $cutoff = time() - ($intervalDays * 86400);
        $scheduled = 0;
        $skipped = 0;

        $tenants = Capsule::table('ms365_tenant_records')
            ->where('connection_status', 'connected')
            ->get(['id', 'whmcs_client_id', 's3_bucket_name', 's3_bucket']);

        foreach ($tenants as $tenant) {
            $tenantRow = (array) $tenant;
            try {
                if (self::scheduleForTenantIfDue($tenantRow, $cutoff)) {
                    ++$scheduled;
                } else {
                    ++$skipped;
                }
            } catch (\Throwable $e) {
                ++$skipped;
                logActivity('MS365 Kopia maintenance skipped for tenant #' . ($tenantRow['id'] ?? '?') . ': ' . $e->getMessage());
            }
        }

        return ['scheduled' => $scheduled, 'skipped' => $skipped];
    }

    /**
     * @param array<string, mixed> $tenantRow
     */
    public static function scheduleForTenantIfDue(array $tenantRow, ?int $cutoffTs = null): bool
    {
        $repoMeta = KopiaRepoBootstrapService::ensureForTenantRecord($tenantRow);
        $repositoryId = (string) ($repoMeta['repository_id'] ?? '');
        if ($repositoryId === '') {
            return false;
        }

        $repoRow = Capsule::table('s3_kopia_repos')->where('repository_id', $repositoryId)->first();
        if ($repoRow === null) {
            return false;
        }

        $repoId = (int) $repoRow->id;
        $intervalDays = Ms365EngineConfig::kopiaMaintenanceIntervalDays();
        $cutoffTs = $cutoffTs ?? (time() - ($intervalDays * 86400));
        $tokenPrefix = 'ms365-maint-' . $repoId . '-';

        $recent = Capsule::table('s3_kopia_repo_operations')
            ->where('repo_id', $repoId)
            ->whereIn('op_type', ['maintenance_quick', 'maintenance_full'])
            ->where('created_at', '>=', date('Y-m-d H:i:s', $cutoffTs))
            ->exists();
        if ($recent) {
            return false;
        }

        $weekToken = $tokenPrefix . gmdate('o-\WW');
        $result = KopiaRetentionOperationService::enqueue(
            $repoId,
            'maintenance_quick',
            ['repo_id' => $repoId, 'engine' => 'ms365'],
            $weekToken,
        );

        return in_array((string) ($result['status'] ?? ''), ['success', 'duplicate'], true);
    }

    /**
     * Claim the next queued maintenance operation for an MS365 repository.
     *
     * @return array<string, mixed>|null
     */
    public static function claimNextForWorker(string $nodeId): ?array
    {
        if ($nodeId === ''
            || !Capsule::schema()->hasTable('s3_kopia_repo_operations')
            || !Capsule::schema()->hasTable('s3_kopia_repos')) {
            return null;
        }

        $op = Capsule::table('s3_kopia_repo_operations as op')
            ->join('s3_kopia_repos as r', 'r.id', '=', 'op.repo_id')
            ->where('op.status', 'queued')
            ->whereIn('op.op_type', ['maintenance_quick', 'maintenance_full'])
            ->where('r.repository_id', 'like', 'ms365:%')
            ->orderBy('op.created_at')
            ->select(['op.*', 'r.repository_id', 'r.client_id', 'r.tenant_id'])
            ->first();

        if ($op === null) {
            return null;
        }

        $now = date('Y-m-d H:i:s');
        $updated = Capsule::table('s3_kopia_repo_operations')
            ->where('id', $op->id)
            ->where('status', 'queued')
            ->update([
                'status' => 'running',
                'updated_at' => $now,
            ]);
        if ($updated === 0) {
            return null;
        }

        $tenantRecordId = (int) ($op->tenant_id ?? 0);
        $record = $tenantRecordId > 0 ? TenantRecordRepository::getById($tenantRecordId) : null;
        if ($record === null) {
            Capsule::table('s3_kopia_repo_operations')->where('id', $op->id)->update([
                'status' => 'error',
                'updated_at' => $now,
            ]);

            return null;
        }

        $dest = WorkerClaimService::destinationForTenantRecord($record);

        return [
            'operation_id' => (int) $op->id,
            'op_type' => (string) $op->op_type,
            'operation_token' => (string) $op->operation_token,
            'repository_id' => (string) $op->repository_id,
            'tenant_record_id' => $tenantRecordId,
            'whmcs_client_id' => (int) ($record['whmcs_client_id'] ?? 0),
            'azure_tenant_id' => (string) (TenantRecordRepository::platformCredentials($record)['tenant_id'] ?? ''),
            'dest_endpoint' => $dest['endpoint'],
            'dest_region' => $dest['region'],
            'dest_bucket' => $dest['bucket'],
            'dest_prefix' => $dest['prefix'],
            'dest_access_key' => $dest['access_key'],
            'dest_secret_key' => $dest['secret_key'],
            'repo_password' => $dest['repo_password'],
            'kopia_repo_id' => $dest['kopia_repo_id'],
            'worker_node_id' => $nodeId,
        ];
    }

    public static function markComplete(int $operationId, string $status = 'success'): void
    {
        if ($operationId <= 0) {
            return;
        }
        Capsule::table('s3_kopia_repo_operations')
            ->where('id', $operationId)
            ->update([
                'status' => $status === 'success' ? 'success' : 'error',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }
}
