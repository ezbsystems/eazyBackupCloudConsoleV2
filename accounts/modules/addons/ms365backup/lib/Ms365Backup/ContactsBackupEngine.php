<?php
declare(strict_types=1);

namespace Ms365Backup;

final class ContactsBackupEngine implements BackupEngineInterface
{
    public function name(): string
    {
        return 'contacts';
    }

    public function supports(PhysicalBackupJob $job, BackupScope $scope): bool
    {
        if (!$scope->isEnabled(BackupScope::CONTACTS)) {
            return false;
        }

        return in_array($job->resourceType(), [TenantResource::TYPE_USER, TenantResource::TYPE_MAILBOX], true);
    }

    public function run(BackupEngineContext $ctx): array
    {
        $userId = $ctx->userGraphId();
        if ($userId === '') {
            return ['skipped' => true, 'reason' => 'Not a user resource'];
        }

        BackupRunRepository::setPhase($ctx->runId, 'contacts_folders');
        $ctx->logger->info('Starting contacts backup phase', ['user_id' => $userId]);
        $service = new ContactsBackupService(
            $ctx->graph,
            $ctx->storage,
            $ctx->logger,
            $ctx->runId,
            $ctx->cancellation,
        );
        BackupRunRepository::setPhase($ctx->runId, 'contacts_sync');

        try {
            return $service->backupUser($userId);
        } catch (ResourceUnavailableException $e) {
            $ctx->logger->warn('Contacts backup skipped: ' . $e->getMessage(), [
                'user_id' => $userId,
                'access_status' => $e->accessResult->status,
            ]);
            ResourceAccessService::recordUserAccessFromException($userId, 'contacts', $e);

            return [
                'skipped' => true,
                'reason' => $e->getMessage(),
                'access_status' => $e->accessResult->status,
            ];
        }
    }
}
