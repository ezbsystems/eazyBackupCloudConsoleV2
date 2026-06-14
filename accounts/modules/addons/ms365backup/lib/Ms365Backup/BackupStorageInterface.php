<?php
declare(strict_types=1);

namespace Ms365Backup;

interface BackupStorageInterface
{
    public function writeJson(string $absolutePath, array $data): void;

    /** @return array<string, mixed>|null */
    public function readJson(string $absolutePath): ?array;

    public function exists(string $absolutePath): bool;

    public function ensureDir(string $absolutePath): void;

    /**
     * @param resource $stream
     */
    public function writeStream(string $absolutePath, $stream): void;
}
