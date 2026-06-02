<?php
declare(strict_types=1);

namespace Ms365Backup;

final class CalendarBackupEngine implements BackupEngineInterface
{
    public function name(): string
    {
        return 'calendar';
    }

    public function supports(PhysicalBackupJob $job, BackupScope $scope): bool
    {
        if (!$scope->isEnabled(BackupScope::CALENDAR)) {
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

        BackupRunRepository::setPhase($ctx->runId, 'calendars');
        $ctx->logger->info('Starting calendar backup phase', ['user_id' => $userId]);
        $calService = new CalendarBackupService($ctx->graph, $ctx->storage, $ctx->logger, $ctx->runId, $ctx->cancellation);
        BackupRunRepository::setPhase($ctx->runId, 'calendar_events');

        try {
            return $calService->backupUser($userId);
        } catch (ResourceUnavailableException $e) {
            $ctx->logger->warn('Calendar backup skipped: ' . $e->getMessage(), [
                'user_id' => $userId,
                'access_status' => $e->accessResult->status,
            ]);
            ResourceAccessService::recordUserAccessFromException($userId, 'calendar', $e);

            return [
                'skipped' => true,
                'reason' => $e->getMessage(),
                'access_status' => $e->accessResult->status,
            ];
        }
    }
}
