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
