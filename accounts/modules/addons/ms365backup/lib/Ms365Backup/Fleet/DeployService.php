<?php
declare(strict_types=1);

namespace Ms365Backup\Fleet;

use Ms365Backup\WorkerNodeRepository;
use WHMCS\Database\Capsule;

final class DeployService
{
    /** @return array<string, mixed> */
    public static function startDeploy(int $releaseId, string $strategy, bool $force, ?string $canaryNodeId, ?int $adminId): array
    {
        $release = ReleaseRepository::get($releaseId);
        if ($release === null) {
            throw new \RuntimeException('Release not found');
        }

        $nodes = WorkerNodeRepository::listNodes(['active', 'draining', 'offline', 'registering']);
        $eligible = array_values(array_filter($nodes, static fn ($n) => ($n['status'] ?? '') !== 'retired'));
        if ($eligible === []) {
            throw new \RuntimeException('No eligible worker nodes to deploy.');
        }

        $now = time();
        $targetVersion = (string) $release['version'];
        $alreadyCurrent = 0;
        $needingUpdate = 0;
        $aheadOfTarget = 0;
        $newestNodeVersion = '';

        foreach ($eligible as $node) {
            $nodeVersion = (string) ($node['version'] ?? '');
            if (ReleaseRepository::nodeMatchesTarget($nodeVersion, $targetVersion)) {
                WorkerNodeRepository::setDeployStatus((string) $node['node_id'], 'current', null, '');
                $alreadyCurrent++;
            } elseif (ReleaseRepository::nodeNeedsUpdate($nodeVersion, $targetVersion)) {
                $needingUpdate++;
            } elseif (ReleaseRepository::nodeAheadOfTarget($nodeVersion, $targetVersion)) {
                $aheadOfTarget++;
                if ($newestNodeVersion === '' || ReleaseRepository::compareVersions($nodeVersion, $newestNodeVersion) > 0) {
                    $newestNodeVersion = $nodeVersion;
                }
            }
        }

        if ($needingUpdate === 0 && $aheadOfTarget > 0) {
            throw new \RuntimeException(sprintf(
                'Cannot deploy v%s: %d worker node(s) already run a newer version (newest: v%s). Build and deploy a higher version label.',
                $targetVersion,
                $aheadOfTarget,
                $newestNodeVersion !== '' ? $newestNodeVersion : '?'
            ));
        }

        $deployId = (int) Capsule::table('ms365_worker_deploy_jobs')->insertGetId([
            'release_id' => $releaseId,
            'strategy' => $strategy,
            'force_deploy' => $force ? 1 : 0,
            'canary_node_id' => $canaryNodeId,
            'status' => 'rolling',
            'nodes_total' => count($eligible),
            'nodes_updated' => $alreadyCurrent,
            'created_by_admin_id' => $adminId,
            'created_at' => $now,
            'started_at' => $now,
            'updated_at' => $now,
        ]);

        FleetStateRepository::setTargetRelease($releaseId, $strategy, $force, $canaryNodeId, $deployId);
        $browseSync = BrowseBinaryInstaller::syncFromRelease($releaseId);

        foreach ($eligible as $node) {
            $nodeVersion = (string) ($node['version'] ?? '');
            if (!ReleaseRepository::nodeNeedsUpdate($nodeVersion, $targetVersion)) {
                continue;
            }
            WorkerNodeRepository::setDeployStatus((string) $node['node_id'], 'pending', $releaseId, '');
        }

        if ($needingUpdate === 0) {
            Capsule::table('ms365_worker_deploy_jobs')->where('id', $deployId)->update([
                'status' => 'succeeded',
                'nodes_updated' => $alreadyCurrent,
                'ended_at' => $now,
                'updated_at' => $now,
            ]);
            FleetStateRepository::clearDeploy();
        }

        FleetAuditLog::write('deploy_started', 'Deploy release ' . $release['version'], 'deploy_job', (string) $deployId, [
            'strategy' => $strategy,
            'force' => $force,
            'nodes_needing_update' => $needingUpdate,
            'already_current' => $alreadyCurrent,
        ]);

        return [
            'deploy_job_id' => $deployId,
            'nodes_total' => count($eligible),
            'nodes_needing_update' => $needingUpdate,
            'already_current' => $alreadyCurrent,
            'target_version' => $targetVersion,
            'browse_sync' => $browseSync,
        ];
    }

    /** @return array<string, mixed>|null */
    public static function updateInstructionForNode(array $node): ?array
    {
        $state = FleetStateRepository::get();
        $releaseId = (int) ($state['target_release_id'] ?? 0);
        $release = null;
        $baselineOnly = false;
        if ($releaseId > 0) {
            $release = ReleaseRepository::get($releaseId);
        } elseif (self::autoBaselineUpdateEnabled()) {
            $release = ReleaseRepository::latest();
            $baselineOnly = $release !== null;
        }
        if ($release === null) {
            return null;
        }

        $nodeId = (string) ($node['node_id'] ?? '');
        $nodeVersion = (string) ($node['version'] ?? '');
        $targetVersion = (string) $release['version'];
        if (!ReleaseRepository::nodeNeedsUpdate($nodeVersion, $targetVersion)) {
            return null;
        }

        $load = \Ms365Backup\WorkerClaimService::effectiveReportedLoad(
            $nodeId,
            (int) ($node['current_load'] ?? 0)
        );
        if ($baselineOnly && $load > 0) {
            return null;
        }

        $strategy = (string) ($state['deploy_strategy'] ?? 'rolling');
        $force = (int) ($state['deploy_force'] ?? 0) === 1;
        if (!$baselineOnly && !self::nodeAllowedInRollout($node, $state, $strategy)) {
            return null;
        }

        $releaseId = (int) ($release['id'] ?? 0);
        $deployStatus = (string) ($node['deploy_status'] ?? 'current');
        if ($deployStatus !== 'updating') {
            WorkerNodeRepository::setDeployStatus($nodeId, 'updating', $releaseId > 0 ? $releaseId : null, '');
        }

        $offer = [
            'version' => $targetVersion,
            'sha256' => (string) $release['sha256'],
            'download_url' => ArtifactService::downloadUrl($releaseId, $nodeId),
            'release_id' => $releaseId,
        ];
        // Rolling deploys hand off active jobs one node at a time. Force deploys
        // (checkbox or strategy=force) must not drain — workers apply in place.
        $needsDrain = !$baselineOnly
            && $load > 0
            && $strategy === 'rolling'
            && !$force;
        if ($needsDrain) {
            $offer['drain'] = true;
            if (($node['status'] ?? '') !== 'draining') {
                Capsule::table('ms365_worker_nodes')->where('node_id', $nodeId)->update([
                    'status' => 'draining',
                    'updated_at' => time(),
                ]);
            }
        } elseif (($node['status'] ?? '') === 'draining') {
            Capsule::table('ms365_worker_nodes')->where('node_id', $nodeId)->update([
                'status' => 'active',
                'updated_at' => time(),
            ]);
        }

        return $offer;
    }

    private static function autoBaselineUpdateEnabled(): bool
    {
        $val = strtolower(trim(\Ms365Backup\Ms365EngineConfig::moduleSettingPublic('ms365_worker_fleet_auto_baseline_update', 'on')));

        return $val !== 'off' && $val !== '0';
    }

    /** @param array<string, mixed> $node */
    public static function nodeAwaitingDeploy(array $node): bool
    {
        $state = FleetStateRepository::get();
        $releaseId = (int) ($state['target_release_id'] ?? 0);
        if ($releaseId <= 0) {
            return false;
        }
        $release = ReleaseRepository::get($releaseId);
        if ($release === null) {
            return false;
        }
        $deployStatus = (string) ($node['deploy_status'] ?? 'current');
        if (!in_array($deployStatus, ['pending', 'updating'], true)) {
            return false;
        }

        return ReleaseRepository::nodeNeedsUpdate(
            (string) ($node['version'] ?? ''),
            (string) $release['version']
        );
    }

    /** @param array<string, mixed> $node */
    /** @param array<string, mixed> $state */
    private static function nodeAllowedInRollout(array $node, array $state, string $strategy): bool
    {
        $nodeId = (string) ($node['node_id'] ?? '');
        $effectiveLoad = \Ms365Backup\WorkerClaimService::effectiveReportedLoad(
            $nodeId,
            (int) ($node['current_load'] ?? 0)
        );
        if ($strategy === 'all_idle') {
            return $effectiveLoad === 0;
        }
        if ($strategy === 'canary') {
            $canary = (string) ($state['canary_node_id'] ?? '');
            if ($canary !== '') {
                $canaryNode = WorkerNodeRepository::get($canary);
                if ($canaryNode && ReleaseRepository::nodeNeedsUpdate((string) ($canaryNode['version'] ?? ''), (string) (ReleaseRepository::get((int) $state['target_release_id'])['version'] ?? ''))) {
                    if ((string) ($node['node_id'] ?? '') !== $canary) {
                        return false;
                    }

                    return $effectiveLoad === 0;
                }
            }
        }
        if ($strategy === 'rolling') {
            $nodeDeployStatus = (string) ($node['deploy_status'] ?? 'current');
            if ($nodeDeployStatus === 'updating') {
                return true;
            }
            foreach (WorkerNodeRepository::listNodes(['active', 'draining', 'offline'], 'updating') as $updatingNode) {
                if ((string) ($updatingNode['node_id'] ?? '') !== $nodeId) {
                    return false;
                }
            }
            $pending = WorkerNodeRepository::listNodes(['active', 'draining', 'offline'], 'pending');
            if ($pending === []) {
                return true;
            }
            usort($pending, static function ($a, $b) {
                $loadA = \Ms365Backup\WorkerClaimService::effectiveReportedLoad(
                    (string) ($a['node_id'] ?? ''),
                    (int) ($a['current_load'] ?? 0)
                );
                $loadB = \Ms365Backup\WorkerClaimService::effectiveReportedLoad(
                    (string) ($b['node_id'] ?? ''),
                    (int) ($b['current_load'] ?? 0)
                );

                return $loadA <=> $loadB;
            });

            return (string) ($pending[0]['node_id'] ?? '') === (string) ($node['node_id'] ?? '');
        }

        return true;
    }

    public static function markNodeUpdated(string $nodeId, string $version): void
    {
        $node = WorkerNodeRepository::get($nodeId);
        $wasPending = in_array((string) ($node['deploy_status'] ?? ''), ['pending', 'updating'], true);

        WorkerNodeRepository::setDeployStatus($nodeId, 'current', null, '');
        WorkerNodeRepository::setVersion($nodeId, $version);
        if ($node !== null && ($node['status'] ?? '') === 'draining') {
            Capsule::table('ms365_worker_nodes')->where('node_id', $nodeId)->update([
                'status' => 'active',
                'updated_at' => time(),
            ]);
        }

        $state = FleetStateRepository::get();
        $deployId = (int) ($state['active_deploy_job_id'] ?? 0);
        if ($deployId > 0) {
            if ($wasPending) {
                Capsule::table('ms365_worker_deploy_jobs')->where('id', $deployId)->increment('nodes_updated');
            }
            self::maybeCompleteDeploy($deployId);
        }
    }

    /** Clear stale deploy_status when a node reports a version that satisfies the target. */
    public static function reconcileNodeVersion(string $nodeId, string $version): void
    {
        if ($version === '') {
            return;
        }
        $node = WorkerNodeRepository::get($nodeId);
        if ($node === null) {
            return;
        }

        $state = FleetStateRepository::get();
        $releaseId = (int) ($state['target_release_id'] ?? 0);
        $activeDeployId = (int) ($state['active_deploy_job_id'] ?? 0);
        $targetRelease = $releaseId > 0 ? ReleaseRepository::get($releaseId) : null;
        if ($targetRelease === null) {
            $targetRelease = ReleaseRepository::latest();
        }
        if ($targetRelease === null) {
            return;
        }

        $targetVersion = (string) $targetRelease['version'];
        if (ReleaseRepository::nodeNeedsUpdate($version, $targetVersion)) {
            return;
        }

        $inActiveDeploy = $activeDeployId > 0
            && $releaseId > 0
            && (int) ($targetRelease['id'] ?? 0) === $releaseId;
        $deployStatus = (string) ($node['deploy_status'] ?? 'current');

        if ($deployStatus !== 'current') {
            if ($inActiveDeploy) {
                self::markNodeUpdated($nodeId, $version);
            } else {
                WorkerNodeRepository::setDeployStatus($nodeId, 'current', null, '');
                WorkerNodeRepository::setVersion($nodeId, $version);
            }

            return;
        }

        if ($inActiveDeploy) {
            self::maybeCompleteDeploy($activeDeployId);
        }
    }

    public static function markNodeDeployFailed(string $nodeId, string $error): void
    {
        WorkerNodeRepository::setDeployStatus($nodeId, 'failed', null, $error);
        $state = FleetStateRepository::get();
        $deployId = (int) ($state['active_deploy_job_id'] ?? 0);
        if ($deployId > 0) {
            Capsule::table('ms365_worker_deploy_jobs')->where('id', $deployId)->update([
                'status' => 'failed',
                'error_message' => mb_substr($error, 0, 2000),
                'ended_at' => time(),
                'updated_at' => time(),
            ]);
        }
    }

    private static function maybeCompleteDeploy(int $deployId): void
    {
        $job = Capsule::table('ms365_worker_deploy_jobs')->where('id', $deployId)->first();
        if (!$job) {
            return;
        }
        $release = ReleaseRepository::get((int) $job->release_id);
        if ($release === null) {
            return;
        }
        $pending = WorkerNodeRepository::countNeedingVersion((string) $release['version']);
        if ($pending > 0) {
            return;
        }
        self::reconcileAllNodesAtVersion((string) $release['version']);
        $now = time();
        $jobRow = Capsule::table('ms365_worker_deploy_jobs')->where('id', $deployId)->first();
        $nodesTotal = $jobRow ? (int) $jobRow->nodes_total : 0;
        Capsule::table('ms365_worker_deploy_jobs')->where('id', $deployId)->update([
            'status' => 'succeeded',
            'nodes_updated' => max($nodesTotal, (int) ($jobRow->nodes_updated ?? 0)),
            'ended_at' => $now,
            'updated_at' => $now,
        ]);
        FleetStateRepository::clearDeploy();
        FleetAuditLog::write('deploy_succeeded', 'Deploy completed', 'deploy_job', (string) $deployId);
    }

    /** @return int Nodes whose deploy_status was cleared */
    public static function reconcileStuckDeployStatuses(): int
    {
        $fixed = 0;
        $now = time();
        foreach (WorkerNodeRepository::listNodes(['active', 'draining', 'offline', 'registering']) as $node) {
            $nodeId = (string) ($node['node_id'] ?? '');
            if ($nodeId === '') {
                continue;
            }
            $deployStatus = (string) ($node['deploy_status'] ?? 'current');
            if ($deployStatus === 'updating') {
                $updatedAt = (int) ($node['deploy_updated_at'] ?? 0);
                $queueLoad = \Ms365Backup\WorkerClaimService::runningClaimCountForNode($nodeId);
                $effectiveLoad = \Ms365Backup\WorkerClaimService::effectiveReportedLoad(
                    $nodeId,
                    (int) ($node['current_load'] ?? 0)
                );
                if ($updatedAt > 0 && $updatedAt < $now - 900 && $queueLoad === 0 && $effectiveLoad === 0) {
                    $state = FleetStateRepository::get();
                    $releaseId = (int) ($state['target_release_id'] ?? 0);
                    WorkerNodeRepository::setDeployStatus($nodeId, 'pending', $releaseId > 0 ? $releaseId : null, '');
                    $fixed++;
                }
            }
        }

        $latest = ReleaseRepository::latest();
        if ($latest === null) {
            self::reconcileActiveDeploy();

            return $fixed;
        }
        $targetVersion = (string) $latest['version'];
        foreach (WorkerNodeRepository::listNodes(['active', 'draining', 'offline', 'registering']) as $node) {
            if (ReleaseRepository::nodeNeedsUpdate((string) ($node['version'] ?? ''), $targetVersion)) {
                continue;
            }
            if (($node['deploy_status'] ?? 'current') !== 'current') {
                WorkerNodeRepository::setDeployStatus((string) $node['node_id'], 'current', null, '');
                $fixed++;
            }
        }

        self::reconcileActiveDeploy();

        return $fixed;
    }

    /** Close out a rolling deploy when every eligible node satisfies the target release. */
    public static function reconcileActiveDeploy(): void
    {
        $state = FleetStateRepository::get();
        $deployId = (int) ($state['active_deploy_job_id'] ?? 0);
        if ($deployId <= 0) {
            return;
        }
        $job = Capsule::table('ms365_worker_deploy_jobs')->where('id', $deployId)->first();
        if (!$job || !in_array((string) $job->status, ['pending', 'rolling'], true)) {
            return;
        }
        self::maybeCompleteDeploy($deployId);
    }

    private static function reconcileAllNodesAtVersion(string $targetVersion): void
    {
        if ($targetVersion === '') {
            return;
        }
        foreach (WorkerNodeRepository::listNodes(['active', 'draining', 'offline', 'registering']) as $node) {
            if (ReleaseRepository::nodeNeedsUpdate((string) ($node['version'] ?? ''), $targetVersion)) {
                continue;
            }
            if (($node['deploy_status'] ?? 'current') !== 'current') {
                WorkerNodeRepository::setDeployStatus((string) $node['node_id'], 'current', null, '');
            }
        }
    }

    /** @return list<array<string, mixed>> */
    public static function listDeployJobs(int $limit = 25): array
    {
        self::reconcileActiveDeploy();

        $rows = Capsule::table('ms365_worker_deploy_jobs')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(static fn ($r) => (array) $r)
            ->all();

        foreach ($rows as &$row) {
            $release = ReleaseRepository::get((int) ($row['release_id'] ?? 0));
            $row['release_version'] = $release ? (string) ($release['version'] ?? '') : '';
        }
        unset($row);

        return $rows;
    }
}
