<?php
declare(strict_types=1);

namespace Ms365Backup\Seeder;

use Ms365Backup\DiscoveryService;
use Ms365Backup\GraphApiException;
use Ms365Backup\StorageLayout;

final class TenantSeederOrchestrator
{
    /** @var array<string, int> */
    private array $stats = [
        'mail' => 0,
        'calendar' => 0,
        'contacts' => 0,
        'tasks' => 0,
        'onedrive' => 0,
        'sharepoint' => 0,
        'teams' => 0,
        'errors' => 0,
    ];

    public function __construct(
        private readonly string $runId,
        private readonly SeederProgressWriter $progress,
    ) {
    }

    /** @param array<string, mixed> $options */
    public function run(array $options): void
    {
        SeederRunRepository::markRunning($this->runId);

        $profileKey = (string) ($options['profile'] ?? 'light');
        $counts = SeederProfileCatalog::resolve($profileKey);
        $workloads = is_array($options['workloads'] ?? null) ? $options['workloads'] : [];
        $scopeAllUsers = filter_var($options['all_users'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $scopeAllSites = filter_var($options['all_sites'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $scopeAllTeams = filter_var($options['all_teams'] ?? true, FILTER_VALIDATE_BOOLEAN);
        /** @var list<string> $selectedUserIds */
        $selectedUserIds = is_array($options['user_ids'] ?? null)
            ? array_values(array_filter(array_map('strval', $options['user_ids'])))
            : [];

        $content = new SyntheticContentGenerator($this->runId);
        $appGraph = SeederGraphFactory::appClient();
        $creds = SeederConfigRepository::credentials();
        $storage = new StorageLayout($creds['tenant_id']);
        $discovery = new DiscoveryService($appGraph, $storage);

        $this->updateProgress('discover', 'Discovering tenant targets…');

        $users = $discovery->listUsers();
        $users = array_values(array_filter($users, static function (array $u): bool {
            $type = (string) ($u['userType'] ?? 'Member');
            if ($type !== 'Member') {
                return false;
            }
            $upn = (string) ($u['userPrincipalName'] ?? '');
            if ($upn === '' || str_contains(strtolower($upn), '#ext#')) {
                return false;
            }

            return true;
        }));

        if (!$scopeAllUsers && $selectedUserIds !== []) {
            $selected = array_flip($selectedUserIds);
            $users = array_values(array_filter($users, static fn (array $u): bool => isset($selected[(string) ($u['id'] ?? '')])));
        }

        $sites = [];
        if ($this->workloadEnabled($workloads, 'sharepoint')) {
            try {
                $sites = $discovery->listSites();
            } catch (\Throwable $e) {
                $this->progress->log('SharePoint discovery skipped: ' . $e->getMessage());
            }
        }

        $teams = [];
        if ($this->workloadEnabled($workloads, 'teams')) {
            try {
                $teams = $discovery->listTeams();
            } catch (\Throwable $e) {
                $this->progress->log('Teams discovery skipped: ' . $e->getMessage());
            }
        }

        $this->progress->log(sprintf(
            'Targets: %d users, %d sites, %d teams',
            count($users),
            count($sites),
            count($teams),
        ));

        if ($this->workloadEnabled($workloads, 'mail') && $counts['mail_per_user'] > 0) {
            $mail = new MailSeederService($appGraph, $content);
            $this->seedUsersPhase('mail', $users, function (array $user) use ($mail, $counts): int {
                return $mail->seedUser((string) $user['id'], $counts['mail_per_user']);
            });
        }

        if ($this->shouldStop()) {
            return;
        }

        if ($this->workloadEnabled($workloads, 'calendar') && $counts['events_per_user'] > 0) {
            $cal = new CalendarSeederService($appGraph, $content);
            $this->seedUsersPhase('calendar', $users, function (array $user) use ($cal, $counts): int {
                return $cal->seedUser((string) $user['id'], $counts['events_per_user']);
            });
        }

        if ($this->shouldStop()) {
            return;
        }

        if ($this->workloadEnabled($workloads, 'contacts') && $counts['contacts_per_user'] > 0) {
            $contacts = new ContactsSeederService($appGraph, $content);
            $this->seedUsersPhase('contacts', $users, function (array $user) use ($contacts, $counts): int {
                return $contacts->seedUser((string) $user['id'], $counts['contacts_per_user']);
            });
        }

        if ($this->shouldStop()) {
            return;
        }

        if ($this->workloadEnabled($workloads, 'tasks') && $counts['tasks_per_user'] > 0) {
            $tasks = new TasksSeederService($appGraph, $content);
            $this->seedUsersPhase('tasks', $users, function (array $user) use ($tasks, $counts): int {
                return $tasks->seedUser((string) $user['id'], $counts['tasks_per_user']);
            });
        }

        if ($this->shouldStop()) {
            return;
        }

        if ($this->workloadEnabled($workloads, 'onedrive') && $counts['onedrive_files_per_user'] > 0) {
            $od = new OneDriveSeederService($appGraph, $content);
            $this->seedUsersPhase('onedrive', $users, function (array $user) use ($od, $counts): int {
                return $od->seedUser((string) $user['id'], $counts['onedrive_files_per_user']);
            });
        }

        if ($this->shouldStop()) {
            return;
        }

        if ($this->workloadEnabled($workloads, 'sharepoint') && $counts['sharepoint_files_per_site'] > 0 && $sites !== []) {
            $sp = new SharePointSeederService($appGraph, $content);
            $siteList = $scopeAllSites ? $sites : array_slice($sites, 0, 5);
            $this->updateProgress('sharepoint', 'Seeding SharePoint files…', 0, count($siteList));
            foreach ($siteList as $idx => $site) {
                if ($this->shouldStop()) {
                    return;
                }
                $siteId = (string) ($site['id'] ?? '');
                if ($siteId === '') {
                    continue;
                }
                try {
                    $n = $sp->seedSite($siteId, $counts['sharepoint_files_per_site']);
                    $this->stats['sharepoint'] += $n;
                    $this->progress->log('SharePoint ' . ($site['displayName'] ?? $siteId) . ': ' . $n . ' files');
                } catch (\Throwable $e) {
                    $this->recordError('sharepoint', $siteId, $e);
                }
                $this->updateProgress('sharepoint', 'SharePoint site ' . ($idx + 1) . '/' . count($siteList), $idx + 1, count($siteList));
            }
        }

        if ($this->shouldStop()) {
            return;
        }

        if ($this->workloadEnabled($workloads, 'teams') && $counts['teams_messages_per_channel'] > 0) {
            if (!SeederConfigRepository::hasDelegatedUser()) {
                $this->progress->log('Teams skipped: seed user not connected (delegated OAuth required)');
            } elseif ($teams !== []) {
                $delegGraph = SeederGraphFactory::delegatedClient();
                $teamsSeeder = new TeamsMessageSeederService($delegGraph, $content);
                $teamList = $scopeAllTeams ? $teams : array_slice($teams, 0, 3);
                $this->updateProgress('teams', 'Seeding Teams messages…');
                foreach ($teamList as $team) {
                    if ($this->shouldStop()) {
                        return;
                    }
                    $teamId = (string) ($team['id'] ?? '');
                    if ($teamId === '') {
                        continue;
                    }
                    try {
                        $channels = $appGraph->get('teams/' . rawurlencode($teamId) . '/channels');
                        foreach ($channels['value'] ?? [] as $channel) {
                            if (!is_array($channel)) {
                                continue;
                            }
                            $channelId = (string) ($channel['id'] ?? '');
                            if ($channelId === '') {
                                continue;
                            }
                            $n = $teamsSeeder->seedChannel($teamId, $channelId, $counts['teams_messages_per_channel']);
                            $this->stats['teams'] += $n;
                            $this->progress->log('Team ' . ($team['displayName'] ?? $teamId) . ' / '
                                . ($channel['displayName'] ?? $channelId) . ': ' . $n . ' messages');
                        }
                    } catch (\Throwable $e) {
                        $this->recordError('teams', $teamId, $e);
                    }
                }
            }
        }

        if (SeederRunRepository::isCancelled($this->runId)) {
            SeederRunRepository::update($this->runId, [
                'stats_json' => json_encode($this->stats, JSON_THROW_ON_ERROR),
            ]);
            $this->updateProgress('cancelled', 'Seeding cancelled', status: 'cancelled');

            return;
        }

        SeederRunRepository::markSuccess($this->runId, $this->stats);
        $this->updateProgress('complete', 'Seeding complete', status: 'success');
        $this->progress->log('Done. Stats: ' . json_encode($this->stats));
    }

    /**
     * @param list<array<string, mixed>> $users
     * @param callable(array<string, mixed>): int $seedFn
     */
    private function seedUsersPhase(string $phase, array $users, callable $seedFn): void
    {
        $total = count($users);
        $this->updateProgress($phase, 'Seeding ' . $phase . '…', 0, $total);
        foreach ($users as $idx => $user) {
            if ($this->shouldStop()) {
                return;
            }
            $userId = (string) ($user['id'] ?? '');
            $label = (string) ($user['displayName'] ?? $user['userPrincipalName'] ?? $userId);
            if ($userId === '') {
                continue;
            }
            try {
                $n = $seedFn($user);
                $this->stats[$phase] = ($this->stats[$phase] ?? 0) + $n;
                $this->progress->log(ucfirst($phase) . ' for ' . $label . ': ' . $n);
            } catch (\Throwable $e) {
                $this->recordError($phase, $userId, $e);
            }
            $this->updateProgress($phase, $phase . ' user ' . ($idx + 1) . '/' . $total, $idx + 1, $total);
        }
    }

    private function recordError(string $phase, string $targetId, \Throwable $e): void
    {
        $this->stats['errors']++;
        $msg = $e instanceof GraphApiException
            ? $e->getMessage() . ' (HTTP ' . $e->statusCode . ')'
            : $e->getMessage();
        $this->progress->log('ERROR [' . $phase . '] ' . $targetId . ': ' . $msg);
    }

    private function shouldStop(): bool
    {
        return SeederRunRepository::isCancelled($this->runId);
    }

    /** @param array<string, bool|string> $workloads */
    private function workloadEnabled(array $workloads, string $key): bool
    {
        if ($workloads === []) {
            return true;
        }

        return filter_var($workloads[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    private function updateProgress(
        string $phase,
        string $message,
        int $current = 0,
        int $total = 0,
        string $status = 'running',
    ): void {
        $this->progress->write([
            'status' => $status,
            'phase' => $phase,
            'message' => $message,
            'current' => $current,
            'total' => $total,
            'stats' => $this->stats,
        ]);
    }
}
