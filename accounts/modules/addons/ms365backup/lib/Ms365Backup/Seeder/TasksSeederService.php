<?php
declare(strict_types=1);

namespace Ms365Backup\Seeder;

use Ms365Backup\GraphClient;

final class TasksSeederService
{
    public function __construct(
        private readonly GraphClient $graph,
        private readonly SyntheticContentGenerator $content,
        private readonly int $delayMs = 250,
    ) {
    }

    public function seedUser(string $userId, int $count): int
    {
        $list = $this->graph->post('users/' . rawurlencode($userId) . '/todo/lists', [
            'displayName' => 'Seeder tasks ' . substr($this->content->mailSubject(0), -12),
        ]);
        $listId = (string) ($list['id'] ?? '');
        if ($listId === '') {
            throw new \RuntimeException('Failed to create To Do list for user ' . $userId);
        }

        $created = 0;
        for ($i = 0; $i < $count; $i++) {
            $this->graph->post(
                'users/' . rawurlencode($userId) . '/todo/lists/' . rawurlencode($listId) . '/tasks',
                [
                    'title' => $this->content->taskTitle($i),
                    'body' => [
                        'content' => 'Seeded task for backup testing.',
                        'contentType' => 'text',
                    ],
                ],
            );
            $created++;
            $this->throttle();
        }

        return $created;
    }

    private function throttle(): void
    {
        if ($this->delayMs > 0) {
            usleep($this->delayMs * 1000);
        }
    }
}
