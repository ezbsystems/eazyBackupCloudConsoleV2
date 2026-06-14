<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;

final class WorkerNodeRepository
{
    public static function register(string $hostname, int $maxConcurrent, string $version = '', ?int $proxmoxVmid = null): string
    {
        $adopted = self::adoptProvisioningRow($hostname, $proxmoxVmid);
        if ($adopted !== null) {
            $now = time();
            Capsule::table('ms365_worker_nodes')->where('node_id', $adopted)->update([
                'hostname' => $hostname,
                'status' => 'active',
                'max_concurrent_runs' => max(1, $maxConcurrent),
                'current_load' => 0,
                'version' => $version,
                'last_heartbeat_at' => $now,
                'updated_at' => $now,
                'deploy_status' => 'current',
            ]);

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
        }
        if (Capsule::schema()->hasColumn('ms365_worker_nodes', 'deploy_status')) {
            $row['deploy_status'] = 'current';
        }
        Capsule::table('ms365_worker_nodes')->insert($row);

        return $nodeId;
    }

    public static function registerProvisioning(string $hostname, int $proxmoxVmid): string
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
        Capsule::table('ms365_worker_nodes')->insert([
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
        ]);

        return $nodeId;
    }

    private static function adoptProvisioningRow(string $hostname, ?int $proxmoxVmid): ?string
    {
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

    public static function heartbeat(string $nodeId, int $currentLoad, string $version = '', ?int $proxmoxVmid = null): void
    {
        $now = time();
        $update = [
            'current_load' => max(0, $currentLoad),
            'last_heartbeat_at' => $now,
            'updated_at' => $now,
        ];
        $node = self::get($nodeId);
        if ($node && ($node['status'] ?? '') !== 'draining') {
            $update['status'] = 'active';
        }
        if ($version !== '') {
            $update['version'] = $version;
        }
        if ($proxmoxVmid !== null && $proxmoxVmid > 0) {
            $update['proxmox_vmid'] = $proxmoxVmid;
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

        return Capsule::table('ms365_worker_nodes')
            ->whereIn('status', ['active', 'draining'])
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('last_heartbeat_at')->orWhere('last_heartbeat_at', '<', $cutoff);
            })
            ->update(['status' => 'offline', 'updated_at' => time()]);
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

    private static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
