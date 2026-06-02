<?php
declare(strict_types=1);

namespace Ms365Backup;

final class ListItemStore
{
    public function __construct(
        private readonly StorageLayout $storage,
        private readonly string $siteId,
        private readonly string $listId,
        private readonly string $runId,
    ) {
    }

    /**
     * @param array<string, mixed> $item
     * @return array{action: string, id: string}
     */
    public function writeRemoved(array $item): array
    {
        $id = (string) ($item['id'] ?? '');
        if ($id === '') {
            return ['action' => 'skip', 'id' => ''];
        }
        $this->storage->writeJson($this->storage->siteListItemRemovedPath($this->siteId, $this->listId, $id), [
            '@removed' => $item['@removed'] ?? [],
            'id' => $id,
            'removedAt' => gmdate('c'),
            'runId' => $this->runId,
        ]);

        return ['action' => 'removed', 'id' => $id];
    }

    /**
     * @param array<string, mixed> $item
     * @return array{action: string, id: string}
     */
    public function writeItem(array $item): array
    {
        $id = (string) ($item['id'] ?? '');
        if ($id === '') {
            return ['action' => 'skip', 'id' => ''];
        }

        $path = $this->storage->siteListItemPath($this->siteId, $this->listId, $id);
        $existed = is_file($path);
        $this->storage->writeJson($path, [
            'siteId' => $this->siteId,
            'listId' => $this->listId,
            'itemId' => $id,
            'rawGraphJson' => $item,
            'backedUpAt' => gmdate('c'),
            'runId' => $this->runId,
        ]);

        return ['action' => $existed ? 'updated' : 'created', 'id' => $id];
    }
}
