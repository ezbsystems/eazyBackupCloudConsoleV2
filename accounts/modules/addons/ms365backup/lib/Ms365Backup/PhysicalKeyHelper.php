<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * Parses physical backup keys, including whale-scale shard suffixes.
 *
 * Examples:
 *   drive:{driveId}
 *   drive:{driveId}#shard:2
 *   user:{userId}#mail:{folderId}
 */
final class PhysicalKeyHelper
{
    public const SHARD_MARKER = '#shard:';
    public const MAIL_SHARD_MARKER = '#mail:';

    public static function baseKey(string $physicalKey): string
    {
        $physicalKey = trim($physicalKey);
        if ($physicalKey === '') {
            return '';
        }
        foreach ([self::SHARD_MARKER, self::MAIL_SHARD_MARKER] as $marker) {
            $pos = strpos($physicalKey, $marker);
            if ($pos !== false) {
                return substr($physicalKey, 0, $pos);
            }
        }

        return $physicalKey;
    }

    /**
     * @return array{index: int, total: int, kind: string, segment: string}|null
     */
    public static function parseShard(string $physicalKey): ?array
    {
        $physicalKey = trim($physicalKey);
        if ($physicalKey === '') {
            return null;
        }

        $pos = strpos($physicalKey, self::SHARD_MARKER);
        if ($pos !== false) {
            $segment = substr($physicalKey, $pos + strlen(self::SHARD_MARKER));
            if ($segment === '' || !ctype_digit($segment)) {
                return null;
            }

            return [
                'index' => (int) $segment,
                'total' => 0,
                'kind' => 'range',
                'segment' => $segment,
            ];
        }

        $pos = strpos($physicalKey, self::MAIL_SHARD_MARKER);
        if ($pos !== false) {
            $segment = substr($physicalKey, $pos + strlen(self::MAIL_SHARD_MARKER));
            if ($segment === '') {
                return null;
            }

            return [
                'index' => 0,
                'total' => 0,
                'kind' => 'mail_folder',
                'segment' => $segment,
            ];
        }

        return null;
    }

    public static function shardKey(string $baseKey, int $index): string
    {
        return rtrim($baseKey, ':') . self::SHARD_MARKER . max(0, $index);
    }

    public static function mailFolderKey(string $userBaseKey, string $folderId): string
    {
        return rtrim($userBaseKey, ':') . self::MAIL_SHARD_MARKER . $folderId;
    }

    public static function isSharded(string $physicalKey): bool
    {
        return self::parseShard($physicalKey) !== null;
    }

    /**
     * Kopia SourceInfo.Path for independent per-shard incrementals.
     */
    public static function kopiaSourcePath(string $azureTenantId, string $physicalKey): string
    {
        $azureTenantId = trim($azureTenantId, '/');
        $base = self::baseKey($physicalKey);
        $parsed = StorageLayout::parsePhysicalKey($base);
        $resourceType = (string) ($parsed['resource_type'] ?? '');
        $graphId = (string) ($parsed['graph_id'] ?? '');
        $shard = self::parseShard($physicalKey);

        $root = match ($resourceType) {
            TenantResource::TYPE_USER_ONEDRIVE => $azureTenantId . '/drives/' . $graphId,
            TenantResource::TYPE_SHAREPOINT_SITE => $azureTenantId . '/sites/' . $graphId,
            TenantResource::TYPE_USER, TenantResource::TYPE_MAILBOX => $azureTenantId . '/users/' . $graphId,
            TenantResource::TYPE_TEAM => $azureTenantId . '/teams/' . $graphId,
            default => $azureTenantId . '/sources/' . rawurlencode($base),
        };

        if ($shard === null) {
            return $root;
        }

        if ($shard['kind'] === 'mail_folder') {
            return $root . '/mail/' . rawurlencode((string) $shard['segment']);
        }

        return $root . '/.shards/' . (int) $shard['index'];
    }

    /**
     * @param array<string, mixed> $resource
     */
    public static function sizeBytesHint(array $resource): int
    {
        $meta = is_array($resource['meta'] ?? null) ? $resource['meta'] : [];

        return max(0, (int) ($meta['size_bytes'] ?? $meta['storage_used_bytes'] ?? 0));
    }
}
