<?php
declare(strict_types=1);

namespace Ms365Backup;

final class TasksBackupService
{
    private const TASK_SELECT = 'id,title,status,importance,createdDateTime,lastModifiedDateTime,completedDateTime,dueDateTime,body,isReminderOn';

    public function __construct(
        private readonly GraphClient $graph,
        private readonly StorageLayout $storage,
        private readonly ProgressLogger $logger,
        private readonly string $runId,
        private readonly ?RunCancellation $cancellation = null,
    ) {
    }

    /**
     * @return array{lists: int, tasks: int, created: int, updated: int, removed: int, lists_resynced: int}
     */
    public function backupUser(string $userId): array
    {
        $this->logger->info('Starting To Do (tasks) backup', ['user_id' => $userId]);

        $stats = [
            'lists' => 0,
            'tasks' => 0,
            'created' => 0,
            'updated' => 0,
            'removed' => 0,
            'lists_resynced' => 0,
        ];

        $listMonitor = PaginationMonitor::forBackup($this->logger, 'todo.lists');
        $lists = [];
        try {
            foreach ($this->graph->paginate("users/{$userId}/todo/lists", ['$top' => '100'], [], $listMonitor) as $list) {
                $this->cancellation?->check();
                $lists[] = $list;
            }
        } catch (GraphApiException $e) {
            $result = ResourceAccessClassifier::classify($e);
            if ($result->skippable) {
                throw ResourceUnavailableException::fromGraph('tasks', $e);
            }
            throw $e;
        }

        $this->storage->writeJson($this->storage->todoListsPath($userId), [
            'fetched_at' => gmdate('c'),
            'value' => $lists,
        ]);
        $stats['lists'] = count($lists);

        foreach ($lists as $list) {
            $this->cancellation?->check();
            $listId = (string) ($list['id'] ?? '');
            $listName = (string) ($list['displayName'] ?? $listId);
            if ($listId === '') {
                continue;
            }
            try {
                $listStats = $this->syncTaskList($userId, $listId, $listName);
                $stats['tasks'] += $listStats['tasks'];
                $stats['created'] += $listStats['created'];
                $stats['updated'] += $listStats['updated'];
                $stats['removed'] += $listStats['removed'];
                if ($listStats['resynced']) {
                    $stats['lists_resynced']++;
                }
            } catch (\Throwable $e) {
                $this->logger->error("To Do list failed: {$listName}", ['error' => $e->getMessage()]);
            }
        }

        $this->logger->info('To Do backup finished', $stats);

        return $stats;
    }

    /**
     * @return array{tasks: int, created: int, updated: int, removed: int, resynced: bool}
     */
    private function syncTaskList(string $userId, string $listId, string $listName): array
    {
        $state = new DeltaSyncState($this->storage, $this->storage->todoListDeltaStatePath($userId, $listId));
        $itemsDir = $this->storage->todoTasksDir($userId, $listId);
        $writer = new DeltaItemWriter($this->storage, $itemsDir, $this->runId);
        $deltaPath = "users/{$userId}/todo/lists/{$listId}/tasks/delta";
        $query = ['$select' => self::TASK_SELECT, '$top' => '100'];

        $stats = ['tasks' => 0, 'created' => 0, 'updated' => 0, 'removed' => 0, 'resynced' => false];

        $runSync = function (bool $resync) use ($state, $writer, $deltaPath, $query, $listName, &$stats): void {
            if ($resync) {
                $state->clear();
                $stats['resynced'] = true;
            }
            $resume = $state->hasToken() ? $state->deltaLink() : null;
            $mode = $resume !== null && $resume !== '' ? 'delta' : 'initial';
            $this->logger->info("To Do list sync ({$mode}): {$listName}");

            $outcome = new DeltaPaginationOutcome();
            $monitor = PaginationMonitor::forBackup($this->logger, 'todo:' . $listName);
            foreach ($this->graph->paginateDelta($deltaPath, $query, [], $resume, $monitor, $outcome) as $item) {
                $this->cancellation?->check();
                $result = $writer->writeItem($item);
                if ($result['action'] === 'skip') {
                    continue;
                }
                $stats['tasks']++;
                if ($result['action'] === 'created') {
                    $stats['created']++;
                } elseif ($result['action'] === 'updated') {
                    $stats['updated']++;
                } elseif ($result['action'] === 'removed') {
                    $stats['removed']++;
                }
            }
            if ($outcome->deltaLink !== '') {
                $state->saveDeltaLink($outcome->deltaLink, $this->runId);
            }
        };

        try {
            $runSync(false);
        } catch (GraphDeltaResetException $e) {
            $this->logger->warn("To Do list delta reset, resyncing: {$listName}", ['error' => $e->getMessage()]);
            $runSync(true);
        }

        return $stats;
    }
}
