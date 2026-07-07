<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * Customer-facing tenant inventory (e3 Cloud Backup).
 */
final class CustomerInventoryService
{
    private const PROGRESS_STALE_SECONDS = 600;
    private const RUNNING_STALE_SECONDS = 90;
    /** @return array<string, mixed> */
    public static function refreshForClient(int $clientId): array
    {
        $record = TenantRecordRepository::getPrimaryForClient($clientId);
        if ($record === null) {
            throw new \RuntimeException('Connect Microsoft 365 before refreshing inventory.');
        }

        return self::refreshForBackupUser($clientId, (int) ($record['backup_user_id'] ?? 0));
    }

    /** @return array<string, mixed> */
    public static function refreshForBackupUser(int $clientId, int $backupUserId): array
    {
        return InventoryBackgroundRefresh::start($clientId, $backupUserId);
    }

    /**
     * Synchronous refresh (admin CLI / tests). Customer UI uses InventoryBackgroundRefresh.
     *
     * @return array<string, mixed>
     */
    public static function refreshForBackupUserSync(int $clientId, int $backupUserId): array
    {
        $tenantRecordId = Ms365ConnectionGuard::tenantRecordIdForBackupUser($clientId, $backupUserId);

        try {
            TenantRecordRepository::ensureCloudStorageBucketForBackupUser($clientId, $backupUserId);
            $ctx = self::clientContext($clientId, $backupUserId);
            $inventory = new InventoryService(
                $ctx->graph,
                $ctx->storageLayout,
                new DiscoveryService($ctx->graph, $ctx->storageLayout),
            );
            $data = $inventory->refresh(lightweight: true);
            $counts = is_array($data['counts'] ?? null) ? $data['counts'] : [];
            $resources = is_array($data['resources'] ?? null) ? $data['resources'] : [];

            $warnings = is_array($data['warnings'] ?? null) ? $data['warnings'] : [];

            return [
                'fetched_at' => (string) ($data['fetched_at'] ?? ''),
                'counts' => $counts,
                'total_resources' => count($resources),
                'warnings' => array_values(array_map('strval', $warnings)),
            ];
        } catch (\Throwable $e) {
            Ms365ConnectionGuard::throwIfReconnectRequired($tenantRecordId, $e);
        }
    }

    /** @return array{has_inventory: bool, fetched_at: string, counts: array<string, int>, total_resources: int, warnings?: list<string>} */
    public static function summaryForClient(int $clientId): array
    {
        return self::summaryForBackupUser($clientId, 0);
    }

    /** @return array{has_inventory: bool, fetched_at: string, counts: array<string, int>, total_resources: int, warnings?: list<string>} */
    public static function summaryForBackupUser(int $clientId, int $backupUserId): array
    {
        $record = $backupUserId > 0
            ? TenantRecordRepository::getForBackupUser($clientId, $backupUserId)
            : TenantRecordRepository::getPrimaryForClient($clientId);
        if ($record === null || ($record['connection_status'] ?? '') !== 'connected') {
            return [
                'has_inventory' => false,
                'fetched_at' => '',
                'counts' => [],
                'total_resources' => 0,
            ];
        }

        try {
            $data = self::loadForBackupUser($clientId, $backupUserId);
        } catch (\Throwable $_) {
            $data = null;
        }

        if ($data === null || empty($data['resources'])) {
            return [
                'has_inventory' => false,
                'fetched_at' => (string) ($data['fetched_at'] ?? ''),
                'counts' => is_array($data['counts'] ?? null) ? $data['counts'] : [],
                'total_resources' => 0,
            ];
        }

        $counts = is_array($data['counts'] ?? null) ? $data['counts'] : [];
        $resources = is_array($data['resources'] ?? null) ? $data['resources'] : [];
        $warnings = is_array($data['warnings'] ?? null) ? $data['warnings'] : [];

        return [
            'has_inventory' => count($resources) > 0,
            'fetched_at' => (string) ($data['fetched_at'] ?? ''),
            'counts' => array_map(static fn ($v) => (int) $v, $counts),
            'total_resources' => count($resources),
            'warnings' => array_values(array_map('strval', $warnings)),
        ];
    }

    /**
     * Live discovery progress for inventory refresh UI (polled while refresh runs).
     *
     * @return array{
     *   phase: string,
     *   message: string,
     *   detail: string,
     *   counts: array<string, int>,
     *   refresh_in_progress: bool,
     * }
     */
    public static function discoveryProgressForBackupUser(int $clientId, int $backupUserId): array
    {
        $ctx = self::clientContext($clientId, $backupUserId);
        $discovery = new DiscoveryService($ctx->graph, $ctx->storageLayout);

        $counts = [];
        foreach (['users', 'sites', 'teams'] as $type) {
            $cached = $discovery->loadCached($type);
            if ($cached === null) {
                continue;
            }
            $counts[$type] = (int) ($cached['count'] ?? count(is_array($cached['value'] ?? null) ? $cached['value'] : []));
        }

        $progress = $ctx->storageLayout->readJson($ctx->storageLayout->discoveryDir() . '/progress.json');
        $phase = 'idle';
        $message = 'No inventory refresh in progress.';
        $detail = '';

        if (is_array($progress)) {
            $phase = (string) ($progress['phase'] ?? $phase);
            $message = trim((string) ($progress['message'] ?? ''));
            $detail = trim((string) ($progress['detail'] ?? ''));
            if (is_array($progress['counts'] ?? null)) {
                foreach ($progress['counts'] as $key => $value) {
                    $counts[(string) $key] = (int) $value;
                }
            }
        } elseif (isset($counts['teams'])) {
            $phase = 'details';
            $message = 'Building resource list…';
            $detail = 'This can take a few minutes on large tenants.';
        } elseif (isset($counts['sites'])) {
            $phase = 'teams';
            $message = 'Discovering Teams…';
        } elseif (isset($counts['users'])) {
            $phase = 'sites';
            $message = 'Discovering SharePoint sites…';
        }

        if ($detail !== '') {
            $detail = Ms365CustomerError::sanitizeRaw($detail);
        }

        if ($message === '') {
            $message = match ($phase) {
                'idle' => 'No inventory refresh in progress.',
                'running' => 'Inventory refresh started…',
                'sites' => 'Discovering SharePoint sites…',
                'teams' => 'Discovering Teams…',
                'groups' => 'Discovering Microsoft 365 groups…',
                'onedrive' => 'Checking OneDrive libraries…',
                'site_access' => 'Checking SharePoint site access…',
                'details' => 'Loading channels, Planner, and OneNote…',
                'assembling' => 'Finalizing inventory…',
                'complete' => 'Inventory ready',
                'error' => 'Inventory refresh failed',
                default => 'Discovering users and mailboxes…',
            };
        }

        $inProgress = is_array($progress)
            && !in_array((string) ($progress['phase'] ?? ''), ['complete', 'error'], true);

        $updatedAt = is_array($progress) ? strtotime((string) ($progress['updated_at'] ?? '')) : 0;
        if ($inProgress && $updatedAt > 0) {
            $staleSeconds = $phase === 'running' ? self::RUNNING_STALE_SECONDS : self::PROGRESS_STALE_SECONDS;
            if ((time() - $updatedAt) > $staleSeconds) {
                $wasRunning = $phase === 'running';
                $inProgress = false;
                $phase = 'error';
                $message = 'Inventory refresh failed';
                $detail = $wasRunning
                    ? 'Background worker did not start. Please try again.'
                    : 'Inventory refresh appears stalled. Please try again.';
            }
        }

        return [
            'phase' => $phase,
            'message' => $message,
            'detail' => $detail,
            'counts' => $counts,
            'display_counts' => is_array($progress['display_counts'] ?? null)
                ? array_map(static fn ($v) => (int) $v, $progress['display_counts'])
                : [],
            'refresh_in_progress' => $inProgress,
        ];
    }

    /**
     * Full inventory payload for resource picker UI.
     *
     * @return array{fetched_at: string, counts: array<string, int>, resources: list<array<string, mixed>>, warnings: list<string>}
     */
    public static function loadForBackupUser(int $clientId, int $backupUserId): array
    {
        $tenantRecordId = Ms365ConnectionGuard::tenantRecordIdForBackupUser($clientId, $backupUserId);

        try {
            $ctx = self::clientContext($clientId, $backupUserId);
            $inventory = new InventoryService(
                $ctx->graph,
                $ctx->storageLayout,
                new DiscoveryService($ctx->graph, $ctx->storageLayout),
            );
            $data = $inventory->load();
            if ($data === null) {
                return [
                    'fetched_at' => '',
                    'counts' => [],
                    'resources' => [],
                    'warnings' => [],
                ];
            }

            $resources = is_array($data['resources'] ?? null) ? array_values($data['resources']) : [];
            $relationships = is_array($data['relationships'] ?? null) ? $data['relationships'] : [];
            $resources = TenantResource::enrichSharePointDisplayMetadata($resources, $relationships);
            foreach ($resources as $i => $resource) {
                if (!is_array($resource)) {
                    continue;
                }
                $type = (string) ($resource['resource_type'] ?? '');
                $resources[$i]['badge_label'] = TenantResource::badgeLabel($type);
                $resources[$i]['capability_chips'] = TenantResource::capabilityChips($type);
                if ($type === TenantResource::TYPE_SHAREPOINT_SITE) {
                    $resources[$i] = array_merge($resource, TenantResource::siteSelectability($resource));
                }
            }

            $counts = is_array($data['counts'] ?? null) ? $data['counts'] : [];
            $warnings = is_array($data['warnings'] ?? null) ? $data['warnings'] : [];

            return [
                'fetched_at' => (string) ($data['fetched_at'] ?? ''),
                'counts' => array_map(static fn ($v) => (int) $v, $counts),
                'display_counts' => is_array($data['display_counts'] ?? null)
                    ? array_map(static fn ($v) => (int) $v, $data['display_counts'])
                    : TenantResource::displayCounts($resources),
                'resources' => $resources,
                'warnings' => array_values(array_map('strval', $warnings)),
            ];
        } catch (\Throwable $e) {
            Ms365ConnectionGuard::throwIfReconnectRequired($tenantRecordId, $e);
        }
    }

    public static function clientContextForRefresh(int $clientId, int $backupUserId): RunTenantContext
    {
        return self::clientContext($clientId, $backupUserId);
    }

    private static function clientContext(int $clientId, int $backupUserId): RunTenantContext
    {
        $record = $backupUserId > 0
            ? TenantRecordRepository::getForBackupUser($clientId, $backupUserId)
            : TenantRecordRepository::getPrimaryForClient($clientId);
        if ($record === null || ($record['connection_status'] ?? '') !== 'connected') {
            throw new \RuntimeException('Connect Microsoft 365 before refreshing inventory.');
        }

        return RunTenantContext::forClientRecord($record);
    }
}
