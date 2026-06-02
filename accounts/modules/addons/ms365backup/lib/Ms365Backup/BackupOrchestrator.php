<?php
declare(strict_types=1);

namespace Ms365Backup;

final class BackupOrchestrator
{
    private readonly RunCancellation $cancellation;

    public function __construct(
        private readonly string $runId,
        private readonly ProgressLogger $logger,
    ) {
        $this->cancellation = new RunCancellation($this->runId);
    }

    public function execute(): void
    {
        $run = BackupRunRepository::get($this->runId);
        if (!$run) {
            throw new \RuntimeException('Run not found: ' . $this->runId);
        }
        if (($run['status'] ?? '') === 'success') {
            return;
        }
        if (BackupRunRepository::isCancelled($this->runId)) {
            throw new RunCancelledException('Backup already cancelled');
        }

        $job = PhysicalBackupJob::fromRunRow($run);
        $scope = BackupScope::fromLegacyRun($run);

        $runDir = (string) ($run['backup_path'] ?? '');
        if ($runDir !== '') {
            WorkerProcess::writePid($runDir);
        }

        BackupRunRepository::update($this->runId, [
            'status' => 'running',
            'started_at' => time(),
        ]);

        $engineResults = [];
        $storage = null;
        $runDir = '';

        try {
            $this->cancellation->check();

            $creds = TenantRepository::credentials();
            $tokens = new TokenProvider(
                $creds['region'],
                $creds['tenant_id'],
                $creds['client_id'],
                $creds['client_secret'],
            );
            $graph = new GraphClient($tokens, $creds['region']);
            $storage = new StorageLayout($creds['tenant_id']);
            StorageLayout::ensureBase();

            $physicalKey = (string) ($run['physical_key'] ?? '');
            if ($physicalKey === '' && $job->graphId() !== '') {
                $physicalKey = 'user:' . $job->graphId();
            }
            $runDir = $storage->runDirForJob($physicalKey, $this->runId);
            if ((string) ($run['backup_path'] ?? '') !== $runDir) {
                BackupRunRepository::update($this->runId, ['backup_path' => $runDir]);
            }
            WorkerProcess::writePid($runDir);

            BackupRunRepository::setPhase($this->runId, 'auth');
            $this->logger->info('Authenticated to Microsoft Graph', [
                'resource_type' => $job->resourceType(),
                'physical_key' => $physicalKey,
                'scope' => $scope->toArray(),
            ]);
            $this->cancellation->check();

            $registry = new BackupEngineRegistry();
            $engines = $registry->enginesFor($job, $scope);

            if ($engines === []) {
                $engineResults['none'] = [
                    'skipped' => true,
                    'reason' => 'No engines match scope and resource type',
                ];
            }

            $ctx = new BackupEngineContext(
                $this->runId,
                $job,
                $scope,
                $graph,
                $storage,
                $this->logger,
                $this->cancellation,
                $runDir,
            );

            foreach ($engines as $engine) {
                if ($engine instanceof DeferredBackupEngine && in_array($job->resourceType(), [
                    TenantResource::TYPE_USER,
                    TenantResource::TYPE_MAILBOX,
                    TenantResource::TYPE_USER_ONEDRIVE,
                    TenantResource::TYPE_SHAREPOINT_SITE,
                    TenantResource::TYPE_TEAM,
                    TenantResource::TYPE_TEAM_CHANNEL,
                ], true)) {
                    continue;
                }
                $this->cancellation->check();
                $engineResults[$engine->name()] = $engine->run($ctx);
            }

            BackupRunRepository::setPhase($this->runId, 'finalize', 1, 1);

            $userId = $job->graphId();
            if ($job->resourceType() === TenantResource::TYPE_USER_ONEDRIVE) {
                $userId = $ctx->ownerUserId();
            }
            $siteId = $job->resourceType() === TenantResource::TYPE_SHAREPOINT_SITE ? $ctx->siteGraphId() : '';
            $teamId = in_array($job->resourceType(), [TenantResource::TYPE_TEAM, TenantResource::TYPE_TEAM_CHANNEL], true)
                ? $ctx->teamGroupId()
                : '';

            $manifest = [
                'run_id' => $this->runId,
                'resource_id' => (string) ($run['resource_id'] ?? ''),
                'resource_type' => $job->resourceType(),
                'physical_key' => $physicalKey,
                'logical_sources' => $job->logicalSources,
                'scope' => $scope->toArray(),
                'user_id' => $userId,
                'user_upn' => $run['user_upn'] ?? '',
                'completed_at' => gmdate('c'),
                'backup_mail' => $scope->isEnabled(BackupScope::MAIL),
                'backup_calendar' => $scope->isEnabled(BackupScope::CALENDAR),
                'backup_contacts' => $scope->isEnabled(BackupScope::CONTACTS),
                'backup_tasks' => $scope->isEnabled(BackupScope::TASKS),
                'backup_onedrive' => $scope->isEnabled(BackupScope::ONEDRIVE),
                'backup_files' => $scope->isEnabled(BackupScope::FILES),
                'backup_lists' => $scope->isEnabled(BackupScope::LISTS),
                'backup_teams_metadata' => $scope->isEnabled(BackupScope::TEAMS_METADATA),
                'backup_teams_messages' => $scope->isEnabled(BackupScope::TEAMS_MESSAGES),
                'drive_id' => $job->resourceType() === TenantResource::TYPE_USER_ONEDRIVE ? $ctx->driveId() : null,
                'site_id' => $siteId !== '' ? $siteId : null,
                'team_id' => $teamId !== '' ? $teamId : null,
                'engines' => $engineResults,
                'mail' => $engineResults['mail'] ?? null,
                'calendar' => $engineResults['calendar'] ?? null,
                'contacts' => $engineResults['contacts'] ?? null,
                'tasks' => $engineResults['tasks'] ?? null,
                'onedrive' => $engineResults['onedrive'] ?? null,
                'sharepoint' => $engineResults['sharepoint'] ?? null,
                'teams' => $engineResults['teams'] ?? null,
            ];
            $storage->writeJson($runDir . '/manifest.json', $manifest);

            $finalStatus = $this->resolveFinalStatus($scope, $engineResults);
            $skipMessage = $this->buildSkipMessage($engineResults);

            if ($finalStatus === 'skipped') {
                $this->logger->warn('Backup skipped: no selected data could be backed up', ['reasons' => $skipMessage]);
                BackupRunRepository::update($this->runId, [
                    'status' => 'skipped',
                    'phase' => 'done',
                    'percent' => 100,
                    'error_message' => substr($skipMessage, 0, 65000),
                    'finished_at' => time(),
                ]);
            } else {
                $this->logger->info('Backup completed', $manifest);
                BackupRunRepository::update($this->runId, [
                    'status' => 'success',
                    'phase' => 'done',
                    'percent' => 100,
                    'finished_at' => time(),
                    'error_message' => $skipMessage !== '' ? substr($skipMessage, 0, 65000) : null,
                ]);
            }
        } catch (RunCancelledException $e) {
            $this->logger->info('Backup aborted by user');
            throw $e;
        } catch (CalendarBackupIncompleteException $e) {
            if (!BackupRunRepository::isCancelled($this->runId)) {
                $this->logger->error('Calendar backup incomplete', [
                    'error' => $e->getMessage(),
                    'calendars' => $e->incompleteCalendars,
                ]);
                try {
                    $partialManifest = [
                        'run_id' => $this->runId,
                        'resource_type' => $job->resourceType(),
                        'physical_key' => $run['physical_key'] ?? '',
                        'completed_at' => gmdate('c'),
                        'status' => 'error',
                        'error' => $e->getMessage(),
                        'engines' => $engineResults,
                        'mail' => $engineResults['mail'] ?? null,
                        'calendar' => [
                            'calendars_incomplete' => $e->incompleteCalendars,
                            'calendar_results' => $e->completedCalendars,
                        ],
                    ];
                    if ($storage !== null && $runDir !== '') {
                        $storage->writeJson($runDir . '/manifest.json', $partialManifest);
                    }
                } catch (\Throwable $_) {
                }
                BackupRunRepository::update($this->runId, [
                    'status' => 'error',
                    'error_message' => substr($e->getMessage(), 0, 65000),
                    'finished_at' => time(),
                ]);
            }
            throw $e;
        } catch (GraphPaginationException $e) {
            if (!BackupRunRepository::isCancelled($this->runId)) {
                $this->logger->error('Backup stopped: Graph pagination safety triggered', [
                    'error' => $e->getMessage(),
                ]);
                BackupRunRepository::update($this->runId, [
                    'status' => 'error',
                    'error_message' => substr($e->getMessage(), 0, 65000),
                    'finished_at' => time(),
                ]);
            }
            throw $e;
        } catch (\Throwable $e) {
            if (!BackupRunRepository::isCancelled($this->runId)) {
                $this->logger->error('Backup failed', ['error' => $e->getMessage()]);
                BackupRunRepository::update($this->runId, [
                    'status' => 'error',
                    'error_message' => substr($e->getMessage(), 0, 65000),
                    'finished_at' => time(),
                ]);
            }
            throw $e;
        }
    }

    /**
     * @param array<string, array<string, mixed>|null> $engineResults
     */
    private function resolveFinalStatus(BackupScope $scope, array $engineResults): string
    {
        $anyEnabled = false;
        $anySucceeded = false;
        $allSkipped = true;

        foreach ($engineResults as $name => $stats) {
            if ($name === 'deferred' || $name === 'none') {
                if (is_array($stats) && empty($stats['skipped'])) {
                    $anySucceeded = true;
                    $allSkipped = false;
                }
                continue;
            }

            if ($name === 'sharepoint' && is_array($stats)) {
                $this->accumulateSharePointStatus($scope, $stats, $anyEnabled, $anySucceeded, $allSkipped);
                continue;
            }

            if ($name === 'teams' && is_array($stats)) {
                $this->accumulateTeamsStatus($scope, $stats, $anyEnabled, $anySucceeded, $allSkipped);
                continue;
            }

            $enabled = match ($name) {
                'mail' => $scope->isEnabled(BackupScope::MAIL),
                'calendar' => $scope->isEnabled(BackupScope::CALENDAR),
                'contacts' => $scope->isEnabled(BackupScope::CONTACTS),
                'tasks' => $scope->isEnabled(BackupScope::TASKS),
                'onedrive' => $scope->isEnabled(BackupScope::ONEDRIVE),
                default => false,
            };
            if (!$enabled) {
                continue;
            }
            $anyEnabled = true;
            if (is_array($stats) && $this->phaseWasSkipped($stats)) {
                continue;
            }
            $anySucceeded = true;
            $allSkipped = false;
        }

        if (!$anyEnabled && !$scope->hasAnyEnabled()) {
            return 'skipped';
        }

        if ($anySucceeded) {
            return 'success';
        }

        if ($anyEnabled && $allSkipped) {
            return 'skipped';
        }

        if (isset($engineResults['deferred']) || isset($engineResults['none'])) {
            return 'skipped';
        }

        return 'success';
    }

    /** @param array<string, mixed>|null $stats */
    private function phaseWasSkipped(?array $stats): bool
    {
        return is_array($stats) && !empty($stats['skipped']);
    }

    /**
     * @param array<string, mixed> $teamsResults
     */
    private function accumulateTeamsStatus(
        BackupScope $scope,
        array $teamsResults,
        bool &$anyEnabled,
        bool &$anySucceeded,
        bool &$allSkipped,
    ): void {
        $parts = [
            'metadata' => BackupScope::TEAMS_METADATA,
            'messages' => BackupScope::TEAMS_MESSAGES,
        ];
        foreach ($parts as $key => $scopeKey) {
            if (!$scope->isEnabled($scopeKey)) {
                continue;
            }
            $anyEnabled = true;
            $sub = $teamsResults[$key] ?? null;
            if (!is_array($sub) || $this->phaseWasSkipped($sub)) {
                continue;
            }
            $anySucceeded = true;
            $allSkipped = false;
        }
    }

    /**
     * @param array<string, mixed> $sharepointResults
     */
    private function accumulateSharePointStatus(
        BackupScope $scope,
        array $sharepointResults,
        bool &$anyEnabled,
        bool &$anySucceeded,
        bool &$allSkipped,
    ): void {
        $parts = [
            'files' => BackupScope::FILES,
            'lists' => BackupScope::LISTS,
        ];
        foreach ($parts as $key => $scopeKey) {
            if (!$scope->isEnabled($scopeKey)) {
                continue;
            }
            $anyEnabled = true;
            $sub = $sharepointResults[$key] ?? null;
            if (!is_array($sub) || $this->phaseWasSkipped($sub)) {
                continue;
            }
            $anySucceeded = true;
            $allSkipped = false;
        }
    }

    /** @param array<string, array<string, mixed>|null> $engineResults */
    private function buildSkipMessage(array $engineResults): string
    {
        $parts = [];
        foreach ($engineResults as $name => $stats) {
            if ($name === 'sharepoint' && is_array($stats)) {
                foreach (['files', 'lists'] as $sub) {
                    $subStats = $stats[$sub] ?? null;
                    if (is_array($subStats) && $this->phaseWasSkipped($subStats)) {
                        $parts[] = 'SharePoint ' . $sub . ': ' . (string) ($subStats['reason'] ?? 'unavailable');
                    }
                }
                continue;
            }
            if ($name === 'teams' && is_array($stats)) {
                foreach (['metadata', 'messages'] as $sub) {
                    $subStats = $stats[$sub] ?? null;
                    if (is_array($subStats) && $this->phaseWasSkipped($subStats)) {
                        $parts[] = 'Teams ' . $sub . ': ' . (string) ($subStats['reason'] ?? 'unavailable');
                    }
                }
                continue;
            }
            if (!is_array($stats) || !$this->phaseWasSkipped($stats)) {
                continue;
            }
            $parts[] = ucfirst($name) . ': ' . (string) ($stats['reason'] ?? 'unavailable');
        }

        return implode(' | ', $parts);
    }
}
