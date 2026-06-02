<?php
declare(strict_types=1);

namespace Ms365Backup;

final class TeamsBackupEngine implements BackupEngineInterface
{
    public function name(): string
    {
        return 'teams';
    }

    public function supports(PhysicalBackupJob $job, BackupScope $scope): bool
    {
        if (!in_array($job->resourceType(), [TenantResource::TYPE_TEAM, TenantResource::TYPE_TEAM_CHANNEL], true)) {
            return false;
        }

        return $scope->isEnabled(BackupScope::TEAMS_METADATA) || $scope->isEnabled(BackupScope::TEAMS_MESSAGES);
    }

    public function run(BackupEngineContext $ctx): array
    {
        $groupId = $ctx->teamGroupId();
        if ($groupId === '') {
            return ['skipped' => true, 'reason' => 'Missing team group ID on resource'];
        }

        $channelIds = $ctx->channelIdsFilter();
        $results = [];
        $scope = $ctx->scope;

        if ($scope->isEnabled(BackupScope::TEAMS_METADATA)) {
            BackupRunRepository::setPhase($ctx->runId, 'teams_metadata');
            $ctx->logger->info('Starting Teams metadata backup', ['team_id' => $groupId]);
            $metadataService = new TeamsMetadataBackupService(
                $ctx->graph,
                $ctx->storage,
                $ctx->logger,
                $ctx->cancellation,
            );
            try {
                $results['metadata'] = $metadataService->backupTeamMetadata($groupId, $channelIds);
            } catch (GraphApiException $e) {
                $result = ResourceAccessClassifier::classify($e);
                if ($result->skippable) {
                    $results['metadata'] = [
                        'skipped' => true,
                        'reason' => $e->getMessage(),
                        'access_status' => $result->status,
                    ];
                    ResourceAccessService::recordTeamAccessFromException($groupId, 'metadata', $e);
                } else {
                    throw $e;
                }
            } catch (ResourceUnavailableException $e) {
                $results['metadata'] = [
                    'skipped' => true,
                    'reason' => $e->getMessage(),
                    'access_status' => $e->accessResult->status,
                ];
            }
        }

        if ($scope->isEnabled(BackupScope::TEAMS_MESSAGES)) {
            BackupRunRepository::setPhase($ctx->runId, 'teams_messages');
            $ctx->logger->info('Starting Teams messages backup', ['team_id' => $groupId]);
            $messagesService = new TeamsMessagesBackupService(
                $ctx->graph,
                $ctx->storage,
                $ctx->logger,
                $ctx->runId,
                $ctx->cancellation,
            );
            try {
                if ($channelIds !== null && count($channelIds) === 1) {
                    $channelId = $channelIds[0];
                    $channelName = (string) ($ctx->job->primaryResource['display_name'] ?? $channelId);
                    $results['messages'] = $messagesService->backupChannelMessages($groupId, $channelId, $channelName);
                } else {
                    $results['messages'] = $messagesService->backupTeamMessages($groupId, $channelIds);
                }
            } catch (GraphApiException $e) {
                $result = ResourceAccessClassifier::classify($e);
                if ($result->skippable) {
                    $results['messages'] = [
                        'skipped' => true,
                        'reason' => $e->getMessage(),
                        'access_status' => $result->status,
                    ];
                    ResourceAccessService::recordTeamAccessFromException($groupId, 'messages', $e);
                } else {
                    throw $e;
                }
            } catch (ResourceUnavailableException $e) {
                $results['messages'] = [
                    'skipped' => true,
                    'reason' => $e->getMessage(),
                    'access_status' => $e->accessResult->status,
                ];
            }
        }

        return $results;
    }
}
