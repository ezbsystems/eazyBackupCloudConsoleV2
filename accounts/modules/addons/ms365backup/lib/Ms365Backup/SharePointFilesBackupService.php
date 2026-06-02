<?php
declare(strict_types=1);

namespace Ms365Backup;

final class SharePointFilesBackupService
{
    private const DRIVE_SELECT = 'id,name,driveType,webUrl,lastModifiedDateTime';

    public function __construct(
        private readonly GraphClient $graph,
        private readonly StorageLayout $storage,
        private readonly ProgressLogger $logger,
        private readonly string $runId,
        private readonly ?RunCancellation $cancellation = null,
    ) {
    }

    /**
     * @return array{
     *   drives: int,
     *   items_seen: int,
     *   files_metadata: int,
     *   folders_metadata: int,
     *   files_downloaded: int,
     *   files_skipped: int,
     *   bytes_downloaded: int,
     *   removed: int,
     *   drives_resynced: int
     * }
     */
    public function backupSiteFiles(string $siteId): array
    {
        $stats = [
            'drives' => 0,
            'items_seen' => 0,
            'files_metadata' => 0,
            'folders_metadata' => 0,
            'files_downloaded' => 0,
            'files_skipped' => 0,
            'bytes_downloaded' => 0,
            'removed' => 0,
            'drives_resynced' => 0,
        ];

        $sitePath = GraphSitePaths::sitePath($siteId);
        $drives = [];
        $monitor = PaginationMonitor::forBackup($this->logger, 'sharepoint.drives:' . $siteId);
        foreach ($this->graph->paginate($sitePath . '/drives', ['$select' => self::DRIVE_SELECT, '$top' => '100'], [], $monitor) as $drive) {
            $this->cancellation?->check();
            if (!$this->shouldBackupDrive($drive)) {
                continue;
            }
            $drives[] = $drive;
        }

        $this->storage->writeJson($this->storage->siteDrivesCatalogPath($siteId), [
            'fetched_at' => gmdate('c'),
            'value' => $drives,
        ]);
        $stats['drives'] = count($drives);

        $lib = new DocumentLibraryBackupService(
            $this->graph,
            $this->storage,
            $this->logger,
            $this->runId,
            $this->cancellation,
        );

        foreach ($drives as $drive) {
            $driveId = (string) ($drive['id'] ?? '');
            $driveName = (string) ($drive['name'] ?? $driveId);
            if ($driveId === '') {
                continue;
            }
            try {
                $driveStats = $lib->backupDrive(
                    $driveId,
                    new SiteDriveStorage($this->storage, $siteId, $driveId),
                    'sharepoint:' . $siteId,
                );
                $stats['items_seen'] += $driveStats['items_seen'];
                $stats['files_metadata'] += $driveStats['files_metadata'];
                $stats['folders_metadata'] += $driveStats['folders_metadata'];
                $stats['files_downloaded'] += $driveStats['files_downloaded'];
                $stats['files_skipped'] += $driveStats['files_skipped'];
                $stats['bytes_downloaded'] += $driveStats['bytes_downloaded'];
                $stats['removed'] += $driveStats['removed'];
                if ($driveStats['resynced']) {
                    $stats['drives_resynced']++;
                }
            } catch (\Throwable $e) {
                $this->logger->error("SharePoint library failed: {$driveName}", ['error' => $e->getMessage()]);
            }
        }

        return $stats;
    }

    /**
     * @param array<string, mixed> $drive
     */
    private function shouldBackupDrive(array $drive): bool
    {
        $driveType = strtolower((string) ($drive['driveType'] ?? ''));
        if ($driveType === '') {
            return true;
        }

        return in_array($driveType, ['documentlibrary', 'business', 'personal'], true);
    }
}
