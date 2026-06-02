<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * Persists document library item metadata and content paths on disk.
 */
final class DriveItemStore
{
    public function __construct(
        private readonly StorageLayout $storage,
        private readonly DriveItemStorage $itemStorage,
        private readonly string $runId,
    ) {
    }

    /**
     * @param array<string, mixed> $item
     * @return array{action: string, id: string, is_file: bool}
     */
    public function writeRemoved(array $item): array
    {
        $id = (string) ($item['id'] ?? '');
        if ($id === '') {
            return ['action' => 'skip', 'id' => '', 'is_file' => false];
        }
        $this->storage->writeJson($this->itemStorage->itemRemovedPath($id), [
            '@removed' => $item['@removed'] ?? [],
            'id' => $id,
            'removedAt' => gmdate('c'),
            'runId' => $this->runId,
        ]);

        return ['action' => 'removed', 'id' => $id, 'is_file' => false];
    }

    /**
     * @param array<string, mixed> $item
     * @return array{action: string, id: string, is_file: bool, metadata_path: string}
     */
    public function writeMetadata(array $item): array
    {
        $id = (string) ($item['id'] ?? '');
        if ($id === '') {
            return ['action' => 'skip', 'id' => '', 'is_file' => false, 'metadata_path' => ''];
        }

        $isFile = isset($item['file']) && is_array($item['file']);
        $metaPath = $this->itemStorage->itemMetadataPath($id);
        $existed = is_file($metaPath);

        $envelope = [
            'driveId' => $this->itemStorage->driveId(),
            'itemId' => $id,
            'isFile' => $isFile,
            'rawGraphJson' => $item,
            'backedUpAt' => gmdate('c'),
            'runId' => $this->runId,
            'contentPath' => null,
            'contentBytes' => null,
            'contentSha256' => null,
        ];

        if ($existed) {
            $existing = json_decode((string) file_get_contents($metaPath), true);
            if (is_array($existing)) {
                foreach (['contentPath', 'contentBytes', 'contentSha256'] as $field) {
                    if (($envelope[$field] ?? null) === null && isset($existing[$field])) {
                        $envelope[$field] = $existing[$field];
                    }
                }
            }
        }

        $this->storage->writeJson($metaPath, $envelope);

        return [
            'action' => $existed ? 'updated' : 'created',
            'id' => $id,
            'is_file' => $isFile,
            'metadata_path' => $metaPath,
        ];
    }

    public function contentPathForItem(string $itemId, string $fileName): string
    {
        return $this->itemStorage->contentDir($itemId) . '/' . $fileName;
    }

    public function attachContent(string $itemId, string $contentPath, int $bytes, string $sha256): void
    {
        $metaPath = $this->itemStorage->itemMetadataPath($itemId);
        $envelope = is_file($metaPath) ? json_decode((string) file_get_contents($metaPath), true) : null;
        if (!is_array($envelope)) {
            $envelope = [
                'driveId' => $this->itemStorage->driveId(),
                'itemId' => $itemId,
                'isFile' => true,
                'rawGraphJson' => [],
                'backedUpAt' => gmdate('c'),
                'runId' => $this->runId,
            ];
        }
        $envelope['contentPath'] = $contentPath;
        $envelope['contentBytes'] = $bytes;
        $envelope['contentSha256'] = $sha256;
        $envelope['contentBackedUpAt'] = gmdate('c');
        $this->storage->writeJson($metaPath, $envelope);
    }

    public static function sanitizeFileName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return 'unnamed.bin';
        }
        $safe = preg_replace('/[^a-zA-Z0-9._ -]/', '_', $name) ?? 'unnamed.bin';
        $safe = trim($safe, '. ');
        if ($safe === '') {
            return 'unnamed.bin';
        }

        return strlen($safe) > 200 ? substr($safe, 0, 200) : $safe;
    }
}
