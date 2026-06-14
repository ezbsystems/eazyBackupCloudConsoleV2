<?php
declare(strict_types=1);

namespace Ms365Backup;

final class PlannerBackupService
{
    public function __construct(
        private readonly GraphClient $graph,
        private readonly StorageLayout $storage,
        private readonly ProgressLogger $logger,
        private readonly ?RunCancellation $cancellation = null,
    ) {
    }

    /**
     * @return array{buckets: int, tasks: int}
     */
    public function backupPlan(string $planId): array
    {
        $this->logger->info('Starting Planner backup', ['plan_id' => $planId]);
        $root = $this->storage->plannerPlanRoot($planId);

        $plan = $this->graph->get("planner/plans/{$planId}");
        $this->storage->writeJson($root . '/plan.json', [
            'fetched_at' => gmdate('c'),
            'value' => $plan,
        ]);

        $buckets = [];
        $monitor = PaginationMonitor::forBackup($this->logger, 'planner.buckets');
        foreach ($this->graph->paginate("planner/plans/{$planId}/buckets", ['$top' => '100'], [], $monitor) as $bucket) {
            $this->cancellation?->check();
            $buckets[] = $bucket;
        }
        $this->storage->writeJson($root . '/buckets.json', [
            'fetched_at' => gmdate('c'),
            'value' => $buckets,
        ]);

        $taskCount = 0;
        $tasksDir = $root . '/tasks';
        foreach ($buckets as $bucket) {
            $this->cancellation?->check();
            $bucketId = (string) ($bucket['id'] ?? '');
            if ($bucketId === '') {
                continue;
            }
            $bucketDir = $this->storage->plannerBucketTasksDir($planId, $bucketId);
            $taskMonitor = PaginationMonitor::forBackup($this->logger, 'planner.tasks:' . $bucketId);
            foreach ($this->graph->paginate("planner/buckets/{$bucketId}/tasks", ['$top' => '100'], [], $taskMonitor) as $task) {
                $this->cancellation?->check();
                $taskId = (string) ($task['id'] ?? '');
                if ($taskId === '') {
                    continue;
                }
                $this->storage->writeJson($this->storage->plannerTaskPath($planId, $bucketId, $taskId), $task);
                $taskCount++;
            }
        }

        $stats = ['buckets' => count($buckets), 'tasks' => $taskCount];
        $this->logger->info('Planner backup finished', $stats);

        return $stats;
    }
}
