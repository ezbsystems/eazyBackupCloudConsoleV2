<?php
declare(strict_types=1);

namespace Ms365Backup;

final class LocalFilesystemBackupStorage implements BackupStorageInterface
{
    public function writeJson(string $absolutePath, array $data): void
    {
        $this->ensureDir(dirname($absolutePath));
        file_put_contents(
            $absolutePath,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
        );
    }

    public function readJson(string $absolutePath): ?array
    {
        if (!is_file($absolutePath)) {
            return null;
        }
        $decoded = json_decode((string) file_get_contents($absolutePath), true);

        return is_array($decoded) ? $decoded : null;
    }

    public function exists(string $absolutePath): bool
    {
        return is_file($absolutePath);
    }

    public function ensureDir(string $absolutePath): void
    {
        if (is_dir($absolutePath)) {
            return;
        }
        if (!@mkdir($absolutePath, 0770, true) && !is_dir($absolutePath)) {
            $err = error_get_last();
            throw new \RuntimeException(
                'Failed to create directory: ' . $absolutePath . ($err ? ' — ' . ($err['message'] ?? '') : ''),
            );
        }
    }

    public function writeStream(string $absolutePath, $stream): void
    {
        $this->ensureDir(dirname($absolutePath));
        $out = fopen($absolutePath, 'wb');
        if ($out === false) {
            throw new \RuntimeException('Failed to open file for writing: ' . $absolutePath);
        }
        stream_copy_to_stream($stream, $out);
        fclose($out);
    }
}
