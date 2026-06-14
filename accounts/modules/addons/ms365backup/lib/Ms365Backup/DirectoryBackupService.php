<?php
declare(strict_types=1);

namespace Ms365Backup;

final class DirectoryBackupService
{
    public function __construct(
        private readonly GraphClient $graph,
        private readonly StorageLayout $storage,
        private readonly ProgressLogger $logger,
        private readonly ?RunCancellation $cancellation = null,
    ) {
    }

    /**
     * @return array{users: int, groups: int}
     */
    public function exportTenantBaseline(): array
    {
        $this->logger->info('Starting directory baseline export');
        $root = $this->storage->directoryRoot();

        $users = [];
        $userMonitor = PaginationMonitor::forBackup($this->logger, 'directory.users');
        foreach ($this->graph->paginate('users', [
            '$select' => 'id,displayName,userPrincipalName,mail,accountEnabled',
            '$top' => '999',
        ], [], $userMonitor) as $user) {
            $this->cancellation?->check();
            $users[] = $user;
        }
        $this->storage->writeJson($root . '/users.json', [
            'fetched_at' => gmdate('c'),
            'count' => count($users),
            'value' => $users,
        ]);

        $groups = [];
        $groupMonitor = PaginationMonitor::forBackup($this->logger, 'directory.groups');
        foreach ($this->graph->paginate('groups', [
            '$select' => 'id,displayName,mail,mailEnabled,securityEnabled,resourceProvisioningOptions',
            '$top' => '999',
        ], [], $groupMonitor) as $group) {
            $this->cancellation?->check();
            $groups[] = $group;
        }
        $this->storage->writeJson($root . '/groups.json', [
            'fetched_at' => gmdate('c'),
            'count' => count($groups),
            'value' => $groups,
        ]);

        $stats = ['users' => count($users), 'groups' => count($groups)];
        $this->logger->info('Directory baseline export finished', $stats);

        return $stats;
    }
}
