<?php
declare(strict_types=1);

namespace Ms365Backup\Seeder;

use Ms365Backup\GraphClient;

final class OneDriveSeederService
{
    public function __construct(
        private readonly GraphClient $graph,
        private readonly SyntheticContentGenerator $content,
        private readonly int $delayMs = 250,
    ) {
    }

    public function seedUser(string $userId, int $count): int
    {
        $drive = $this->graph->get('users/' . rawurlencode($userId) . '/drive', ['$select' => 'id']);
        $driveId = (string) ($drive['id'] ?? '');
        if ($driveId === '') {
            throw new \RuntimeException('No OneDrive for user ' . $userId);
        }

        $created = 0;
        $folder = 'SeederData';
        for ($i = 0; $i < $count; $i++) {
            $ext = match ($i % 3) {
                1 => 'csv',
                2 => 'md',
                default => 'txt',
            };
            $this->graph->uploadSmallFile(
                $driveId,
                $folder,
                $this->content->fileName($i, $ext),
                $this->content->fileContents($i, 2 + ($i % 5)),
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
