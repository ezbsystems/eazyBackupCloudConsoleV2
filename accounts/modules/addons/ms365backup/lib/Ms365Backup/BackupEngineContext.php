<?php
declare(strict_types=1);

namespace Ms365Backup;

final class BackupEngineContext
{
    public function __construct(
        public readonly string $runId,
        public readonly PhysicalBackupJob $job,
        public readonly BackupScope $scope,
        public readonly GraphClient $graph,
        public readonly StorageLayout $storage,
        public readonly ProgressLogger $logger,
        public readonly RunCancellation $cancellation,
        public readonly string $runDir,
    ) {
    }

    public function userGraphId(): string
    {
        if (in_array($this->job->resourceType(), [TenantResource::TYPE_USER, TenantResource::TYPE_MAILBOX], true)) {
            return $this->job->graphId();
        }

        return '';
    }

    public function driveId(): string
    {
        if ($this->job->resourceType() !== TenantResource::TYPE_USER_ONEDRIVE) {
            return '';
        }

        $meta = is_array($this->job->primaryResource['meta'] ?? null)
            ? $this->job->primaryResource['meta']
            : [];

        return (string) ($meta['drive_id'] ?? $this->job->graphId());
    }

    public function ownerUserId(): string
    {
        if ($this->job->resourceType() !== TenantResource::TYPE_USER_ONEDRIVE) {
            return '';
        }

        $meta = is_array($this->job->primaryResource['meta'] ?? null)
            ? $this->job->primaryResource['meta']
            : [];

        $owner = (string) ($meta['owner_user_id'] ?? '');
        if ($owner !== '') {
            return $owner;
        }

        $parentId = (string) ($this->job->primaryResource['parent_id'] ?? '');

        return $parentId !== '' ? TenantResource::graphIdFromResourceId($parentId) : '';
    }

    public function siteGraphId(): string
    {
        if ($this->job->resourceType() !== TenantResource::TYPE_SHAREPOINT_SITE) {
            return '';
        }

        return $this->job->graphId();
    }

    public function teamGroupId(): string
    {
        if ($this->job->resourceType() === TenantResource::TYPE_TEAM) {
            $meta = is_array($this->job->primaryResource['meta'] ?? null)
                ? $this->job->primaryResource['meta']
                : [];

            return (string) ($meta['group_id'] ?? $this->job->graphId());
        }

        if ($this->job->resourceType() === TenantResource::TYPE_TEAM_CHANNEL) {
            $parsed = GraphTeamPaths::parseChannelResourceId($this->job->resourceId());
            if ($parsed['group_id'] !== '') {
                return $parsed['group_id'];
            }

            return GraphTeamPaths::groupIdFromResource($this->job->primaryResource);
        }

        return '';
    }

    /**
     * @return list<string>|null null = all channels; non-null = restrict to these channel IDs
     */
    public function channelIdsFilter(): ?array
    {
        if ($this->job->resourceType() === TenantResource::TYPE_TEAM) {
            return null;
        }

        if ($this->job->resourceType() !== TenantResource::TYPE_TEAM_CHANNEL) {
            return null;
        }

        $channelId = GraphTeamPaths::channelIdFromResource($this->job->primaryResource);
        if ($channelId === '') {
            $composite = $this->job->graphId();
            $channelId = StorageLayout::channelIdFromComposite($composite);
        }

        return $channelId !== '' ? [$channelId] : null;
    }
}
