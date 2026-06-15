<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * Resolves selected resources into runnable backup jobs and dedup groups.
 */
final class BackupPlanner
{
    /**
     * @param list<string> $selectedIds
     * @param array<string, mixed>|null $inventory
     * @param array<string, array<string, bool>> $scopeOverridesByResourceId
     * @return array{
     *   runnable: list<array<string, mixed>>,
     *   deferred: list<array<string, mixed>>,
     *   dedup_groups: list<array{physical_key: string, logical_ids: list<string>, message: string}>,
     *   warnings: list<string>
     * }
     */
    public function plan(array $selectedIds, ?array $inventory, array $scopeOverridesByResourceId = []): array
    {
        $queue = $this->buildPhysicalQueue($selectedIds, $inventory, BackupScope::empty(), $scopeOverridesByResourceId);
        $runnable = [];
        $deferred = [];
        foreach ($queue['physical_jobs'] as $job) {
            if ($job->isRunnable()) {
                $runnable[] = $job->primaryResource;
            } else {
                $deferred[] = array_merge($job->primaryResource, [
                    'reason' => $job->deferReason,
                ]);
            }
        }

        return [
            'runnable' => $runnable,
            'deferred' => $deferred,
            'dedup_groups' => $queue['dedup_groups'],
            'warnings' => $queue['warnings'],
            'physical_jobs' => array_map(static fn (PhysicalBackupJob $j) => $j->toArray(), $queue['physical_jobs']),
            'summary' => $queue['summary'],
        ];
    }

    /**
     * @param list<string> $selectedIds
     * @param array<string, mixed>|null $inventory
     * @param array<string, array<string, bool>> $scopeOverridesByResourceId
     * @return array{
     *   physical_jobs: list<PhysicalBackupJob>,
     *   dedup_groups: list<array{physical_key: string, logical_ids: list<string>, message: string}>,
     *   warnings: list<string>,
     *   summary: array{runnable: int, deferred: int}
     * }
     */
    public function buildPhysicalQueue(
        array $selectedIds,
        ?array $inventory,
        BackupScope $defaultScope,
        array $scopeOverridesByResourceId = [],
    ): array {
        $selectedIds = array_values(array_unique(array_filter(array_map('strval', $selectedIds))));
        if ($selectedIds === []) {
            return [
                'physical_jobs' => [],
                'dedup_groups' => [],
                'warnings' => [],
                'summary' => ['runnable' => 0, 'deferred' => 0],
            ];
        }

        $resources = is_array($inventory) && is_array($inventory['resources'] ?? null) ? $inventory['resources'] : [];
        $relationships = is_array($inventory) && is_array($inventory['relationships'] ?? null) ? $inventory['relationships'] : [];

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

        $siteDedupGroups = $this->buildSiteDedupGroups($selectedIds, $byId, $relationships);
        $messageDedupGroups = $this->buildMessageDedupGroups($selectedIds, $byId, $relationships);
        $warnings = [];
        foreach (array_merge($siteDedupGroups, $messageDedupGroups) as $group) {
            if (count($group['logical_ids']) > 1) {
                $warnings[] = $group['message'];
            }
        }

        $consumedLogical = [];
        $physicalJobs = [];

        foreach ($siteDedupGroups as $group) {
            if (count($group['logical_ids']) < 2) {
                continue;
            }
            $job = $this->buildSitePhysicalJob($group, $byId, $defaultScope, $scopeOverridesByResourceId);
            if ($job !== null) {
                $this->mergePhysicalJob($physicalJobs, $job);
                foreach ($group['logical_ids'] as $lid) {
                    $consumedLogical[$lid] = true;
                }
            }
        }

        foreach ($messageDedupGroups as $group) {
            if (count($group['logical_ids']) < 2) {
                continue;
            }
            $job = $this->buildTeamPhysicalJob($group, $byId, $defaultScope, $scopeOverridesByResourceId);
            if ($job !== null) {
                $this->mergePhysicalJob($physicalJobs, $job);
                foreach ($group['logical_ids'] as $lid) {
                    $consumedLogical[$lid] = true;
                }
            }
        }

        $userScopes = [];
        foreach ($selectedIds as $id) {
            if (isset($consumedLogical[$id])) {
                continue;
            }
            $resource = $byId[$id] ?? null;
            if ($resource === null) {
                $physicalJobs['unknown:' . $id] = new PhysicalBackupJob(
                    'unknown:' . $id,
                    ['id' => $id, 'resource_type' => 'unknown', 'graph_id' => '', 'display_name' => $id],
                    [['id' => $id, 'resource_type' => 'unknown', 'display_name' => $id]],
                    $defaultScope,
                    PhysicalBackupJob::STATUS_DEFERRED,
                    'Resource not found in inventory',
                );
                continue;
            }

            $type = (string) ($resource['resource_type'] ?? '');
            $scope = $this->resolveScope($resource, $defaultScope, $scopeOverridesByResourceId);

            if (in_array($type, [TenantResource::TYPE_USER, TenantResource::TYPE_MAILBOX], true)) {
                $graphId = (string) ($resource['graph_id'] ?? '');
                $physicalKey = 'user:' . $graphId;
                $logical = [['id' => $id, 'resource_type' => $type, 'display_name' => (string) ($resource['display_name'] ?? '')]];
                if (isset($physicalJobs[$physicalKey])) {
                    $existing = $physicalJobs[$physicalKey];
                    $mergedScope = $existing->scope->merge($scope);
                    $mergedLogical = array_merge($existing->logicalSources, $logical);
                    $physicalJobs[$physicalKey] = new PhysicalBackupJob(
                        $physicalKey,
                        $existing->primaryResource,
                        $mergedLogical,
                        $mergedScope,
                        $this->resolveUserEngineStatus($mergedScope),
                        '',
                    );
                } else {
                    $physicalJobs[$physicalKey] = new PhysicalBackupJob(
                        $physicalKey,
                        $resource,
                        $logical,
                        $scope,
                        $this->resolveUserEngineStatus($scope),
                        '',
                    );
                }
                $consumedLogical[$id] = true;
                continue;
            }

            if ($type === TenantResource::TYPE_PLANNER_PLAN) {
                $planId = (string) ($resource['graph_id'] ?? '');
                $physicalKey = 'planner:' . $planId;
                $physicalJobs[$physicalKey] = new PhysicalBackupJob(
                    $physicalKey,
                    $resource,
                    [$this->logicalSourceFromResource($resource)],
                    $scope,
                    $this->resolvePlannerEngineStatus($scope),
                    $this->resolvePlannerEngineStatus($scope) === PhysicalBackupJob::STATUS_DEFERRED
                        ? 'Planner scope not enabled'
                        : '',
                );
                $consumedLogical[$id] = true;
                continue;
            }

            if ($type === TenantResource::TYPE_ONENOTE_NOTEBOOK) {
                $meta = is_array($resource['meta'] ?? null) ? $resource['meta'] : [];
                $notebookId = (string) ($meta['notebook_id'] ?? $resource['graph_id'] ?? '');
                $physicalKey = 'onenote:' . $notebookId;
                $physicalJobs[$physicalKey] = new PhysicalBackupJob(
                    $physicalKey,
                    $resource,
                    [$this->logicalSourceFromResource($resource)],
                    $scope,
                    $this->resolveOneNoteEngineStatus($scope),
                    $this->resolveOneNoteEngineStatus($scope) === PhysicalBackupJob::STATUS_DEFERRED
                        ? 'OneNote scope not enabled'
                        : '',
                );
                $consumedLogical[$id] = true;
                continue;
            }

            if ($type === TenantResource::TYPE_DIRECTORY_BASELINE) {
                $physicalKey = 'directory:tenant';
                $physicalJobs[$physicalKey] = new PhysicalBackupJob(
                    $physicalKey,
                    $resource,
                    [$this->logicalSourceFromResource($resource)],
                    $scope,
                    PhysicalBackupJob::STATUS_RUNNABLE,
                    '',
                );
                $consumedLogical[$id] = true;
                continue;
            }

            if ($type === TenantResource::TYPE_USER_ONEDRIVE) {
                $meta = is_array($resource['meta'] ?? null) ? $resource['meta'] : [];
                $ownerId = (string) ($meta['owner_user_id'] ?? TenantResource::graphIdFromResourceId((string) ($resource['parent_id'] ?? '')));
                $driveId = (string) ($meta['drive_id'] ?? $resource['graph_id'] ?? '');
                $physicalKey = $driveId !== '' ? 'drive:' . $driveId : 'onedrive:' . $ownerId;
                $physicalJobs[$physicalKey] = new PhysicalBackupJob(
                    $physicalKey,
                    $resource,
                    [$this->logicalSourceFromResource($resource)],
                    $scope,
                    $this->resolveDriveEngineStatus($scope),
                    $this->resolveDriveEngineStatus($scope) === PhysicalBackupJob::STATUS_DEFERRED
                        ? 'OneDrive scope not enabled'
                        : '',
                );
                $consumedLogical[$id] = true;
                continue;
            }

            if ($type === TenantResource::TYPE_SHAREPOINT_SITE) {
                if (!isset($physicalJobs['site:' . ($resource['graph_id'] ?? '')])) {
                    $siteKey = 'site:' . (string) $resource['graph_id'];
                    $physicalJobs[$siteKey] = new PhysicalBackupJob(
                        $siteKey,
                        $resource,
                        [$this->logicalSourceFromResource($resource)],
                        $scope,
                        $this->resolveSiteEngineStatus($scope),
                        $this->resolveSiteEngineStatus($scope) === PhysicalBackupJob::STATUS_DEFERRED
                            ? 'SharePoint files/lists scope not enabled'
                            : '',
                    );
                }
                $consumedLogical[$id] = true;
                continue;
            }

            if (in_array($type, [TenantResource::TYPE_TEAM, TenantResource::TYPE_TEAM_CHANNEL, TenantResource::TYPE_M365_GROUP], true)) {
                if ($this->scopeNeedsSharePointSite($scope)) {
                    $siteJob = $this->tryOrphanSiteJob($resource, $byId, $relationships, $defaultScope, $scopeOverridesByResourceId);
                    if ($siteJob !== null) {
                        $this->mergePhysicalJob($physicalJobs, $siteJob);
                    }
                }

                if (in_array($type, [TenantResource::TYPE_TEAM, TenantResource::TYPE_TEAM_CHANNEL], true)
                    && $this->scopeNeedsTeamsBackup($scope)) {
                    $teamJob = $this->buildTeamJobForResource($resource, $scope);
                    if ($teamJob !== null) {
                        $this->mergePhysicalJob($physicalJobs, $teamJob);
                    }
                } elseif ($type === TenantResource::TYPE_M365_GROUP
                    && $this->scopeNeedsGroupMailbox($scope)) {
                    $groupJob = $this->buildGroupJobForResource($resource, $scope);
                    if ($groupJob !== null) {
                        $this->mergePhysicalJob($physicalJobs, $groupJob);
                    }
                } elseif ($type === TenantResource::TYPE_M365_GROUP
                    && !$this->scopeNeedsGroupMailbox($scope)
                    && !$this->scopeNeedsSharePointSite($scope)) {
                    $physicalKey = 'group:' . (string) ($resource['graph_id'] ?? $id);
                    if (!isset($physicalJobs[$physicalKey])) {
                        $physicalJobs[$physicalKey] = new PhysicalBackupJob(
                            $physicalKey,
                            $resource,
                            [$this->logicalSourceFromResource($resource)],
                            $scope,
                            PhysicalBackupJob::STATUS_DEFERRED,
                            'Enable mail, calendar, or SharePoint files/lists scope for this group',
                        );
                    }
                } elseif (!in_array($type, [TenantResource::TYPE_TEAM, TenantResource::TYPE_TEAM_CHANNEL, TenantResource::TYPE_M365_GROUP], true)
                    || (!$this->scopeNeedsTeamsBackup($scope) && !$this->scopeNeedsSharePointSite($scope))) {
                    $physicalKey = $type . ':' . (string) ($resource['graph_id'] ?? $id);
                    if (!isset($physicalJobs[$physicalKey])) {
                        $physicalJobs[$physicalKey] = new PhysicalBackupJob(
                            $physicalKey,
                            $resource,
                            [$this->logicalSourceFromResource($resource)],
                            $scope,
                            PhysicalBackupJob::STATUS_DEFERRED,
                            'No backup engine for this resource type yet',
                        );
                    }
                }

                $consumedLogical[$id] = true;
            }
        }

        $physicalJobs = (new ResourceShardPlanner())->expand($physicalJobs, $byId);

        $runnable = 0;
        $deferred = 0;
        foreach ($physicalJobs as $job) {
            if ($job->isRunnable()) {
                $runnable++;
            } else {
                $deferred++;
            }
        }

        return [
            'physical_jobs' => array_values($physicalJobs),
            'dedup_groups' => array_merge($siteDedupGroups, $messageDedupGroups),
            'warnings' => $warnings,
            'summary' => ['runnable' => $runnable, 'deferred' => $deferred],
        ];
    }

    /**
     * @param array{physical_key: string, logical_ids: list<string>, message: string} $group
     * @param array<string, array<string, mixed>> $byId
     * @param array<string, array<string, bool>> $scopeOverridesByResourceId
     */
    private function buildSitePhysicalJob(
        array $group,
        array $byId,
        BackupScope $defaultScope,
        array $scopeOverridesByResourceId,
    ): ?PhysicalBackupJob {
        $logicalSources = [];
        $primary = null;
        $siteGraphId = '';

        foreach ($group['logical_ids'] as $lid) {
            $resource = $byId[$lid] ?? null;
            if ($resource === null) {
                continue;
            }
            $logicalSources[] = $this->logicalSourceFromResource($resource);
            $type = (string) ($resource['resource_type'] ?? '');
            if ($type === TenantResource::TYPE_SHAREPOINT_SITE) {
                $primary = $resource;
                $siteGraphId = (string) ($resource['graph_id'] ?? '');
            } elseif ($siteGraphId === '') {
                $meta = is_array($resource['meta'] ?? null) ? $resource['meta'] : [];
                $siteGraphId = (string) ($meta['sharepoint_site_id'] ?? $meta['channel_site_id'] ?? '');
            }
        }

        if ($siteGraphId === '' && $primary === null) {
            return null;
        }

        if ($primary === null) {
            $primary = [
                'id' => TenantResource::makeId(TenantResource::TYPE_SHAREPOINT_SITE, $siteGraphId),
                'resource_type' => TenantResource::TYPE_SHAREPOINT_SITE,
                'graph_id' => $siteGraphId,
                'display_name' => 'SharePoint site',
            ];
        }

        $scope = $this->resolveScope($primary, $defaultScope, $scopeOverridesByResourceId);
        $physicalKey = (string) ($group['physical_key'] ?? 'site:' . $siteGraphId);

        return new PhysicalBackupJob(
            $physicalKey,
            $primary,
            $logicalSources,
            $scope,
            $this->resolveSiteEngineStatus($scope),
            $this->resolveSiteEngineStatus($scope) === PhysicalBackupJob::STATUS_DEFERRED
                ? 'SharePoint files/lists scope not enabled'
                : '',
        );
    }

    /**
     * @param array<string, mixed> $resource
     * @param array<string, array<string, mixed>> $byId
     * @param list<array<string, mixed>> $relationships
     * @param array<string, array<string, bool>> $scopeOverridesByResourceId
     */
    private function tryOrphanSiteJob(
        array $resource,
        array $byId,
        array $relationships,
        BackupScope $defaultScope,
        array $scopeOverridesByResourceId,
    ): ?PhysicalBackupJob {
        $resourceId = (string) ($resource['id'] ?? '');
        $siteId = '';
        foreach ($relationships as $rel) {
            if (!is_array($rel) || ($rel['from_id'] ?? '') !== $resourceId) {
                continue;
            }
            if (($rel['rel'] ?? '') !== RelationshipResolver::REL_FILES_IN_SITE) {
                continue;
            }
            $toId = (string) ($rel['to_id'] ?? '');
            $target = $byId[$toId] ?? null;
            if ($target !== null && ($target['resource_type'] ?? '') === TenantResource::TYPE_SHAREPOINT_SITE) {
                $siteId = (string) ($target['graph_id'] ?? '');
                break;
            }
            $physicalKey = (string) ($rel['physical_key'] ?? '');
            if (str_starts_with($physicalKey, 'site:')) {
                $siteId = substr($physicalKey, 5);
                break;
            }
        }

        $meta = is_array($resource['meta'] ?? null) ? $resource['meta'] : [];
        if ($siteId === '') {
            $siteId = (string) ($meta['sharepoint_site_id'] ?? $meta['channel_site_id'] ?? '');
        }

        if ($siteId === '') {
            return null;
        }

        $primary = $byId[TenantResource::makeId(TenantResource::TYPE_SHAREPOINT_SITE, $siteId)] ?? [
            'id' => TenantResource::makeId(TenantResource::TYPE_SHAREPOINT_SITE, $siteId),
            'resource_type' => TenantResource::TYPE_SHAREPOINT_SITE,
            'graph_id' => $siteId,
            'display_name' => (string) ($resource['display_name'] ?? '') . ' (site)',
        ];

        $scope = $this->resolveScope($primary, $defaultScope, $scopeOverridesByResourceId);

        return new PhysicalBackupJob(
            'site:' . $siteId,
            $primary,
            [$this->logicalSourceFromResource($resource)],
            $scope,
            $this->resolveSiteEngineStatus($scope),
            $this->resolveSiteEngineStatus($scope) === PhysicalBackupJob::STATUS_DEFERRED
                ? 'SharePoint files/lists scope not enabled'
                : '',
        );
    }

  /**
     * @param array<string, mixed> $resource
     * @param array<string, array<string, bool>> $scopeOverridesByResourceId
     */
    private function resolveScope(array $resource, BackupScope $defaultScope, array $scopeOverridesByResourceId): BackupScope
    {
        $id = (string) ($resource['id'] ?? '');
        $type = (string) ($resource['resource_type'] ?? '');
        if (isset($scopeOverridesByResourceId[$id])) {
            return BackupScope::fromAuthoritativeOverride($type, $scopeOverridesByResourceId[$id]);
        }

        return BackupScope::forResourceType($type)->merge($defaultScope);
    }

    private function resolveUserEngineStatus(BackupScope $scope): string
    {
        if (!$scope->hasAnyEnabled()) {
            return PhysicalBackupJob::STATUS_DEFERRED;
        }
        if ($scope->isEnabled(BackupScope::MAIL)
            || $scope->isEnabled(BackupScope::CALENDAR)
            || $scope->isEnabled(BackupScope::CONTACTS)
            || $scope->isEnabled(BackupScope::TASKS)) {
            return PhysicalBackupJob::STATUS_RUNNABLE;
        }

        return PhysicalBackupJob::STATUS_DEFERRED;
    }

    private function resolveDriveEngineStatus(BackupScope $scope): string
    {
        if ($scope->isEnabled(BackupScope::ONEDRIVE)) {
            return PhysicalBackupJob::STATUS_RUNNABLE;
        }

        return PhysicalBackupJob::STATUS_DEFERRED;
    }

    private function resolveSiteEngineStatus(BackupScope $scope): string
    {
        if ($scope->isEnabled(BackupScope::FILES) || $scope->isEnabled(BackupScope::LISTS)) {
            return PhysicalBackupJob::STATUS_RUNNABLE;
        }

        return PhysicalBackupJob::STATUS_DEFERRED;
    }

    private function resolveTeamEngineStatus(BackupScope $scope): string
    {
        if ($scope->isEnabled(BackupScope::TEAMS_METADATA) || $scope->isEnabled(BackupScope::TEAMS_MESSAGES)) {
            return PhysicalBackupJob::STATUS_RUNNABLE;
        }

        return PhysicalBackupJob::STATUS_DEFERRED;
    }

    private function resolveGroupEngineStatus(BackupScope $scope): string
    {
        if ($this->scopeNeedsGroupMailbox($scope)) {
            return PhysicalBackupJob::STATUS_RUNNABLE;
        }

        return PhysicalBackupJob::STATUS_DEFERRED;
    }

    private function scopeNeedsGroupMailbox(BackupScope $scope): bool
    {
        return $scope->isEnabled(BackupScope::MAIL) || $scope->isEnabled(BackupScope::CALENDAR);
    }

    private function resolvePlannerEngineStatus(BackupScope $scope): string
    {
        return $scope->isEnabled(BackupScope::PLANNER)
            ? PhysicalBackupJob::STATUS_RUNNABLE
            : PhysicalBackupJob::STATUS_DEFERRED;
    }

    private function resolveOneNoteEngineStatus(BackupScope $scope): string
    {
        return $scope->isEnabled(BackupScope::ONENOTE)
            ? PhysicalBackupJob::STATUS_RUNNABLE
            : PhysicalBackupJob::STATUS_DEFERRED;
    }

    /**
     * @param array<string, mixed> $resource
     */
    private function buildGroupJobForResource(array $resource, BackupScope $scope): ?PhysicalBackupJob
    {
        $groupId = (string) ($resource['graph_id'] ?? '');
        if ($groupId === '') {
            return null;
        }

        $physicalKey = 'group:' . $groupId;

        return new PhysicalBackupJob(
            $physicalKey,
            $resource,
            [$this->logicalSourceFromResource($resource)],
            $scope,
            $this->resolveGroupEngineStatus($scope),
            $this->resolveGroupEngineStatus($scope) === PhysicalBackupJob::STATUS_DEFERRED
                ? 'Group mail/calendar scope not enabled'
                : '',
        );
    }

    private function scopeNeedsSharePointSite(BackupScope $scope): bool
    {
        return $scope->isEnabled(BackupScope::FILES) || $scope->isEnabled(BackupScope::LISTS);
    }

    private function scopeNeedsTeamsBackup(BackupScope $scope): bool
    {
        return $scope->isEnabled(BackupScope::TEAMS_METADATA) || $scope->isEnabled(BackupScope::TEAMS_MESSAGES);
    }

    /**
     * @param array<string, PhysicalBackupJob> $physicalJobs
     */
    private function mergePhysicalJob(array &$physicalJobs, PhysicalBackupJob $job): void
    {
        $key = $job->physicalKey;
        if (!isset($physicalJobs[$key])) {
            $physicalJobs[$key] = $job;

            return;
        }

        $existing = $physicalJobs[$key];
        $mergedLogical = $existing->logicalSources;
        $seen = array_fill_keys(array_column($mergedLogical, 'id'), true);
        foreach ($job->logicalSources as $source) {
            $sid = (string) ($source['id'] ?? '');
            if ($sid === '' || isset($seen[$sid])) {
                continue;
            }
            $mergedLogical[] = $source;
            $seen[$sid] = true;
        }

        $mergedScope = $existing->scope->merge($job->scope);
        $status = $job->engineStatus === PhysicalBackupJob::STATUS_RUNNABLE
            || $existing->engineStatus === PhysicalBackupJob::STATUS_RUNNABLE
            ? PhysicalBackupJob::STATUS_RUNNABLE
            : PhysicalBackupJob::STATUS_DEFERRED;

        $physicalJobs[$key] = new PhysicalBackupJob(
            $key,
            $existing->primaryResource,
            $mergedLogical,
            $mergedScope,
            $status,
            $status === PhysicalBackupJob::STATUS_DEFERRED ? ($job->deferReason ?: $existing->deferReason) : '',
        );
    }

    /**
     * @param array{physical_key: string, logical_ids: list<string>, message: string} $group
     * @param array<string, array<string, mixed>> $byId
     * @param array<string, array<string, bool>> $scopeOverridesByResourceId
     */
    private function buildTeamPhysicalJob(
        array $group,
        array $byId,
        BackupScope $defaultScope,
        array $scopeOverridesByResourceId,
    ): ?PhysicalBackupJob {
        $logicalSources = [];
        $primary = null;
        $groupId = '';

        foreach ($group['logical_ids'] as $lid) {
            $resource = $byId[$lid] ?? null;
            if ($resource === null) {
                continue;
            }
            $logicalSources[] = $this->logicalSourceFromResource($resource);
            $type = (string) ($resource['resource_type'] ?? '');
            if ($type === TenantResource::TYPE_TEAM) {
                $primary = $resource;
                $groupId = GraphTeamPaths::groupIdFromResource($resource);
            } elseif ($groupId === '') {
                $groupId = GraphTeamPaths::groupIdFromResource($resource);
            }
        }

        if ($groupId === '') {
            return null;
        }

        if ($primary === null) {
            $primary = $byId[TenantResource::makeId(TenantResource::TYPE_TEAM, $groupId)] ?? [
                'id' => TenantResource::makeId(TenantResource::TYPE_TEAM, $groupId),
                'resource_type' => TenantResource::TYPE_TEAM,
                'graph_id' => $groupId,
                'display_name' => 'Team',
                'meta' => ['group_id' => $groupId],
            ];
        }

        $scope = $this->resolveScope($primary, $defaultScope, $scopeOverridesByResourceId);
        $physicalKey = (string) ($group['physical_key'] ?? 'team:' . $groupId);

        return new PhysicalBackupJob(
            $physicalKey,
            $primary,
            $logicalSources,
            $scope,
            $this->resolveTeamEngineStatus($scope),
            $this->resolveTeamEngineStatus($scope) === PhysicalBackupJob::STATUS_DEFERRED
                ? 'Teams metadata/messages scope not enabled'
                : '',
        );
    }

    /**
     * @param array<string, mixed> $resource
     */
    private function buildTeamJobForResource(array $resource, BackupScope $scope): ?PhysicalBackupJob
    {
        $type = (string) ($resource['resource_type'] ?? '');
        $groupId = GraphTeamPaths::groupIdFromResource($resource);
        if ($groupId === '') {
            return null;
        }

        if ($type === TenantResource::TYPE_TEAM_CHANNEL) {
            $channelId = GraphTeamPaths::channelIdFromResource($resource);
            if ($channelId === '') {
                return null;
            }
            $physicalKey = 'channel:' . $groupId . ':' . $channelId;

            return new PhysicalBackupJob(
                $physicalKey,
                $resource,
                [$this->logicalSourceFromResource($resource)],
                $scope,
                $this->resolveTeamEngineStatus($scope),
                $this->resolveTeamEngineStatus($scope) === PhysicalBackupJob::STATUS_DEFERRED
                    ? 'Teams metadata/messages scope not enabled'
                    : '',
            );
        }

        if ($type === TenantResource::TYPE_TEAM) {
            return new PhysicalBackupJob(
                'team:' . $groupId,
                $resource,
                [$this->logicalSourceFromResource($resource)],
                $scope,
                $this->resolveTeamEngineStatus($scope),
                $this->resolveTeamEngineStatus($scope) === PhysicalBackupJob::STATUS_DEFERRED
                    ? 'Teams metadata/messages scope not enabled'
                    : '',
            );
        }

        return null;
    }

    /** @param array<string, mixed> $resource */
    private function logicalSourceFromResource(array $resource): array
    {
        return [
            'id' => (string) ($resource['id'] ?? ''),
            'resource_type' => (string) ($resource['resource_type'] ?? ''),
            'display_name' => (string) ($resource['display_name'] ?? ''),
        ];
    }

    /**
     * @param list<string> $selectedIds
     * @param array<string, array<string, mixed>> $byId
     * @param list<array<string, mixed>> $relationships
     * @return list<array{physical_key: string, logical_ids: list<string>, message: string}>
     */
    /**
     * @param list<string> $selectedIds
     * @param array<string, array<string, mixed>> $byId
     * @param list<array<string, mixed>> $relationships
     * @return list<array{physical_key: string, logical_ids: list<string>, message: string}>
     */
    private function buildSiteDedupGroups(array $selectedIds, array $byId, array $relationships): array
    {
        $selectedSet = array_fill_keys($selectedIds, true);
        $physicalToLogical = [];

        foreach ($relationships as $rel) {
            if (!is_array($rel)) {
                continue;
            }
            if (($rel['rel'] ?? '') !== RelationshipResolver::REL_FILES_IN_SITE) {
                continue;
            }
            $fromId = (string) ($rel['from_id'] ?? '');
            $toId = (string) ($rel['to_id'] ?? '');
            $physicalKey = (string) ($rel['physical_key'] ?? '');
            if ($physicalKey === '') {
                continue;
            }

            if (isset($selectedSet[$fromId])) {
                $physicalToLogical[$physicalKey][$fromId] = true;
            }
            if (isset($selectedSet[$toId])) {
                $physicalToLogical[$physicalKey][$toId] = true;
            }
        }

        $groups = [];
        foreach ($physicalToLogical as $physicalKey => $logicalMap) {
            $logicalIds = array_keys($logicalMap);
            if ($logicalIds === []) {
                continue;
            }

            $names = [];
            foreach ($logicalIds as $lid) {
                $names[] = (string) ($byId[$lid]['display_name'] ?? $lid);
            }

            $message = count($logicalIds) > 1
                ? 'SharePoint file content for ' . implode(', ', $names) . ' will be backed up once (duplicate selection).'
                : '';

            $groups[] = [
                'physical_key' => $physicalKey,
                'logical_ids' => $logicalIds,
                'message' => $message,
            ];
        }

        return $groups;
    }

    /**
     * @param list<string> $selectedIds
     * @param array<string, array<string, mixed>> $byId
     * @param list<array<string, mixed>> $relationships
     * @return list<array{physical_key: string, logical_ids: list<string>, message: string}>
     */
    private function buildMessageDedupGroups(array $selectedIds, array $byId, array $relationships): array
    {
        $selectedSet = array_fill_keys($selectedIds, true);
        $physicalToLogical = [];

        foreach ($relationships as $rel) {
            if (!is_array($rel)) {
                continue;
            }
            if (($rel['rel'] ?? '') !== RelationshipResolver::REL_MESSAGES_IN_TEAM) {
                continue;
            }
            $fromId = (string) ($rel['from_id'] ?? '');
            $physicalKey = (string) ($rel['physical_key'] ?? '');
            if ($physicalKey === '' || !isset($selectedSet[$fromId])) {
                continue;
            }
            $physicalToLogical[$physicalKey][$fromId] = true;
        }

        foreach ($selectedIds as $id) {
            $resource = $byId[$id] ?? null;
            if ($resource === null) {
                continue;
            }
            if ((string) ($resource['resource_type'] ?? '') !== TenantResource::TYPE_TEAM) {
                continue;
            }
            $groupId = GraphTeamPaths::groupIdFromResource($resource);
            if ($groupId === '') {
                continue;
            }
            $physicalKey = 'team:' . $groupId;
            $physicalToLogical[$physicalKey][$id] = true;
        }

        $groups = [];
        foreach ($physicalToLogical as $physicalKey => $logicalMap) {
            $logicalIds = array_keys($logicalMap);
            if ($logicalIds === []) {
                continue;
            }

            $names = [];
            foreach ($logicalIds as $lid) {
                $names[] = (string) ($byId[$lid]['display_name'] ?? $lid);
            }

            $message = count($logicalIds) > 1
                ? 'Teams messages for ' . implode(', ', $names) . ' will be backed up once (duplicate selection).'
                : '';

            $groups[] = [
                'physical_key' => $physicalKey,
                'logical_ids' => $logicalIds,
                'message' => $message,
            ];
        }

        return $groups;
    }

    /**
     * @param list<PhysicalBackupJob> $jobs
     * @return list<array{id: string, upn: string, name: string}>
     */
    public function runnableJobsToBatchUsers(array $jobs): array
    {
        $users = [];
        foreach ($jobs as $job) {
            if (!$job->isRunnable()) {
                continue;
            }
            if (!in_array($job->resourceType(), [TenantResource::TYPE_USER, TenantResource::TYPE_MAILBOX], true)) {
                continue;
            }
            $graphId = $job->graphId();
            if ($graphId === '') {
                continue;
            }
            $users[] = [
                'id' => $graphId,
                'upn' => $job->email(),
                'name' => $job->displayName(),
            ];
        }

        return $users;
    }

    /**
     * @param list<array<string, mixed>> $runnable
     * @return list<array{id: string, upn: string, name: string}>
     */
    public function runnableToBatchUsers(array $runnable): array
    {
        $users = [];
        foreach ($runnable as $resource) {
            $graphId = (string) ($resource['graph_id'] ?? TenantResource::graphIdFromResourceId((string) ($resource['id'] ?? '')));
            if ($graphId === '') {
                continue;
            }
            $users[] = [
                'id' => $graphId,
                'upn' => (string) ($resource['email'] ?? ''),
                'name' => (string) ($resource['display_name'] ?? ''),
            ];
        }

        return $users;
    }
}
