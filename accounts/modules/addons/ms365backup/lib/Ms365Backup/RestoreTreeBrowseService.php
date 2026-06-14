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

        $cacheKey = hash('sha256', 'v3-calendar-labels' . "\0" . $manifestId . "\0" . $path);
        $cached = self::readCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $raw = self::listKopiaDirectory($tenantRecord, $manifestId, $path);
        $entries = self::autoDescendIfNeeded($tenantRecord, $manifestId, $path, $raw);
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
        $resourceType = (string) ($parsed['resource_type'] ?? '');
        $resourceGraphId = (string) ($parsed['graph_id'] ?? $graphId);

        $workloads = self::workloadsForResource($resourceType, $tenantId, $resourceGraphId, $physicalKey);
        if ($workloads === []) {
            return [];
        }

        $out = [];
        foreach ($workloads as $w) {
            $out[] = [
                'name' => $w['path'],
                'label' => $w['label'],
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
     * @return list<array{label: string, path: string}>
     */
    private static function workloadsForResource(
        string $resourceType,
        string $tenantId,
        string $graphId,
        string $physicalKey,
    ): array {
        $base = rtrim($tenantId, '/');
        if (str_starts_with($physicalKey, 'user:') || str_starts_with($physicalKey, 'mailbox:')) {
            $userRoot = $base . '/users/' . $graphId;

            return [
                ['label' => 'Mail', 'path' => $userRoot . '/mail'],
                ['label' => 'Calendar', 'path' => $userRoot . '/calendars'],
                ['label' => 'Contacts', 'path' => $userRoot . '/contacts'],
                ['label' => 'Tasks', 'path' => $userRoot . '/tasks'],
            ];
        }
        if (str_starts_with($physicalKey, 'onedrive:') || str_starts_with($physicalKey, 'drive:')) {
            return [
                ['label' => 'OneDrive files', 'path' => $base . '/drives/' . $graphId],
            ];
        }
        if (str_starts_with($physicalKey, 'site:')) {
            return [
                ['label' => 'Site files', 'path' => $base . '/sites/' . $graphId],
                ['label' => 'Site lists', 'path' => $base . '/sites/' . $graphId . '/lists'],
            ];
        }
        if (str_starts_with($physicalKey, 'team:')) {
            return [
                ['label' => 'Team channels', 'path' => $base . '/teams/' . $graphId],
            ];
        }
        if (str_starts_with($physicalKey, 'channel:')) {
            $parts = explode(':', $physicalKey, 3);

            return [
                ['label' => 'Channel messages', 'path' => $base . '/teams/' . ($parts[1] ?? $graphId) . '/channels/' . ($parts[2] ?? '')],
            ];
        }
        if (str_starts_with($physicalKey, 'planner:')) {
            return [
                ['label' => 'Planner', 'path' => $base . '/planner/' . $graphId],
            ];
        }
        if (str_starts_with($physicalKey, 'onenote:')) {
            return [
                ['label' => 'OneNote', 'path' => $base . '/onenote/' . $graphId],
            ];
        }

        return [];
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
            $current = self::listKopiaDirectory($tenantRecord, $manifestId, $nextPath);
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

        return false;
    }

    private static function formatLabel(string $name, string $path, bool $hasChildren = false): string
    {
        $lower = strtolower($name);
        if (isset(self::SEGMENT_LABELS[$lower])) {
            return self::SEGMENT_LABELS[$lower];
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

            return '';
        }

        return $name;
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
    private static function listKopiaDirectory(array $tenantRecord, string $manifestId, string $path): array
    {
        try {
            return KopiaSnapshotBrowseService::listDirectory($tenantRecord, $manifestId, $path);
        } catch (\RuntimeException $e) {
            if (!self::isBrowsePathNotFound($e)) {
                throw $e;
            }

            $alt = self::calendarPathAlias($path);
            if ($alt !== null && $alt !== $path) {
                try {
                    return KopiaSnapshotBrowseService::listDirectory($tenantRecord, $manifestId, $alt);
                } catch (\RuntimeException $altError) {
                    if (self::isMissingWorkloadRoot($path, $altError)) {
                        return [];
                    }

                    throw $altError;
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

        return preg_match('#/(mail|calendars?|contacts|tasks)$#', $path) === 1;
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
