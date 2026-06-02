<?php
declare(strict_types=1);

namespace Ms365Backup;

final class OneDriveBackupEngine implements BackupEngineInterface
{
    public function name(): string
    {
        return 'onedrive';
    }

    public function supports(PhysicalBackupJob $job, BackupScope $scope): bool
    {
        if (!$scope->isEnabled(BackupScope::ONEDRIVE)) {
            return false;
        }

        return $job->resourceType() === TenantResource::TYPE_USER_ONEDRIVE;
    }

    public function run(BackupEngineContext $ctx): array
    {
        $driveId = $ctx->driveId();
        if ($driveId === '') {
            return ['skipped' => true, 'reason' => 'Missing drive ID on OneDrive resource'];
        }

        BackupRunRepository::setPhase($ctx->runId, 'onedrive_delta');
        $ctx->logger->info('Starting OneDrive backup phase', ['drive_id' => $driveId]);

        $service = new OneDriveBackupService(
            $ctx->graph,
            $ctx->storage,
            $ctx->logger,
            $ctx->runId,
            $ctx->cancellation,
        );
        BackupRunRepository::setPhase($ctx->runId, 'onedrive_content');

        try {
            return $service->backupDrive($driveId);
        } catch (ResourceUnavailableException $e) {
            $ctx->logger->warn('OneDrive backup skipped: ' . $e->getMessage(), [
                'drive_id' => $driveId,
                'access_status' => $e->accessResult->status,
            ]);
            ResourceAccessService::recordDriveAccessFromException($driveId, $e);

            return [
                'skipped' => true,
                'reason' => $e->getMessage(),
                'access_status' => $e->accessResult->status,
            ];
        }
    }
}
