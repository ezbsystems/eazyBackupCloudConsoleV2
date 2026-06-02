<?php
declare(strict_types=1);

namespace Ms365Backup;

final class OneDriveBackupService
{
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
     *   items_seen: int,
     *   files_metadata: int,
     *   folders_metadata: int,
     *   files_downloaded: int,
     *   files_skipped: int,
     *   bytes_downloaded: int,
     *   removed: int,
     *   resynced: bool
     * }
     */
    public function backupDrive(string $driveId): array
    {
        if ($driveId === '') {
            throw new \InvalidArgumentException('OneDrive drive ID is required');
        }

        $this->logger->info('Starting OneDrive backup', [
            'drive_id' => $driveId,
            'endpoint' => 'drives/{id}/root/delta',
        ]);

        $lib = new DocumentLibraryBackupService(
            $this->graph,
            $this->storage,
            $this->logger,
            $this->runId,
            $this->cancellation,
        );

        try {
            $stats = $lib->backupDrive(
                $driveId,
                new PersonalDriveStorage($this->storage, $driveId),
                'onedrive',
            );
        } catch (GraphApiException $e) {
            $result = ResourceAccessClassifier::classify($e);
            if ($result->skippable) {
                throw ResourceUnavailableException::fromGraph('onedrive', $e);
            }
            throw $e;
        }

        $this->logger->info('OneDrive backup finished', $stats);

        return $stats;
    }
}
