<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * Path provider for document library item storage (OneDrive or site library).
 */
interface DriveItemStorage
{
    public function driveId(): string;

    public function deltaStatePath(): string;

    public function itemMetadataPath(string $itemId): string;

    public function itemRemovedPath(string $itemId): string;

    public function contentDir(string $itemId): string;
}
