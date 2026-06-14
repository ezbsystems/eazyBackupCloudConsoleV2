<?php
declare(strict_types=1);

namespace Ms365Backup;

final class PlannerBackupEngine implements BackupEngineInterface
{
    public function name(): string
    {
        return 'planner';
    }

    public function supports(PhysicalBackupJob $job, BackupScope $scope): bool
    {
        if ($job->resourceType() !== TenantResource::TYPE_PLANNER_PLAN) {
            return false;
        }

        return $scope->isEnabled(BackupScope::PLANNER);
    }

    public function run(BackupEngineContext $ctx): array
    {
        $planId = $ctx->job->graphId();
        if ($planId === '') {
            return ['skipped' => true, 'reason' => 'Missing Planner plan ID'];
        }

        BackupRunRepository::setPhase($ctx->runId, 'planner');
        $service = new PlannerBackupService($ctx->graph, $ctx->storage, $ctx->logger, $ctx->cancellation);

        try {
            return $service->backupPlan($planId);
        } catch (GraphApiException $e) {
            $result = ResourceAccessClassifier::classify($e);
            if ($result->skippable) {
                return [
                    'skipped' => true,
                    'reason' => $e->getMessage(),
                    'access_status' => $result->status,
                ];
            }
            throw $e;
        }
    }
}
