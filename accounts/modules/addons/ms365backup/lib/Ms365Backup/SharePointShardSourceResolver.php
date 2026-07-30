<?php
declare(strict_types=1);

namespace Ms365Backup;

/**
 * Resolves validated SharePoint drive shard groups for multi-manifest browse.
 */
final class SharePointShardSourceResolver
{
    /** @var array<string, list<array<string, mixed>>> */
    private static array $batchChildrenCache = [];

    /**
     * Inject batch children for unit tests (avoids database reads).
     *
     * @param list<array<string, mixed>> $children
     */
    public static function seedBatchChildrenCache(string $batchRunId, array $children): void
    {
        self::$batchChildrenCache[trim($batchRunId)] = $children;
    }

    public static function clearBatchChildrenCache(): void
    {
        self::$batchChildrenCache = [];
    }

    /**
     * @return array{
     *   use_multi_source: bool,
     *   sources: list<array{child_run_id: string, manifest_id: string, candidate_paths: list<string>}>,
     *   source_set_hash: string,
     *   representative_child_run_id: string,
     *   representative_manifest_id: string,
     *   drive_group: ?array<string, mixed>
     * }
     */
    public static function buildBrowseContext(
        string $batchRunId,
        ?array $childRun,
        array $tenantRecord,
        string $path,
        string $manifestId,
    ): array {
        $default = [
            'use_multi_source' => false,
            'sources' => [],
            'source_set_hash' => '',
            'representative_child_run_id' => is_array($childRun) ? (string) ($childRun['id'] ?? '') : '',
            'representative_manifest_id' => $manifestId,
            'drive_group' => null,
        ];

        $batchRunId = trim($batchRunId);
        if ($batchRunId === '' || $childRun === null || !self::pathUsesDriveSourceGroup($path, $childRun)) {
            return $default;
        }

        $group = self::resolveDriveGroupForChild($batchRunId, $childRun, $tenantRecord, $path);
        if ($group === null || !$group['is_sharded']) {
            return $default;
        }

        if (!KopiaSnapshotBrowseService::supportsMultiSourceBrowse()) {
            return $default;
        }

        $sources = self::buildSourcesForPath($group, $path, $tenantRecord);
        if ($sources === []) {
            return $default;
        }

        $representative = $group['representative'];

        return [
            'use_multi_source' => true,
            'sources' => $sources,
            'source_set_hash' => self::sourceSetHash($sources),
            'representative_child_run_id' => (string) ($representative['id'] ?? ''),
            'representative_manifest_id' => trim((string) ($representative['manifest_id'] ?? '')) !== ''
                ? (string) $representative['manifest_id']
                : $manifestId,
            'drive_group' => $group,
        ];
    }

    /**
     * @param array{child_run_id?: string, manifest_id?: string, source_path?: string} $sourceRef
     */
    public static function isAuthorizedSourceRef(
        string $batchRunId,
        array $sourceRef,
        ?array $childRun,
        array $tenantRecord,
        string $path = '',
    ): bool {
        $batchRunId = trim($batchRunId);
        $childRunId = trim((string) ($sourceRef['child_run_id'] ?? ''));
        $manifestId = trim((string) ($sourceRef['manifest_id'] ?? ''));
        if ($batchRunId === '' || $childRunId === '' || $manifestId === '') {
            return false;
        }

        $group = self::resolveDriveGroupForChild($batchRunId, $childRun ?? [], $tenantRecord, $path);
        if ($group === null) {
            return false;
        }

        foreach ($group['members'] as $member) {
            if ((string) ($member['id'] ?? '') === $childRunId
                && trim((string) ($member['manifest_id'] ?? '')) === $manifestId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function resolveDriveGroupsForBatch(string $batchRunId): array
    {
        $batchRunId = trim($batchRunId);
        if ($batchRunId === '') {
            return [];
        }

        $groups = [];
        foreach (self::batchChildren($batchRunId) as $child) {
            $baseKey = PhysicalKeyHelper::baseKey((string) ($child['physical_key'] ?? ''));
            if (!str_starts_with($baseKey, 'drive:')) {
                continue;
            }
            $driveId = substr($baseKey, 6);
            $groupKey = $driveId;
            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = self::buildDriveGroup($batchRunId, $driveId, $child);
            }
        }

        return array_values(array_filter($groups, static fn (?array $g) => $g !== null));
    }

    /**
     * @param list<array{child_run_id?: string, manifest_id?: string, candidate_paths?: list<string>}> $sources
     */
    public static function sourceSetHash(array $sources): string
    {
        $parts = [];
        foreach ($sources as $source) {
            $parts[] = trim((string) ($source['child_run_id'] ?? ''))
                . '|'
                . trim((string) ($source['manifest_id'] ?? ''));
        }
        sort($parts);

        return hash('sha256', implode("\n", $parts));
    }

    /**
     * @return ?array{
     *   base_drive_key: string,
     *   drive_id: string,
     *   parent_site_key: string,
     *   site_id: string,
     *   members: list<array<string, mixed>>,
     *   representative: array<string, mixed>,
     *   is_sharded: bool,
     *   shard_count: int
     * }
     */
    public static function resolveDriveGroupForChild(
        string $batchRunId,
        array $childRun,
        array $tenantRecord,
        string $path = '',
    ): ?array {
        $driveId = self::driveIdFromContext($childRun, $path);
        if ($driveId === '') {
            return null;
        }

        return self::buildDriveGroup($batchRunId, $driveId, $childRun);
    }

    /**
     * @param array<string, mixed> $group
     * @return list<array{child_run_id: string, manifest_id: string, candidate_paths: list<string>}>
     */
    public static function buildSourcesForPath(array $group, string $path, array $tenantRecord): array
    {
        $path = trim($path, '/');
        $sources = [];
        foreach ($group['members'] as $member) {
            $manifestId = trim((string) ($member['manifest_id'] ?? ''));
            if ($manifestId === '') {
                continue;
            }
            $candidatePaths = self::candidatePathsForMember($path, $member, $tenantRecord);
            if ($candidatePaths === []) {
                continue;
            }
            $sources[] = [
                'child_run_id' => (string) ($member['id'] ?? ''),
                'manifest_id' => $manifestId,
                'candidate_paths' => $candidatePaths,
            ];
        }

        return $sources;
    }

    /**
     * @return list<string>
     */
    public static function candidatePathsForMember(string $logicalPath, array $member, array $tenantRecord): array
    {
        $logicalPath = trim($logicalPath, '/');
        $scope = self::scopeArray($member);
        $physicalKey = (string) ($member['physical_key'] ?? '');
        $azureTenantId = trim((string) (TenantRecordRepository::platformCredentials($tenantRecord)['tenant_id'] ?? ''));
        if ($azureTenantId === '') {
            return [];
        }

        $kopiaRoot = trim(PhysicalKeyHelper::kopiaSourcePath($azureTenantId, $physicalKey, $scope), '/');
        $shard = PhysicalKeyHelper::parseShard($physicalKey);
        $contentBase = self::driveContentBasePath($logicalPath, $member, $azureTenantId);
        $suffix = '';
        if ($contentBase !== '' && $logicalPath !== '') {
            if ($logicalPath === $contentBase) {
                $suffix = '';
            } elseif (str_starts_with($logicalPath, $contentBase . '/')) {
                $suffix = substr($logicalPath, strlen($contentBase) + 1);
            }
        }

        $candidates = [];
        if ($kopiaRoot !== '') {
            $candidates[] = $suffix !== '' ? $kopiaRoot . '/' . $suffix : $kopiaRoot;
        }
        if ($logicalPath !== '') {
            $candidates[] = $logicalPath;
        }
        if ($suffix !== '') {
            $candidates[] = 'content/' . $suffix;
            if ($shard !== null) {
                $candidates[] = '.shards/' . (int) ($shard['index'] ?? 0) . '/' . $suffix;
            }
        } else {
            $candidates[] = 'content';
            if ($shard !== null) {
                $candidates[] = '.shards/' . (int) ($shard['index'] ?? 0);
            }
        }
        if ($contentBase !== '') {
            $candidates[] = $contentBase;
            if ($suffix !== '') {
                $candidates[] = $contentBase . '/' . $suffix;
            }
        }

        foreach (RestoreTreeBrowseService::browsePathCandidates($logicalPath, $member) as $alias) {
            $alias = trim($alias, '/');
            if ($alias === '') {
                continue;
            }
            $candidates[] = $alias;
            if ($suffix !== '' && (str_ends_with($alias, '/content') || $alias === 'content')) {
                $candidates[] = $alias . '/' . $suffix;
            }
            if ($shard !== null && $suffix !== '') {
                $candidates[] = $alias . '/.shards/' . (int) ($shard['index'] ?? 0) . '/' . $suffix;
            }
        }

        $out = [];
        foreach ($candidates as $candidate) {
            $candidate = trim($candidate, '/');
            if ($candidate !== '' && !in_array($candidate, $out, true)) {
                $out[] = $candidate;
            }
        }

        return $out;
    }

    /**
     * @return ?array{
     *   base_drive_key: string,
     *   drive_id: string,
     *   parent_site_key: string,
     *   site_id: string,
     *   members: list<array<string, mixed>>,
     *   representative: array<string, mixed>,
     *   is_sharded: bool,
     *   shard_count: int
     * }
     */
    private static function buildDriveGroup(string $batchRunId, string $driveId, array $contextChild): ?array
    {
        $baseDriveKey = 'drive:' . $driveId;
        $siteId = self::siteIdFromChild($contextChild);
        $parentSiteKey = $siteId !== '' ? 'site:' . $siteId : '';

        $members = [];
        foreach (self::batchChildren($batchRunId) as $child) {
            if (!self::isEligibleMember($batchRunId, $child, $baseDriveKey, $siteId)) {
                continue;
            }
            $members[] = $child;
        }

        if ($members === []) {
            return null;
        }

        usort($members, static function (array $a, array $b): int {
            $shardA = PhysicalKeyHelper::parseShard((string) ($a['physical_key'] ?? ''));
            $shardB = PhysicalKeyHelper::parseShard((string) ($b['physical_key'] ?? ''));
            $indexA = $shardA !== null ? (int) ($shardA['index'] ?? 0) : 0;
            $indexB = $shardB !== null ? (int) ($shardB['index'] ?? 0) : 0;
            if ($indexA !== $indexB) {
                return $indexA <=> $indexB;
            }

            return strcmp((string) ($a['id'] ?? ''), (string) ($b['id'] ?? ''));
        });

        $shardMembers = [];
        foreach ($members as $member) {
            if (PhysicalKeyHelper::isSharded((string) ($member['physical_key'] ?? ''))) {
                $shardMembers[] = $member;
            }
        }

        $isSharded = count($shardMembers) > 1;
        $representative = self::pickRepresentativeMember($members);

        return [
            'base_drive_key' => $baseDriveKey,
            'drive_id' => $driveId,
            'parent_site_key' => $parentSiteKey,
            'site_id' => $siteId,
            'members' => $members,
            'representative' => $representative,
            'is_sharded' => $isSharded,
            'shard_count' => $isSharded ? count($shardMembers) : count($members),
        ];
    }

  /**
     * @param array<string, mixed> $child
     */
    private static function isEligibleMember(
        string $batchRunId,
        array $child,
        string $expectedBaseDriveKey,
        string $expectedSiteId,
    ): bool {
        if (($child['status'] ?? '') !== 'success') {
            return false;
        }
        if (trim((string) ($child['e3_batch_run_id'] ?? '')) !== $batchRunId) {
            return false;
        }

        $physicalKey = (string) ($child['physical_key'] ?? '');
        $baseKey = PhysicalKeyHelper::baseKey($physicalKey);
        if ($baseKey !== $expectedBaseDriveKey) {
            return false;
        }

        if ($expectedSiteId !== '') {
            $memberSiteId = self::siteIdFromChild($child);
            if ($memberSiteId !== '' && $memberSiteId !== $expectedSiteId) {
                return false;
            }
            $parentKey = PhysicalKeyHelper::aggregateParentKey($physicalKey, $child);
            if (str_starts_with($parentKey, 'site:') && substr($parentKey, 5) !== $expectedSiteId) {
                return false;
            }
        }

        return trim((string) ($child['manifest_id'] ?? '')) !== '';
    }

    /**
     * @param list<array<string, mixed>> $members
     * @return array<string, mixed>
     */
    private static function pickRepresentativeMember(array $members): array
    {
        $best = $members[0];
        $bestScore = self::representativeScore($best);
        foreach ($members as $member) {
            $score = self::representativeScore($member);
            if ($score > $bestScore) {
                $best = $member;
                $bestScore = $score;
            }
        }

        return $best;
    }

    /** @param array<string, mixed> $member */
    private static function representativeScore(array $member): int
    {
        $score = 0;
        if (trim((string) ($member['manifest_id'] ?? '')) !== '') {
            $score += 10;
        }
        $statsRaw = (string) ($member['stats_json'] ?? '');
        if ($statsRaw !== '') {
            $stats = json_decode($statsRaw, true);
            if (is_array($stats)) {
                $files = (int) ($stats['files'] ?? 0);
                $score += min(40, (int) log10($files + 1) * 10);
            }
        }
        $shard = PhysicalKeyHelper::parseShard((string) ($member['physical_key'] ?? ''));
        if ($shard !== null) {
            $score += 1;
        }

        return $score;
    }

    private static function pathUsesDriveSourceGroup(string $path, array $childRun): bool
    {
        $path = trim($path, '/');
        if ($path !== '' && preg_match('#/sites/[^/]+/drives/[^/]+(/content.*)?$#', $path) === 1) {
            return true;
        }
        if ($path !== '' && preg_match('#^[^/]+/drives/[^/]+/content#', $path) === 1) {
            return true;
        }

        $baseKey = PhysicalKeyHelper::baseKey((string) ($childRun['physical_key'] ?? ''));

        return str_starts_with($baseKey, 'drive:');
    }

    /** @return list<array<string, mixed>> */
    private static function batchChildren(string $batchRunId): array
    {
        if (!isset(self::$batchChildrenCache[$batchRunId])) {
            self::$batchChildrenCache[$batchRunId] = Ms365BatchRunRepository::getChildrenForBatch($batchRunId);
        }

        return self::$batchChildrenCache[$batchRunId];
    }

    /** @param array<string, mixed> $childRun */
    private static function driveIdFromContext(array $childRun, string $path): string
    {
        $path = trim($path, '/');
        if (preg_match('#/drives/([^/]+)#', $path, $m) === 1) {
            return (string) $m[1];
        }

        $scope = self::scopeArray($childRun);
        $fromScope = trim((string) ($scope['_drive_id'] ?? ''));
        if ($fromScope !== '') {
            return PhysicalKeyHelper::pathSafeId($fromScope) !== $fromScope
                ? $fromScope
                : $fromScope;
        }

        $baseKey = PhysicalKeyHelper::baseKey((string) ($childRun['physical_key'] ?? ''));
        if (str_starts_with($baseKey, 'drive:')) {
            return substr($baseKey, 6);
        }

        return '';
    }

    /** @param array<string, mixed> $child */
    private static function siteIdFromChild(array $child): string
    {
        $scope = self::scopeArray($child);
        $siteId = trim((string) ($scope['_site_id'] ?? ''));
        if ($siteId !== '') {
            return $siteId;
        }

        $parentKey = PhysicalKeyHelper::aggregateParentKey((string) ($child['physical_key'] ?? ''), $child);
        if (str_starts_with($parentKey, 'site:')) {
            return substr($parentKey, 5);
        }

        return '';
    }

    /** @param array<string, mixed> $child */
    private static function scopeArray(array $child): array
    {
        $raw = $child['scope_json'] ?? null;
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private static function driveContentBasePath(string $logicalPath, array $member, string $azureTenantId): string
    {
        $logicalPath = trim($logicalPath, '/');
        if ($logicalPath === '') {
            return '';
        }

        if (preg_match('#^([^/]+)/sites/([^/]+)/drives/([^/]+)/content#', $logicalPath, $m) === 1) {
            return $m[1] . '/sites/' . $m[2] . '/drives/' . $m[3] . '/content';
        }

        $siteId = self::siteIdFromChild($member);
        $driveId = self::driveIdFromContext($member, $logicalPath);
        if ($siteId === '' || $driveId === '') {
            return '';
        }

        return trim($azureTenantId, '/') . '/sites/' . PhysicalKeyHelper::storageSafeId($siteId)
            . '/drives/' . PhysicalKeyHelper::pathSafeId($driveId) . '/content';
    }
}
