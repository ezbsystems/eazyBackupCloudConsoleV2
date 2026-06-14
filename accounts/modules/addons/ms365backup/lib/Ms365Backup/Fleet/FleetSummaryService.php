<?php
declare(strict_types=1);

namespace Ms365Backup\Fleet;

use Ms365Backup\JobQueueRepository;
use Ms365Backup\Ms365EngineConfig;
use Ms365Backup\WorkerClaimService;
use Ms365Backup\WorkerNodeRepository;

final class FleetSummaryService
{
    /** @return array<string, mixed> */
    public static function summary(): array
    {
        $stale = WorkerNodeRepository::markOfflineStale(FleetSettings::staleHeartbeatSeconds());
        $nodes = WorkerNodeRepository::listNodes();
        $active = array_filter($nodes, static fn ($n) => ($n['status'] ?? '') === 'active');
        $offline = array_filter($nodes, static fn ($n) => ($n['status'] ?? '') === 'offline');
        $state = FleetStateRepository::get();
        $targetRelease = null;
        if (!empty($state['target_release_id'])) {
            $targetRelease = ReleaseRepository::get((int) $state['target_release_id']);
        }
        $latestRelease = ReleaseRepository::latest();

        $versionCounts = [];
        foreach ($nodes as $n) {
            if (($n['status'] ?? '') === 'retired') {
                continue;
            }
            $v = (string) ($n['version'] ?? '') ?: '(unknown)';
            $versionCounts[$v] = ($versionCounts[$v] ?? 0) + 1;
        }

        return [
            'queued_jobs' => JobQueueRepository::countQueued(),
            'running_jobs' => WorkerClaimService::countPlatformRunning(),
            'platform_max_concurrent' => Ms365EngineConfig::platformMaxConcurrent(),
            'active_nodes' => count($active),
            'offline_nodes' => count($offline),
            'total_nodes' => count($nodes),
            'capacity' => WorkerNodeRepository::totalCapacity(),
            'load' => array_sum(array_map(static fn ($n) => (int) ($n['current_load'] ?? 0), $active)),
            'stale_marked_offline' => $stale,
            'target_release' => $targetRelease,
            'latest_release' => $latestRelease,
            'version_counts' => $versionCounts,
            'engine_mode' => Ms365EngineConfig::engineMode(),
        ];
    }
}
