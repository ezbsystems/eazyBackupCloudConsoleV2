<?php
declare(strict_types=1);

namespace Ms365Backup;

final class OneNoteBackupEngine implements BackupEngineInterface
{
    public function name(): string
    {
        return 'onenote';
    }

    public function supports(PhysicalBackupJob $job, BackupScope $scope): bool
    {
        if ($job->resourceType() !== TenantResource::TYPE_ONENOTE_NOTEBOOK) {
            return false;
        }

        return $scope->isEnabled(BackupScope::ONENOTE);
    }

    public function run(BackupEngineContext $ctx): array
    {
        $meta = is_array($ctx->job->primaryResource['meta'] ?? null)
            ? $ctx->job->primaryResource['meta']
            : [];
        $ownerKind = (string) ($meta['owner_kind'] ?? 'users');
        $ownerId = (string) ($meta['owner_id'] ?? '');
        $notebookId = (string) ($meta['notebook_id'] ?? $ctx->job->graphId());
        if ($notebookId === '' || $ownerId === '') {
            return ['skipped' => true, 'reason' => 'Missing OneNote notebook context'];
        }

        BackupRunRepository::setPhase($ctx->runId, 'onenote');
        $service = new OneNoteBackupService($ctx->graph, $ctx->storage, $ctx->logger, $ctx->cancellation);

        try {
            return $service->backupNotebook([
                'owner_kind' => $ownerKind,
                'owner_id' => $ownerId,
                'notebook_id' => $notebookId,
            ]);
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
