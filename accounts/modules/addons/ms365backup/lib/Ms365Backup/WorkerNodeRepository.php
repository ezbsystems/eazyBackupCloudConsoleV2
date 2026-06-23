<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;

final class WorkerNodeRepository
{
    public static function register(string $hostname, int $maxConcurrent, string $version = '', ?int $proxmoxVmid = null): string
    {
        if ($proxmoxVmid === null || $proxmoxVmid <= 0) {
            $proxmoxVmid = self::inferProxmoxVmidFromHostname($hostname);
        }

        if ($proxmoxVmid !== null && $proxmoxVmid > 0 && strtolower(trim($hostname)) === 'ms365-template') {
            $hostname = 'ms365-worker-' . $proxmoxVmid;
        }

        $adopted = self::adoptProvisioningRow($hostname, $proxmoxVmid);
        if ($adopted !== null) {
            $provisioningRow = self::get($adopted);
            $now = time();
            $update = [
                'hostname' => $hostname,
                'status' => 'active',
                'max_concurrent_runs' => max(1, $maxConcurrent),
                'current_load' => 0,
                'version' => $version,
                'last_heartbeat_at' => $now,
                'updated_at' => $now,
                'deploy_status' => 'current',
            ];
            if ($provisioningRow !== null) {
                $rowVmid = (int) ($provisioningRow['proxmox_vmid'] ?? 0);
                if ($rowVmid > 0) {
                    $update['proxmox_vmid'] = $rowVmid;
                } elseif ($proxmoxVmid !== null && $proxmoxVmid > 0) {
                    $update['proxmox_vmid'] = $proxmoxVmid;
                }
                $rowNode = trim((string) ($provisioningRow['proxmox_node'] ?? ''));
                if ($rowNode !== '') {
                    $update['proxmox_node'] = $rowNode;
                }
            }
            Capsule::table('ms365_worker_nodes')->where('node_id', $adopted)->update($update);

            return $adopted;
        }

        $existing = Capsule::table('ms365_worker_nodes')
            ->where('hostname', $hostname)
            ->whereIn('status', ['active', 'registering', 'draining', 'offline'])
            ->orderByDesc('updated_at')
            ->first();
        if ($existing) {
            $nodeId = (string) $existing->node_id;
            $now = time();
            $update = [
                'status' => 'active',
                'max_concurrent_runs' => max(1, $maxConcurrent),
                'version' => $version !== '' ? $version : (string) $existing->version,
                'last_heartbeat_at' => $now,
                'updated_at' => $now,
            ];
            if ($version !== '' && Capsule::schema()->hasColumn('ms365_worker_nodes', 'deploy_status')) {
                $latest = \Ms365Backup\Fleet\ReleaseRepository::latest();
                if ($latest !== null && !\Ms365Backup\Fleet\ReleaseRepository::nodeNeedsUpdate($version, (string) $latest['version'])) {
                    $update['deploy_status'] = 'current';
                    $update['target_release_id'] = null;
                    $update['deploy_error'] = '';
                }
            }
            Capsule::table('ms365_worker_nodes')->where('node_id', $nodeId)->update($update);
            if ($proxmoxVmid !== null && $proxmoxVmid > 0) {
                self::setProxmoxVmid($nodeId, $proxmoxVmid);
            } elseif (($existing->proxmox_vmid ?? null) === null) {
                $inferred = self::inferProxmoxVmidFromHostname($hostname);
                if ($inferred !== null && $inferred > 0) {
                    self::setProxmoxVmid($nodeId, $inferred);
                }
            }

            return $nodeId;
        }

        $nodeId = self::uuid();
        $now = time();
        $row = [
            'node_id' => $nodeId,
            'hostname' => $hostname,
            'status' => 'active',
            'max_concurrent_runs' => max(1, $maxConcurrent),
            'current_load' => 0,
            'version' => $version,
            'last_heartbeat_at' => $now,
            'registered_at' => $now,
            'updated_at' => $now,
        ];
        if ($proxmoxVmid !== null && $proxmoxVmid > 0) {
            $row['proxmox_vmid'] = $proxmoxVmid;
        } elseif (($inferred = self::inferProxmoxVmidFromHostname($hostname)) !== null) {
            $row['proxmox_vmid'] = $inferred;
        }
        if (Capsule::schema()->hasColumn('ms365_worker_nodes', 'deploy_status')) {
            $row['deploy_status'] = 'current';
        }
        Capsule::table('ms365_worker_nodes')->insert($row);

        return $nodeId;
    }

    public static function registerProvisioning(string $hostname, int $proxmoxVmid, ?string $proxmoxNode = null): string
    {
        $existing = Capsule::table('ms365_worker_nodes')
            ->where('proxmox_vmid', $proxmoxVmid)
            ->whereIn('status', ['registering', 'active', 'offline'])
            ->first();
        if ($existing) {
            return (string) $existing->node_id;
        }

        $nodeId = self::uuid();
        $now = time();
        $row = [
            'node_id' => $nodeId,
            'hostname' => $hostname,
            'proxmox_vmid' => $proxmoxVmid,
            'status' => 'registering',
            'max_concurrent_runs' => 10,
            'current_load' => 0,
            'version' => '',
            'last_heartbeat_at' => null,
            'registered_at' => $now,
            'updated_at' => $now,
            'deploy_status' => 'pending',
        ];
        if ($proxmoxNode !== null && $proxmoxNode !== '' && Capsule::schema()->hasColumn('ms365_worker_nodes', 'proxmox_node')) {
            $row['proxmox_node'] = $proxmoxNode;
        }
        Capsule::table('ms365_worker_nodes')->insert($row);

        return $nodeId;
    }

    private static function adoptProvisioningRow(string $hostname, ?int $proxmoxVmid): ?string
    {
        if ($proxmoxVmid !== null && $proxmoxVmid > 0) {
            $row = Capsule::table('ms365_worker_nodes')
                ->where('proxmox_vmid', $proxmoxVmid)
                ->whereIn('status', ['registering', 'active', 'offline', 'draining'])
                ->orderByDesc('updated_at')
                ->first();
            if ($row) {
                return (string) $row->node_id;
            }
        }

        $q = Capsule::table('ms365_worker_nodes')->where('status', 'registering');
        if ($proxmoxVmid !== null && $proxmoxVmid > 0) {
            $row = (clone $q)->where('proxmox_vmid', $proxmoxVmid)->first();
            if ($row) {
                return (string) $row->node_id;
            }
        }
        $row = Capsule::table('ms365_worker_nodes')
            ->where('status', 'registering')
            ->where('hostname', $hostname)
            ->first();

        return $row ? (string) $row->node_id : null;
    }

    /** @param array<string, mixed> $telemetry */
    public static function recordTelemetry(string $nodeId, array $telemetry): void
    {
        if (!Capsule::schema()->hasColumn('ms365_worker_nodes', 'telemetry_at')) {
            return;
        }
        $sampledAt = (int) ($telemetry['sampled_at'] ?? time());
        if ($sampledAt <= 0) {
            $sampledAt = time();
        }
        $row = [
            'cpu_pct' => isset($telemetry['cpu_pct']) ? round((float) $telemetry['cpu_pct'], 2) : null,
            'mem_used_mib' => isset($telemetry['mem_used_mib']) ? max(0, (int) $telemetry['mem_used_mib']) : null,
            'mem_total_mib' => isset($telemetry['mem_total_mib']) ? max(0, (int) $telemetry['mem_total_mib']) : null,
            'disk_free_mib' => isset($telemetry['disk_free_mib']) ? max(0, (int) $telemetry['disk_free_mib']) : null,
            'disk_total_mib' => isset($telemetry['disk_total_mib']) ? max(0, (int) $telemetry['disk_total_mib']) : null,
            'telemetry_at' => $sampledAt,
            'updated_at' => time(),
        ];
        Capsule::table('ms365_worker_nodes')->where('node_id', $nodeId)->update($row);
        if (!Capsule::schema()->hasTable('ms365_worker_telemetry')) {
            return;
        }
        Capsule::table('ms365_worker_telemetry')->insert([
            'node_id' => $nodeId,
            'cpu_pct' => $row['cpu_pct'],
            'mem_used_mib' => $row['mem_used_mib'],
            'mem_total_mib' => $row['mem_total_mib'],
            'disk_free_mib' => $row['disk_free_mib'],
            'disk_total_mib' => $row['disk_total_mib'],
            'sampled_at' => $sampledAt,
        ]);
    }

    public static function pruneTelemetryHistory(int $retentionHours = 48): int
    {
        if (!Capsule::schema()->hasTable('ms365_worker_telemetry')) {
            return 0;
        }
        $cutoff = time() - max(1, $retentionHours) * 3600;

        return Capsule::table('ms365_worker_telemetry')->where('sampled_at', '<', $cutoff)->delete();
    }

    /** @return list<array<string, mixed>> */
    public static function telemetryHistory(string $nodeId, int $limit = 96): array
    {
        if (!Capsule::schema()->hasTable('ms365_worker_telemetry')) {
            return [];
        }

        return Capsule::table('ms365_worker_telemetry')
            ->where('node_id', $nodeId)
            ->orderByDesc('sampled_at')
            ->limit(max(1, min(500, $limit)))
            ->get()
            ->map(static fn ($r) => (array) $r)
            ->all();
    }

    public static function heartbeat(string $nodeId, int $currentLoad, string $version = '', ?int $proxmoxVmid = null, int $claimAdmitRejects = 0): void
    {
        $now = time();
        $update = [
            'current_load' => max(0, $currentLoad),
            'last_heartbeat_at' => $now,
            'updated_at' => $now,
        ];
        if (Capsule::schema()->hasColumn('ms365_worker_nodes', 'claim_admit_rejects')) {
            $update['claim_admit_rejects'] = max(0, $claimAdmitRejects);
        }
        $node = self::get($nodeId);
        if ($node && ($node['status'] ?? '') !== 'draining' && ($node['status'] ?? '') !== 'stopped') {
            $update['status'] = 'active';
        }
        if ($version !== '') {
            $update['version'] = $version;
        }
        if ($proxmoxVmid !== null && $proxmoxVmid > 0) {
            $update['proxmox_vmid'] = $proxmoxVmid;
        } elseif ($node && (int) ($node['proxmox_vmid'] ?? 0) <= 0) {
            $inferred = self::inferProxmoxVmidFromHostname((string) ($node['hostname'] ?? ''));
            if ($inferred !== null && $inferred > 0) {
                $update['proxmox_vmid'] = $inferred;
            }
        }
        Capsule::table('ms365_worker_nodes')
            ->where('node_id', $nodeId)
            ->update($update);
    }

    public static function setVersion(string $nodeId, string $version): void
    {
        Capsule::table('ms365_worker_nodes')->where('node_id', $nodeId)->update([
            'version' => $version,
            'updated_at' => time(),
        ]);
    }

    public static function setDeployStatus(string $nodeId, string $status, ?int $targetReleaseId, string $error): void
    {
        if (!Capsule::schema()->hasColumn('ms365_worker_nodes', 'deploy_status')) {
            return;
        }
        Capsule::table('ms365_worker_nodes')->where('node_id', $nodeId)->update([
            'deploy_status' => $status,
            'target_release_id' => $targetReleaseId,
            'deploy_error' => mb_substr($error, 0, 500),
            'deploy_updated_at' => time(),
            'updated_at' => time(),
        ]);
    }

    public static function get(string $nodeId): ?array
    {
        $row = Capsule::table('ms365_worker_nodes')->where('node_id', $nodeId)->first();

        return $row ? (array) $row : null;
    }

    public static function markOfflineStale(int $staleSeconds = 120): int
    {
        $cutoff = time() - $staleSeconds;
        $attempts = 0;
        while ($attempts < 3) {
            try {
                return Capsule::table('ms365_worker_nodes')
                    ->whereIn('status', ['active', 'draining'])
                    ->where(function ($q) use ($cutoff) {
                        $q->whereNull('last_heartbeat_at')->orWhere('last_heartbeat_at', '<', $cutoff);
                    })
                    ->update(['status' => 'offline', 'updated_at' => time()]);
            } catch (\Throwable $e) {
                if (!self::isDeadlock($e) || ++$attempts >= 3) {
                    throw $e;
                }
                usleep(100000 * $attempts);
            }
        }

        return 0;
    }

    private static function isDeadlock(\Throwable $e): bool
    {
        $msg = $e->getMessage();

        return str_contains($msg, '1213') || str_contains(strtolower($msg), 'deadlock');
    }

    /** @param list<string> $statuses */
    /** @return list<array<string, mixed>> */
    public static function listNodes(array $statuses = [], ?string $deployStatus = null): array
    {
        $q = Capsule::table('ms365_worker_nodes')->orderBy('hostname');
        if ($statuses !== []) {
            $q->whereIn('status', $statuses);
        }
        if ($deployStatus !== null && Capsule::schema()->hasColumn('ms365_worker_nodes', 'deploy_status')) {
            $q->where('deploy_status', $deployStatus);
        }

        return $q->get()->map(static fn ($r) => (array) $r)->all();
    }

    /** @return list<array<string, mixed>> */
    public static function activeNodes(): array
    {
        return self::listNodes(['active']);
    }

    public static function totalCapacity(): int
    {
        return (int) Capsule::table('ms365_worker_nodes')
            ->where('status', 'active')
            ->sum('max_concurrent_runs');
    }

    public static function setProxmoxVmid(string $nodeId, int $vmid): void
    {
        Capsule::table('ms365_worker_nodes')
            ->where('node_id', $nodeId)
            ->update(['proxmox_vmid' => $vmid, 'updated_at' => time()]);
    }

    public static function setProxmoxNode(string $nodeId, string $node): void
    {
        if (!Capsule::schema()->hasColumn('ms365_worker_nodes', 'proxmox_node')) {
            return;
        }
        Capsule::table('ms365_worker_nodes')
            ->where('node_id', $nodeId)
            ->update(['proxmox_node' => $node, 'updated_at' => time()]);
    }

    public static function stop(string $nodeId): void
    {
        $node = self::get($nodeId);
        if ($node === null) {
            throw new \RuntimeException('Node not found');
        }
        $status = (string) ($node['status'] ?? '');
        if (!in_array($status, ['active', 'draining'], true)) {
            throw new \RuntimeException('Only active or draining nodes can be stopped');
        }
        Capsule::table('ms365_worker_nodes')->where('node_id', $nodeId)->update([
            'status' => 'stopped',
            'current_load' => 0,
            'updated_at' => time(),
        ]);
    }

    public static function start(string $nodeId): void
    {
        $node = self::get($nodeId);
        if ($node === null) {
            throw new \RuntimeException('Node not found');
        }
        if (($node['status'] ?? '') !== 'stopped') {
            throw new \RuntimeException('Only stopped nodes can be started');
        }
        Capsule::table('ms365_worker_nodes')->where('node_id', $nodeId)->update([
            'status' => 'active',
            'updated_at' => time(),
        ]);
    }

    public static function drain(string $nodeId): void
    {
        $node = self::get($nodeId);
        if ($node === null) {
            throw new \RuntimeException('Node not found');
        }
        if (($node['status'] ?? '') === 'retired') {
            throw new \RuntimeException('Cannot drain a retired node');
        }
        Capsule::table('ms365_worker_nodes')->where('node_id', $nodeId)->update([
            'status' => 'draining',
            'updated_at' => time(),
        ]);
    }

    public static function activate(string $nodeId): void
    {
        $node = self::get($nodeId);
        if ($node === null) {
            throw new \RuntimeException('Node not found');
        }
        if (($node['status'] ?? '') !== 'draining') {
            throw new \RuntimeException('Only draining nodes can be activated');
        }
        Capsule::table('ms365_worker_nodes')->where('node_id', $nodeId)->update([
            'status' => 'active',
            'updated_at' => time(),
        ]);
    }

    public static function retire(string $nodeId): void
    {
        $node = self::get($nodeId);
        if ($node === null) {
            throw new \RuntimeException('Node not found');
        }
        if (($node['status'] ?? '') === 'retired') {
            throw new \RuntimeException('Node is already retired');
        }
        Capsule::table('ms365_worker_nodes')->where('node_id', $nodeId)->update([
            'status' => 'retired',
            'deploy_status' => 'current',
            'target_release_id' => null,
            'deploy_error' => '',
            'updated_at' => time(),
        ]);
    }

    public static function deleteRetired(string $nodeId): bool
    {
        $node = self::get($nodeId);
        if ($node === null || ($node['status'] ?? '') !== 'retired') {
            return false;
        }

        return Capsule::table('ms365_worker_nodes')->where('node_id', $nodeId)->delete() > 0;
    }

    /** Retire or remove a `registering` row left by a failed provision. */
    public static function abandonProvisioning(?string $nodeId, ?int $proxmoxVmid = null): void
    {
        $q = Capsule::table('ms365_worker_nodes')->where('status', 'registering');
        if ($nodeId !== null && $nodeId !== '') {
            $q->where('node_id', $nodeId);
        } elseif ($proxmoxVmid !== null && $proxmoxVmid > 0) {
            $q->where('proxmox_vmid', $proxmoxVmid);
        } else {
            return;
        }
        $row = $q->first();
        if ($row === null) {
            return;
        }
        $id = (string) $row->node_id;
        $hadHeartbeat = !empty($row->last_heartbeat_at);
        if ($hadHeartbeat) {
            Capsule::table('ms365_worker_nodes')->where('node_id', $id)->update([
                'status' => 'retired',
                'updated_at' => time(),
            ]);

            return;
        }
        Capsule::table('ms365_worker_nodes')->where('node_id', $id)->delete();
    }

    /**
     * VMIDs reserved by fleet rows that must not be reused until Proxmox confirms the CT is gone.
     *
     * @param list<int> $clusterVmids VMIDs present in Proxmox (empty when cluster query failed)
     * @return list<int>
     */
    public static function reservedProxmoxVmids(array $clusterVmids): array
    {
        $clusterSet = array_flip($clusterVmids);
        $clusterKnown = $clusterVmids !== [];
        $rows = Capsule::table('ms365_worker_nodes')
            ->whereIn('status', ['registering', 'active', 'stopped'])
            ->where('proxmox_vmid', '>', 0)
            ->pluck('proxmox_vmid')
            ->all();

        $reserved = [];
        foreach ($rows as $vmid) {
            $vmid = (int) $vmid;
            if ($vmid <= 0) {
                continue;
            }
            if (!$clusterKnown || isset($clusterSet[$vmid])) {
                $reserved[] = $vmid;
            }
        }

        return $reserved;
    }

    public static function countByDeployStatus(string $status): int
    {
        if (!Capsule::schema()->hasColumn('ms365_worker_nodes', 'deploy_status')) {
            return 0;
        }

        return (int) Capsule::table('ms365_worker_nodes')->where('deploy_status', $status)->count();
    }

    public static function countNeedingVersion(string $targetVersion): int
    {
        $nodes = self::listNodes(['active', 'draining', 'offline', 'registering']);

        return count(array_filter($nodes, static function ($n) use ($targetVersion) {
            if (($n['status'] ?? '') === 'retired') {
                return false;
            }

            return \Ms365Backup\Fleet\ReleaseRepository::nodeNeedsUpdate((string) ($n['version'] ?? ''), $targetVersion);
        }));
    }

    /** @return list<array<string, mixed>> */
    public static function offlineBeyond(int $seconds): array
    {
        $cutoff = time() - $seconds;

        return Capsule::table('ms365_worker_nodes')
            ->where('status', 'offline')
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('last_heartbeat_at')->orWhere('last_heartbeat_at', '<', $cutoff);
            })
            ->get()
            ->map(static fn ($r) => (array) $r)
            ->all();
    }

    /** Parse ms365-worker-9017 style hostnames from fleet clones. */
    private static function inferProxmoxVmidFromHostname(string $hostname): ?int
    {
        if (preg_match('/^ms365-worker-(\d+)$/', trim($hostname), $m) !== 1) {
            return null;
        }
        $vmid = (int) $m[1];

        return $vmid >= 9000 ? $vmid : null;
    }

    private static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
