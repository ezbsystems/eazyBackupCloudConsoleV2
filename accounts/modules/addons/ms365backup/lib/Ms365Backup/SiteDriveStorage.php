<?php
declare(strict_types=1);

namespace Ms365Backup;

final class SiteDriveStorage implements DriveItemStorage
{
    public function __construct(
        private readonly StorageLayout $layout,
        private readonly string $siteId,
        private readonly string $driveId,
    ) {
    }

    public function driveId(): string
    {
        return $this->driveId;
    }

    public function deltaStatePath(): string
    {
        return $this->layout->siteDriveDeltaStatePath($this->siteId, $this->driveId);
    }

    public function itemMetadataPath(string $itemId): string
    {
        return $this->layout->siteDriveItemMetadataPath($this->siteId, $this->driveId, $itemId);
    }

    public function itemRemovedPath(string $itemId): string
    {
        return $this->layout->siteDriveItemRemovedPath($this->siteId, $this->driveId, $itemId);
    }

    public function contentDir(string $itemId): string
    {
        return $this->layout->siteDriveContentDir($this->siteId, $this->driveId, $itemId);
    }
}
