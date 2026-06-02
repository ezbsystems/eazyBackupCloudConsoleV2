<?php
declare(strict_types=1);

namespace Ms365Backup;

final class PersonalDriveStorage implements DriveItemStorage
{
    public function __construct(
        private readonly StorageLayout $layout,
        private readonly string $driveId,
    ) {
    }

    public function driveId(): string
    {
        return $this->driveId;
    }

    public function deltaStatePath(): string
    {
        return $this->layout->driveDeltaStatePath($this->driveId);
    }

    public function itemMetadataPath(string $itemId): string
    {
        return $this->layout->driveItemMetadataPath($this->driveId, $itemId);
    }

    public function itemRemovedPath(string $itemId): string
    {
        return $this->layout->driveItemRemovedPath($this->driveId, $itemId);
    }

    public function contentDir(string $itemId): string
    {
        return $this->layout->driveContentDir($this->driveId, $itemId);
    }
}
