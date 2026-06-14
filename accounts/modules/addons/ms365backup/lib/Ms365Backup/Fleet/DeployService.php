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
        $now = time();
        $targetVersion = (string) $release['version'];
        $alreadyCurrent = 0;
        $needingUpdate = 0;

        foreach ($eligible as $node) {
            $nodeVersion = (string) ($node['version'] ?? '');
            if (!ReleaseRepository::nodeNeedsUpdate($nodeVersion, $targetVersion)) {
                WorkerNodeRepository::setDeployStatus((string) $node['node_id'], 'current', null, '');
                $alreadyCurrent++;
                continue;
            }
            $needingUpdate++;
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
                'nodes_updated' => count($eligible),
                'ended_at' => $now,
                'updated_at' => $now,
            ]);
            FleetStateRepository::clearDeploy();
        }

        FleetAuditLog::write('deploy_started', 'Deploy release ' . $release['version'], 'deploy_job', (string) $deployId, [
            'strategy' => $strategy,
            'force' => $force,
        ]);

        return ['deploy_job_id' => $deployId, 'nodes_total' => count($eligible)];
    }

    /** @return array<string, mixed>|null */
    public static function updateInstructionForNode(array $node): ?array
    {
        $state = FleetStateRepository::get();
        $releaseId = (int) ($state['target_release_id'] ?? 0);
        if ($releaseId <= 0) {
            return null;
        }
        $release = ReleaseRepository::get($releaseId);
        if ($release === null) {
            return null;
        }

        $nodeId = (string) ($node['node_id'] ?? '');
        $nodeVersion = (string) ($node['version'] ?? '');
        $targetVersion = (string) $release['version'];
        if (!ReleaseRepository::nodeNeedsUpdate($nodeVersion, $targetVersion)) {
            return null;
        }

        $deployStatus = (string) ($node['deploy_status'] ?? 'current');
        if ($deployStatus === 'updating') {
            return null;
        }

        $strategy = (string) ($state['deploy_strategy'] ?? 'rolling');
        $force = (int) ($state['deploy_force'] ?? 0) === 1;
        $load = (int) ($node['current_load'] ?? 0);
        if (!$force && $load > 0) {
            return null;
        }

        if (!self::nodeAllowedInRollout($node, $state, $strategy)) {
            return null;
        }

        WorkerNodeRepository::setDeployStatus($nodeId, 'updating', $releaseId, '');

        return [
            'version' => $targetVersion,
            'sha256' => (string) $release['sha256'],
            'download_url' => ArtifactService::downloadUrl($releaseId, $nodeId),
            'release_id' => $releaseId,
        ];
    }

    /** @param array<string, mixed> $node */
    /** @param array<string, mixed> $state */
    private static function nodeAllowedInRollout(array $node, array $state, string $strategy): bool
    {
        if ($strategy === 'all_idle') {
            return (int) ($node['current_load'] ?? 0) === 0;
        }
        if ($strategy === 'canary') {
            $canary = (string) ($state['canary_node_id'] ?? '');
            if ($canary !== '') {
                $canaryNode = WorkerNodeRepository::get($canary);
                if ($canaryNode && ReleaseRepository::nodeNeedsUpdate((string) ($canaryNode['version'] ?? ''), (string) (ReleaseRepository::get((int) $state['target_release_id'])['version'] ?? ''))) {
                    return (string) ($node['node_id'] ?? '') === $canary;
                }
            }
        }
        if ($strategy === 'rolling') {
            if (WorkerNodeRepository::countByDeployStatus('updating') > 0) {
                return false;
            }
            $pending = WorkerNodeRepository::listNodes(['active', 'draining', 'offline'], 'pending');
            if ($pending === []) {
                return true;
            }
            usort($pending, static fn ($a, $b) => ((int) ($a['current_load'] ?? 0)) <=> ((int) ($b['current_load'] ?? 0)));

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

        $state = FleetStateRepository::get();
        $deployId = (int) ($state['active_deploy_job_id'] ?? 0);
        if ($deployId > 0 && $wasPending) {
            Capsule::table('ms365_worker_deploy_jobs')->where('id', $deployId)->increment('nodes_updated');
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

        $deployStatus = (string) ($node['deploy_status'] ?? 'current');
        if ($deployStatus === 'current') {
            return;
        }

        if ($releaseId > 0 && (int) ($state['active_deploy_job_id'] ?? 0) > 0) {
            self::markNodeUpdated($nodeId, $version);

            return;
        }

        WorkerNodeRepository::setDeployStatus($nodeId, 'current', null, '');
        WorkerNodeRepository::setVersion($nodeId, $version);
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
        $latest = ReleaseRepository::latest();
        if ($latest === null) {
            return 0;
        }
        $targetVersion = (string) $latest['version'];
        $fixed = 0;
        foreach (WorkerNodeRepository::listNodes(['active', 'draining', 'offline', 'registering']) as $node) {
            if (ReleaseRepository::nodeNeedsUpdate((string) ($node['version'] ?? ''), $targetVersion)) {
                continue;
            }
            if (($node['deploy_status'] ?? 'current') !== 'current') {
                WorkerNodeRepository::setDeployStatus((string) $node['node_id'], 'current', null, '');
                $fixed++;
            }
        }

        return $fixed;
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
        return Capsule::table('ms365_worker_deploy_jobs')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(static fn ($r) => (array) $r)
            ->all();
    }
}
