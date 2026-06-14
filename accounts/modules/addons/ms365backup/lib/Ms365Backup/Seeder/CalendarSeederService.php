<?php
declare(strict_types=1);

namespace Ms365Backup\Seeder;

use Ms365Backup\GraphClient;

final class CalendarSeederService
{
    public function __construct(
        private readonly GraphClient $graph,
        private readonly SyntheticContentGenerator $content,
        private readonly int $delayMs = 250,
    ) {
    }

    public function seedUser(string $userId, int $count): int
    {
        $created = 0;
        $startBase = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        for ($i = 0; $i < $count; $i++) {
            $start = $startBase->modify('+' . ($i * 2) . ' days + ' . ($i % 8) . ' hours');
            $end = $start->modify('+1 hour');
            $this->graph->post('users/' . rawurlencode($userId) . '/events', [
                'subject' => $this->content->eventTitle($i),
                'body' => [
                    'contentType' => 'Text',
                    'content' => 'Seeded calendar event for backup testing.',
                ],
                'start' => [
                    'dateTime' => $start->format('Y-m-d\TH:i:s'),
                    'timeZone' => 'UTC',
                ],
                'end' => [
                    'dateTime' => $end->format('Y-m-d\TH:i:s'),
                    'timeZone' => 'UTC',
                ],
            ]);
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
