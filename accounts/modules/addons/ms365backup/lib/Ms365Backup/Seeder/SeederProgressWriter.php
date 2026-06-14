<?php
declare(strict_types=1);

namespace Ms365Backup\Seeder;

use Ms365Backup\StorageLayout;

final class SeederProgressWriter
{
    /** @var list<string> */
    private array $logLines = [];

    public function __construct(
        private readonly string $runId,
    ) {
    }

    /** @param array<string, mixed> $data */
    public function write(array $data): void
    {
        $path = $this->progressPath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0770, true);
        }
        $payload = array_merge([
            'run_id' => $this->runId,
            'updated_at' => gmdate('c'),
        ], $data);
        if ($this->logLines !== []) {
            $payload['log_lines'] = array_slice($this->logLines, -200);
        }
        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    public function log(string $message): void
    {
        $line = gmdate('H:i:s') . ' ' . $message;
        $this->logLines[] = $line;
        $logFile = StorageLayout::BASE_PATH . '/_logs/seeder_' . $this->runId . '.log';
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0770, true);
        }
        file_put_contents($logFile, $line . "\n", FILE_APPEND);
    }

    public function progressPath(): string
    {
        return StorageLayout::BASE_PATH . '/seeder/' . $this->runId . '/progress.json';
    }

    /** @return array<string, mixed>|null */
    public static function read(string $runId): ?array
    {
        $path = StorageLayout::BASE_PATH . '/seeder/' . $runId . '/progress.json';
        if (!is_file($path)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : null;
    }
}
