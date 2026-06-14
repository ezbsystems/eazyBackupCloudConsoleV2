<?php
declare(strict_types=1);

/**
 * Processes ms365_job_queue entries (spawn via WorkerSpawner::spawnQueueProcessor).
 */
require __DIR__ . '/bootstrap.php';

use Ms365Backup\BackupOrchestrator;
use Ms365Backup\JobQueueRepository;
use Ms365Backup\ProgressLogger;
use Ms365Backup\StorageLayout;

$maxJobs = (int) ($argv[1] ?? 10);
$processed = 0;

while ($processed < $maxJobs) {
    $job = JobQueueRepository::claimNext();
    if ($job === null) {
        break;
    }
    $runId = (string) ($job['run_id'] ?? '');
    if ($runId === '') {
        continue;
    }
    $logger = new ProgressLogger($runId, StorageLayout::BASE_PATH . '/_logs/worker.log');
    try {
        (new BackupOrchestrator($runId, $logger))->execute();
        JobQueueRepository::markDone($runId);
    } catch (\Throwable $e) {
        JobQueueRepository::markFailed($runId, $e->getMessage());
        $logger->error('Queue worker failed', ['error' => $e->getMessage()]);
    }
    $processed++;
}

exit(0);
