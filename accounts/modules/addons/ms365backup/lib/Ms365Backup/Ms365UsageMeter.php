<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;

/**
 * Computes Protected Objects and OneDrive overage from inventory + MS365 job selections.
 */
final class Ms365UsageMeter
{
    /** @var list<string> */
    private const PERSONAL_TYPES = [
        TenantResource::TYPE_USER,
        TenantResource::TYPE_MAILBOX,
        TenantResource::TYPE_USER_ONEDRIVE,
    ];

    /**
     * @param array<string, mixed> $inventory
     * @param list<string> $selectedIds
     * @param array<string, array<string, bool>> $scopeOverrides
     * @return array{
     *   protected_users: int,
     *   onedrive_overage_gib: int,
     *   onedrive_users: list<array<string, mixed>>,
     *   inventory_stale: bool,
     *   member_resolution_pending: bool,
     *   breakdown: list<array{resource_id: string, label: string, member_count: int}>
     * }
     */
    public static function measureSelection(
        array $inventory,
        array $selectedIds,
        array $scopeOverrides,
        ?DiscoveryService $discovery = null,
    ): array {
        $empty = [
            'protected_users' => 0,
            'onedrive_overage_gib' => 0,
            'onedrive_users' => [],
            'inventory_stale' => false,
            'member_resolution_pending' => false,
            'breakdown' => [],
        ];
        if ($selectedIds === []) {
            return $empty;
        }

        $resolution = ProtectedUserResolver::resolve($inventory, $selectedIds, $scopeOverrides, $discovery);
        $protectedAzureIds = array_fill_keys($resolution['protected_azure_ids'], true);
        $byId = self::resourcesById($inventory);
        $selection = [
            'selected_ids' => CustomerSelectionCodec::normalizeIds($selectedIds),
            'scope_overrides' => $scopeOverrides,
        ];
        $includedBytes = Ms365BillingConfig::onedriveIncludedBytes();
        $onedriveUsers = [];

        foreach ($selection['selected_ids'] as $resourceId) {
            $resource = $byId[$resourceId] ?? null;
            if ($resource === null) {
                continue;
            }
            $type = (string) ($resource['resource_type'] ?? '');
            if (!in_array($type, self::PERSONAL_TYPES, true)) {
                continue;
            }
            if (!self::hasEnabledPersonalScope($type, $resourceId, $scopeOverrides, $inventory)) {
                continue;
            }

            $azureUserId = self::azureUserIdForResource($resource, $type);
            if ($azureUserId === '' || !isset($protectedAzureIds[$azureUserId])) {
                continue;
            }

            if (self::onedriveProtectedForUser($azureUserId, $resource, $type, $selection, $byId, $inventory)) {
                $drive = self::findOneDriveResource($azureUserId, $byId);
                $usedBytes = (int) ($drive['meta']['size_bytes'] ?? 0);
                $overageBytes = max(0, $usedBytes - $includedBytes);
                $onedriveUsers[$azureUserId] = [
                    'azure_user_id' => $azureUserId,
                    'upn' => (string) ($drive['email'] ?? $resource['email'] ?? ''),
                    'display_name' => (string) ($drive['display_name'] ?? $resource['display_name'] ?? ''),
                    'drive_id' => (string) ($drive['meta']['drive_id'] ?? $drive['graph_id'] ?? ''),
                    'used_bytes' => $usedBytes,
                    'included_bytes' => $includedBytes,
                    'overage_bytes' => $overageBytes,
                ];
            }
        }

        $totalOverageGiB = 0;
        foreach ($onedriveUsers as $row) {
            $totalOverageGiB += (int) ceil(((int) $row['overage_bytes']) / (1024 * 1024 * 1024));
        }

        return [
            'protected_users' => count($protectedAzureIds),
            'onedrive_overage_gib' => $totalOverageGiB,
            'onedrive_users' => array_values($onedriveUsers),
            'inventory_stale' => false,
            'member_resolution_pending' => (bool) ($resolution['member_resolution_pending'] ?? false),
            'breakdown' => $resolution['breakdown'] ?? [],
        ];
    }

    /**
     * @param array<string, mixed> $inventory
     * @param list<string> $selectedIds
     * @param array<string, array<string, bool>> $scopeOverrides
     * @return array<string, mixed>
     */
    public static function previewBillingForSelection(
        int $clientId,
        int $backupUserId,
        array $inventory,
        array $selectedIds,
        array $scopeOverrides,
        ?DiscoveryService $discovery = null,
    ): array {
        $zeroed = self::emptyBillingPreview();
        if ($selectedIds === []) {
            return $zeroed;
        }

        $discovery ??= self::discoveryForBackupUser($clientId, $backupUserId);
        $measure = self::measureSelection($inventory, $selectedIds, $scopeOverrides, $discovery);
        $serviceId = Ms365BillingService::resolveServiceIdForBackupUser($clientId, $backupUserId);
        $trialStatus = $serviceId > 0 ? Ms365BillingTrial::status($serviceId) : null;
        $protectedPrice = Ms365BillingConfig::protectedUserPriceCad();
        $overagePrice = Ms365BillingConfig::onedriveOveragePricePerGibCad();
        $wouldBill = ((int) $measure['protected_users'] * $protectedPrice)
            + ((int) $measure['onedrive_overage_gib'] * $overagePrice);
        if ($trialStatus === 'trialing') {
            $wouldBill = 0.0;
        }

        return [
            'protected_users' => (int) $measure['protected_users'],
            'onedrive_overage_gib' => (int) $measure['onedrive_overage_gib'],
            'pricing' => [
                'protected_user_price_cad' => $protectedPrice,
                'onedrive_overage_per_gib_cad' => $overagePrice,
                'estimated_monthly_cad' => round($wouldBill, 2),
            ],
            'trial_status' => $trialStatus,
            'inventory_stale' => (bool) ($measure['inventory_stale'] ?? false),
            'member_resolution_pending' => (bool) ($measure['member_resolution_pending'] ?? false),
            'breakdown' => $measure['breakdown'] ?? [],
        ];
    }

    /**
     * @return array{
     *   protected_users: int,
     *   onedrive_overage_gib: int,
     *   onedrive_users: list<array<string, mixed>>,
     *   inventory_stale: bool
     * }
     */
    public static function measureBackupUser(int $clientId, int $backupUserId, int $tenantRecordId = 0): array
    {
        $empty = [
            'protected_users' => 0,
            'onedrive_overage_gib' => 0,
            'onedrive_users' => [],
            'inventory_stale' => false,
        ];
        if ($clientId <= 0 || $backupUserId <= 0) {
            return $empty;
        }

        $record = TenantRecordRepository::getForBackupUser($clientId, $backupUserId);
        if ($record === null || ($record['connection_status'] ?? '') !== 'connected') {
            return $empty;
        }
        if ($tenantRecordId <= 0) {
            $tenantRecordId = (int) ($record['id'] ?? 0);
        }

        $inventory = self::loadInventorySafe($clientId, $backupUserId);
        if ($inventory === null || empty($inventory['resources'])) {
            return array_merge($empty, ['inventory_stale' => true]);
        }

        $selection = self::mergeJobSelections($clientId, $backupUserId);
        if ($selection['selected_ids'] === []) {
            return $empty;
        }

        $discovery = self::discoveryForBackupUser($clientId, $backupUserId);
        $measure = self::measureSelection(
            $inventory,
            $selection['selected_ids'],
            $selection['scope_overrides'],
            $discovery,
        );

        return [
            'protected_users' => (int) $measure['protected_users'],
            'onedrive_overage_gib' => (int) $measure['onedrive_overage_gib'],
            'onedrive_users' => $measure['onedrive_users'],
            'inventory_stale' => false,
        ];
    }

    /**
     * @return array{protected_users: int, onedrive_overage_gib: int}
     */
    public static function measureClient(int $clientId): array
    {
        $protected = 0;
        $overageGiB = 0;
        if ($clientId <= 0 || !Capsule::schema()->hasTable('ms365_tenant_records')) {
            return ['protected_users' => 0, 'onedrive_overage_gib' => 0];
        }

        $tenantRows = Capsule::table('ms365_tenant_records')
            ->where('whmcs_client_id', $clientId)
            ->where('is_active', 1)
            ->where('connection_status', 'connected')
            ->whereNotNull('backup_user_id')
            ->where('backup_user_id', '>', 0)
            ->get(['id', 'backup_user_id']);

        foreach ($tenantRows as $row) {
            $m = self::measureBackupUser($clientId, (int) $row->backup_user_id, (int) $row->id);
            $protected += (int) $m['protected_users'];
            $overageGiB += (int) $m['onedrive_overage_gib'];
        }

        return [
            'protected_users' => $protected,
            'onedrive_overage_gib' => $overageGiB,
        ];
    }

    /** @return array<string, mixed> */
    private static function emptyBillingPreview(): array
    {
        return [
            'protected_users' => 0,
            'onedrive_overage_gib' => 0,
            'pricing' => [
                'protected_user_price_cad' => Ms365BillingConfig::protectedUserPriceCad(),
                'onedrive_overage_per_gib_cad' => Ms365BillingConfig::onedriveOveragePricePerGibCad(),
                'estimated_monthly_cad' => 0.0,
            ],
            'trial_status' => null,
            'inventory_stale' => false,
            'member_resolution_pending' => false,
            'breakdown' => [],
        ];
    }

    private static function discoveryForBackupUser(int $clientId, int $backupUserId): ?DiscoveryService
    {
        if ($clientId <= 0 || $backupUserId <= 0) {
            return null;
        }
        $record = TenantRecordRepository::getForBackupUser($clientId, $backupUserId);
        if ($record === null) {
            return null;
        }
        try {
            $ctx = RunTenantContext::forClientRecord($record);

            return new DiscoveryService($ctx->graph, $ctx->storageLayout);
        } catch (\Throwable $_) {
            return null;
        }
    }

    /** @return array{selected_ids: list<string>, scope_overrides: array<string, array<string, bool>>} */
    private static function mergeJobSelections(int $clientId, int $backupUserId): array
    {
        $selected = [];
        $scopes = [];
        if (!Capsule::schema()->hasTable('s3_cloudbackup_jobs')) {
            return ['selected_ids' => [], 'scope_overrides' => []];
        }

        $jobs = Capsule::table('s3_cloudbackup_jobs')
            ->where('client_id', $clientId)
            ->where('backup_user_id', $backupUserId)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->where('source_type', Ms365CustomerJobService::SOURCE_TYPE);
                if (Capsule::schema()->hasColumn('s3_cloudbackup_jobs', 'engine')) {
                    $q->orWhere('engine', Ms365CustomerJobService::ENGINE);
                }
            })
            ->get();

        foreach ($jobs as $job) {
            $fromConfig = self::decodeSourceConfig((string) ($job->source_config_enc ?? ''));
            foreach ($fromConfig['selected_resource_ids'] ?? [] as $id) {
                $id = (string) $id;
                if ($id !== '') {
                    $selected[$id] = true;
                }
            }
            foreach ($fromConfig['scope_overrides'] ?? [] as $rid => $flags) {
                if (!is_string($rid) || !is_array($flags)) {
                    continue;
                }
                if (!isset($scopes[$rid])) {
                    $scopes[$rid] = [];
                }
                foreach ($flags as $k => $v) {
                    if (is_string($k) && $v) {
                        $scopes[$rid][$k] = true;
                    }
                }
            }

            $sched = self::decodeJson((string) ($job->schedule_json ?? ''));
            foreach ($sched['selected_resource_ids'] ?? [] as $id) {
                $id = (string) $id;
                if ($id !== '') {
                    $selected[$id] = true;
                }
            }
            foreach ($sched['scope_overrides'] ?? [] as $rid => $flags) {
                if (!is_string($rid) || !is_array($flags)) {
                    continue;
                }
                if (!isset($scopes[$rid])) {
                    $scopes[$rid] = [];
                }
                foreach ($flags as $k => $v) {
                    if (is_string($k) && $v) {
                        $scopes[$rid][$k] = true;
                    }
                }
            }
        }

        return [
            'selected_ids' => array_keys($selected),
            'scope_overrides' => $scopes,
        ];
    }

    /** @return array<string, mixed>|null */
    private static function loadInventorySafe(int $clientId, int $backupUserId): ?array
    {
        try {
            $data = CustomerInventoryService::loadForBackupUser($clientId, $backupUserId);
            if (empty($data['resources'])) {
                return null;
            }

            return $data;
        } catch (\Throwable $_) {
            return null;
        }
    }

    /** @param array<string, mixed> $inventory */
    private static function hasEnabledPersonalScope(
        string $type,
        string $resourceId,
        array $scopeOverrides,
        array $inventory,
    ): bool {
        $resolved = CustomerSelectionCodec::resolveForExecution(
            [$resourceId],
            $scopeOverrides,
            $inventory,
        );
        $flags = $resolved['scope_overrides'][$resourceId] ?? [];
        if ($flags === []) {
            return in_array($type, self::PERSONAL_TYPES, true);
        }
        foreach ([
            BackupScope::MAIL,
            BackupScope::CALENDAR,
            BackupScope::CONTACTS,
            BackupScope::TASKS,
            BackupScope::ONEDRIVE,
            BackupScope::FILES,
        ] as $key) {
            if (!empty($flags[$key])) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $resource */
    private static function azureUserIdForResource(array $resource, string $type): string
    {
        if ($type === TenantResource::TYPE_USER_ONEDRIVE) {
            $owner = (string) ($resource['meta']['owner_user_id'] ?? '');
            if ($owner !== '') {
                return $owner;
            }

            return TenantResource::graphIdFromResourceId((string) ($resource['id'] ?? ''));
        }

        return (string) ($resource['graph_id'] ?? TenantResource::graphIdFromResourceId((string) ($resource['id'] ?? '')));
    }

    /**
     * @param array<string, array<string, mixed>> $byId
     * @param array<string, mixed> $resource
     * @param array{selected_ids: list<string>, scope_overrides: array<string, array<string, bool>>} $selection
     */
    private static function onedriveProtectedForUser(
        string $azureUserId,
        array $resource,
        string $type,
        array $selection,
        array $byId,
        array $inventory,
    ): bool {
        if ($type === TenantResource::TYPE_USER_ONEDRIVE) {
            return self::hasEnabledPersonalScope($type, (string) $resource['id'], $selection['scope_overrides'], $inventory);
        }
        $userResourceId = 'user:' . $azureUserId;
        if (isset($byId[$userResourceId])) {
            $flags = $selection['scope_overrides'][$userResourceId] ?? [];
            if (!empty($flags[BackupScope::ONEDRIVE]) || !empty($flags[BackupScope::FILES])) {
                return true;
            }
        }
        $onedriveId = 'onedrive:' . $azureUserId;

        return isset($byId[$onedriveId]) && in_array($onedriveId, $selection['selected_ids'], true);
    }

    /**
     * @param array<string, array<string, mixed>> $byId
     * @return array<string, mixed>
     */
    private static function findOneDriveResource(string $azureUserId, array $byId): array
    {
        $onedriveId = 'onedrive:' . $azureUserId;
        if (isset($byId[$onedriveId])) {
            return $byId[$onedriveId];
        }
        foreach ($byId as $resource) {
            if (($resource['resource_type'] ?? '') === TenantResource::TYPE_USER_ONEDRIVE
                && (string) ($resource['meta']['owner_user_id'] ?? '') === $azureUserId) {
                return $resource;
            }
        }

        return [];
    }

    /** @param array<string, mixed> $inventory */
    /** @return array<string, array<string, mixed>> */
    private static function resourcesById(array $inventory): array
    {
        $byId = [];
        foreach ($inventory['resources'] ?? [] as $resource) {
            if (!is_array($resource)) {
                continue;
            }
            $id = (string) ($resource['id'] ?? '');
            if ($id !== '') {
                $byId[$id] = $resource;
            }
        }

        return $byId;
    }

    /** @return array<string, mixed> */
    private static function decodeSourceConfig(string $enc): array
    {
        if ($enc === '') {
            return [];
        }
        try {
            $raw = decrypt($enc);
        } catch (\Throwable $_) {
            return [];
        }
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @return array<string, mixed> */
    private static function decodeJson(string $raw): array
    {
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
