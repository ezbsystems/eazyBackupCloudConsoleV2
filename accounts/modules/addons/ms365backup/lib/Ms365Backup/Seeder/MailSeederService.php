<?php
declare(strict_types=1);

namespace Ms365Backup\Seeder;

use Ms365Backup\GraphClient;

final class MailSeederService
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
        for ($i = 0; $i < $count; $i++) {
            $this->graph->post('users/' . rawurlencode($userId) . '/mailFolders/inbox/messages', [
                'subject' => $this->content->mailSubject($i),
                'body' => [
                    'contentType' => 'HTML',
                    'content' => $this->content->mailBody($i),
                ],
                'toRecipients' => [],
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
