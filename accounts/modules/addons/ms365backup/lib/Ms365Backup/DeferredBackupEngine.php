<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * Catches non-user resource types if they are queued by mistake.
 */
final class DeferredBackupEngine implements BackupEngineInterface
{
    public function name(): string
    {
        return 'deferred';
    }

    public function supports(PhysicalBackupJob $job, BackupScope $scope): bool
    {
        return !in_array($job->resourceType(), [
            TenantResource::TYPE_USER,
            TenantResource::TYPE_MAILBOX,
            TenantResource::TYPE_USER_ONEDRIVE,
            TenantResource::TYPE_SHAREPOINT_SITE,
            TenantResource::TYPE_TEAM,
            TenantResource::TYPE_TEAM_CHANNEL,
        ], true);
    }

    public function run(BackupEngineContext $ctx): array
    {
        return [
            'skipped' => true,
            'reason' => $ctx->job->deferReason !== ''
                ? $ctx->job->deferReason
                : 'No backup engine registered for resource type: ' . $ctx->job->resourceType(),
        ];
    }
}
