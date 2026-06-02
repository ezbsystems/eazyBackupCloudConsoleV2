<?php
declare(strict_types=1);

namespace Ms365Backup;

final class GraphTeamPaths
{
    public static function teamPath(string $groupId, string $suffix = ''): string
    {
        $suffix = ltrim($suffix, '/');

        return $suffix === '' ? 'teams/' . $groupId : 'teams/' . $groupId . '/' . $suffix;
    }

    public static function channelPath(string $groupId, string $channelId, string $suffix = ''): string
    {
        $base = 'teams/' . $groupId . '/channels/' . $channelId;
        $suffix = ltrim($suffix, '/');

        return $suffix === '' ? $base : $base . '/' . $suffix;
    }

    /**
     * @return array{group_id: string, channel_id: string}
     */
    public static function parseChannelResourceId(string $resourceId): array
    {
        $graphPart = TenantResource::graphIdFromResourceId($resourceId);
        $pos = strpos($graphPart, ':');
        if ($pos === false) {
            return ['group_id' => '', 'channel_id' => $graphPart];
        }

        return [
            'group_id' => substr($graphPart, 0, $pos),
            'channel_id' => substr($graphPart, $pos + 1),
        ];
    }

    public static function groupIdFromResource(array $resource): string
    {
        $type = (string) ($resource['resource_type'] ?? '');
        if ($type === TenantResource::TYPE_TEAM) {
            $meta = is_array($resource['meta'] ?? null) ? $resource['meta'] : [];

            return (string) ($meta['group_id'] ?? $resource['graph_id'] ?? '');
        }
        if ($type === TenantResource::TYPE_TEAM_CHANNEL) {
            $parsed = self::parseChannelResourceId((string) ($resource['id'] ?? ''));
            if ($parsed['group_id'] !== '') {
                return $parsed['group_id'];
            }
            $parentId = (string) ($resource['parent_id'] ?? '');
            if ($parentId !== '') {
                return TenantResource::graphIdFromResourceId($parentId);
            }
        }

        return '';
    }

    public static function channelIdFromResource(array $resource): string
    {
        if ((string) ($resource['resource_type'] ?? '') !== TenantResource::TYPE_TEAM_CHANNEL) {
            return '';
        }

        $parsed = self::parseChannelResourceId((string) ($resource['id'] ?? ''));

        return $parsed['channel_id'] !== ''
            ? $parsed['channel_id']
            : (string) ($resource['graph_id'] ?? '');
    }
}
