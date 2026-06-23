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

        $staleRunningJobs = JobQueueRepository::countStaleRunning();
        $exhaustedJobs = JobQueueRepository::countExhaustedJobs();
        $capacity = WorkerNodeRepository::totalCapacity();
        $load = array_sum(array_map(static fn ($n) => (int) ($n['current_load'] ?? 0), $active));
        $claimAdmitRejects = array_sum(array_map(static fn ($n) => (int) ($n['claim_admit_rejects'] ?? 0), $nodes));

        $telemetryNodes = array_filter($active, static fn ($n) => !empty($n['telemetry_at']));
        $avgCpu = 0.0;
        $memUsed = 0;
        $memTotal = 0;
        $diskFree = 0;
        $diskTotal = 0;
        $telemetryFresh = 0;
        $staleCutoff = time() - 120;
        foreach ($telemetryNodes as $n) {
            if ((int) ($n['telemetry_at'] ?? 0) >= $staleCutoff) {
                $telemetryFresh++;
            }
            $avgCpu += (float) ($n['cpu_pct'] ?? 0);
            $memUsed += (int) ($n['mem_used_mib'] ?? 0);
            $memTotal += (int) ($n['mem_total_mib'] ?? 0);
            $diskFree += (int) ($n['disk_free_mib'] ?? 0);
            $diskTotal += (int) ($n['disk_total_mib'] ?? 0);
        }
        $telemetryCount = count($telemetryNodes);

        return [
            'queued_jobs' => JobQueueRepository::countQueued(),
            'queued_by_keytype' => JobQueueRepository::countQueuedByPhysicalKeyPrefix(),
            'running_jobs' => WorkerClaimService::countPlatformRunning(),
            'stale_running_jobs' => $staleRunningJobs,
            'exhausted_jobs' => $exhaustedJobs,
            'platform_max_concurrent' => Ms365EngineConfig::platformMaxConcurrent(),
            'per_tenant_max_concurrent' => Ms365EngineConfig::perTenantMaxConcurrent(),
            'per_client_max_concurrent' => Ms365EngineConfig::perClientMaxConcurrent(),
            'active_nodes' => count($active),
            'offline_nodes' => count($offline),
            'total_nodes' => count($nodes),
            'capacity' => $capacity,
            'load' => $load,
            'utilization_pct' => $capacity > 0 ? round(100.0 * $load / $capacity, 1) : 0.0,
            'claim_admit_rejects' => $claimAdmitRejects,
            'stale_marked_offline' => $stale,
            'telemetry_nodes' => $telemetryCount,
            'telemetry_fresh_nodes' => $telemetryFresh,
            'avg_cpu_pct' => $telemetryCount > 0 ? round($avgCpu / $telemetryCount, 1) : null,
            'mem_used_mib' => $memUsed,
            'mem_total_mib' => $memTotal,
            'disk_free_mib' => $diskFree,
            'disk_total_mib' => $diskTotal,
            'target_release' => $targetRelease,
            'latest_release' => $latestRelease,
            'suggest_next_version' => ReleaseRepository::suggestNextVersion(),
            'version_counts' => $versionCounts,
            'engine_mode' => Ms365EngineConfig::engineMode(),
        ];
    }
}
