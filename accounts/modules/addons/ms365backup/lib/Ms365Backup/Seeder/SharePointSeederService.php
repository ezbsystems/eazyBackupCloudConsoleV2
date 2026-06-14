<?php
declare(strict_types=1);

namespace Ms365Backup\Seeder;

use Ms365Backup\GraphClient;
use Ms365Backup\GraphSitePaths;

final class SharePointSeederService
{
    public function __construct(
        private readonly GraphClient $graph,
        private readonly SyntheticContentGenerator $content,
        private readonly int $delayMs = 250,
    ) {
    }

    public function seedSite(string $siteId, int $count): int
    {
        $encoded = GraphSitePaths::encodeSiteId($siteId);
        $drives = $this->graph->get('sites/' . $encoded . '/drives', ['$top' => '5']);
        $driveId = '';
        foreach ($drives['value'] ?? [] as $drive) {
            if (!is_array($drive)) {
                continue;
            }
            $driveId = (string) ($drive['id'] ?? '');
            if ($driveId !== '') {
                break;
            }
        }
        if ($driveId === '') {
            return 0;
        }

        $created = 0;
        $folder = 'SeederUploads';
        for ($i = 0; $i < $count; $i++) {
            $this->graph->uploadSmallFile(
                $driveId,
                $folder,
                $this->content->fileName($i, 'txt'),
                $this->content->fileContents($i, 3),
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
