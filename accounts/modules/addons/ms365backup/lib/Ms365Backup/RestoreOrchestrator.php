<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * Queues Kopia restore jobs for the Go worker fleet.
 */
final class RestoreOrchestrator
{
    public function __construct(private readonly string $restoreRunId)
    {
    }

    public function execute(): void
    {
        $run = RestoreRunRepository::get($this->restoreRunId);
        if ($run === null) {
            throw new \RuntimeException('Restore run not found.');
        }

        $manifestId = trim((string) ($run['source_manifest_id'] ?? ''));
        if ($manifestId === '') {
            RestoreRunRepository::update($this->restoreRunId, [
                'status' => 'error',
                'error_message' => 'No Kopia manifest for restore.',
                'finished_at' => time(),
            ]);

            return;
        }

        JobQueueRepository::enqueueRestore($this->restoreRunId);
        RestoreRunRepository::update($this->restoreRunId, [
            'status' => 'queued',
            'phase' => 'queued',
        ]);
    }
}
