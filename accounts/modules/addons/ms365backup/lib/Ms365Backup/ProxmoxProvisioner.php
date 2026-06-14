<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;

/**
 * Proxmox LXC fleet provisioning for ms365-backup-worker nodes.
 */
final class ProxmoxProvisioner
{
    public static function autoscale(): array
    {
        $minNodes = max(0, (int) Ms365EngineConfig::moduleSettingPublic('ms365_worker_fleet_min_nodes', '2'));
        $maxNodes = max($minNodes, (int) Ms365EngineConfig::moduleSettingPublic('ms365_worker_fleet_max_nodes', '20'));
        $queued = JobQueueRepository::countQueued();
        $active = WorkerNodeRepository::activeNodes();
        $capacity = WorkerNodeRepository::totalCapacity();
        $load = self::countActiveLoad($active);
        $idle = max(0, $capacity - $load);

        $created = [];
        $destroyed = [];

        if ($queued > 0 && $idle < max(1, (int) ($queued * 0.8)) && count($active) < $maxNodes) {
            $need = min($maxNodes - count($active), max(1, (int) ceil($queued / 10)));
            for ($i = 0; $i < $need; $i++) {
                $res = self::cloneWorkerLxc();
                if (($res['status'] ?? '') === 'success') {
                    $created[] = $res;
                }
            }
        }

        if (count($active) > $minNodes && $queued === 0) {
            foreach ($active as $node) {
                if ((int) ($node['current_load'] ?? 0) === 0 && count($active) - count($destroyed) > $minNodes) {
                    $vmid = (int) ($node['proxmox_vmid'] ?? 0);
                    if ($vmid > 0 && self::destroyLxc($vmid)) {
                        Capsule::table('ms365_worker_nodes')->where('node_id', $node['node_id'])->update([
                            'status' => 'retired',
                            'updated_at' => time(),
                        ]);
                        $destroyed[] = $node['node_id'];
                    }
                }
            }
        }

        return [
            'queued' => $queued,
            'active_nodes' => count($active),
            'capacity' => $capacity,
            'load' => $load,
            'created' => $created,
            'destroyed' => $destroyed,
        ];
    }

    /** @return array{status: string, vmid?: int, hostname?: string, message?: string} */
    public static function cloneWorkerLxc(): array
    {
        $apiUrl = rtrim(Ms365EngineConfig::moduleSettingPublic('proxmox_api_url', ''), '/');
        $node = Ms365EngineConfig::moduleSettingPublic('proxmox_node', '');
        $templateVmid = (int) Ms365EngineConfig::moduleSettingPublic('proxmox_lxc_template_vmid', '0');
        $tokenId = Ms365EngineConfig::moduleSettingPublic('proxmox_api_token_id', '');
        $tokenSecret = Ms365EngineConfig::moduleSettingPublic('proxmox_api_token_secret', '');

        if ($apiUrl === '' || $node === '' || $templateVmid <= 0 || $tokenId === '' || $tokenSecret === '') {
            return ['status' => 'skip', 'message' => 'Proxmox not configured'];
        }

        $newVmid = self::nextVmid();
        $hostname = 'ms365-worker-' . substr(sha1((string) microtime(true)), 0, 8);

        $payload = [
            'newid' => $newVmid,
            'hostname' => $hostname,
            'full' => 1,
            'storage' => 'local-lvm',
        ];
        $path = '/nodes/' . rawurlencode($node) . '/lxc/' . $templateVmid . '/clone';
        $res = self::apiPost($apiUrl, $path, $payload, $tokenId, $tokenSecret);
        if (($res['status'] ?? '') !== 'success') {
            return $res;
        }

        $configPath = '/nodes/' . rawurlencode($node) . '/lxc/' . $newVmid . '/config';
        $configRes = self::apiPut($apiUrl, $configPath, [
            'hostname' => $hostname,
            'environment' => 'PROXMOX_VMID=' . $newVmid,
        ], $tokenId, $tokenSecret);
        if (($configRes['status'] ?? '') !== 'success') {
            return [
                'status' => 'error',
                'message' => 'clone ok but post-clone config failed: ' . ($configRes['message'] ?? 'unknown'),
                'vmid' => $newVmid,
            ];
        }

        $startPath = '/nodes/' . rawurlencode($node) . '/lxc/' . $newVmid . '/status/start';
        $startRes = self::apiPost($apiUrl, $startPath, [], $tokenId, $tokenSecret);
        if (($startRes['status'] ?? '') !== 'success') {
            return [
                'status' => 'error',
                'message' => 'clone configured but start failed: ' . ($startRes['message'] ?? 'unknown'),
                'vmid' => $newVmid,
            ];
        }

        $nodeId = WorkerNodeRepository::registerProvisioning($hostname, $newVmid);

        return ['status' => 'success', 'vmid' => $newVmid, 'hostname' => $hostname, 'node_id' => $nodeId];
    }

    private static function destroyLxc(int $vmid): bool
    {
        $apiUrl = rtrim(Ms365EngineConfig::moduleSettingPublic('proxmox_api_url', ''), '/');
        $node = Ms365EngineConfig::moduleSettingPublic('proxmox_node', '');
        $tokenId = Ms365EngineConfig::moduleSettingPublic('proxmox_api_token_id', '');
        $tokenSecret = Ms365EngineConfig::moduleSettingPublic('proxmox_api_token_secret', '');
        if ($apiUrl === '' || $node === '') {
            return false;
        }
        $stopPath = '/nodes/' . rawurlencode($node) . '/lxc/' . $vmid . '/status/stop';
        self::apiPost($apiUrl, $stopPath, [], $tokenId, $tokenSecret);
        $delPath = '/nodes/' . rawurlencode($node) . '/lxc/' . $vmid;
        $res = self::apiDelete($apiUrl, $delPath, $tokenId, $tokenSecret);

        return ($res['status'] ?? '') === 'success';
    }

    private static function nextVmid(): int
    {
        $max = (int) Capsule::table('ms365_worker_nodes')->max('proxmox_vmid');

        return max(9000, $max + 1);
    }

    /** @param array<string, mixed> $active */
    private static function countActiveLoad(array $active): int
    {
        $sum = 0;
        foreach ($active as $n) {
            $sum += (int) ($n['current_load'] ?? 0);
        }

        return $sum;
    }

    /** @param array<string, mixed> $body */
    private static function apiPost(string $base, string $path, array $body, string $tokenId, string $tokenSecret): array
    {
        return self::apiRequest($base, $path, 'POST', $body, $tokenId, $tokenSecret);
    }

    /** @param array<string, mixed> $body */
    private static function apiPut(string $base, string $path, array $body, string $tokenId, string $tokenSecret): array
    {
        return self::apiRequest($base, $path, 'PUT', $body, $tokenId, $tokenSecret);
    }

    /** @param array<string, mixed> $body */
    private static function apiRequest(string $base, string $path, string $method, array $body, string $tokenId, string $tokenSecret): array
    {
        $url = $base . $path;
        $ch = curl_init($url);
        if ($ch === false) {
            return ['status' => 'error', 'message' => 'curl init failed'];
        }
        $opts = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => [
                'Authorization: PVEAPIToken=' . $tokenId . '=' . $tokenSecret,
            ],
            CURLOPT_TIMEOUT => 120,
        ];
        if ($body !== []) {
            $opts[CURLOPT_POSTFIELDS] = http_build_query($body);
        }
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false || $code >= 400) {
            return ['status' => 'error', 'message' => 'Proxmox HTTP ' . $code . ': ' . (string) $raw];
        }

        return ['status' => 'success', 'raw' => $raw];
    }

    private static function apiDelete(string $base, string $path, string $tokenId, string $tokenSecret): array
    {
        $url = $base . $path;
        $ch = curl_init($url);
        if ($ch === false) {
            return ['status' => 'error', 'message' => 'curl init failed'];
        }
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => [
                'Authorization: PVEAPIToken=' . $tokenId . '=' . $tokenSecret,
            ],
            CURLOPT_TIMEOUT => 120,
        ]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false || $code >= 400) {
            return ['status' => 'error', 'message' => 'Proxmox HTTP ' . $code];
        }

        return ['status' => 'success'];
    }
}
