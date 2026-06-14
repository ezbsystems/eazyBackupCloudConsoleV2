<?php
declare(strict_types=1);

namespace Ms365Backup\Seeder;

use Ms365Backup\GraphClient;

final class TeamsMessageSeederService
{
    public function __construct(
        private readonly GraphClient $graph,
        private readonly SyntheticContentGenerator $content,
        private readonly int $delayMs = 400,
    ) {
    }

    public function seedChannel(string $teamId, string $channelId, int $count): int
    {
        $created = 0;
        for ($i = 0; $i < $count; $i++) {
            $this->graph->post(
                'teams/' . rawurlencode($teamId) . '/channels/' . rawurlencode($channelId) . '/messages',
                [
                    'body' => [
                        'contentType' => 'text',
                        'content' => $this->content->teamsMessage($i),
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
