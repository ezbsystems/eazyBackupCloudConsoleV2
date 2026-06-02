<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * Writes Graph delta items to disk (entity JSON or removal tombstone).
 */
final class DeltaItemWriter
{
    public function __construct(
        private readonly StorageLayout $storage,
        private readonly string $itemsDir,
        private readonly string $runId,
    ) {
    }

    /**
     * @param array<string, mixed> $item
     * @return array{action: string, id: string}
     */
    public function writeItem(array $item): array
    {
        if (isset($item['@removed']) && is_array($item['@removed'])) {
            $id = (string) ($item['id'] ?? '');
            if ($id === '') {
                return ['action' => 'skip', 'id' => ''];
            }
            $safeId = $this->safeId($id);
            $this->storage->writeJson($this->itemsDir . '/' . $safeId . '.removed.json', [
                '@removed' => $item['@removed'],
                'id' => $id,
                'removedAt' => gmdate('c'),
                'runId' => $this->runId,
            ]);

            return ['action' => 'removed', 'id' => $id];
        }

        $id = (string) ($item['id'] ?? '');
        if ($id === '') {
            return ['action' => 'skip', 'id' => ''];
        }
        $safeId = $this->safeId($id);
        $path = $this->itemsDir . '/' . $safeId . '.json';
        $existed = is_file($path);
        $this->storage->writeJson($path, $item);

        return ['action' => $existed ? 'updated' : 'created', 'id' => $id];
    }

    private function safeId(string $id): string
    {
        return preg_replace('/[^a-zA-Z0-9._-]/', '_', $id) ?: 'unknown';
    }
}
