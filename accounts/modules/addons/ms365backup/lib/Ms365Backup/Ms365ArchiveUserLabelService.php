<?php

declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;

/**
 * Resolves Azure user GUIDs to UPN/email for archive zip folder names.
 */
final class Ms365ArchiveUserLabelService
{
    /**
     * @param list<array<string, mixed>> $restoreItems
     * @param list<array<string, mixed>> $restoreTargets
     * @param array<string, mixed> $run
     * @return array<string, string> guid => UPN
     */
    public static function buildUserLabels(
        array $restoreItems,
        array $restoreTargets,
        array $run,
        int $tenantRecordId,
        int $clientId,
    ): array {
        $userIds = self::collectUserIds($restoreItems, $restoreTargets, $run);
        if ($userIds === []) {
            return [];
        }

        $labels = [];
        $unresolved = [];
        foreach ($userIds as $userId) {
            $upn = self::lookupUpnFromBackupRuns($userId);
            if ($upn !== '') {
                $labels[self::normalizeGuidKey($userId)] = $upn;
                continue;
            }
            $unresolved[] = $userId;
        }

        if ($unresolved !== []) {
            $inventoryLabels = self::lookupUpnsFromInventory($unresolved, $clientId, $tenantRecordId);
            foreach ($inventoryLabels as $guid => $upn) {
                if ($upn !== '') {
                    $labels[self::normalizeGuidKey($guid)] = $upn;
                }
            }
        }

        return $labels;
    }

    /**
     * @param list<array<string, mixed>> $restoreItems
     * @param list<array<string, mixed>> $restoreTargets
     * @param array<string, mixed> $run
     * @return list<string>
     */
    public static function collectUserIds(array $restoreItems, array $restoreTargets, array $run): array
    {
        $ids = [];

        foreach ($restoreItems as $item) {
            if (!is_array($item)) {
                continue;
            }
            foreach (['path', 'source_path', 'logical_path', 'path_prefix'] as $field) {
                self::extractUserIdsFromPath((string) ($item[$field] ?? ''), $ids);
            }
            $childRunId = trim((string) ($item['child_run_id'] ?? ''));
            if ($childRunId !== '') {
                self::addGraphIdFromBackupRun($childRunId, $ids);
            }
        }

        foreach ($restoreTargets as $target) {
            if (!is_array($target)) {
                continue;
            }
            $graphId = trim((string) ($target['graph_id'] ?? ''));
            if ($graphId !== '') {
                $ids[] = $graphId;
            }
        }

        $runGraphId = trim((string) ($run['target_graph_id'] ?? ''));
        if ($runGraphId !== '') {
            $ids[] = $runGraphId;
        }

        $backupRunId = trim((string) ($run['backup_run_id'] ?? ''));
        if ($backupRunId !== '') {
            self::addGraphIdFromBackupRun($backupRunId, $ids);
        }

        $normalized = [];
        foreach ($ids as $id) {
            $key = self::normalizeGuidKey($id);
            if ($key !== '') {
                $normalized[$key] = $id;
            }
        }

        return array_values($normalized);
    }

    public static function lookupUpnFromBackupRuns(string $graphId): string
    {
        $graphId = trim($graphId);
        if ($graphId === '' || !Capsule::schema()->hasTable('ms365_backup_runs')) {
            return '';
        }

        $row = Capsule::table('ms365_backup_runs')
            ->where('graph_id', $graphId)
            ->orderByDesc('created_at')
            ->first(['user_upn']);
        if ($row === null) {
            $row = Capsule::table('ms365_backup_runs')
                ->where('resource_id', 'like', '%' . $graphId . '%')
                ->orderByDesc('created_at')
                ->first(['user_upn']);
        }
        if ($row === null) {
            return '';
        }

        return trim((string) ($row->user_upn ?? ''));
    }

    /**
     * @param list<string> $graphIds
     * @return array<string, string>
     */
    public static function lookupUpnsFromInventory(array $graphIds, int $clientId, int $tenantRecordId): array
    {
        if ($graphIds === [] || $clientId <= 0) {
            return [];
        }

        $record = TenantRecordRepository::getById($tenantRecordId);
        if ($record === null) {
            return [];
        }

        $backupUserId = (int) ($record['backup_user_id'] ?? 0);
        if ($backupUserId <= 0) {
            return [];
        }

        try {
            $data = CustomerInventoryService::loadForBackupUser($clientId, $backupUserId);
        } catch (\Throwable $_) {
            return [];
        }

        $resources = is_array($data['resources'] ?? null) ? $data['resources'] : [];
        $wanted = [];
        foreach ($graphIds as $graphId) {
            $wanted[self::normalizeGuidKey($graphId)] = true;
        }

        $out = [];
        foreach ($resources as $resource) {
            if (!is_array($resource)) {
                continue;
            }
            $type = (string) ($resource['resource_type'] ?? '');
            if (!in_array($type, [TenantResource::TYPE_USER, TenantResource::TYPE_MAILBOX, TenantResource::TYPE_USER_ONEDRIVE], true)) {
                continue;
            }
            $graphId = trim((string) ($resource['graph_id'] ?? ''));
            $key = self::normalizeGuidKey($graphId);
            if ($key === '' || !isset($wanted[$key])) {
                continue;
            }
            $meta = is_array($resource['meta'] ?? null) ? $resource['meta'] : [];
            $email = trim((string) ($resource['email'] ?? $meta['email'] ?? $meta['mail'] ?? ''));
            if ($email !== '') {
                $out[$key] = $email;
            }
        }

        return $out;
    }

    /**
     * @param array<string, bool> $ids
     */
    private static function extractUserIdsFromPath(string $path, array &$ids): void
    {
        $path = trim($path);
        if ($path === '') {
            return;
        }

        if (preg_match_all('#/users/([0-9a-fA-F-]{36})/#', $path, $matches) !== false) {
            foreach ($matches[1] as $match) {
                $ids[] = $match;
            }
        }
        if (preg_match('#/users/([0-9a-fA-F-]{36})$#', $path, $match) === 1) {
            $ids[] = $match[1];
        }
    }

    /** @param array<string, bool> $ids */
    private static function addGraphIdFromBackupRun(string $runId, array &$ids): void
    {
        $backupRun = BackupRunRepository::get($runId);
        if (!is_array($backupRun)) {
            return;
        }
        $graphId = trim((string) ($backupRun['graph_id'] ?? ''));
        if ($graphId !== '') {
            $ids[] = $graphId;
        }
    }

    public static function normalizeGuidKey(string $guid): string
    {
        $guid = strtolower(trim($guid));
        if ($guid === '') {
            return '';
        }
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $guid) !== 1) {
            return '';
        }

        return $guid;
    }
}
