<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * Restore tree browse with caching, workload shortcuts, and customer-friendly labels.
 */
final class RestoreTreeBrowseService
{
    private const CACHE_TTL_SECONDS = 3600;

    /** @var array<string, string> */
    private const SEGMENT_LABELS = [
        'users' => 'Users',
        'user' => 'User',
        'mail' => 'Mail',
        'calendars' => 'Calendar',
        'calendar' => 'Calendar',
        'calendar' => 'Calendar',
        'contacts' => 'Contacts',
        'tasks' => 'Tasks',
        'drives' => 'OneDrive & drives',
        'sites' => 'SharePoint',
        'teams' => 'Teams',
        'groups' => 'Groups',
        'planner' => 'Planner',
        'onenote' => 'OneNote',
        'onedrive' => 'OneDrive',
        'content' => 'Files',
        'lists' => 'Lists',
        'messages' => 'Messages',
    ];

    /**
     * @return array{entries: list<array<string, mixed>>, total_count: int, has_more: bool, offset: int, limit: int}
     */
    public static function list(
        array $tenantRecord,
        string $manifestId,
        string $path,
        ?array $childRun = null,
        string $batchRunId = '',
        int $limit = 500,
        int $offset = 0,
    ): array {
        $manifestId = trim($manifestId);
        if ($manifestId === '') {
            throw new \RuntimeException('manifest_id is required.');
        }

        $path = trim($path, '/');
        $limit = max(0, $limit);
        $offset = max(0, $offset);

        if ($path === '' && $childRun !== null) {
            $synthetic = self::syntheticWorkloadEntries($tenantRecord, $childRun, $batchRunId);
            if ($synthetic !== []) {
                return self::paginateEntries($synthetic, $limit, $offset);
            }
        }

        $cacheKey = hash('sha256', 'v17-browse' . "\0" . $manifestId . "\0" . $path . "\0" . $limit . "\0" . $offset);
        $cached = self::readCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $raw = self::listKopiaDirectoryWithAliases($tenantRecord, $manifestId, $path, $childRun, $limit, $offset);
        $entries = self::autoDescendIfNeeded($tenantRecord, $manifestId, $path, $raw['entries'], $childRun);
        $result = [
            'entries' => self::enrichEntries($entries, $path, $childRun),
            'total_count' => (int) ($raw['total_count'] ?? count($entries)),
            'has_more' => (bool) ($raw['has_more'] ?? false),
            'offset' => (int) ($raw['offset'] ?? $offset),
            'limit' => (int) ($raw['limit'] ?? $limit),
        ];

        self::writeCache($cacheKey, $result);

        return $result;
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @return array{entries: list<array<string, mixed>>, total_count: int, has_more: bool, offset: int, limit: int}
     */
    private static function paginateEntries(array $entries, int $limit, int $offset): array
    {
        $total = count($entries);
        if ($limit <= 0) {
            return [
                'entries' => $entries,
                'total_count' => $total,
                'has_more' => false,
                'offset' => 0,
                'limit' => 0,
            ];
        }
        $offset = min($offset, $total);
        $page = array_slice($entries, $offset, $limit);

        return [
            'entries' => $page,
            'total_count' => $total,
            'has_more' => ($offset + count($page)) < $total,
            'offset' => $offset,
            'limit' => $limit,
        ];
    }

    /**
     * @param array<string, mixed> $childRun
     * @return list<array<string, mixed>>
     */
    private static function syntheticWorkloadEntries(array $tenantRecord, array $childRun, string $batchRunId = ''): array
    {
        $tenantId = trim((string) (TenantRecordRepository::platformCredentials($tenantRecord)['tenant_id'] ?? ''));
        $physicalKey = PhysicalKeyHelper::baseKey((string) ($childRun['physical_key'] ?? ''));
        $graphId = (string) ($childRun['graph_id'] ?? $childRun['user_id'] ?? '');
        if ($tenantId === '' || $physicalKey === '') {
            return [];
        }

        $siteBrowse = self::resolveSharePointBrowseContext($childRun, $batchRunId);
        $runByLabel = [];
        if ($siteBrowse !== null) {
            $physicalKey = $siteBrowse['parent_key'];
            $graphId = $siteBrowse['site_graph_id'];
            $runByLabel = $siteBrowse['run_by_label'];
        }

        $parsed = StorageLayout::parsePhysicalKey($physicalKey);
        $resourceGraphId = (string) ($parsed['graph_id'] ?? $graphId);
        $scope = $siteBrowse !== null && $siteBrowse['merged_scope']->hasAnyEnabled()
            ? $siteBrowse['merged_scope']
            : BackupScope::fromLegacyRun($childRun);
        $scopeRaw = self::scopeArrayFromChildRun($childRun);
        $driveId = self::driveIdFromChildRun($childRun, $scopeRaw);

        $workloads = self::workloadsForResource($tenantId, $resourceGraphId, $physicalKey, $scope, $scopeRaw, $driveId);
        if ($workloads === []) {
            return [];
        }

        $out = [];
        foreach ($workloads as $w) {
            $runSource = $runByLabel[$w['label']] ?? $childRun;
            $entryManifest = (string) ($runSource['manifest_id'] ?? $childRun['manifest_id'] ?? '');
            if ($w['label'] === 'Files') {
                $driveManifest = self::resolveDriveManifestForRun($tenantRecord, $runSource);
                if ($driveManifest !== '') {
                    $entryManifest = $driveManifest;
                }
            }
            $out[] = [
                'name' => $w['path'],
                'label' => $w['label'],
                'subtitle' => (string) ($w['subtitle'] ?? ''),
                'path' => $w['path'],
                'type' => 'folder',
                'has_children' => true,
                'size' => 0,
                'manifest_id' => $entryManifest,
                'child_run_id' => (string) ($runSource['id'] ?? $childRun['id'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * SharePoint sites are split into drive (files) and site/list child runs; merge scopes and manifests.
     *
     * @param array<string, mixed> $childRun
     * @return array{
     *   parent_key: string,
     *   site_graph_id: string,
     *   merged_scope: BackupScope,
     *   run_by_label: array<string, array<string, mixed>>
     * }|null
     */
    private static function resolveSharePointBrowseContext(array $childRun, string $batchRunId): ?array
    {
        $batchRunId = trim($batchRunId);
        if ($batchRunId === '') {
            return null;
        }

        $physicalKey = (string) ($childRun['physical_key'] ?? '');
        $parentKey = PhysicalKeyHelper::aggregateParentKey($physicalKey, $childRun);
        if (!str_starts_with($parentKey, 'site:')) {
            return null;
        }

        $siteGraphId = substr($parentKey, 5);
        $mergedScope = BackupScope::empty();
        $filesRun = null;
        $listsRun = null;

        foreach (Ms365BatchRunRepository::getChildrenForBatch($batchRunId) as $sibling) {
            if (($sibling['status'] ?? '') !== 'success') {
                continue;
            }

            $sibKey = (string) ($sibling['physical_key'] ?? '');
            $sibBase = PhysicalKeyHelper::baseKey($sibKey);
            $sibParent = PhysicalKeyHelper::aggregateParentKey($sibKey, $sibling);
            if ($sibParent !== $parentKey) {
                continue;
            }

            $sibScope = BackupScope::fromLegacyRun($sibling);
            $mergedScope = $mergedScope->merge($sibScope);

            if (str_starts_with($sibBase, 'drive:')) {
                $mergedScope = $mergedScope->merge(new BackupScope([BackupScope::FILES => true]));
                $filesRun ??= $sibling;
            }
            if (str_starts_with($sibBase, 'site:') && $sibScope->isEnabled(BackupScope::LISTS)) {
                $listsRun ??= $sibling;
            }
            if (str_starts_with($sibBase, 'list:')) {
                $mergedScope = $mergedScope->merge(new BackupScope([BackupScope::LISTS => true]));
                $listsRun ??= $sibling;
            }
        }

        if (!$mergedScope->hasAnyEnabled()) {
            $mergedScope = BackupScope::fromLegacyRun($childRun);
        }
        if ($filesRun === null && $mergedScope->isEnabled(BackupScope::FILES)) {
            $filesRun = $childRun;
        }
        if ($listsRun === null && $mergedScope->isEnabled(BackupScope::LISTS)) {
            $listsRun = $childRun;
        }

        $runByLabel = [];
        if ($filesRun !== null) {
            $runByLabel['Files'] = self::sharePointRunWithManifestFallback($filesRun, $listsRun);
        }
        if ($listsRun !== null) {
            $runByLabel['Lists'] = $listsRun;
        }

        return [
            'parent_key' => $parentKey,
            'site_graph_id' => $siteGraphId,
            'merged_scope' => $mergedScope,
            'run_by_label' => $runByLabel,
        ];
    }

    /**
     * SharePoint drive child runs often complete as no_changes with an empty manifest_id while
     * document libraries remain in the site snapshot tree.
     *
     * @param array<string, mixed> $run
     * @param array<string, mixed>|null $siteRun
     * @return array<string, mixed>
     */
    private static function sharePointRunWithManifestFallback(array $run, ?array $siteRun): array
    {
        if (trim((string) ($run['manifest_id'] ?? '')) !== '') {
            return $run;
        }

        $statsRaw = (string) ($run['stats_json'] ?? '');
        if ($statsRaw !== '') {
            $stats = json_decode($statsRaw, true);
            if (is_array($stats)) {
                $fromStats = trim((string) ($stats['manifest_id'] ?? ''));
                if ($fromStats !== '') {
                    $run['manifest_id'] = $fromStats;

                    return $run;
                }
            }
        }

        if ($siteRun !== null) {
            $siteManifest = trim((string) ($siteRun['manifest_id'] ?? ''));
            if ($siteManifest !== '') {
                $run['manifest_id'] = $siteManifest;
            }
        }

        return $run;
    }

    /**
     * @param array<string, mixed> $tenantRecord
     * @param array<string, mixed>|null $childRun
     */
    private static function resolveDriveManifestForRun(array $tenantRecord, ?array $childRun): string
    {
        if ($childRun === null) {
            return '';
        }

        $manifest = trim((string) ($childRun['manifest_id'] ?? ''));
        if ($manifest !== '') {
            return $manifest;
        }

        $statsRaw = (string) ($childRun['stats_json'] ?? '');
        if ($statsRaw !== '') {
            $stats = json_decode($statsRaw, true);
            if (is_array($stats)) {
                $fromStats = trim((string) ($stats['manifest_id'] ?? ''));
                if ($fromStats !== '') {
                    return $fromStats;
                }
            }
        }

        $physicalKey = PhysicalKeyHelper::baseKey((string) ($childRun['physical_key'] ?? ''));
        if (!str_starts_with($physicalKey, 'drive:')) {
            return '';
        }

        $tenantRecordId = (int) ($tenantRecord['id'] ?? 0);
        if ($tenantRecordId <= 0) {
            return '';
        }

        $jobId = trim((string) ($childRun['e3_job_id'] ?? ''));

        return KopiaRepoBootstrapService::latestManifestForSource(
            $tenantRecordId,
            (string) ($childRun['physical_key'] ?? $physicalKey),
            $jobId !== '' ? $jobId : null,
        );
    }

    /**
     * @param array<string, mixed> $scopeRaw
     * @return list<array{label: string, path: string, subtitle?: string}>
     */
    private static function workloadsForResource(
        string $tenantId,
        string $graphId,
        string $physicalKey,
        BackupScope $scope,
        array $scopeRaw,
        string $driveId = '',
    ): array {
        $base = rtrim($tenantId, '/');
        $physicalKey = PhysicalKeyHelper::baseKey($physicalKey);

        if (str_starts_with($physicalKey, 'user:') || str_starts_with($physicalKey, 'mailbox:')) {
            $userRoot = $base . '/users/' . $graphId;
            $out = [];
            if (self::scopeShowsWorkload($scope, BackupScope::MAIL)) {
                $out[] = ['label' => 'Mail', 'path' => $userRoot . '/mail'];
            }
            if (self::scopeShowsWorkload($scope, BackupScope::CALENDAR)) {
                $out[] = ['label' => 'Calendar', 'path' => $userRoot . '/calendar'];
            }
            if (self::scopeShowsWorkload($scope, BackupScope::CONTACTS)) {
                $out[] = ['label' => 'Contacts', 'path' => $userRoot . '/contacts'];
            }
            if (self::scopeShowsWorkload($scope, BackupScope::TASKS)) {
                $out[] = ['label' => 'Tasks', 'path' => $userRoot . '/tasks'];
            }
            if (self::scopeShowsOneDrive($scope, $scopeRaw, $driveId)) {
                $out[] = ['label' => 'OneDrive', 'path' => $userRoot . '/onedrive/content', 'subtitle' => 'Files'];
            }

            return $out;
        }
        if (str_starts_with($physicalKey, 'onedrive:') || str_starts_with($physicalKey, 'drive:')) {
            $driveGraphId = str_starts_with($physicalKey, 'drive:')
                ? substr($physicalKey, 6)
                : $graphId;

            return [
                ['label' => 'OneDrive', 'path' => $base . '/drives/' . $driveGraphId . '/content', 'subtitle' => 'Files'],
            ];
        }
        if (str_starts_with($physicalKey, 'site:')) {
            $siteSegment = PhysicalKeyHelper::storageSafeId($graphId);
            $siteRoot = $base . '/sites/' . $siteSegment;
            $out = [];
            if (self::scopeShowsWorkload($scope, BackupScope::FILES)) {
                $out[] = [
                    'label' => 'Files',
                    'path' => $siteRoot . '/drives',
                    'subtitle' => 'Document libraries',
                ];
            }
            if (self::scopeShowsWorkload($scope, BackupScope::LISTS)) {
                $out[] = ['label' => 'Lists', 'path' => $siteRoot . '/lists'];
            }

            return $out;
        }
        if (str_starts_with($physicalKey, 'team:')) {
            $teamRoot = $base . '/teams/' . $graphId;
            $out = [];
            if (self::scopeShowsWorkload($scope, BackupScope::TEAMS_METADATA)) {
                $out[] = ['label' => 'Metadata', 'path' => $teamRoot];
            }
            if (self::scopeShowsWorkload($scope, BackupScope::TEAMS_MESSAGES)) {
                $out[] = ['label' => 'Messages', 'path' => $teamRoot . '/channels'];
            }

            return $out;
        }
        if (str_starts_with($physicalKey, 'channel:')) {
            $parts = explode(':', $physicalKey, 3);
            $teamId = (string) ($parts[1] ?? $graphId);
            $channelId = (string) ($parts[2] ?? '');
            $out = [];
            if (self::scopeShowsWorkload($scope, BackupScope::TEAMS_MESSAGES)) {
                $out[] = [
                    'label' => 'Messages',
                    'path' => $base . '/teams/' . $teamId . '/channels/' . $channelId,
                ];
            }

            return $out;
        }
        if (str_starts_with($physicalKey, 'group:')) {
            $groupRoot = $base . '/groups/' . $graphId;
            $out = [];
            if (self::scopeShowsWorkload($scope, BackupScope::MAIL)) {
                $out[] = ['label' => 'Mail', 'path' => $groupRoot . '/mail'];
            }
            if (self::scopeShowsWorkload($scope, BackupScope::CALENDAR)) {
                $out[] = ['label' => 'Calendar', 'path' => $groupRoot . '/calendar'];
            }

            return $out;
        }
        if (str_starts_with($physicalKey, 'planner:')) {
            if (!self::scopeShowsWorkload($scope, BackupScope::PLANNER)) {
                return [];
            }

            return [
                ['label' => 'Planner', 'path' => $base . '/planner/' . $graphId],
            ];
        }
        if (str_starts_with($physicalKey, 'onenote:')) {
            if (!self::scopeShowsWorkload($scope, BackupScope::ONENOTE)) {
                return [];
            }

            return [
                ['label' => 'OneNote', 'path' => $base . '/onenote/' . $graphId],
            ];
        }

        return [];
    }

    /** @param array<string, mixed> $childRun */
    private static function scopeArrayFromChildRun(array $childRun): array
    {
        $raw = $childRun['scope_json'] ?? null;
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $scopeRaw
     */
    private static function driveIdFromChildRun(array $childRun, array $scopeRaw): string
    {
        $fromScope = trim((string) ($scopeRaw['_drive_id'] ?? ''));
        if ($fromScope !== '') {
            return $fromScope;
        }

        $logicalRaw = (string) ($childRun['logical_sources_json'] ?? '');
        if ($logicalRaw !== '') {
            $logical = json_decode($logicalRaw, true);
            if (is_array($logical)) {
                foreach ($logical as $source) {
                    if (!is_array($source)) {
                        continue;
                    }
                    if ((string) ($source['resource_type'] ?? '') !== TenantResource::TYPE_USER_ONEDRIVE) {
                        continue;
                    }
                    $id = (string) ($source['id'] ?? '');
                    if ($id === '') {
                        continue;
                    }
                    $graphId = TenantResource::graphIdFromResourceId($id);
                    if ($graphId !== '') {
                        return $graphId;
                    }
                }
            }
        }

        $physical = PhysicalKeyHelper::baseKey((string) ($childRun['physical_key'] ?? ''));
        if (str_starts_with($physical, 'drive:')) {
            return substr($physical, 6);
        }

        return '';
    }

    private static function scopeShowsWorkload(BackupScope $scope, string $capability): bool
    {
        if ($scope->hasAnyEnabled()) {
            return $scope->isEnabled($capability);
        }

        return match ($capability) {
            BackupScope::MAIL, BackupScope::CALENDAR, BackupScope::CONTACTS, BackupScope::TASKS,
            BackupScope::FILES, BackupScope::LISTS,
            BackupScope::TEAMS_METADATA, BackupScope::TEAMS_MESSAGES,
            BackupScope::PLANNER, BackupScope::ONENOTE => true,
            default => false,
        };
    }

    /**
     * @param array<string, mixed> $scopeRaw
     */
    private static function scopeShowsOneDrive(BackupScope $scope, array $scopeRaw, string $driveId): bool
    {
        if ($driveId === '') {
            return false;
        }
        if (trim((string) ($scopeRaw['_drive_id'] ?? '')) !== '') {
            return true;
        }
        if ($scope->hasAnyEnabled()) {
            return $scope->isEnabled(BackupScope::ONEDRIVE) || $scope->isEnabled(BackupScope::FILES);
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @return list<array<string, mixed>>
     */
    private static function autoDescendIfNeeded(
        array $tenantRecord,
        string $manifestId,
        string $path,
        array $entries,
        ?array $childRun = null,
    ): array {
        $currentPath = $path;
        $current = $entries;
        $guard = 0;
        while ($guard < 10 && count($current) === 1 && ($current[0]['has_children'] ?? false)) {
            $only = $current[0];
            $name = (string) ($only['name'] ?? '');
            if (!self::shouldAutoDescend($name, $currentPath)) {
                break;
            }
            $nextPath = $currentPath === '' ? $name : $currentPath . '/' . $name;
            $page = self::listKopiaDirectory($tenantRecord, $manifestId, $nextPath, $childRun);
            $current = $page['entries'];
            $currentPath = $nextPath;
            $guard++;
        }

        if ($currentPath !== $path && $current !== $entries) {
            self::writeCache(
                hash('sha256', $manifestId . "\0" . $path),
                ['entries' => self::enrichEntries($current, $currentPath, null), 'total_count' => count($current), 'has_more' => false, 'offset' => 0, 'limit' => 0],
            );
        }

        return $current;
    }

    private static function shouldAutoDescend(string $name, string $path): bool
    {
        if ($name === '') {
            return false;
        }
        if (self::isGuidLike($name)) {
            return true;
        }
        if (str_starts_with($name, 'user:') || str_starts_with($name, 'site:') || str_starts_with($name, 'team:')) {
            return true;
        }
        if ($path === '' && preg_match('/^[a-z]+:[0-9a-f-]+$/i', $name) === 1) {
            return true;
        }
        if ($name === 'content' && (str_contains($path, '/drives/') || preg_match('#/sites/[^/]+/drives/#', $path) === 1)) {
            return true;
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @return list<array<string, mixed>>
     */
    private static function enrichEntries(array $entries, string $path, ?array $childRun): array
    {
        $out = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $name = (string) ($entry['name'] ?? '');
            if (self::shouldHideEntry($name)) {
                continue;
            }
            $entryPath = (string) ($entry['path'] ?? '');
            $label = (string) ($entry['label'] ?? '');
            if ($label === '') {
                $label = self::formatLabel(
                    $name,
                    $entryPath,
                    (bool) ($entry['has_children'] ?? false),
                );
            }
            $label = self::resolveSharePointDriveLabel($label, $name, $entryPath, $childRun);
            if ($label === '') {
                continue;
            }
            $entry['label'] = $label;
            if (!isset($entry['subtitle'])) {
                $entry['subtitle'] = '';
            }
            $out[] = $entry;
        }

        return $out;
    }

    private static function shouldHideEntry(string $name): bool
    {
        if ($name === '' || $name === 'folders.json' || $name === 'delta_state.json') {
            return true;
        }
        if ($name === 'lists.json' || $name === 'drives.json') {
            return true;
        }
        if ($name === '_folder.json' || $name === '_calendar.json' || str_ends_with($name, '.removed.json')) {
            return true;
        }
        if ($name === '.catalog') {
            return true;
        }

        return false;
    }

    private static function formatLabel(string $name, string $path, bool $hasChildren = false): string
    {
        $lower = strtolower($name);
        if (isset(self::SEGMENT_LABELS[$lower])) {
            return self::SEGMENT_LABELS[$lower];
        }
        if (self::isDriveContentPath($path)) {
            if ($hasChildren && (self::isGuidLike($name) || self::isOpaqueDriveSegment($name))) {
                return 'Folder';
            }

            return $name;
        }
        if (self::isGuidLike($name)) {
            if (str_contains($path, '/mail/') && $hasChildren) {
                return 'Folder';
            }

            return '';
        }
        if (str_ends_with($lower, '.json')) {
            if (str_contains($path, '/mail/')) {
                return '(No subject)';
            }
            if (str_contains($path, '/calendars/') || str_contains($path, '/calendar/')) {
                return 'Calendar event';
            }
            if (str_contains($path, '/contacts/')) {
                return 'Contact';
            }
            if (str_contains($path, '/tasks/')) {
                return 'Task';
            }

            return 'Item';
        }
        if (preg_match('/^[A-Za-z0-9_-]{20,}$/', $name) === 1 && !str_contains($name, ' ')) {
            if (str_contains($path, '/mail/')) {
                return 'Folder';
            }
            if (self::isDriveContentPath($path) && $hasChildren) {
                return 'Folder';
            }

            return '';
        }

        return $name;
    }

    /**
     * @param array<string, mixed>|null $childRun
     */
    private static function resolveSharePointDriveLabel(string $label, string $name, string $path, ?array $childRun): string
    {
        if (!self::isSharePointDriveRootEntry($path, $name)) {
            return $label;
        }
        if ($label !== '' && $label !== $name && !self::isSharePointDriveId($name)) {
            return $label;
        }

        $scope = self::scopeArrayFromChildRun($childRun ?? []);
        $display = trim((string) ($scope['_drive_display_name'] ?? ''));
        if ($display !== '') {
            return $display;
        }

        if ($label !== '' && $label !== $name) {
            return $label;
        }

        return self::isSharePointDriveId($name) ? 'Documents' : $label;
    }

    private static function isSharePointDriveRootEntry(string $path, string $name): bool
    {
        if (preg_match('#/sites/[^/]+/drives/[^/]+$#', $path) === 1) {
            return true;
        }

        return preg_match('#/sites/[^/]+/drives$#', $path) === 1 && self::isSharePointDriveId($name);
    }

    private static function isSharePointDriveId(string $name): bool
    {
        if (str_starts_with($name, 'b!')) {
            return true;
        }

        return preg_match('/^[A-Za-z0-9_-!]{20,}$/', $name) === 1 && !str_contains($name, ' ');
    }

    private static function isDriveContentPath(string $path): bool
    {
        return str_contains($path, '/content')
            && (str_contains($path, '/drives/') || str_contains($path, '/sites/') || str_contains($path, '/onedrive/'));
    }

    private static function isOpaqueDriveSegment(string $name): bool
    {
        return preg_match('/^[A-Za-z0-9_-]{20,}$/', $name) === 1 && !str_contains($name, ' ');
    }

    private static function isGuidLike(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1
            || preg_match('/^[0-9a-f]{32}$/i', $value) === 1;
    }

    /** @return list<array<string, mixed>>|null */
    private static function readCache(string $cacheKey): ?array
    {
        $file = self::cacheDir() . '/' . $cacheKey . '.json';
        if (!is_file($file)) {
            return null;
        }
        if (filemtime($file) < time() - self::CACHE_TTL_SECONDS) {
            @unlink($file);

            return null;
        }
        $raw = file_get_contents($file);
        if ($raw === false) {
            return null;
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /** @param array{entries?: list<array<string, mixed>>, total_count?: int, has_more?: bool, offset?: int, limit?: int} $result */
    private static function writeCache(string $cacheKey, array $result): void
    {
        $dir = self::cacheDir();
        if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) {
            return;
        }
        @file_put_contents($dir . '/' . $cacheKey . '.json', json_encode($result) ?: '{}');
    }

    private static function cacheDir(): string
    {
        return sys_get_temp_dir() . '/ms365-restore-browse-cache';
    }

    /**
     * @return array{entries: list<array<string, mixed>>, total_count: int, has_more: bool, offset: int, limit: int}
     */
    private static function listKopiaDirectoryWithAliases(
        array $tenantRecord,
        string $manifestId,
        string $path,
        ?array $childRun,
        int $limit = 500,
        int $offset = 0,
    ): array {
        $pathCandidates = [$path];
        foreach (self::oneDriveBrowsePathAliases($path, $childRun) as $alias) {
            if ($alias !== '' && !in_array($alias, $pathCandidates, true)) {
                $pathCandidates[] = $alias;
            }
        }
        foreach (self::sharePointBrowsePathAliases($path, $childRun) as $alias) {
            if ($alias !== '' && !in_array($alias, $pathCandidates, true)) {
                $pathCandidates[] = $alias;
            }
        }
        foreach (self::sharePointDrivePathAliases($path, $childRun) as $alias) {
            if ($alias !== '' && !in_array($alias, $pathCandidates, true)) {
                $pathCandidates[] = $alias;
            }
        }

        $manifestCandidates = [];
        $primaryManifest = trim($manifestId);
        if ($primaryManifest !== '') {
            $manifestCandidates[] = $primaryManifest;
        }
        $driveManifest = self::resolveDriveManifestForRun($tenantRecord, $childRun);
        if ($driveManifest !== '' && !in_array($driveManifest, $manifestCandidates, true)) {
            $manifestCandidates[] = $driveManifest;
        }
        if ($driveManifest !== '' && ($driveManifest !== $primaryManifest || $primaryManifest === '')) {
            if (!in_array('content', $pathCandidates, true)) {
                $pathCandidates[] = 'content';
            }
        }
        if ($manifestCandidates === []) {
            $manifestCandidates[] = '';
        }

        $lastError = null;
        foreach ($manifestCandidates as $candidateManifest) {
            if ($candidateManifest === '') {
                continue;
            }
            foreach ($pathCandidates as $candidate) {
                try {
                    $result = self::listKopiaDirectory($tenantRecord, $candidateManifest, $candidate, $childRun, $limit, $offset);
                    if ($result['entries'] !== []) {
                        $result['entries'] = self::rebaseSharePointDriveBrowsePaths($result['entries'], $path, $candidate, $childRun);

                        return $result;
                    }
                } catch (\RuntimeException $e) {
                    $lastError = $e;
                }
            }
        }

        if ($lastError !== null) {
            if (self::isMissingWorkloadRoot($path, $lastError)) {
                return self::emptyBrowsePage($limit, $offset);
            }
            throw $lastError;
        }

        return self::emptyBrowsePage($limit, $offset);
    }

    /** @return array{entries: list<array<string, mixed>>, total_count: int, has_more: bool, offset: int, limit: int} */
    private static function emptyBrowsePage(int $limit, int $offset): array
    {
        return [
            'entries' => [],
            'total_count' => 0,
            'has_more' => false,
            'offset' => $offset,
            'limit' => $limit,
        ];
    }

    /**
     * @return list<string>
     */
    private static function oneDriveBrowsePathAliases(string $path, ?array $childRun): array
    {
        $aliases = [];
        if (preg_match('#/users/([^/]+)/onedrive/content$#', $path, $m) === 1) {
            $driveId = self::driveIdFromChildRun($childRun ?? [], self::scopeArrayFromChildRun($childRun ?? []));
            if ($driveId !== '' && preg_match('#^([^/]+)/users/#', $path, $tenantMatch) === 1) {
                $aliases[] = $tenantMatch[1] . '/drives/' . $driveId . '/content';
            }
        }
        if (preg_match('#/drives/([^/]+)/content$#', $path, $m) === 1 && $childRun !== null) {
            $graphId = (string) ($childRun['graph_id'] ?? $childRun['user_id'] ?? '');
            if ($graphId !== '' && preg_match('#^([^/]+)/drives/#', $path, $tenantMatch) === 1) {
                $aliases[] = $tenantMatch[1] . '/users/' . $graphId . '/onedrive/content';
            }
        }

        return $aliases;
    }

    /**
     * SharePoint site IDs in Graph use commas (hostname,guid,guid); Kopia stores sanitized segments.
     *
     * @return list<string>
     */
    private static function sharePointBrowsePathAliases(string $path, ?array $childRun): array
    {
        if (!preg_match('#^([^/]+)/sites/([^/]+)(/.*)?$#', $path, $m)) {
            return [];
        }

        $tenant = $m[1];
        $siteSeg = $m[2];
        $rest = $m[3] ?? '';
        $aliases = [];

        $sanitized = PhysicalKeyHelper::storageSafeId($siteSeg);
        if ($sanitized !== $siteSeg) {
            $aliases[] = $tenant . '/sites/' . $sanitized . $rest;
        }

        if ($childRun !== null) {
            $graphId = trim((string) ($childRun['graph_id'] ?? $childRun['user_id'] ?? ''));
            if ($graphId !== '' && $graphId !== $siteSeg) {
                $aliases[] = $tenant . '/sites/' . $graphId . $rest;
            }
            if ($graphId !== '') {
                $safeFromRun = PhysicalKeyHelper::storageSafeId($graphId);
                $safePath = $tenant . '/sites/' . $safeFromRun . $rest;
                if ($safePath !== $path) {
                    $aliases[] = $safePath;
                }
            }
        }

        return $aliases;
    }

    /**
     * SharePoint document libraries live under per-drive snapshot roots or site/drives/{id}/content.
     *
     * @return list<string>
     */
    private static function sharePointDrivePathAliases(string $path, ?array $childRun): array
    {
        $aliases = [];
        $scopeRaw = self::scopeArrayFromChildRun($childRun ?? []);
        $driveId = self::driveIdFromChildRun($childRun ?? [], $scopeRaw);

        if (preg_match('#^([^/]+)/sites/([^/]+)/drives/([^/]+)$#', $path) === 1) {
            $aliases[] = $path . '/content';
        }

        if (preg_match('#^([^/]+)/sites/([^/]+)/drives$#', $path, $m) !== 1) {
            return $aliases;
        }

        $tenant = $m[1];
        $site = $m[2];
        if ($driveId === '') {
            return $aliases;
        }

        $safeDrive = PhysicalKeyHelper::storageSafeId($driveId);
        $aliases[] = $tenant . '/sites/' . $site . '/drives/' . $safeDrive;
        $aliases[] = $tenant . '/sites/' . $site . '/drives/' . $safeDrive . '/content';
        $aliases[] = $tenant . '/drives/' . $safeDrive . '/content';

        return $aliases;
    }

    /**
     * Drive-scoped snapshots expose content/ at the manifest root; rebase to full tenant paths.
     *
     * @param list<array<string, mixed>> $entries
     * @return list<array<string, mixed>>
     */
    private static function rebaseSharePointDriveBrowsePaths(
        array $entries,
        string $requestedPath,
        string $resolvedPath,
        ?array $childRun,
    ): array {
        if ($resolvedPath === $requestedPath) {
            return $entries;
        }

        $basePath = self::sharePointDriveContentBasePath($requestedPath, $childRun);
        if ($basePath === '') {
            return $entries;
        }

        $resolvedNorm = trim($resolvedPath, '/');
        if ($resolvedNorm !== '' && $resolvedNorm !== 'content' && str_contains($resolvedNorm, '/sites/')) {
            return $entries;
        }

        $out = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $entryPath = trim((string) ($entry['path'] ?? ''), '/');
            $name = (string) ($entry['name'] ?? '');
            if ($entryPath === '' && $name !== '') {
                $entry['path'] = $basePath . '/' . $name;
            } elseif ($entryPath !== '' && !str_starts_with($entryPath, $basePath)) {
                if ($resolvedNorm === 'content' || $resolvedNorm === '') {
                    $entry['path'] = $basePath . '/' . ltrim($entryPath, '/');
                }
            }
            $out[] = $entry;
        }

        return $out !== [] ? $out : $entries;
    }

    private static function sharePointDriveContentBasePath(string $requestedPath, ?array $childRun): string
    {
        if (preg_match('#^([^/]+)/sites/([^/]+)/drives(?:/([^/]+))?(?:/content)?$#', $requestedPath, $m) !== 1) {
            return '';
        }

        $driveSeg = (string) ($m[3] ?? '');
        if ($driveSeg === '') {
            $driveId = self::driveIdFromChildRun($childRun ?? [], self::scopeArrayFromChildRun($childRun ?? []));
            if ($driveId === '') {
                return '';
            }
            $driveSeg = PhysicalKeyHelper::storageSafeId($driveId);
        }

        return $m[1] . '/sites/' . $m[2] . '/drives/' . $driveSeg . '/content';
    }

    /**
     * @return array{entries: list<array<string, mixed>>, total_count: int, has_more: bool, offset: int, limit: int}
     */
    private static function listKopiaDirectory(
        array $tenantRecord,
        string $manifestId,
        string $path,
        ?array $childRun = null,
        int $limit = 0,
        int $offset = 0,
    ): array {
        $e3JobId = is_array($childRun) ? trim((string) ($childRun['e3_job_id'] ?? '')) : '';
        $jobArg = $e3JobId !== '' ? $e3JobId : null;
        try {
            return KopiaSnapshotBrowseService::listDirectory($tenantRecord, $manifestId, $path, $jobArg, $limit, $offset);
        } catch (\RuntimeException $e) {
            if (!self::isBrowsePathNotFound($e)) {
                throw $e;
            }

            $alt = self::calendarPathAlias($path);
            if ($alt !== null && $alt !== $path) {
                try {
                    return KopiaSnapshotBrowseService::listDirectory($tenantRecord, $manifestId, $alt, $jobArg, $limit, $offset);
                } catch (\RuntimeException $altError) {
                    if (self::isMissingWorkloadRoot($path, $altError)) {
                        return self::emptyBrowsePage($limit, $offset);
                    }

                    throw $altError;
                }
            }

            $driveAlt = self::driveContentPathAlias($path);
            if ($driveAlt !== null && $driveAlt !== $path) {
                try {
                    return KopiaSnapshotBrowseService::listDirectory($tenantRecord, $manifestId, $driveAlt, $jobArg, $limit, $offset);
                } catch (\RuntimeException $driveAltError) {
                    if (self::isMissingWorkloadRoot($path, $driveAltError)) {
                        return self::emptyBrowsePage($limit, $offset);
                    }

                    throw $driveAltError;
                }
            }

            if (self::isMissingWorkloadRoot($path, $e)) {
                return self::emptyBrowsePage($limit, $offset);
            }

            throw $e;
        }
    }

    private static function isBrowsePathNotFound(\RuntimeException $e): bool
    {
        return str_contains($e->getMessage(), 'path not found');
    }

    private static function isMissingWorkloadRoot(string $path, \RuntimeException $e): bool
    {
        if (!self::isBrowsePathNotFound($e)) {
            return false;
        }

        return preg_match(
            '#/(mail|calendars?|contacts|tasks|onedrive/content|drives/[^/]+(/content)?|groups/[^/]+/(mail|calendars?)|teams/[^/]+(/channels)?|sites/[^/]+(/drives(/[^/]+(/content)?)?|(/lists(/[^/]+(/items)?)?)?)?)$#',
            $path,
        ) === 1;
    }

    private static function driveContentPathAlias(string $path): ?string
    {
        if (preg_match('#/drives/[^/]+$#', $path) === 1) {
            return $path . '/content';
        }
        if (preg_match('#/sites/[^/]+/drives/[^/]+$#', $path) === 1) {
            return $path . '/content';
        }

        return null;
    }

    private static function calendarPathAlias(string $path): ?string
    {
        if (str_contains($path, '/calendars')) {
            return str_replace('/calendars', '/calendar', $path);
        }
        if (str_contains($path, '/calendar')) {
            return str_replace('/calendar', '/calendars', $path);
        }

        return null;
    }
}
