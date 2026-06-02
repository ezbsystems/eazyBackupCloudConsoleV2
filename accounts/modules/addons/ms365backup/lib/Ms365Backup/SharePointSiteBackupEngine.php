<?php
declare(strict_types=1);

namespace Ms365Backup;

final class SharePointSiteBackupEngine implements BackupEngineInterface
{
    public function name(): string
    {
        return 'sharepoint';
    }

    public function supports(PhysicalBackupJob $job, BackupScope $scope): bool
    {
        if ($job->resourceType() !== TenantResource::TYPE_SHAREPOINT_SITE) {
            return false;
        }

        return $scope->isEnabled(BackupScope::FILES) || $scope->isEnabled(BackupScope::LISTS);
    }

    public function run(BackupEngineContext $ctx): array
    {
        $siteId = $ctx->siteGraphId();
        if ($siteId === '') {
            return ['skipped' => true, 'reason' => 'Missing site ID on SharePoint resource'];
        }

        $results = [];
        $scope = $ctx->scope;

        if ($scope->isEnabled(BackupScope::FILES)) {
            BackupRunRepository::setPhase($ctx->runId, 'sharepoint_files');
            $ctx->logger->info('Starting SharePoint files backup', ['site_id' => $siteId]);
            $filesService = new SharePointFilesBackupService(
                $ctx->graph,
                $ctx->storage,
                $ctx->logger,
                $ctx->runId,
                $ctx->cancellation,
            );
            try {
                $results['files'] = $filesService->backupSiteFiles($siteId);
            } catch (GraphApiException $e) {
                $result = ResourceAccessClassifier::classify($e);
                if ($result->skippable) {
                    $results['files'] = [
                        'skipped' => true,
                        'reason' => $e->getMessage(),
                        'access_status' => $result->status,
                    ];
                    ResourceAccessService::recordSiteAccessFromException($siteId, 'files', $e);
                } else {
                    throw $e;
                }
            } catch (ResourceUnavailableException $e) {
                $results['files'] = [
                    'skipped' => true,
                    'reason' => $e->getMessage(),
                    'access_status' => $e->accessResult->status,
                ];
            }
        }

        if ($scope->isEnabled(BackupScope::LISTS)) {
            BackupRunRepository::setPhase($ctx->runId, 'sharepoint_lists');
            $ctx->logger->info('Starting SharePoint lists backup', ['site_id' => $siteId]);
            $listsService = new SharePointListsBackupService(
                $ctx->graph,
                $ctx->storage,
                $ctx->logger,
                $ctx->runId,
                $ctx->cancellation,
            );
            try {
                $results['lists'] = $listsService->backupSiteLists($siteId);
            } catch (GraphApiException $e) {
                $result = ResourceAccessClassifier::classify($e);
                if ($result->skippable) {
                    $results['lists'] = [
                        'skipped' => true,
                        'reason' => $e->getMessage(),
                        'access_status' => $result->status,
                    ];
                    ResourceAccessService::recordSiteAccessFromException($siteId, 'lists', $e);
                } else {
                    throw $e;
                }
            } catch (ResourceUnavailableException $e) {
                $results['lists'] = [
                    'skipped' => true,
                    'reason' => $e->getMessage(),
                    'access_status' => $e->accessResult->status,
                ];
            }
        }

        return $results;
    }
}
