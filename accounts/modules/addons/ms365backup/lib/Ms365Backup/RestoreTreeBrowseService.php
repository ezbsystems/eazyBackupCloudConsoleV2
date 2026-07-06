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
     * @return list<array<string, mixed>>
     */
    public static function list(
        array $tenantRecord,
        string $manifestId,
        string $path,
        ?array $childRun = null,
    ): array {
        $manifestId = trim($manifestId);
        if ($manifestId === '') {
            throw new \RuntimeException('manifest_id is required.');
        }

        $path = trim($path, '/');

        if ($path === '' && $childRun !== null) {
            $synthetic = self::syntheticWorkloadEntries($tenantRecord, $childRun);
            if ($synthetic !== []) {
                return $synthetic;
            }
        }

        $cacheKey = hash('sha256', 'v11-sharepoint-lists-paths' . "\0" . $manifestId . "\0" . $path);
        $cached = self::readCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $raw = self::listKopiaDirectoryWithAliases($tenantRecord, $manifestId, $path, $childRun);
        $entries = self::autoDescendIfNeeded($tenantRecord, $manifestId, $path, $raw, $childRun);
        $entries = self::enrichEntries($entries, $path, $childRun);

        self::writeCache($cacheKey, $entries);

        return $entries;
    }

    /**
     * @param array<string, mixed> $childRun
     * @return list<array<string, mixed>>
     */
    private static function syntheticWorkloadEntries(array $tenantRecord, array $childRun): array
    {
        $tenantId = trim((string) (TenantRecordRepository::platformCredentials($tenantRecord)['tenant_id'] ?? ''));
        $physicalKey = PhysicalKeyHelper::baseKey((string) ($childRun['physical_key'] ?? ''));
        $graphId = (string) ($childRun['graph_id'] ?? $childRun['user_id'] ?? '');
        if ($tenantId === '' || $physicalKey === '') {
            return [];
        }

        $parsed = StorageLayout::parsePhysicalKey($physicalKey);
        $resourceGraphId = (string) ($parsed['graph_id'] ?? $graphId);
        $scope = BackupScope::fromLegacyRun($childRun);
        $scopeRaw = self::scopeArrayFromChildRun($childRun);
        $driveId = self::driveIdFromChildRun($childRun, $scopeRaw);

        $workloads = self::workloadsForResource($tenantId, $resourceGraphId, $physicalKey, $scope, $scopeRaw, $driveId);
        if ($workloads === []) {
            return [];
        }

        $out = [];
        foreach ($workloads as $w) {
            $out[] = [
                'name' => $w['path'],
                'label' => $w['label'],
                'subtitle' => (string) ($w['subtitle'] ?? ''),
                'path' => $w['path'],
                'type' => 'folder',
                'has_children' => true,
                'size' => 0,
                'manifest_id' => (string) ($childRun['manifest_id'] ?? ''),
                'child_run_id' => (string) ($childRun['id'] ?? ''),
            ];
        }

        return $out;
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
                $out[] = ['label' => 'Calendar', 'path' => $userRoot . '/calendars'];
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
                $out[] = ['label' => 'Files', 'path' => $siteRoot];
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
                $out[] = ['label' => 'Calendar', 'path' => $groupRoot . '/calendars'];
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
            $current = self::listKopiaDirectory($tenantRecord, $manifestId, $nextPath, $childRun);
            $currentPath = $nextPath;
            $guard++;
        }

        if ($currentPath !== $path && $current !== $entries) {
            self::writeCache(hash('sha256', $manifestId . "\0" . $path), self::enrichEntries($current, $currentPath, null));
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

    /** @param list<array<string, mixed>> $entries */
    private static function writeCache(string $cacheKey, array $entries): void
    {
        $dir = self::cacheDir();
        if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) {
            return;
        }
        @file_put_contents($dir . '/' . $cacheKey . '.json', json_encode($entries) ?: '[]');
    }

    private static function cacheDir(): string
    {
        return sys_get_temp_dir() . '/ms365-restore-browse-cache';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function listKopiaDirectoryWithAliases(
        array $tenantRecord,
        string $manifestId,
        string $path,
        ?array $childRun,
    ): array {
        $candidates = [$path];
        foreach (self::oneDriveBrowsePathAliases($path, $childRun) as $alias) {
            if ($alias !== '' && !in_array($alias, $candidates, true)) {
                $candidates[] = $alias;
            }
        }
        foreach (self::sharePointBrowsePathAliases($path, $childRun) as $alias) {
            if ($alias !== '' && !in_array($alias, $candidates, true)) {
                $candidates[] = $alias;
            }
        }

        $lastError = null;
        foreach ($candidates as $candidate) {
            try {
                $entries = self::listKopiaDirectory($tenantRecord, $manifestId, $candidate, $childRun);
                if ($entries !== []) {
                    return $entries;
                }
            } catch (\RuntimeException $e) {
                $lastError = $e;
            }
        }

        if ($lastError !== null) {
            throw $lastError;
        }

        return [];
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
     * @return list<array<string, mixed>>
     */
    private static function listKopiaDirectory(array $tenantRecord, string $manifestId, string $path, ?array $childRun = null): array
    {
        $e3JobId = is_array($childRun) ? trim((string) ($childRun['e3_job_id'] ?? '')) : '';
        $jobArg = $e3JobId !== '' ? $e3JobId : null;
        try {
            return KopiaSnapshotBrowseService::listDirectory($tenantRecord, $manifestId, $path, $jobArg);
        } catch (\RuntimeException $e) {
            if (!self::isBrowsePathNotFound($e)) {
                throw $e;
            }

            $alt = self::calendarPathAlias($path);
            if ($alt !== null && $alt !== $path) {
                try {
                    return KopiaSnapshotBrowseService::listDirectory($tenantRecord, $manifestId, $alt, $jobArg);
                } catch (\RuntimeException $altError) {
                    if (self::isMissingWorkloadRoot($path, $altError)) {
                        return [];
                    }

                    throw $altError;
                }
            }

            $driveAlt = self::driveContentPathAlias($path);
            if ($driveAlt !== null && $driveAlt !== $path) {
                try {
                    return KopiaSnapshotBrowseService::listDirectory($tenantRecord, $manifestId, $driveAlt, $jobArg);
                } catch (\RuntimeException $driveAltError) {
                    if (self::isMissingWorkloadRoot($path, $driveAltError)) {
                        return [];
                    }

                    throw $driveAltError;
                }
            }

            if (self::isMissingWorkloadRoot($path, $e)) {
                return [];
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
            '#/(mail|calendars?|contacts|tasks|onedrive/content|drives/[^/]+(/content)?|groups/[^/]+/(mail|calendars?)|teams/[^/]+(/channels)?|sites/[^/]+(/lists(/[^/]+(/items)?)?)?)$#',
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
