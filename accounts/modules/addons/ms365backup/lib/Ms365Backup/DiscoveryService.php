<?php
declare(strict_types=1);

namespace Ms365Backup;

final class DiscoveryService
{
    public function __construct(
        private readonly GraphClient $graph,
        private readonly StorageLayout $storage,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function listUsers(): array
    {
        $users = [];
        $query = [
            '$select' => 'id,displayName,userPrincipalName,mail,userType,assignedLicenses',
            '$top' => '999',
        ];
        foreach ($this->graph->paginate('users', $query) as $user) {
            $users[] = $user;
        }
        $this->storage->writeJson($this->storage->discoveryDir() . '/users.json', [
            'fetched_at' => gmdate('c'),
            'count' => count($users),
            'value' => $users,
        ]);
        return $users;
    }

    /** @return list<array<string, mixed>> */
    public function listSites(): array
    {
        $sites = [];
        $sharepointUnavailable = false;
        $sharepointReason = '';

        try {
            try {
                foreach ($this->graph->paginate('sites', ['search' => '*']) as $site) {
                    $sites[] = $site;
                }
            } catch (\Throwable $e) {
                if (GraphApiException::isSharePointUnavailable($e)) {
                    throw $e;
                }
                foreach ($this->graph->paginate('sites') as $site) {
                    $sites[] = $site;
                }
            }
        } catch (\Throwable $e) {
            if (!GraphApiException::isSharePointUnavailable($e)) {
                throw $e;
            }
            $sharepointUnavailable = true;
            $sharepointReason = $e->getMessage();
        }

        $payload = [
            'fetched_at' => gmdate('c'),
            'count' => count($sites),
            'value' => $sites,
        ];
        if ($sharepointUnavailable) {
            $payload['sharepoint_unavailable'] = true;
            $payload['sharepoint_unavailable_reason'] = $sharepointReason;
        }
        $this->storage->writeJson($this->storage->discoveryDir() . '/sites.json', $payload);

        return $sites;
    }

    public function isSharePointUnavailableFromCache(): bool
    {
        $cached = $this->loadCached('sites');

        return is_array($cached) && !empty($cached['sharepoint_unavailable']);
    }

    /** @return list<array<string, mixed>> */
    public function listTeams(): array
    {
        $teams = [];
        $query = [
            '$filter' => "resourceProvisioningOptions/Any(x:x eq 'Team')",
            '$select' => 'id,displayName,description,mail,mailEnabled,securityEnabled',
            '$top' => '999',
        ];
        foreach ($this->graph->paginate('groups', $query) as $group) {
            $teams[] = $group;
        }
        $this->storage->writeJson($this->storage->discoveryDir() . '/teams.json', [
            'fetched_at' => gmdate('c'),
            'count' => count($teams),
            'value' => $teams,
        ]);
        return $teams;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listTeamMembers(string $groupId): array
    {
        $groupId = trim($groupId);
        if ($groupId === '') {
            return [];
        }

        $members = [];
        $query = [
            '$select' => 'id,displayName,userPrincipalName,mail,userType',
        ];
        foreach ($this->graph->paginate('teams/' . rawurlencode($groupId) . '/members', $query) as $member) {
            if (!is_array($member) || !self::isGraphUserMember($member)) {
                continue;
            }
            $members[] = $member;
        }

        $this->storage->writeJson($this->teamMembersDiscoveryPath($groupId), [
            'fetched_at' => gmdate('c'),
            'group_id' => $groupId,
            'source' => 'team',
            'count' => count($members),
            'value' => $members,
        ]);

        return $members;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listGroupMembers(string $groupId): array
    {
        $groupId = trim($groupId);
        if ($groupId === '') {
            return [];
        }

        $members = [];
        $query = [
            '$select' => 'id,displayName,userPrincipalName,mail,userType',
        ];
        foreach ($this->graph->paginate('groups/' . rawurlencode($groupId) . '/members', $query) as $member) {
            if (!is_array($member) || !self::isGraphUserMember($member)) {
                continue;
            }
            $members[] = $member;
        }

        $this->storage->writeJson($this->teamMembersDiscoveryPath($groupId), [
            'fetched_at' => gmdate('c'),
            'group_id' => $groupId,
            'source' => 'group',
            'count' => count($members),
            'value' => $members,
        ]);

        return $members;
    }

    /**
     * List user principals granted on a SharePoint site (Graph permissions).
     *
     * @return list<array<string, mixed>> Rows shaped like team/group members:
     *   id, displayName, userPrincipalName, mail, userType
     */
    public function listSiteMembers(string $siteId): array
    {
        $siteId = trim($siteId);
        if ($siteId === '') {
            return [];
        }

        $byId = [];
        try {
            foreach ($this->graph->paginate(
                'sites/' . rawurlencode($siteId) . '/permissions',
                [],
            ) as $permission) {
                if (!is_array($permission)) {
                    continue;
                }
                foreach ($this->extractUsersFromSitePermission($permission) as $user) {
                    $id = trim((string) ($user['id'] ?? ''));
                    if ($id === '' || !self::isGraphUserMember($user)) {
                        continue;
                    }
                    $byId[$id] = [
                        'id' => $id,
                        'displayName' => (string) ($user['displayName'] ?? ''),
                        'userPrincipalName' => (string) ($user['userPrincipalName'] ?? $user['email'] ?? ''),
                        'mail' => (string) ($user['email'] ?? $user['mail'] ?? ''),
                        'userType' => (string) ($user['userType'] ?? ''),
                        '@odata.type' => '#microsoft.graph.user',
                    ];
                }
            }
        } catch (\Throwable $_) {
            return [];
        }

        $members = array_values($byId);
        $this->storage->writeJson($this->siteMembersDiscoveryPath($siteId), [
            'fetched_at' => gmdate('c'),
            'site_id' => $siteId,
            'source' => 'site_permissions',
            'count' => count($members),
            'value' => $members,
        ]);

        return $members;
    }

    /**
     * @param array<string, mixed> $permission
     * @return list<array<string, mixed>>
     */
    private function extractUsersFromSitePermission(array $permission): array
    {
        $users = [];
        $identitySets = [];
        foreach (['grantedToIdentitiesV2', 'grantedToIdentities', 'grantedToV2', 'grantedTo'] as $key) {
            $val = $permission[$key] ?? null;
            if ($val === null) {
                continue;
            }
            if (isset($val['user']) || isset($val['application'])) {
                $identitySets[] = $val;
            } elseif (is_array($val)) {
                foreach ($val as $identity) {
                    if (is_array($identity)) {
                        $identitySets[] = $identity;
                    }
                }
            }
        }
        foreach ($identitySets as $identity) {
            $user = $identity['user'] ?? null;
            if (is_array($user) && trim((string) ($user['id'] ?? '')) !== '') {
                $users[] = $user;
            }
        }

        return $users;
    }

    private function siteMembersDiscoveryPath(string $siteId): string
    {
        $dir = $this->storage->discoveryDir() . '/site_members';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', $siteId) ?? 'unknown';

        return $dir . '/' . $safe . '.json';
    }

    /** @param array<string, mixed> $member */
    public static function isGraphUserMember(array $member): bool
    {
        $id = trim((string) ($member['id'] ?? ''));
        if ($id === '') {
            return false;
        }
        $odataType = strtolower((string) ($member['@odata.type'] ?? ''));
        if ($odataType !== '' && !str_contains($odataType, 'user')) {
            return false;
        }

        return true;
    }

    private function teamMembersDiscoveryPath(string $groupId): string
    {
        $dir = $this->storage->discoveryDir() . '/team_members';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', $groupId) ?? 'unknown';

        return $dir . '/' . $safe . '.json';
    }

    public function loadCached(string $type): ?array
    {
        $path = $this->storage->discoveryDir() . '/' . $type . '.json';
        $data = $this->storage->readJson($path);

        return is_array($data) ? $data : null;
    }
}
