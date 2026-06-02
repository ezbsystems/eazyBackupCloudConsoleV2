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
        try {
            foreach ($this->graph->paginate('sites', ['search' => '*']) as $site) {
                $sites[] = $site;
            }
        } catch (\Throwable $e) {
            foreach ($this->graph->paginate('sites') as $site) {
                $sites[] = $site;
            }
        }
        $this->storage->writeJson($this->storage->discoveryDir() . '/sites.json', [
            'fetched_at' => gmdate('c'),
            'count' => count($sites),
            'value' => $sites,
        ]);
        return $sites;
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

    public function loadCached(string $type): ?array
    {
        $file = $this->storage->discoveryDir() . '/' . $type . '.json';
        if (!is_file($file)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($file), true);
        return is_array($data) ? $data : null;
    }
}
