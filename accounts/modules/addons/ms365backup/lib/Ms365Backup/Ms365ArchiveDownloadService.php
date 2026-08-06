<?php

declare(strict_types=1);

namespace Ms365Backup;

/**
 * Derives client-facing archive download readiness from restore batch children.
 */
final class Ms365ArchiveDownloadService
{
    /**
     * @return array{
     *   archive_restore: bool,
     *   archive_download_ready: bool,
     *   archive_expired: bool,
     *   restore_run_id: string,
     *   archive_expires_at: ?int,
     *   archive_size_bytes: ?int
     * }
     */
    public static function defaultPayload(): array
    {
        return [
            'archive_restore' => false,
            'archive_download_ready' => false,
            'archive_expired' => false,
            'restore_run_id' => '',
            'archive_expires_at' => null,
            'archive_size_bytes' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function forBatchRunId(string $batchRunId): array
    {
        if ($batchRunId === '') {
            return self::defaultPayload();
        }

        return self::forRestoreChildren(Ms365BatchRunRepository::getChildrenForRestoreBatch($batchRunId));
    }

    /**
     * @param list<array<string, mixed>> $children
     * @return array<string, mixed>
     */
    public static function forRestoreChildren(array $children): array
    {
        $archiveChild = self::findArchiveChild($children);
        if ($archiveChild === null) {
            return self::defaultPayload();
        }

        return self::fromRestoreChild($archiveChild);
    }

    /**
     * @param list<array<string, mixed>> $children
     * @return ?array<string, mixed>
     */
    public static function findArchiveChild(array $children): ?array
    {
        foreach ($children as $child) {
            if (self::isArchiveChild($child)) {
                return $child;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $child
     */
    public static function isArchiveChild(array $child): bool
    {
        return strtolower((string) ($child['restore_mode'] ?? '')) === 'archive';
    }

    public static function isDownloadableStatus(string $status): bool
    {
        return in_array(strtolower($status), ['success', 'partial_success'], true);
    }

    public static function isExpired(?int $expiresAt, ?int $now = null): bool
    {
        if ($expiresAt === null || $expiresAt <= 0) {
            return false;
        }

        return ($now ?? time()) >= $expiresAt;
    }

    /**
     * @param array<string, mixed> $child
     * @return array<string, mixed>
     */
    public static function fromRestoreChild(array $child, ?int $now = null): array
    {
        $payload = self::defaultPayload();
        if (!self::isArchiveChild($child)) {
            return $payload;
        }

        $payload['archive_restore'] = true;
        $payload['restore_run_id'] = (string) ($child['id'] ?? '');

        $objectKey = trim((string) ($child['archive_object_key'] ?? ''));
        $expiresAt = self::normalizeExpiresAt($child['archive_expires_at'] ?? null);
        $payload['archive_expires_at'] = $expiresAt;

        $sizeBytes = $child['archive_size_bytes'] ?? null;
        if ($sizeBytes !== null && $sizeBytes !== '') {
            $payload['archive_size_bytes'] = (int) $sizeBytes;
        }

        $expired = self::isExpired($expiresAt, $now);
        $payload['archive_expired'] = $expired;
        $payload['archive_download_ready'] = self::isDownloadableStatus((string) ($child['status'] ?? ''))
            && $objectKey !== ''
            && !$expired;

        return $payload;
    }

    private static function normalizeExpiresAt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $expiresAt = (int) $value;

        return $expiresAt > 0 ? $expiresAt : null;
    }
}
