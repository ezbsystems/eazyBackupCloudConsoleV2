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

        $threshold = Ms365EngineConfig::shardThresholdBytes();
        $target = Ms365EngineConfig::shardTargetBytes();
        if ($threshold <= 0 || $target <= 0) {
            return $physicalJobs;
        }

        $expanded = [];
        foreach ($physicalJobs as $key => $job) {
            if (!$job->isRunnable()) {
                $expanded[$key] = $job;
                continue;
            }

            $shards = $this->shardJob($job, $byId, $threshold, $target);
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
     * @param array<string, array<string, mixed>> $byId
     * @return list<PhysicalBackupJob>
     */
    private function shardJob(PhysicalBackupJob $job, array $byId, int $threshold, int $target): array
    {
        $baseKey = $job->physicalKey;
        if (PhysicalKeyHelper::isSharded($baseKey)) {
            return [];
        }

        $resource = $job->primaryResource;
        $type = (string) ($resource['resource_type'] ?? '');
        $sizeBytes = PhysicalKeyHelper::sizeBytesHint($resource);

        if (in_array($type, [TenantResource::TYPE_USER_ONEDRIVE], true)
            || str_starts_with($baseKey, 'drive:')
            || str_starts_with($baseKey, 'onedrive:')) {
            return $this->sizeRangeShards($job, $sizeBytes, $threshold, $target);
        }

        if ($type === TenantResource::TYPE_SHAREPOINT_SITE || str_starts_with($baseKey, 'site:')) {
            return $this->sizeRangeShards($job, $sizeBytes, $threshold, $target);
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
    private function sizeRangeShards(PhysicalBackupJob $job, int $sizeBytes, int $threshold, int $target): array
    {
        if ($sizeBytes < $threshold) {
            return [];
        }

        $shardCount = max(2, (int) ceil($sizeBytes / $target));
        $shardCount = min($shardCount, Ms365EngineConfig::shardMaxCount());
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
                $job->physicalKey,
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
