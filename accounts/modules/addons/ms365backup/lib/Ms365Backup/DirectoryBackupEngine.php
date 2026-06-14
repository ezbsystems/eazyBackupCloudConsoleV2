<?php
declare(strict_types=1);

namespace Ms365Backup;

final class DirectoryBackupEngine implements BackupEngineInterface
{
    public function name(): string
    {
        return 'directory';
    }

    public function supports(PhysicalBackupJob $job, BackupScope $scope): bool
    {
        return $job->resourceType() === TenantResource::TYPE_DIRECTORY_BASELINE;
    }

    public function run(BackupEngineContext $ctx): array
    {
        BackupRunRepository::setPhase($ctx->runId, 'directory_baseline');
        $service = new DirectoryBackupService($ctx->graph, $ctx->storage, $ctx->logger, $ctx->cancellation);

        return $service->exportTenantBaseline();
    }
}
