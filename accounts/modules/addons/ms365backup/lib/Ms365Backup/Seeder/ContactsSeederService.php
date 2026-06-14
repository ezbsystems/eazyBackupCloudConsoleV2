<?php
declare(strict_types=1);

namespace Ms365Backup\Seeder;

use Ms365Backup\GraphClient;

final class ContactsSeederService
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
            $this->graph->post('users/' . rawurlencode($userId) . '/contacts', [
                'givenName' => $this->content->contactName($i),
                'emailAddresses' => [
                    ['address' => $this->content->contactEmail($i), 'name' => $this->content->contactName($i)],
                ],
                'businessPhones' => ['+1-555-010' . str_pad((string) ($i % 10), 1, '0', STR_PAD_LEFT)],
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
