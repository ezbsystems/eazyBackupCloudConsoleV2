<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * Builds relationship edges and physical dedup keys between tenant resources.
 */
final class RelationshipResolver
{
    public const REL_USES_GROUP = 'uses_group';
    public const REL_FILES_IN_SITE = 'files_in_site';
    public const REL_MESSAGES_IN_TEAM = 'messages_in_team';
    public const REL_OWNED_BY = 'owned_by';

    /**
     * @param list<array<string, mixed>> $resources
     * @return list<array{from_id: string, rel: string, to_id: string, physical_key: string}>
     */
    public function build(array $resources): array
    {
        $byId = [];
        foreach ($resources as $resource) {
            if (!is_array($resource)) {
                continue;
            }
            $id = (string) ($resource['id'] ?? '');
            if ($id !== '') {
                $byId[$id] = $resource;
            }
        }

        $relationships = [];

        foreach ($resources as $resource) {
            if (!is_array($resource)) {
                continue;
            }
            $fromId = (string) ($resource['id'] ?? '');
            $type = (string) ($resource['resource_type'] ?? '');
            $meta = is_array($resource['meta'] ?? null) ? $resource['meta'] : [];

            if ($type === TenantResource::TYPE_TEAM) {
                $groupId = (string) ($meta['group_id'] ?? $resource['graph_id'] ?? '');
                if ($groupId !== '') {
                    $toId = TenantResource::makeId(TenantResource::TYPE_M365_GROUP, $groupId);
                    if (isset($byId[$toId])) {
                        $relationships[] = $this->edge($fromId, self::REL_USES_GROUP, $toId, 'group:' . $groupId);
                    }
                }
                $siteId = (string) ($meta['sharepoint_site_id'] ?? '');
                if ($siteId !== '') {
                    $toId = TenantResource::makeId(TenantResource::TYPE_SHAREPOINT_SITE, $siteId);
                    if (isset($byId[$toId])) {
                        $relationships[] = $this->edge(
                            $fromId,
                            self::REL_FILES_IN_SITE,
                            $toId,
                            'site:' . $siteId,
                        );
                    }
                }
            }

            if ($type === TenantResource::TYPE_TEAM_CHANNEL) {
                $siteId = (string) ($meta['channel_site_id'] ?? '');
                if ($siteId !== '') {
                    $toId = TenantResource::makeId(TenantResource::TYPE_SHAREPOINT_SITE, $siteId);
                    if (isset($byId[$toId])) {
                        $relationships[] = $this->edge(
                            $fromId,
                            self::REL_FILES_IN_SITE,
                            $toId,
                            'site:' . $siteId,
                        );
                    }
                }

                $parentId = (string) ($resource['parent_id'] ?? '');
                $groupId = (string) ($meta['group_id'] ?? '');
                if ($groupId === '' && $parentId !== '') {
                    $groupId = TenantResource::graphIdFromResourceId($parentId);
                }
                if ($groupId === '') {
                    $parsed = GraphTeamPaths::parseChannelResourceId($fromId);
                    $groupId = $parsed['group_id'];
                }
                if ($groupId !== '') {
                    $teamId = TenantResource::makeId(TenantResource::TYPE_TEAM, $groupId);
                    $relationships[] = $this->edge(
                        $fromId,
                        self::REL_MESSAGES_IN_TEAM,
                        $teamId,
                        'team:' . $groupId,
                    );
                }
            }

            if ($type === TenantResource::TYPE_M365_GROUP) {
                $siteId = (string) ($meta['sharepoint_site_id'] ?? '');
                if ($siteId !== '') {
                    $toId = TenantResource::makeId(TenantResource::TYPE_SHAREPOINT_SITE, $siteId);
                    if (isset($byId[$toId])) {
                        $relationships[] = $this->edge(
                            $fromId,
                            self::REL_FILES_IN_SITE,
                            $toId,
                            'site:' . $siteId,
                        );
                    }
                }
            }

            if ($type === TenantResource::TYPE_USER_ONEDRIVE) {
                $parentId = (string) ($resource['parent_id'] ?? '');
                if ($parentId !== '' && isset($byId[$parentId])) {
                    $relationships[] = $this->edge(
                        $fromId,
                        self::REL_OWNED_BY,
                        $parentId,
                        'user:' . TenantResource::graphIdFromResourceId($parentId),
                    );
                }
            }
        }

        return $this->dedupeEdges($relationships);
    }

    /**
     * @param list<array{from_id: string, rel: string, to_id: string, physical_key: string}> $edges
     * @return list<array{from_id: string, rel: string, to_id: string, physical_key: string}>
     */
    private function dedupeEdges(array $edges): array
    {
        $seen = [];
        $out = [];
        foreach ($edges as $edge) {
            $key = $edge['from_id'] . '|' . $edge['rel'] . '|' . $edge['to_id'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $edge;
        }

        return $out;
    }

    /**
     * @return array{from_id: string, rel: string, to_id: string, physical_key: string}
     */
    private function edge(string $fromId, string $rel, string $toId, string $physicalKey): array
    {
        return [
            'from_id' => $fromId,
            'rel' => $rel,
            'to_id' => $toId,
            'physical_key' => $physicalKey,
        ];
    }
}
