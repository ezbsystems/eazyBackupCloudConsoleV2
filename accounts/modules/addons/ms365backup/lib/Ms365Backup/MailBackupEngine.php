<?php
declare(strict_types=1);

namespace Ms365Backup;

final class MailBackupEngine implements BackupEngineInterface
{
    public function name(): string
    {
        return 'mail';
    }

    public function supports(PhysicalBackupJob $job, BackupScope $scope): bool
    {
        if (!$scope->isEnabled(BackupScope::MAIL)) {
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

        BackupRunRepository::setPhase($ctx->runId, 'mail_folders');
        $ctx->logger->info('Starting mail backup phase', ['user_id' => $userId]);
        $mailService = new MailBackupService($ctx->graph, $ctx->storage, $ctx->logger, $ctx->runId, $ctx->cancellation);
        BackupRunRepository::setPhase($ctx->runId, 'mail_messages');

        try {
            return $mailService->backupUser($userId);
        } catch (ResourceUnavailableException $e) {
            $ctx->logger->warn('Mail backup skipped: ' . $e->getMessage(), [
                'user_id' => $userId,
                'access_status' => $e->accessResult->status,
            ]);
            ResourceAccessService::recordUserAccessFromException($userId, 'mail', $e);

            return [
                'skipped' => true,
                'reason' => $e->getMessage(),
                'access_status' => $e->accessResult->status,
            ];
        }
    }
}
