<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * Splits large physical backup jobs into claimable shards for the Go worker fleet.
 */
final class ResourceShardPlanner
{
    /**
     * @param array<string, PhysicalBackupJob> $physicalJobs keyed by physical_key
     * @param array<string, array<string, mixed>> $byId inventory resources by id
     * @return array<string, PhysicalBackupJob>
     */
    public function expand(array $physicalJobs, array $byId): array
    {
        if (!Ms365EngineConfig::shardingEnabled()) {
            return $physicalJobs;
        }

        $physicalJobs = $this->expandSharePointToDrives($physicalJobs);
        $physicalJobs = $this->expandSharePointLists($physicalJobs);

        $threshold = Ms365EngineConfig::shardThresholdBytes();
        $target = Ms365EngineConfig::shardTargetBytes();
        $itemThreshold = Ms365EngineConfig::shardItemThreshold();
        $itemTarget = Ms365EngineConfig::shardTargetItems();
        if ($threshold <= 0 || $target <= 0) {
            return $physicalJobs;
        }

        $expanded = [];
        foreach ($physicalJobs as $key => $job) {
            if (!$job->isRunnable()) {
                $expanded[$key] = $job;
                continue;
            }

            $shards = $this->shardJob($job, $byId, $threshold, $target, $itemThreshold, $itemTarget);
            if ($shards === []) {
                $expanded[$key] = $job;
                continue;
            }

            foreach ($shards as $shardJob) {
                $expanded[$shardJob->physicalKey] = $shardJob;
            }
        }

        return $expanded;
    }

    /**
     * When sharding is enabled, split SharePoint Files scope into one job per document library.
     *
     * @param array<string, PhysicalBackupJob> $physicalJobs
     * @return array<string, PhysicalBackupJob>
     */
    private function expandSharePointToDrives(array $physicalJobs): array
    {
        $expanded = [];
        foreach ($physicalJobs as $key => $job) {
            if (!$this->shouldExpandSharePointSite($job)) {
                $expanded[$key] = $job;
                continue;
            }

            $siteId = $job->graphId();
            $drives = $this->sharePointDrivesForJob($job);
            if ($drives === []) {
                $expanded[$key] = $job;
                continue;
            }

            $siteKey = 'site:' . $siteId;
            foreach ($drives as $drive) {
                if (!is_array($drive)) {
                    continue;
                }
                $driveId = trim((string) ($drive['id'] ?? ''));
                if ($driveId === '') {
                    continue;
                }
                $driveKey = 'drive:' . $driveId;
                $driveResource = $job->primaryResource;
                $meta = is_array($driveResource['meta'] ?? null) ? $driveResource['meta'] : [];
                $meta['drive_id'] = $driveId;
                $meta['site_id'] = $siteId;
                $meta['size_bytes'] = max(0, (int) ($drive['size_bytes'] ?? 0));
                $meta['item_count'] = max(0, (int) ($drive['item_count'] ?? 0));
                $meta['display_name'] = (string) ($drive['name'] ?? $driveId);
                $driveResource['meta'] = $meta;

                $driveScope = $this->sharePointFilesOnlyScope($job->scope);
                $expanded[$driveKey] = new PhysicalBackupJob(
                    $driveKey,
                    $driveResource,
                    $job->logicalSources,
                    $driveScope,
                    $job->engineStatus,
                    $job->deferReason,
                    $siteKey,
                );
            }

            if ($job->scope->isEnabled(BackupScope::LISTS)) {
                $listsScope = $this->sharePointListsOnlyScope($job->scope);
                $expanded[$siteKey] = new PhysicalBackupJob(
                    $siteKey,
                    $job->primaryResource,
                    $job->logicalSources,
                    $listsScope,
                    $job->engineStatus,
                    $job->deferReason,
                );
            }
        }

        return $expanded;
    }

    /**
     * Emit dedicated list:{listId} jobs for large lists; time-range shards for whale lists.
     *
     * @param array<string, PhysicalBackupJob> $physicalJobs
     * @return array<string, PhysicalBackupJob>
     */
    private function expandSharePointLists(array $physicalJobs): array
    {
        $jobThreshold = Ms365EngineConfig::listJobItemThreshold();
        $shardThreshold = Ms365EngineConfig::listShardItemThreshold();
        $shardTarget = Ms365EngineConfig::listShardTargetItems();
        $shardMax = Ms365EngineConfig::listShardMaxCount();

        $expanded = [];
        foreach ($physicalJobs as $key => $job) {
            if (!$this->shouldExpandSharePointLists($job)) {
                $expanded[$key] = $job;
                continue;
            }

            $siteId = $job->graphId();
            $siteKey = 'site:' . $siteId;
            $lists = $this->sharePointListsForJob($job);
            $excluded = [];
            $listJobs = [];

            foreach ($lists as $list) {
                if (!is_array($list)) {
                    continue;
                }
                $listId = trim((string) ($list['id'] ?? ''));
                if ($listId === '') {
                    continue;
                }
                $itemCount = $list['item_count'] ?? null;
                if ($itemCount === null || (int) $itemCount < $jobThreshold) {
                    continue;
                }
                $count = (int) $itemCount;
                $excluded[] = $listId;
                $listKey = 'list:' . $listId;
                $listResource = $job->primaryResource;
                $meta = is_array($listResource['meta'] ?? null) ? $listResource['meta'] : [];
                $meta['list_id'] = $listId;
                $meta['site_id'] = $siteId;
                $meta['item_count'] = $count;
                $meta['display_name'] = (string) ($list['display_name'] ?? $listId);
                $listResource['meta'] = $meta;
                $listsScope = $this->sharePointListsOnlyScope($job->scope);

                if ($count >= $shardThreshold) {
                    $ranges = ListShardRangeHelper::rangesForItemCount($count, $shardTarget, $shardMax);
                    $total = count($ranges);
                    foreach ($ranges as $index => $range) {
                        $shardKey = PhysicalKeyHelper::shardKey($listKey, $index);
                        $shardMeta = $meta;
                        $shardMeta['shard_kind'] = 'list_created_range';
                        $shardMeta['shard_segment'] = ListShardRangeHelper::segmentForRange(
                            (string) ($range['start'] ?? ''),
                            (string) ($range['end'] ?? ''),
                        );
                        $shardResource = $listResource;
                        $shardResource['meta'] = $shardMeta;
                        $listJobs[$shardKey] = new PhysicalBackupJob(
                            $shardKey,
                            $shardResource,
                            $job->logicalSources,
                            $listsScope,
                            $job->engineStatus,
                            $job->deferReason,
                            $listKey,
                            $index,
                            $total,
                        );
                    }
                } else {
                    $listJobs[$listKey] = new PhysicalBackupJob(
                        $listKey,
                        $listResource,
                        $job->logicalSources,
                        $listsScope,
                        $job->engineStatus,
                        $job->deferReason,
                        $siteKey,
                    );
                }
            }

            if ($excluded !== []) {
                $siteResource = $job->primaryResource;
                $meta = is_array($siteResource['meta'] ?? null) ? $siteResource['meta'] : [];
                $meta['excluded_list_ids'] = $excluded;
                $siteResource['meta'] = $meta;
                $job = new PhysicalBackupJob(
                    $job->physicalKey,
                    $siteResource,
                    $job->logicalSources,
                    $job->scope,
                    $job->engineStatus,
                    $job->deferReason,
                    $job->parentPhysicalKey,
                    $job->shardIndex,
                    $job->shardTotal,
                );
            }

            $expanded[$key] = $job;
            foreach ($listJobs as $listKey => $listJob) {
                $expanded[$listKey] = $listJob;
            }
        }

        return $expanded;
    }

    private function shouldExpandSharePointLists(PhysicalBackupJob $job): bool
    {
        if (!Ms365EngineConfig::shardingEnabled() || !$job->isRunnable()) {
            return false;
        }
        $baseKey = $job->physicalKey;
        if (!str_starts_with($baseKey, 'site:') || PhysicalKeyHelper::isSharded($baseKey)) {
            return false;
        }

        return $job->scope->isEnabled(BackupScope::LISTS);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sharePointListsForJob(PhysicalBackupJob $job): array
    {
        $meta = is_array($job->primaryResource['meta'] ?? null) ? $job->primaryResource['meta'] : [];
        $lists = $meta['lists'] ?? [];
        if (!is_array($lists)) {
            return [];
        }
        $out = [];
        foreach ($lists as $list) {
            if (is_array($list) && trim((string) ($list['id'] ?? '')) !== '') {
                $out[] = $list;
            }
        }

        return $out;
    }

    private function shouldExpandSharePointSite(PhysicalBackupJob $job): bool
    {
        if (!$job->isRunnable()) {
            return false;
        }
        $baseKey = $job->physicalKey;
        if (!str_starts_with($baseKey, 'site:') || PhysicalKeyHelper::isSharded($baseKey)) {
            return false;
        }

        return $job->scope->isEnabled(BackupScope::FILES);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sharePointDrivesForJob(PhysicalBackupJob $job): array
    {
        $meta = is_array($job->primaryResource['meta'] ?? null) ? $job->primaryResource['meta'] : [];
        $drives = $meta['drives'] ?? [];
        if (!is_array($drives)) {
            return [];
        }
        $out = [];
        foreach ($drives as $drive) {
            if (is_array($drive) && trim((string) ($drive['id'] ?? '')) !== '') {
                $out[] = $drive;
            }
        }

        return $out;
    }

    private function sharePointFilesOnlyScope(BackupScope $siteScope): BackupScope
    {
        $flags = $siteScope->toArray();
        $flags[BackupScope::FILES] = true;
        $flags[BackupScope::LISTS] = false;

        return new BackupScope($flags);
    }

    private function sharePointListsOnlyScope(BackupScope $siteScope): BackupScope
    {
        $flags = $siteScope->toArray();
        $flags[BackupScope::FILES] = false;
        $flags[BackupScope::LISTS] = true;

        return new BackupScope($flags);
    }

    /**
     * @param array<string, array<string, mixed>> $byId
     * @return list<PhysicalBackupJob>
     */
    private function shardJob(
        PhysicalBackupJob $job,
        array $byId,
        int $threshold,
        int $target,
        int $itemThreshold,
        int $itemTarget,
    ): array {
        $baseKey = $job->physicalKey;
        if (PhysicalKeyHelper::isSharded($baseKey)) {
            return [];
        }
        if (str_starts_with($baseKey, 'list:')) {
            return [];
        }

        $resource = $job->primaryResource;
        $type = (string) ($resource['resource_type'] ?? '');
        $sizeBytes = PhysicalKeyHelper::sizeBytesHint($resource);
        $itemCount = PhysicalKeyHelper::itemCountHint($resource);

        if (in_array($type, [TenantResource::TYPE_USER_ONEDRIVE], true)
            || str_starts_with($baseKey, 'onedrive:')) {
            return $this->sizeRangeShards($job, $sizeBytes, $itemCount, $threshold, $target, $itemThreshold, $itemTarget);
        }

        if (str_starts_with($baseKey, 'drive:')) {
            $siteId = trim((string) ($resource['meta']['site_id'] ?? ''));
            if ($siteId !== '') {
                return $this->sizeRangeShards($job, $sizeBytes, $itemCount, $threshold, $target, $itemThreshold, $itemTarget);
            }

            return $this->sizeRangeShards($job, $sizeBytes, $itemCount, $threshold, $target, $itemThreshold, $itemTarget);
        }

        if ($type === TenantResource::TYPE_SHAREPOINT_SITE || str_starts_with($baseKey, 'site:')) {
            return $this->sizeRangeShards($job, $sizeBytes, $itemCount, $threshold, $target, $itemThreshold, $itemTarget);
        }

        if (in_array($type, [TenantResource::TYPE_USER, TenantResource::TYPE_MAILBOX], true)
            || str_starts_with($baseKey, 'user:')) {
            return $this->mailFolderShards($job, $resource, $byId, $threshold);
        }

        return [];
    }

    /**
     * @return list<PhysicalBackupJob>
     */
    private function sizeRangeShards(
        PhysicalBackupJob $job,
        int $sizeBytes,
        int $itemCount,
        int $threshold,
        int $target,
        int $itemThreshold,
        int $itemTarget,
    ): array {
        $byteShard = $sizeBytes >= $threshold;
        $itemShard = $itemCount >= $itemThreshold;
        if (!$byteShard && !$itemShard) {
            return [];
        }

        $byBytes = $byteShard ? max(2, (int) ceil($sizeBytes / $target)) : 1;
        $byItems = $itemShard ? max(2, (int) ceil($itemCount / $itemTarget)) : 1;
        $shardCount = max($byBytes, $byItems);
        $shardCount = min($shardCount, Ms365EngineConfig::shardMaxCount());

        $parentKey = $job->physicalKey;
        $out = [];
        for ($i = 0; $i < $shardCount; $i++) {
            $shardKey = PhysicalKeyHelper::shardKey($job->physicalKey, $i);
            $out[] = new PhysicalBackupJob(
                $shardKey,
                $job->primaryResource,
                $job->logicalSources,
                $job->scope,
                $job->engineStatus,
                $job->deferReason,
                $parentKey,
                $i,
                $shardCount,
            );
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $resource
     * @param array<string, array<string, mixed>> $byId
     * @return list<PhysicalBackupJob>
     */
    private function mailFolderShards(PhysicalBackupJob $job, array $resource, array $byId, int $threshold): array
    {
        if (!$job->scope->isEnabled(BackupScope::MAIL)) {
            return [];
        }

        $mailFolders = [];
        $sizeBytes = PhysicalKeyHelper::sizeBytesHint($resource);
        $meta = is_array($resource['meta'] ?? null) ? $resource['meta'] : [];
        if (is_array($meta['mail_folders'] ?? null)) {
            foreach ($meta['mail_folders'] as $folder) {
                if (!is_array($folder)) {
                    continue;
                }
                $folderId = trim((string) ($folder['id'] ?? ''));
                if ($folderId === '') {
                    continue;
                }
                $mailFolders[] = [
                    'id' => $folderId,
                    'size_bytes' => max(0, (int) ($folder['size_bytes'] ?? $folder['total_item_size'] ?? 0)),
                ];
            }
        }

        if ($mailFolders === [] && $sizeBytes >= $threshold) {
            $defaultFolders = ['inbox', 'sentitems', 'archive'];
            foreach ($defaultFolders as $folderId) {
                $mailFolders[] = ['id' => $folderId, 'size_bytes' => (int) floor($sizeBytes / count($defaultFolders))];
            }
        }

        if ($mailFolders === []) {
            return [];
        }

        $largeFolders = array_values(array_filter(
            $mailFolders,
            static fn (array $f) => ($f['size_bytes'] ?? 0) >= $threshold
                || ($sizeBytes >= $threshold && count($mailFolders) > 1),
        ));

        if ($largeFolders === [] && $sizeBytes < $threshold) {
            return [];
        }

        if ($largeFolders === []) {
            $largeFolders = $mailFolders;
        }

        $out = [];
        $total = count($largeFolders);
        foreach ($largeFolders as $index => $folder) {
            $folderId = (string) ($folder['id'] ?? '');
            if ($folderId === '') {
                continue;
            }
            $shardKey = PhysicalKeyHelper::mailFolderKey($job->physicalKey, $folderId);
            $out[] = new PhysicalBackupJob(
                $shardKey,
                $job->primaryResource,
                $job->logicalSources,
                $job->scope,
                $job->engineStatus,
                $job->deferReason,
                $job->physicalKey,
                $index,
                $total,
            );
        }

        return $out;
    }
}
