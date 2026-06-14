<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * Re-queue a failed backup run with a subset of engines (mail/calendar first).
 */
final class FailedEngineRetryService
{
    /**
     * @param list<string> $engines e.g. mail, calendar
     */
    public static function retryRun(string $runId, array $engines = [BackupScope::MAIL, BackupScope::CALENDAR]): string
    {
        $run = BackupRunRepository::get($runId);
        if ($run === null) {
            throw new \RuntimeException('Run not found.');
        }
        if (!in_array($run['status'] ?? '', ['error', 'cancelled'], true)) {
            throw new \RuntimeException('Only failed or cancelled runs can be retried.');
        }

        $scopeData = [];
        if (is_string($run['scope_json'] ?? null) && $run['scope_json'] !== '') {
            $decoded = json_decode($run['scope_json'], true);
            if (is_array($decoded)) {
                foreach ($decoded as $k => $v) {
                    $scopeData[$k] = in_array($k, $engines, true) ? true : false;
                }
            }
        }
        foreach ($engines as $engine) {
            $scopeData[$engine] = true;
        }

        $scope = new BackupScope($scopeData);
        $physicalKey = (string) ($run['physical_key'] ?? '');
        $resourceType = (string) ($run['resource_type'] ?? TenantResource::TYPE_USER);
        $graphId = (string) ($run['graph_id'] ?? $run['user_id'] ?? '');
        $resourceId = (string) ($run['resource_id'] ?? TenantResource::makeId($resourceType, $graphId));

        $job = new PhysicalBackupJob(
            $physicalKey !== '' ? $physicalKey : ('user:' . $graphId),
            [
                'id' => $resourceId,
                'resource_type' => $resourceType,
                'graph_id' => $graphId,
                'display_name' => (string) ($run['user_display_name'] ?? ''),
                'email' => (string) ($run['user_upn'] ?? ''),
            ],
            [],
            $scope,
            PhysicalBackupJob::STATUS_RUNNABLE,
        );

        $tenantId = (string) (($run['tenant_record_id'] ?? 0) > 0
            ? (TenantRecordRepository::getById((int) $run['tenant_record_id'])['azure_tenant_id'] ?? '')
            : (TenantRepository::get()['tenant_id'] ?? ''));
        if ($tenantId === '') {
            throw new \RuntimeException('Tenant not configured for retry.');
        }

        $storage = new StorageLayout($tenantId);
        $newRunId = BackupRunRepository::createFromPhysicalJob(
            $job,
            $storage,
            isset($run['tenant_record_id']) ? (int) $run['tenant_record_id'] : null,
            (int) ($run['whmcs_client_id'] ?? 0),
        );
        JobQueueRepository::enqueue($newRunId, 80);
        $logger = new ProgressLogger($newRunId, $storage->runDirForJob($job->physicalKey, $newRunId) . '/run.log');
        WorkerSpawner::spawn($newRunId, $logger);

        return $newRunId;
    }
}
