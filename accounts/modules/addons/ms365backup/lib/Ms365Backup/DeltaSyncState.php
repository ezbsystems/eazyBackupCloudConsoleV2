<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * Per-folder/list delta sync token persisted on disk.
 */
final class DeltaSyncState
{
    public function __construct(
        private readonly StorageLayout $storage,
        private readonly string $statePath,
    ) {
    }

    public function hasToken(): bool
    {
        $link = $this->deltaLink();

        return $link !== '';
    }

    public function deltaLink(): string
    {
        $data = $this->read();

        return (string) ($data['deltaLink'] ?? '');
    }

    public function isInitialSyncComplete(): bool
    {
        $data = $this->read();

        return (bool) ($data['initialSyncComplete'] ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function read(): array
    {
        if (!is_file($this->statePath)) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents($this->statePath), true);

        return is_array($decoded) ? $decoded : [];
    }

    public function saveDeltaLink(string $deltaLink, ?string $runId = null): void
    {
        $data = $this->read();
        $data['deltaLink'] = $deltaLink;
        $data['lastSyncAt'] = gmdate('c');
        $data['initialSyncComplete'] = true;
        if ($runId !== null && $runId !== '') {
            $data['lastRunId'] = $runId;
        }
        unset($data['lastError']);
        $this->storage->writeJson($this->statePath, $data);
    }

    public function recordError(string $message): void
    {
        $data = $this->read();
        $data['lastError'] = $message;
        $data['lastErrorAt'] = gmdate('c');
        $this->storage->writeJson($this->statePath, $data);
    }

    public function clear(): void
    {
        if (is_file($this->statePath)) {
            @unlink($this->statePath);
        }
    }
}
