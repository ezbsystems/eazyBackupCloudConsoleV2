<?php
declare(strict_types=1);

namespace Ms365Backup;

final class GroupBackupEngine implements BackupEngineInterface
{
    public function name(): string
    {
        return 'group';
    }

    public function supports(PhysicalBackupJob $job, BackupScope $scope): bool
    {
        if ($job->resourceType() !== TenantResource::TYPE_M365_GROUP) {
            return false;
        }

        return $scope->isEnabled(BackupScope::MAIL) || $scope->isEnabled(BackupScope::CALENDAR);
    }

    public function run(BackupEngineContext $ctx): array
    {
        $groupId = $ctx->groupGraphId();
        if ($groupId === '') {
            return ['skipped' => true, 'reason' => 'Missing group ID on resource'];
        }

        $owner = GraphMailboxOwner::group($groupId);
        $results = [];

        if ($ctx->scope->isEnabled(BackupScope::MAIL)) {
            BackupRunRepository::setPhase($ctx->runId, 'mail_folders');
            $ctx->logger->info('Starting group mail backup', ['group_id' => $groupId]);
            $mailService = new MailBackupService(
                $ctx->graph,
                $ctx->storage,
                $ctx->logger,
                $ctx->runId,
                $ctx->cancellation,
            );
            BackupRunRepository::setPhase($ctx->runId, 'mail_messages');
            try {
                $results['mail'] = $mailService->backupMailbox($owner);
            } catch (ResourceUnavailableException $e) {
                $results['mail'] = [
                    'skipped' => true,
                    'reason' => $e->getMessage(),
                    'access_status' => $e->accessResult->status,
                ];
                ResourceAccessService::recordGroupAccessFromException($groupId, 'mail', $e);
            } catch (GraphApiException $e) {
                $result = ResourceAccessClassifier::classify($e);
                if ($result->skippable) {
                    $results['mail'] = [
                        'skipped' => true,
                        'reason' => $e->getMessage(),
                        'access_status' => $result->status,
                    ];
                    ResourceAccessService::recordGroupAccessFromException($groupId, 'mail', $e);
                } else {
                    throw $e;
                }
            }
        }

        if ($ctx->scope->isEnabled(BackupScope::CALENDAR)) {
            BackupRunRepository::setPhase($ctx->runId, 'calendars');
            $ctx->logger->info('Starting group calendar backup', ['group_id' => $groupId]);
            $calendarService = new CalendarBackupService(
                $ctx->graph,
                $ctx->storage,
                $ctx->logger,
                $ctx->runId,
                $ctx->cancellation,
            );
            BackupRunRepository::setPhase($ctx->runId, 'calendar_events');
            try {
                $results['calendar'] = $calendarService->backupMailbox($owner);
            } catch (ResourceUnavailableException $e) {
                $results['calendar'] = [
                    'skipped' => true,
                    'reason' => $e->getMessage(),
                    'access_status' => $e->accessResult->status,
                ];
                ResourceAccessService::recordGroupAccessFromException($groupId, 'calendar', $e);
            } catch (CalendarBackupIncompleteException $e) {
                throw $e;
            } catch (GraphApiException $e) {
                $result = ResourceAccessClassifier::classify($e);
                if ($result->skippable) {
                    $results['calendar'] = [
                        'skipped' => true,
                        'reason' => $e->getMessage(),
                        'access_status' => $result->status,
                    ];
                    ResourceAccessService::recordGroupAccessFromException($groupId, 'calendar', $e);
                } else {
                    throw $e;
                }
            }
        }

        return $results;
    }
}
