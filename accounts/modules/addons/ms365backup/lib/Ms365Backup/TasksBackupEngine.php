<?php
declare(strict_types=1);

namespace Ms365Backup;

final class TasksBackupEngine implements BackupEngineInterface
{
    public function name(): string
    {
        return 'tasks';
    }

    public function supports(PhysicalBackupJob $job, BackupScope $scope): bool
    {
        if (!$scope->isEnabled(BackupScope::TASKS)) {
            return false;
        }

        // Microsoft To Do is per-user; shared mailboxes do not expose /todo.
        return $job->resourceType() === TenantResource::TYPE_USER;
    }

    public function run(BackupEngineContext $ctx): array
    {
        $userId = $ctx->userGraphId();
        if ($userId === '') {
            return ['skipped' => true, 'reason' => 'Not a user resource'];
        }

        if ($ctx->job->resourceType() === TenantResource::TYPE_MAILBOX) {
            return [
                'skipped' => true,
                'reason' => 'Microsoft To Do is not available for shared mailbox resources',
            ];
        }

        BackupRunRepository::setPhase($ctx->runId, 'todo_lists');
        $ctx->logger->info('Starting To Do backup phase', ['user_id' => $userId]);
        $service = new TasksBackupService(
            $ctx->graph,
            $ctx->storage,
            $ctx->logger,
            $ctx->runId,
            $ctx->cancellation,
        );
        BackupRunRepository::setPhase($ctx->runId, 'todo_tasks');

        try {
            return $service->backupUser($userId);
        } catch (ResourceUnavailableException $e) {
            $ctx->logger->warn('To Do backup skipped: ' . $e->getMessage(), [
                'user_id' => $userId,
                'access_status' => $e->accessResult->status,
            ]);
            ResourceAccessService::recordUserAccessFromException($userId, 'tasks', $e);

            return [
                'skipped' => true,
                'reason' => $e->getMessage(),
                'access_status' => $e->accessResult->status,
            ];
        }
    }
}
