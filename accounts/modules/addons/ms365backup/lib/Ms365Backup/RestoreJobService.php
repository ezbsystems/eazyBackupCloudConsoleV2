<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;

/**
 * Starts granular MS365 restore jobs (Kopia-only, async via worker queue).
 */
final class RestoreJobService
{
    /**
     * @param array<string, mixed> $selection
     * @return array{batch_run_id: string, restore_run_ids: list<string>}
     */
    public static function start(
        int $clientId,
        int $backupUserId,
        string $jobId,
        array $selection,
    ): array {
        $record = TenantRecordRepository::getForBackupUser($clientId, $backupUserId);
        if ($record === null) {
            $record = TenantRecordRepository::getPrimaryForClient($clientId);
        }
        if ($record === null) {
            throw new \RuntimeException('Microsoft 365 is not connected.');
        }

        $sourceBatchRunId = trim((string) ($selection['snapshot_batch_run_id'] ?? ''));
        if ($sourceBatchRunId === '') {
            throw new \RuntimeException('snapshot_batch_run_id is required.');
        }

        $items = $selection['items'] ?? [];
        if (!is_array($items) || $items === []) {
            throw new \RuntimeException('At least one item must be selected for restore.');
        }

        $items = self::expandAndValidateSelectionItems($sourceBatchRunId, $items, $record);

        $restoreMode = (string) ($selection['restore_mode'] ?? 'tenant');
        if ($restoreMode !== 'archive') {
            $restoreMode = 'tenant';
        }

        $destinationMode = Ms365RestoreDestinationResolver::normalizeDestinationMode(
            (string) ($selection['destination_mode'] ?? Ms365RestoreDestinationResolver::MODE_ORIGINAL)
        );

        $targets = $selection['targets'] ?? [];
        if (!is_array($targets)) {
            $targets = [];
        }

        $conflictPolicy = (string) ($selection['conflict_policy'] ?? 'skip_duplicates');
        $batchRunId = Ms365BatchRunRepository::createRestoreBatch($clientId, $jobId);

        if ($restoreMode === 'archive') {
            return self::startArchiveRestore(
                $clientId,
                $backupUserId,
                $record,
                $jobId,
                $batchRunId,
                $sourceBatchRunId,
                $items,
                $conflictPolicy,
            );
        }

        $inventoryResources = self::loadInventoryResourcesSafe($clientId, $backupUserId);

        if ($destinationMode === Ms365RestoreDestinationResolver::MODE_ORIGINAL) {
            $targets = Ms365RestoreDestinationResolver::deriveOriginalTargets($items, $inventoryResources);
            Ms365RestoreDestinationResolver::assertSelectionCompatible($items, $targets, $destinationMode, $inventoryResources);
        } else {
            if ($targets === []) {
                throw new \RuntimeException('Restore target is required.');
            }
            Ms365RestoreDestinationResolver::assertSelectionCompatible($items, $targets, $destinationMode, $inventoryResources);
        }

        $grouped = self::groupItemsByWorkload($items);
        $restoreRunIds = [];
        $primaryTarget = $targets[0];

        foreach ($grouped as $groupKey => $groupItems) {
            $manifestId = '';
            $childRunId = '';
            foreach ($groupItems as $gi) {
                if (trim((string) ($gi['manifest_id'] ?? '')) !== '') {
                    $manifestId = (string) $gi['manifest_id'];
                }
                if (trim((string) ($gi['child_run_id'] ?? '')) !== '') {
                    $childRunId = (string) $gi['child_run_id'];
                }
            }
            if ($manifestId === '' && $childRunId !== '') {
                $backupRun = BackupRunRepository::get($childRunId);
                $manifestId = (string) ($backupRun['manifest_id'] ?? '');
            }
            if ($manifestId === '') {
                continue;
            }

            $target = self::resolveTargetForGroup($groupKey, $targets, $primaryTarget);
            $restoreId = RestoreRunRepository::create([
                'tenant_record_id' => (int) $record['id'],
                'whmcs_client_id' => $clientId,
                'resource_type' => (string) ($target['resource_type'] ?? 'user'),
                'target_graph_id' => (string) ($target['graph_id'] ?? ''),
                'target_resource_id' => (string) ($target['resource_id'] ?? ''),
                'backup_run_id' => $childRunId !== '' ? $childRunId : null,
                'e3_batch_run_id' => $batchRunId,
                'source_batch_run_id' => $sourceBatchRunId,
                'source_manifest_id' => $manifestId,
                'selection_json' => [
                    'items' => $groupItems,
                    'targets' => $targets,
                    'destination_mode' => $destinationMode,
                    'conflict_policy' => $conflictPolicy,
                    'snapshot_batch_run_id' => $sourceBatchRunId,
                ],
                'conflict_policy' => $conflictPolicy,
                'items_total' => count($groupItems),
            ]);

            JobQueueRepository::enqueueRestore($restoreId);
            $restoreRunIds[] = $restoreId;
        }

        if ($restoreRunIds === []) {
            throw new \RuntimeException('No restorable workloads found in selection.');
        }

        return [
            'batch_run_id' => $batchRunId,
            'restore_run_ids' => $restoreRunIds,
        ];
    }

    /**
     * @param array<string, mixed> $record
     * @param list<array<string, mixed>> $items
     * @return array{batch_run_id: string, restore_run_ids: list<string>}
     */
    private static function startArchiveRestore(
        int $clientId,
        int $backupUserId,
        array $record,
        string $jobId,
        string $batchRunId,
        string $sourceBatchRunId,
        array $items,
        string $conflictPolicy,
    ): array {
        $manifestId = '';
        $childRunId = '';
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (trim((string) ($item['manifest_id'] ?? '')) !== '') {
                $manifestId = (string) $item['manifest_id'];
            }
            if (trim((string) ($item['child_run_id'] ?? '')) !== '') {
                $childRunId = (string) $item['child_run_id'];
            }
            if ($manifestId !== '') {
                break;
            }
        }
        if ($manifestId === '' && $childRunId !== '') {
            $backupRun = BackupRunRepository::get($childRunId);
            $manifestId = (string) ($backupRun['manifest_id'] ?? '');
        }
        if ($manifestId === '') {
            throw new \RuntimeException('No restorable workloads found in selection.');
        }

        $dest = Ms365JobDestinationService::resolveForJobId($jobId, $record);
        $archiveBucket = trim((string) ($dest['bucket'] ?? ''));

        Ms365ArchiveExportService::ensureLifecycleRule(
            $clientId,
            $backupUserId,
            $jobId,
            Ms365ArchiveExportService::archiveExportTtlDays(),
        );

        $createData = [
            'tenant_record_id' => (int) $record['id'],
            'whmcs_client_id' => $clientId,
            'resource_type' => '',
            'target_graph_id' => '',
            'target_resource_id' => '',
            'backup_run_id' => $childRunId !== '' ? $childRunId : null,
            'e3_batch_run_id' => $batchRunId,
            'source_batch_run_id' => $sourceBatchRunId,
            'source_manifest_id' => $manifestId,
            'restore_mode' => 'archive',
            'selection_json' => [
                'items' => $items,
                'targets' => [],
                'restore_mode' => 'archive',
                'conflict_policy' => $conflictPolicy,
                'snapshot_batch_run_id' => $sourceBatchRunId,
            ],
            'conflict_policy' => $conflictPolicy,
            'items_total' => count($items),
        ];
        if ($archiveBucket !== '') {
            $createData['archive_bucket'] = $archiveBucket;
        }

        $restoreId = RestoreRunRepository::create($createData);

        JobQueueRepository::enqueueRestore($restoreId);

        return [
            'batch_run_id' => $batchRunId,
            'restore_run_ids' => [$restoreId],
        ];
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return array<string, list<array<string, mixed>>>
     */
    private static function groupItemsByWorkload(array $items): array
    {
        $groups = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $key = (string) ($item['child_run_id'] ?? $item['manifest_id'] ?? 'default');
            $groups[$key][] = $item;
        }

        return $groups;
    }

    /**
     * @param array<string, mixed> $primary
     * @param list<array<string, mixed>> $targets
     * @return array<string, mixed>
     */
    private static function resolveTargetForGroup(string $groupKey, array $targets, array $primary): array
    {
        foreach ($targets as $target) {
            if (!is_array($target)) {
                continue;
            }
            if ((string) ($target['child_run_id'] ?? '') === $groupKey) {
                return $target;
            }
        }

        return $primary;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param array<string, mixed> $tenantRecord
     * @return list<array<string, mixed>>
     */
    public static function expandAndValidateSelectionItems(
        string $sourceBatchRunId,
        array $items,
        array $tenantRecord,
    ): array {
        $sourceBatchRunId = trim($sourceBatchRunId);
        if ($sourceBatchRunId === '') {
            throw new \RuntimeException('snapshot_batch_run_id is required.');
        }

        $expanded = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            foreach (self::expandSelectionItem($sourceBatchRunId, $item, $tenantRecord) as $bound) {
                $expanded[] = $bound;
            }
        }

        if ($expanded === []) {
            throw new \RuntimeException('At least one item must be selected for restore.');
        }

        return $expanded;
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, mixed> $tenantRecord
     * @return list<array<string, mixed>>
     */
    private static function expandSelectionItem(
        string $sourceBatchRunId,
        array $item,
        array $tenantRecord,
    ): array {
        $type = strtolower(trim((string) ($item['type'] ?? '')));
        $logicalPath = self::selectionLogicalPath($item);
        $sourceRefs = $item['source_refs'] ?? null;

        if (is_array($sourceRefs) && $sourceRefs !== []) {
            $childRun = self::childRunForItem($sourceBatchRunId, $item);
            $isFolder = $type === 'folder' || ($logicalPath !== '' && self::selectionPathPrefix($item) !== '');

            if ($isFolder) {
                $out = [];
                foreach ($sourceRefs as $ref) {
                    if (!is_array($ref)) {
                        throw new \RuntimeException('Invalid source reference in selection.');
                    }
                    if (!SharePointShardSourceResolver::isAuthorizedSourceRef(
                        $sourceBatchRunId,
                        $ref,
                        $childRun,
                        $tenantRecord,
                        $logicalPath,
                    )) {
                        throw new \RuntimeException('Restore selection contains an unauthorized source reference.');
                    }
                    $sourcePath = trim((string) ($ref['source_path'] ?? ''));
                    if ($sourcePath === '') {
                        throw new \RuntimeException('Restore selection is missing a source path.');
                    }
                    $out[] = [
                        'child_run_id' => (string) ($ref['child_run_id'] ?? ''),
                        'manifest_id' => (string) ($ref['manifest_id'] ?? ''),
                        'path' => '',
                        'path_prefix' => self::selectionPathPrefix($item),
                        'source_path' => $sourcePath,
                        'logical_path' => $logicalPath,
                        'type' => 'folder',
                    ];
                }

                return $out;
            }

            $primary = $sourceRefs[0];
            if (!is_array($primary)) {
                throw new \RuntimeException('Invalid source reference in selection.');
            }
            if (!SharePointShardSourceResolver::isAuthorizedSourceRef(
                $sourceBatchRunId,
                $primary,
                $childRun,
                $tenantRecord,
                $logicalPath,
            )) {
                throw new \RuntimeException('Restore selection contains an unauthorized source reference.');
            }
            $sourcePath = trim((string) ($primary['source_path'] ?? ''));
            if ($sourcePath === '') {
                $sourcePath = $logicalPath;
            }

            return [[
                'child_run_id' => (string) ($primary['child_run_id'] ?? $item['child_run_id'] ?? ''),
                'manifest_id' => (string) ($primary['manifest_id'] ?? $item['manifest_id'] ?? ''),
                'path' => trim((string) ($item['path'] ?? '')),
                'path_prefix' => '',
                'source_path' => $sourcePath,
                'logical_path' => $logicalPath !== '' ? $logicalPath : trim((string) ($item['path'] ?? '')),
                'type' => $type !== '' ? $type : 'file',
            ]];
        }

        $bound = [
            'child_run_id' => (string) ($item['child_run_id'] ?? ''),
            'manifest_id' => (string) ($item['manifest_id'] ?? ''),
            'path' => trim((string) ($item['path'] ?? '')),
            'path_prefix' => self::selectionPathPrefix($item),
            'type' => $type !== '' ? $type : ($item['path'] !== '' ? 'file' : 'folder'),
        ];
        $logical = $logicalPath;
        if ($logical !== '') {
            $bound['logical_path'] = $logical;
            $bound['source_path'] = $logical;
        }

        return [$bound];
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function selectionLogicalPath(array $item): string
    {
        $path = trim((string) ($item['path'] ?? ''));
        if ($path !== '') {
            return trim($path, '/');
        }

        return trim((string) ($item['path_prefix'] ?? ''), '/');
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function selectionPathPrefix(array $item): string
    {
        $type = strtolower(trim((string) ($item['type'] ?? '')));
        if ($type === 'file') {
            return '';
        }
        $prefix = trim((string) ($item['path_prefix'] ?? ''));
        if ($prefix !== '' && !str_ends_with($prefix, '/')) {
            $prefix .= '/';
        }
        if ($prefix === '') {
            $path = trim((string) ($item['path'] ?? ''));
            if ($path !== '' && str_ends_with($path, '/')) {
                return $path;
            }
        }

        return $prefix;
    }

    /**
     * @param array<string, mixed> $item
     * @return ?array<string, mixed>
     */
    private static function childRunForItem(string $sourceBatchRunId, array $item): ?array
    {
        $childRunId = trim((string) ($item['child_run_id'] ?? ''));
        if ($childRunId === '') {
            return null;
        }
        foreach (Ms365BatchRunRepository::getChildrenForBatch($sourceBatchRunId) as $child) {
            if ((string) ($child['id'] ?? '') === $childRunId) {
                return $child;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function loadInventoryResourcesSafe(int $clientId, int $backupUserId): array
    {
        try {
            $data = CustomerInventoryService::loadForBackupUser($clientId, $backupUserId);
            $resources = $data['resources'] ?? [];

            return is_array($resources) ? $resources : [];
        } catch (\Throwable $_) {
            return [];
        }
    }
}
