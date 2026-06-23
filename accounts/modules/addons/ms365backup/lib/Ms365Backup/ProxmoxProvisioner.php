<?php
declare(strict_types=1);

namespace Ms365Backup;

use Ms365Backup\Fleet\FleetAuditLog;
use Ms365Backup\Fleet\FleetSettings;
use Ms365Backup\Fleet\ReleaseRepository;
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

        $autoscaleEnabled = strtolower(trim(Ms365EngineConfig::moduleSettingPublic('ms365_worker_fleet_autoscale_enabled', '0'))) === 'on'
            || Ms365EngineConfig::moduleSettingPublic('ms365_worker_fleet_autoscale_enabled', '0') === '1';

        if ($autoscaleEnabled) {
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
                        if ($vmid > 0 && self::destroyLxc($vmid, (string) ($node['proxmox_node'] ?? ''))) {
                            Capsule::table('ms365_worker_nodes')->where('node_id', $node['node_id'])->update([
                                'status' => 'retired',
                                'updated_at' => time(),
                            ]);
                            $destroyed[] = $node['node_id'];
                        }
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

    /** @return array{created: list<array<string, mixed>>, failed: list<array<string, mixed>>, errors: list<string>} */
    public static function scaleUp(string $targetNode, int $count): array
    {
        $targetNode = trim($targetNode);
        $count = max(1, min(20, $count));
        $created = [];
        $failed = [];
        if ($targetNode === '') {
            return ['created' => [], 'failed' => [], 'errors' => ['proxmox_node required']];
        }
        $blockedVmids = [];
        for ($i = 0; $i < $count; $i++) {
            $res = self::cloneWorkerLxc($targetNode, null, $blockedVmids);
            if (($res['status'] ?? '') === 'success') {
                $created[] = $res;
            } else {
                $failed[] = [
                    'message' => (string) ($res['message'] ?? 'clone failed'),
                    'vmid' => isset($res['vmid']) ? (int) $res['vmid'] : null,
                    'node_id' => isset($res['node_id']) ? (string) $res['node_id'] : null,
                    'verification' => $res['verification'] ?? null,
                ];
                $failedVmid = (int) ($res['vmid'] ?? 0);
                if ($failedVmid > 0) {
                    $blockedVmids[$failedVmid] = $failedVmid;
                }
            }
        }

        return [
            'created' => $created,
            'failed' => $failed,
            'errors' => array_map(static fn (array $f): string => (string) ($f['message'] ?? 'clone failed'), $failed),
        ];
    }

    public static function stopWorker(string $nodeId): bool
    {
        $node = WorkerNodeRepository::get($nodeId);
        if ($node === null) {
            throw new \RuntimeException('Node not found');
        }
        $status = (string) ($node['status'] ?? '');
        if (!in_array($status, ['active', 'draining'], true)) {
            throw new \RuntimeException('Only active or draining nodes can be stopped');
        }
        $vmid = (int) ($node['proxmox_vmid'] ?? 0);
        if ($vmid <= 0) {
            throw new \RuntimeException('Node has no Proxmox VMID');
        }
        $hostNode = trim((string) ($node['proxmox_node'] ?? ''));
        if ($hostNode === '') {
            $hostNode = Ms365EngineConfig::moduleSettingPublic('proxmox_node', '');
        }
        if ($hostNode === '') {
            throw new \RuntimeException('Proxmox host node unknown');
        }
        if (!self::stopLxc($vmid, $hostNode)) {
            throw new \RuntimeException('Proxmox stop failed for VMID ' . $vmid);
        }
        WorkerNodeRepository::stop($nodeId);

        return true;
    }

    public static function startWorker(string $nodeId): bool
    {
        $node = WorkerNodeRepository::get($nodeId);
        if ($node === null) {
            throw new \RuntimeException('Node not found');
        }
        if (($node['status'] ?? '') !== 'stopped') {
            throw new \RuntimeException('Only stopped nodes can be started');
        }
        $vmid = (int) ($node['proxmox_vmid'] ?? 0);
        if ($vmid <= 0) {
            throw new \RuntimeException('Node has no Proxmox VMID');
        }
        $hostNode = trim((string) ($node['proxmox_node'] ?? ''));
        if ($hostNode === '') {
            $hostNode = Ms365EngineConfig::moduleSettingPublic('proxmox_node', '');
        }
        if ($hostNode === '') {
            throw new \RuntimeException('Proxmox host node unknown');
        }
        if (!self::startLxc($vmid, $hostNode)) {
            throw new \RuntimeException('Proxmox start failed for VMID ' . $vmid);
        }
        WorkerNodeRepository::start($nodeId);

        return true;
    }

    /** @return list<string> */
    public static function clusterNodes(): array
    {
        $csv = trim(Ms365EngineConfig::moduleSettingPublic('proxmox_cluster_nodes', ''));
        if ($csv !== '') {
            $nodes = array_values(array_filter(array_map('trim', explode(',', $csv))));

            return $nodes;
        }

        $apiUrl = rtrim(Ms365EngineConfig::moduleSettingPublic('proxmox_api_url', ''), '/');
        $tokenId = Ms365EngineConfig::moduleSettingPublic('proxmox_api_token_id', '');
        $tokenSecret = Ms365EngineConfig::moduleSettingPublic('proxmox_api_token_secret', '');
        if ($apiUrl === '' || $tokenId === '' || $tokenSecret === '') {
            return [];
        }
        $res = self::apiGet($apiUrl, '/nodes', $tokenId, $tokenSecret);
        if (($res['status'] ?? '') !== 'success') {
            return [];
        }
        $decoded = json_decode((string) ($res['raw'] ?? ''), true);
        $data = $decoded['data'] ?? [];
        if (!is_array($data)) {
            return [];
        }
        $out = [];
        foreach ($data as $row) {
            $name = trim((string) ($row['node'] ?? ''));
            if ($name !== '') {
                $out[] = $name;
            }
        }
        sort($out);

        return $out;
    }

    /**
     * @param list<int> $extraBlockedVmids VMIDs to skip (e.g. failed in the same scale-up batch)
     * @return array{status: string, vmid?: int, hostname?: string, message?: string, node_id?: string, proxmox_node?: string, verification?: array<string, mixed>}
     */
    public static function cloneWorkerLxc(?string $targetNode = null, ?int $templateVmid = null, array $extraBlockedVmids = []): array
    {
        $apiUrl = rtrim(Ms365EngineConfig::moduleSettingPublic('proxmox_api_url', ''), '/');
        $templateHomeNode = Ms365EngineConfig::moduleSettingPublic('proxmox_node', '');
        $defaultTemplateVmid = (int) Ms365EngineConfig::moduleSettingPublic('proxmox_lxc_template_vmid', '0');
        $tokenId = Ms365EngineConfig::moduleSettingPublic('proxmox_api_token_id', '');
        $tokenSecret = Ms365EngineConfig::moduleSettingPublic('proxmox_api_token_secret', '');

        $hostNode = $targetNode !== null && trim($targetNode) !== '' ? trim($targetNode) : $templateHomeNode;
        [$sourceNode, $resolvedTemplateVmid] = self::resolveTemplateSource($hostNode, $templateVmid, $templateHomeNode, $defaultTemplateVmid);
        $templateVmid = $resolvedTemplateVmid;

        if ($apiUrl === '' || $sourceNode === '' || $hostNode === '' || $templateVmid <= 0 || $tokenId === '' || $tokenSecret === '') {
            return ['status' => 'skip', 'message' => 'Proxmox not configured'];
        }

        try {
            $newVmid = self::nextAvailableVmid($hostNode, $apiUrl, $tokenId, $tokenSecret, $extraBlockedVmids);
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }

        $hostname = 'ms365-worker-' . $newVmid;
        $cloned = false;
        $nodeId = null;

        try {
            $storage = self::lxcCloneStorage();
            $payload = [
                'newid' => $newVmid,
                'hostname' => $hostname,
                'full' => 1,
                'storage' => $storage,
            ];
            if ($hostNode !== $sourceNode) {
                $payload['target'] = $hostNode;
            }
            $path = '/nodes/' . rawurlencode($sourceNode) . '/lxc/' . $templateVmid . '/clone';
            $res = self::apiPost($apiUrl, $path, $payload, $tokenId, $tokenSecret);
            if (($res['status'] ?? '') !== 'success') {
                return [
                    'status' => 'error',
                    'message' => self::enrichCloneError(
                        (string) ($res['message'] ?? 'clone failed'),
                        $sourceNode,
                        $hostNode,
                        $templateVmid,
                        $newVmid
                    ),
                    'vmid' => $newVmid,
                ];
            }
            $cloned = true;

            $upid = self::parseUpidFromResponse((string) ($res['raw'] ?? ''));
            if ($upid !== null) {
                $taskNode = self::nodeFromUpid($upid) ?? $sourceNode;
                $waitRes = self::waitForProxmoxTask($apiUrl, $taskNode, $upid, $tokenId, $tokenSecret, 180);
                if (!($waitRes['ok'] ?? false)) {
                    throw new \RuntimeException(
                        'clone accepted but async task failed: ' . ($waitRes['message'] ?? 'unknown')
                    );
                }
            }

            // LXC config API accepts only schema-defined keys (hostname, memory, etc.).
            // env/PROXMOX_VMID is CLI-only (pct set --env) on many PVE versions — not valid via PUT.
            // Worker identity: registerProvisioning row (proxmox_vmid) + hostname adoption on register.
            $configPath = '/nodes/' . rawurlencode($hostNode) . '/lxc/' . $newVmid . '/config';
            $configRes = self::apiPutWithLockRetry($apiUrl, $configPath, [
                'hostname' => $hostname,
            ], $tokenId, $tokenSecret);
            if (($configRes['status'] ?? '') !== 'success') {
                throw new \RuntimeException(
                    'clone ok but post-clone config failed: ' . ($configRes['message'] ?? 'unknown')
                );
            }

            $envInject = self::injectWorkerEnvironment($newVmid, $hostNode, $apiUrl, $tokenId, $tokenSecret);
            if (!($envInject['ok'] ?? false)) {
                throw new \RuntimeException(
                    (string) ($envInject['message'] ?? 'post-clone environment.conf injection failed')
                );
            }

            $nodeId = WorkerNodeRepository::registerProvisioning($hostname, $newVmid, $hostNode);

            $startPath = '/nodes/' . rawurlencode($hostNode) . '/lxc/' . $newVmid . '/status/start';
            $startRes = self::apiPostWithLockRetry($apiUrl, $startPath, [], $tokenId, $tokenSecret);
            if (($startRes['status'] ?? '') !== 'success') {
                throw new \RuntimeException(
                    'clone configured but start failed: ' . ($startRes['message'] ?? 'unknown')
                );
            }

            $verification = self::verifyProvisionedWorker($newVmid, $hostNode, $nodeId, $apiUrl, $tokenId, $tokenSecret);
            if (!($verification['ok'] ?? false)) {
                $reason = (string) ($verification['message'] ?? 'post-create verification failed');
                self::cleanupFailedProvision($newVmid, $hostNode, $nodeId, $reason);
                $verification['partial'] = true;

                return [
                    'status' => 'error',
                    'message' => $reason,
                    'vmid' => $newVmid,
                    'hostname' => $hostname,
                    'node_id' => $nodeId,
                    'proxmox_node' => $hostNode,
                    'verification' => $verification,
                ];
            }

            return [
                'status' => 'success',
                'vmid' => $newVmid,
                'hostname' => $hostname,
                'node_id' => $nodeId,
                'proxmox_node' => $hostNode,
                'verification' => $verification,
            ];
        } catch (\Throwable $e) {
            if ($cloned) {
                self::cleanupFailedProvision($newVmid, $hostNode, $nodeId, $e->getMessage());
            }

            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'vmid' => $newVmid,
                'hostname' => $hostname,
                'node_id' => $nodeId,
                'proxmox_node' => $hostNode,
            ];
        }
    }

    /** @return array{0: string, 1: int} */
    private static function resolveTemplateSource(string $hostNode, ?int $templateVmid, string $defaultHomeNode, int $defaultTemplateVmid): array
    {
        $map = self::templateVmidMap();
        if ($templateVmid !== null && $templateVmid > 0) {
            foreach ($map as $nodeName => $vmid) {
                if ($vmid === $templateVmid) {
                    return [$nodeName, $templateVmid];
                }
            }

            return [$defaultHomeNode, $templateVmid];
        }
        if (isset($map[$hostNode]) && $map[$hostNode] > 0) {
            return [$hostNode, $map[$hostNode]];
        }
        if ($defaultTemplateVmid > 0) {
            return [$defaultHomeNode, $defaultTemplateVmid];
        }

        return ['', 0];
    }

    /** @return array<string, int> */
    private static function templateVmidMap(): array
    {
        $raw = trim(Ms365EngineConfig::moduleSettingPublic('proxmox_template_vmid_map', ''));
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }
        $out = [];
        foreach ($decoded as $node => $vmid) {
            $node = trim((string) $node);
            $vmid = (int) $vmid;
            if ($node !== '' && $vmid > 0) {
                $out[$node] = $vmid;
            }
        }

        return $out;
    }

    private static function stopLxc(int $vmid, string $node): bool
    {
        $apiUrl = rtrim(Ms365EngineConfig::moduleSettingPublic('proxmox_api_url', ''), '/');
        $tokenId = Ms365EngineConfig::moduleSettingPublic('proxmox_api_token_id', '');
        $tokenSecret = Ms365EngineConfig::moduleSettingPublic('proxmox_api_token_secret', '');
        if ($apiUrl === '' || $node === '' || $tokenId === '' || $tokenSecret === '') {
            return false;
        }
        $stopPath = '/nodes/' . rawurlencode($node) . '/lxc/' . $vmid . '/status/stop';
        $res = self::apiPost($apiUrl, $stopPath, [], $tokenId, $tokenSecret);

        return ($res['status'] ?? '') === 'success';
    }

    private static function startLxc(int $vmid, string $node): bool
    {
        $apiUrl = rtrim(Ms365EngineConfig::moduleSettingPublic('proxmox_api_url', ''), '/');
        $tokenId = Ms365EngineConfig::moduleSettingPublic('proxmox_api_token_id', '');
        $tokenSecret = Ms365EngineConfig::moduleSettingPublic('proxmox_api_token_secret', '');
        if ($apiUrl === '' || $node === '' || $tokenId === '' || $tokenSecret === '') {
            return false;
        }
        $startPath = '/nodes/' . rawurlencode($node) . '/lxc/' . $vmid . '/status/start';
        $res = self::apiPostWithLockRetry($apiUrl, $startPath, [], $tokenId, $tokenSecret);

        return ($res['status'] ?? '') === 'success';
    }

    private static function destroyLxc(int $vmid, string $hostNode = ''): bool
    {
        $apiUrl = rtrim(Ms365EngineConfig::moduleSettingPublic('proxmox_api_url', ''), '/');
        $node = $hostNode !== '' ? $hostNode : Ms365EngineConfig::moduleSettingPublic('proxmox_node', '');
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

    /** @param list<int> $extraBlockedVmids */
    private static function nextAvailableVmid(
        string $targetNode,
        string $apiUrl,
        string $tokenId,
        string $tokenSecret,
        array $extraBlockedVmids = []
    ): int {
        $minVmid = 9000;
        $maxVmid = 9999;
        $dbMax = (int) Capsule::table('ms365_worker_nodes')->max('proxmox_vmid');
        $candidate = max($minVmid, $dbMax + 1);

        $clusterVmids = self::clusterVmids($apiUrl, $tokenId, $tokenSecret);
        $reservedDbVmids = WorkerNodeRepository::reservedProxmoxVmids($clusterVmids);
        $blocked = array_flip(array_merge($clusterVmids, $reservedDbVmids, $extraBlockedVmids));

        $attempts = 0;
        $maxAttempts = $maxVmid - $minVmid + 1;
        while ($attempts < $maxAttempts) {
            if ($candidate > $maxVmid) {
                break;
            }
            if (!isset($blocked[$candidate]) && !self::lxcExistsOnNode($apiUrl, $targetNode, $candidate, $tokenId, $tokenSecret)) {
                return $candidate;
            }
            $candidate++;
            $attempts++;
        }

        throw new \RuntimeException(
            'No free worker VMID in range ' . $minVmid . '-' . $maxVmid
            . ' on node ' . $targetNode
            . ' (cluster has ' . count($clusterVmids) . ' VM(s); check for orphaned CTs blocking allocation)'
        );
    }

    /** @return list<int> */
    private static function clusterVmids(string $apiUrl, string $tokenId, string $tokenSecret): array
    {
        $res = self::apiGet($apiUrl, '/cluster/resources?type=vm', $tokenId, $tokenSecret);
        if (($res['status'] ?? '') !== 'success') {
            return [];
        }
        $decoded = json_decode((string) ($res['raw'] ?? ''), true);
        $data = $decoded['data'] ?? [];
        if (!is_array($data)) {
            return [];
        }
        $out = [];
        foreach ($data as $row) {
            if (!is_array($row)) {
                continue;
            }
            $vmid = (int) ($row['vmid'] ?? 0);
            if ($vmid > 0) {
                $out[] = $vmid;
            }
        }

        return $out;
    }

    private static function lxcExistsOnNode(string $apiUrl, string $node, int $vmid, string $tokenId, string $tokenSecret): bool
    {
        if ($node === '' || $vmid <= 0) {
            return false;
        }
        $path = '/nodes/' . rawurlencode($node) . '/lxc/' . $vmid . '/status/current';
        $res = self::apiGet($apiUrl, $path, $tokenId, $tokenSecret);

        return ($res['status'] ?? '') === 'success';
    }

    private static function lxcStatus(string $apiUrl, string $node, int $vmid, string $tokenId, string $tokenSecret): ?string
    {
        $path = '/nodes/' . rawurlencode($node) . '/lxc/' . $vmid . '/status/current';
        $res = self::apiGet($apiUrl, $path, $tokenId, $tokenSecret);
        if (($res['status'] ?? '') !== 'success') {
            return null;
        }
        $decoded = json_decode((string) ($res['raw'] ?? ''), true);
        $status = trim((string) ($decoded['data']['status'] ?? ''));

        return $status !== '' ? $status : null;
    }

    private static function cleanupFailedProvision(int $vmid, string $hostNode, ?string $nodeId, string $reason): void
    {
        $destroyed = self::cleanupOrphanVmid($vmid, $hostNode);
        WorkerNodeRepository::abandonProvisioning($nodeId, $vmid);
        FleetAuditLog::write(
            'provision_cleanup',
            'Rolled back failed worker provision VMID ' . $vmid . ' on ' . $hostNode . ': ' . mb_substr($reason, 0, 200),
            'vmid',
            (string) $vmid,
            [
                'proxmox_node' => $hostNode,
                'node_id' => $nodeId,
                'destroyed' => $destroyed,
                'reason' => $reason,
            ]
        );
    }

    public static function cleanupOrphanVmid(int $vmid, string $hostNode = ''): bool
    {
        return self::destroyLxc($vmid, $hostNode);
    }

    /**
     * @return array{ok: bool, message?: string, proxmox_status?: string, whmcs_status?: string, heartbeat?: bool, version?: string, version_ok?: bool, deploy_status?: string, partial?: bool, warnings?: list<string>}
     */
    private static function verifyProvisionedWorker(
        int $vmid,
        string $hostNode,
        string $nodeId,
        string $apiUrl,
        string $tokenId,
        string $tokenSecret
    ): array {
        $warnings = [];
        $proxmoxStatus = null;
        $deadline = time() + 75;
        while (time() < $deadline) {
            $proxmoxStatus = self::lxcStatus($apiUrl, $hostNode, $vmid, $tokenId, $tokenSecret);
            if ($proxmoxStatus === 'running') {
                break;
            }
            sleep(2);
        }
        if ($proxmoxStatus !== 'running') {
            return [
                'ok' => false,
                'message' => 'Proxmox CT ' . $vmid . ' did not reach running within 75s (last status: '
                    . ($proxmoxStatus ?? 'unknown') . ')',
                'proxmox_status' => $proxmoxStatus,
                'vmid' => $vmid,
                'node_id' => $nodeId,
            ];
        }

        $whmcsStatus = 'registering';
        $heartbeat = false;
        $version = '';
        $deployStatus = '';
        $registerDeadline = time() + 150;
        while (time() < $registerDeadline) {
            $node = WorkerNodeRepository::get($nodeId);
            if ($node === null) {
                break;
            }
            $whmcsStatus = (string) ($node['status'] ?? 'registering');
            $heartbeat = !empty($node['last_heartbeat_at']);
            $version = (string) ($node['version'] ?? '');
            $deployStatus = (string) ($node['deploy_status'] ?? '');
            if ($whmcsStatus === 'active' && $heartbeat) {
                break;
            }
            sleep(3);
        }

        if ($whmcsStatus !== 'active' || !$heartbeat) {
            $registerHint = self::registerFailureHint($vmid, $hostNode, $apiUrl, $tokenId, $tokenSecret);
            $message = 'Worker node ' . $nodeId . ' did not register within 150s (status=' . $whmcsStatus
                . ', heartbeat=' . ($heartbeat ? 'yes' : 'no') . ')';
            if ($registerHint !== '') {
                $message .= '. ' . $registerHint;
            }

            return [
                'ok' => false,
                'message' => $message,
                'proxmox_status' => $proxmoxStatus,
                'whmcs_status' => $whmcsStatus,
                'heartbeat' => $heartbeat,
                'vmid' => $vmid,
                'node_id' => $nodeId,
            ];
        }

        $latest = ReleaseRepository::latest();
        $targetVersion = $latest !== null ? (string) ($latest['version'] ?? '') : '';
        $versionOk = $targetVersion === ''
            || $version === ''
            || !ReleaseRepository::nodeNeedsUpdate($version, $targetVersion);
        $deployOk = in_array($deployStatus, ['current', 'updating', 'pending'], true);
        $partial = false;
        if (!$versionOk && !$deployOk) {
            $partial = true;
            $warnings[] = 'Worker version ' . ($version !== '' ? $version : '(empty)')
                . ' does not match latest ' . $targetVersion . ' (deploy_status=' . $deployStatus . ')';
        } elseif (!$versionOk && $deployOk) {
            $warnings[] = 'Baseline auto-update in progress (deploy_status=' . $deployStatus . ')';
        }

        return [
            'ok' => true,
            'proxmox_status' => $proxmoxStatus,
            'whmcs_status' => $whmcsStatus,
            'heartbeat' => $heartbeat,
            'version' => $version,
            'version_ok' => $versionOk,
            'deploy_status' => $deployStatus,
            'target_version' => $targetVersion,
            'partial' => $partial,
            'warnings' => $warnings,
            'vmid' => $vmid,
            'node_id' => $nodeId,
        ];
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

    /** @return array{ok: bool, message?: string} */
    private static function waitForProxmoxTask(
        string $apiUrl,
        string $node,
        string $upid,
        string $tokenId,
        string $tokenSecret,
        int $timeoutSeconds = 180
    ): array {
        $deadline = time() + max(1, $timeoutSeconds);
        $path = '/nodes/' . rawurlencode($node) . '/tasks/' . rawurlencode($upid) . '/status';

        while (time() < $deadline) {
            $res = self::apiGet($apiUrl, $path, $tokenId, $tokenSecret);
            if (($res['status'] ?? '') !== 'success') {
                sleep(1);
                continue;
            }
            $decoded = json_decode((string) ($res['raw'] ?? ''), true);
            $data = is_array($decoded) ? ($decoded['data'] ?? []) : [];
            if (!is_array($data)) {
                sleep(1);
                continue;
            }
            $status = trim((string) ($data['status'] ?? ''));
            if ($status === 'running') {
                sleep(2);
                continue;
            }
            if ($status === 'stopped') {
                $exitStatus = trim((string) ($data['exitstatus'] ?? ''));
                if ($exitStatus === 'OK') {
                    return ['ok' => true];
                }

                return [
                    'ok' => false,
                    'message' => $exitStatus !== '' ? $exitStatus : 'task stopped without OK exit status',
                ];
            }
            sleep(1);
        }

        return ['ok' => false, 'message' => 'timed out after ' . $timeoutSeconds . 's waiting for task ' . $upid];
    }

    private static function parseUpidFromResponse(string $raw): ?string
    {
        if ($raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }
        $upid = trim((string) ($decoded['data'] ?? ''));
        if ($upid === '' || !str_starts_with($upid, 'UPID:')) {
            return null;
        }

        return $upid;
    }

    private static function nodeFromUpid(string $upid): ?string
    {
        $parts = explode(':', $upid, 3);
        $node = trim((string) ($parts[1] ?? ''));

        return $node !== '' ? $node : null;
    }

    private static function isCtLockedError(string $message): bool
    {
        $lower = strtolower($message);

        return str_contains($lower, 'locked') || str_contains($lower, 'ct is locked');
    }

    /** @param array<string, mixed> $body */
    private static function apiPost(string $base, string $path, array $body, string $tokenId, string $tokenSecret): array
    {
        return self::apiRequest($base, $path, 'POST', $body, $tokenId, $tokenSecret);
    }

    /** @param array<string, mixed> $body */
    private static function apiPostWithLockRetry(
        string $base,
        string $path,
        array $body,
        string $tokenId,
        string $tokenSecret,
        int $maxAttempts = 5,
        int $initialDelaySeconds = 2
    ): array {
        return self::apiRequestWithLockRetry($base, $path, 'POST', $body, $tokenId, $tokenSecret, $maxAttempts, $initialDelaySeconds);
    }

    /** @param array<string, mixed> $body */
    private static function apiPut(string $base, string $path, array $body, string $tokenId, string $tokenSecret): array
    {
        return self::apiRequest($base, $path, 'PUT', $body, $tokenId, $tokenSecret);
    }

    /** @param array<string, mixed> $body */
    private static function apiPutWithLockRetry(
        string $base,
        string $path,
        array $body,
        string $tokenId,
        string $tokenSecret,
        int $maxAttempts = 5,
        int $initialDelaySeconds = 2
    ): array {
        return self::apiRequestWithLockRetry($base, $path, 'PUT', $body, $tokenId, $tokenSecret, $maxAttempts, $initialDelaySeconds);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private static function apiRequestWithLockRetry(
        string $base,
        string $path,
        string $method,
        array $body,
        string $tokenId,
        string $tokenSecret,
        int $maxAttempts = 5,
        int $initialDelaySeconds = 2
    ): array {
        $maxAttempts = max(1, $maxAttempts);
        $delay = max(1, $initialDelaySeconds);
        $lastRes = ['status' => 'error', 'message' => 'unknown'];

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $lastRes = self::apiRequest($base, $path, $method, $body, $tokenId, $tokenSecret);
            if (($lastRes['status'] ?? '') === 'success') {
                return $lastRes;
            }
            $message = (string) ($lastRes['message'] ?? '');
            if (!self::isCtLockedError($message) || $attempt >= $maxAttempts) {
                return $lastRes;
            }
            sleep($delay);
            $delay = min($delay * 2, 10);
        }

        return $lastRes;
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
            return ['status' => 'error', 'message' => self::formatProxmoxApiError($code, (string) $raw)];
        }

        return ['status' => 'success', 'raw' => $raw];
    }

    private static function formatProxmoxApiError(int $code, string $raw): string
    {
        $decoded = self::decodeProxmoxErrorBody($raw);
        $permMsg = trim((string) ($decoded['message'] ?? ''));
        if ($code === 403 && $permMsg !== '') {
            if (preg_match('#/vms/(\d+),\s*VM\.Clone#', $permMsg, $m)) {
                $vmid = (int) $m[1];

                return 'Proxmox API token lacks VM.Clone on template VMID '
                    . $vmid
                    . '. Grant role privileges VM.Clone on ACL path /vms/'
                    . $vmid
                    . ' (or /vms with propagate), plus Datastore.Allocate on target storage ('
                    . self::lxcCloneStorage()
                    . ').'
                    . ' Proxmox: ' . $permMsg;
            }
            if (str_contains($permMsg, 'Datastore.Allocate')) {
                $storage = self::lxcCloneStorage();

                return 'Proxmox API token lacks Datastore.Allocate on target storage (clone uses storage '
                    . $storage
                    . ').'
                    . ' Grant Datastore.Allocate and Datastore.AllocateSpace on /storage/'
                    . $storage
                    . ' for each fleet node.'
                    . ' Proxmox: ' . $permMsg;
            }
            if (str_contains($permMsg, 'VM.Config') || str_contains($permMsg, 'VM.PowerMgmt')) {
                return 'Proxmox API token lacks post-clone privileges (VM.Config / VM.PowerMgmt on worker VMIDs).'
                    . ' Proxmox: ' . $permMsg;
            }

            return 'Proxmox permission denied (HTTP 403): ' . $permMsg;
        }

        return 'Proxmox HTTP ' . $code . ($raw !== '' ? ': ' . $raw : '');
    }

    /** @return array<string, mixed> */
    private static function decodeProxmoxErrorBody(string $raw): array
    {
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private static function enrichCloneError(string $message, string $sourceNode, string $targetNode, int $templateVmid, int $attemptedVmid = 0): string
    {
        if (self::isVmidAlreadyExistsError($message)) {
            $message = 'CT VMID ' . ($attemptedVmid > 0 ? $attemptedVmid : '?')
                . ' already exists on node ' . $targetNode
                . '. An orphaned container may block scale-up — destroy it on the host'
                . ' (e.g. pct destroy ' . ($attemptedVmid > 0 ? $attemptedVmid : '<vmid>') . ')'
                . ' or retire the matching fleet row, then retry.'
                . ' Proxmox: ' . $message;
        }
        if ($sourceNode !== $targetNode) {
            $message .= sprintf(
                ' Cross-node clone: template VMID %d on %s → workers on %s.',
                $templateVmid,
                $sourceNode,
                $targetNode
            );
            if (str_contains(strtolower($message), 'non-shared storage')) {
                $message .= ' Use shared storage (set proxmox_lxc_storage, e.g. ceph-rbd)'
                    . ' and ensure the golden template rootfs is on that pool.';
            }
            if (self::templateVmidMap() === []) {
                $message .= ' Empty proxmox_template_vmid_map — using proxmox_node as template home.'
                    . ' If each node has a local copy of the template, set proxmox_template_vmid_map,'
                    . ' e.g. {"'
                    . $targetNode
                    . '":'
                    . $templateVmid
                    . '}.';
            }
        } else {
            $message .= sprintf(' Clone template VMID %d on node %s.', $templateVmid, $sourceNode);
        }

        return $message;
    }

    private static function isVmidAlreadyExistsError(string $message): bool
    {
        $lower = strtolower($message);

        return str_contains($lower, 'already exists')
            || (str_contains($lower, 'ct ') && str_contains($lower, 'exists'));
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
            return ['status' => 'error', 'message' => self::formatProxmoxApiError($code, (string) $raw)];
        }

        return ['status' => 'success'];
    }

    /**
     * @param array<string, mixed> $parentRun
     * @param list<string> $childRunIds
     * @param list<array<string, mixed>> $assignments
     * @return list<string>
     */
    public static function buildJournalCommands(array $parentRun, array $childRunIds, array $assignments): array
    {
        $since = self::journalSince($parentRun);
        $until = self::journalUntil($parentRun);
        $vmids = [];
        foreach ($assignments as $a) {
            $vmid = (int) ($a['proxmox_vmid'] ?? 0);
            if ($vmid > 0) {
                $vmids[$vmid] = (string) ($a['hostname'] ?? ('vmid-' . $vmid));
            }
        }
        $cmds = [];
        foreach ($vmids as $vmid => $host) {
            $base = 'pct exec ' . $vmid . ' -- journalctl -u ms365-backup-worker --since "' . $since . '" --until "' . $until . '" --no-pager';
            if ($childRunIds === []) {
                $cmds[] = '# ' . $host . "\n" . $base;
                continue;
            }
            foreach ($childRunIds as $runId) {
                if ($runId === '') {
                    continue;
                }
                $cmds[] = '# ' . $host . ' — grep ' . $runId . "\n" . $base . ' | grep ' . $runId;
            }
        }

        return $cmds;
    }

    /**
     * @param array<string, mixed> $parentRun
     * @param list<string> $childRunIds
     * @param list<array<string, mixed>> $assignments
     */
    public static function fetchBatchWorkerJournal(array $parentRun, array $childRunIds, array $assignments): string
    {
        if ($childRunIds === [] || $assignments === []) {
            return '';
        }
        if (!self::isRecentBatch($parentRun, 7)) {
            return '';
        }

        $apiUrl = rtrim(Ms365EngineConfig::moduleSettingPublic('proxmox_api_url', ''), '/');
        $node = Ms365EngineConfig::moduleSettingPublic('proxmox_node', '');
        $tokenId = Ms365EngineConfig::moduleSettingPublic('proxmox_api_token_id', '');
        $tokenSecret = Ms365EngineConfig::moduleSettingPublic('proxmox_api_token_secret', '');
        if ($apiUrl === '' || $node === '' || $tokenId === '' || $tokenSecret === '') {
            return '';
        }

        $since = self::journalSince($parentRun);
        $until = self::journalUntil($parentRun);
        $grepPattern = implode('|', array_map('preg_quote', array_filter($childRunIds)));
        if ($grepPattern === '') {
            return '';
        }

        $vmids = [];
        foreach ($assignments as $a) {
            $vmid = (int) ($a['proxmox_vmid'] ?? 0);
            if ($vmid > 0) {
                $vmids[$vmid] = (string) ($a['hostname'] ?? '');
            }
        }

        $out = [];
        foreach ($vmids as $vmid => $host) {
            $text = self::execWorkerJournal($apiUrl, $node, $vmid, $since, $until, $grepPattern, $tokenId, $tokenSecret);
            if ($text !== '') {
                $out[] = '### ' . ($host !== '' ? $host : 'vmid-' . $vmid) . ' ###';
                $out[] = $text;
            }
        }

        $joined = implode("\n", $out);

        return strlen($joined) > 512000 ? substr($joined, 0, 512000) . "\n…(truncated)" : $joined;
    }

    public static function fetchWorkerJournal(
        int $vmid,
        string $since,
        string $until,
        ?string $grep = null
    ): string {
        $apiUrl = rtrim(Ms365EngineConfig::moduleSettingPublic('proxmox_api_url', ''), '/');
        $node = Ms365EngineConfig::moduleSettingPublic('proxmox_node', '');
        $tokenId = Ms365EngineConfig::moduleSettingPublic('proxmox_api_token_id', '');
        $tokenSecret = Ms365EngineConfig::moduleSettingPublic('proxmox_api_token_secret', '');
        if ($apiUrl === '' || $node === '' || $tokenId === '' || $tokenSecret === '' || $vmid <= 0) {
            return '';
        }

        return self::execWorkerJournal($apiUrl, $node, $vmid, $since, $until, $grep ?? '', $tokenId, $tokenSecret);
    }

    private static function execWorkerJournal(
        string $apiUrl,
        string $node,
        int $vmid,
        string $since,
        string $until,
        string $grepPattern,
        string $tokenId,
        string $tokenSecret
    ): string {
        $shell = 'journalctl -u ms365-backup-worker --since ' . escapeshellarg($since)
            . ' --until ' . escapeshellarg($until) . ' --no-pager -n 2000 2>/dev/null';
        if ($grepPattern !== '') {
            $shell .= ' | grep -E ' . escapeshellarg($grepPattern) . ' || true';
        }

        $sshTarget = self::proxmoxShellTarget();
        $execRes = self::execLxcShell($apiUrl, $node, $vmid, $shell, $tokenId, $tokenSecret);
        if ($execRes['ok']) {
            $output = (string) ($execRes['output'] ?? '');
        } elseif ($sshTarget !== '') {
            $remoteCmd = 'pct exec ' . $vmid . ' -- /bin/sh -c ' . escapeshellarg($shell);
            $sshRes = self::runSshCommand($sshTarget, $remoteCmd);
            if ($sshRes['ok']) {
                $output = (string) ($sshRes['output'] ?? '');
            } else {
                return '';
            }
        } else {
            return '';
        }

        return strlen($output) > 512000 ? substr($output, 0, 512000) . "\n…(truncated)" : $output;
    }

    /** @param array<string, mixed> $parentRun */
    private static function journalSince(array $parentRun): string
    {
        $started = (string) ($parentRun['started_at'] ?? $parentRun['created_at'] ?? '');
        if ($started === '') {
            return '-1 day';
        }
        try {
            $dt = new \DateTime($started);

            return $dt->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return '-1 day';
        }
    }

    /** @param array<string, mixed> $parentRun */
    private static function journalUntil(array $parentRun): string
    {
        $finished = (string) ($parentRun['finished_at'] ?? '');
        if ($finished !== '') {
            try {
                $dt = new \DateTime($finished);
                $dt->modify('+5 minutes');

                return $dt->format('Y-m-d H:i:s');
            } catch (\Throwable $e) {
                // fall through
            }
        }

        return 'now';
    }

    /** @param array<string, mixed> $parentRun */
    private static function isRecentBatch(array $parentRun, int $days): bool
    {
        $started = (string) ($parentRun['started_at'] ?? $parentRun['created_at'] ?? '');
        if ($started === '') {
            return false;
        }
        try {
            $dt = new \DateTime($started);
            $cutoff = new \DateTime('-' . max(1, $days) . ' days');

            return $dt >= $cutoff;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function apiGet(string $base, string $path, string $tokenId, string $tokenSecret): array
    {
        $url = $base . $path;
        $ch = curl_init($url);
        if ($ch === false) {
            return ['status' => 'error', 'message' => 'curl init failed'];
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => [
                'Authorization: PVEAPIToken=' . $tokenId . '=' . $tokenSecret,
            ],
            CURLOPT_TIMEOUT => 20,
        ]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false || $code >= 400) {
            return ['status' => 'error', 'message' => self::formatProxmoxApiError($code, (string) $raw)];
        }

        return ['status' => 'success', 'raw' => $raw];
    }

    private const WORKER_ENV_DROPIN = '/etc/systemd/system/ms365-backup-worker.service.d/environment.conf';

    /** @return array{ok: bool, method?: string, message?: string, warnings?: list<string>} */
    private static function injectWorkerEnvironment(
        int $vmid,
        string $hostNode,
        string $apiUrl,
        string $tokenId,
        string $tokenSecret
    ): array {
        $content = self::buildWorkerEnvironmentConf($vmid);
        $b64 = base64_encode($content);
        $dropin = self::WORKER_ENV_DROPIN;
        $shell = 'mkdir -p ' . escapeshellarg(dirname($dropin))
            . ' && echo ' . escapeshellarg($b64) . ' | base64 -d > ' . escapeshellarg($dropin)
            . ' && chmod 600 ' . escapeshellarg($dropin);

        $execRes = self::execLxcShell($apiUrl, $hostNode, $vmid, $shell, $tokenId, $tokenSecret);
        if ($execRes['ok']) {
            FleetAuditLog::write(
                'provision_env_inject',
                'Injected worker environment.conf into VMID ' . $vmid . ' via LXC exec',
                'vmid',
                (string) $vmid,
                ['proxmox_node' => $hostNode, 'method' => 'lxc_exec']
            );

            return ['ok' => true, 'method' => 'lxc_exec'];
        }

        $sshTarget = self::proxmoxShellTarget();
        if ($sshTarget !== '') {
            $hostTmp = '/tmp/ms365-worker-env-' . $vmid . '.conf';
            $remoteCmd = 'echo ' . escapeshellarg($b64)
                . ' | base64 -d > ' . escapeshellarg($hostTmp)
                . ' && pct push ' . $vmid . ' ' . escapeshellarg($hostTmp) . ' ' . escapeshellarg($dropin)
                . ' && rm -f ' . escapeshellarg($hostTmp);
            $sshRes = self::runSshCommand($sshTarget, $remoteCmd);
            if ($sshRes['ok']) {
                FleetAuditLog::write(
                    'provision_env_inject',
                    'Injected worker environment.conf into VMID ' . $vmid . ' via SSH pct push',
                    'vmid',
                    (string) $vmid,
                    ['proxmox_node' => $hostNode, 'method' => 'ssh_pct_push']
                );

                return ['ok' => true, 'method' => 'ssh_pct_push'];
            }

            $sshDetail = (string) ($sshRes['message'] ?? 'unknown');
            $lxcNote = self::isLxcExecUnavailable($execRes)
                ? 'LXC exec API also unavailable on this Proxmox build.'
                : 'LXC exec failed: ' . ($execRes['message'] ?? 'unknown') . '.';

            return self::skipEnvInjectUsingTemplate($vmid, $hostNode, [
                'SSH environment injection failed (' . $sshDetail . '); ' . $lxcNote,
                'Relying on golden template environment.conf. Expected MS365_WORKER_API_BASE='
                    . FleetSettings::workerApiBaseUrl()
                    . '. Fix proxmox_ssh_target / proxmox_ssh_identity or bake env into template VMID '
                    . Ms365EngineConfig::moduleSettingPublic('proxmox_lxc_template_vmid', '9010') . '.',
            ]);
        }

        if (self::isLxcExecUnavailable($execRes)) {
            return self::skipEnvInjectUsingTemplate($vmid, $hostNode, [
                'LXC exec API unavailable on this Proxmox version; relying on golden template environment.conf.'
                    . ' Expected MS365_WORKER_API_BASE=' . FleetSettings::workerApiBaseUrl()
                    . '. Set proxmox_ssh_target (e.g. root@192.168.92.195) to inject env on every clone.',
            ]);
        }

        return [
            'ok' => false,
            'message' => 'Failed to inject worker environment.conf into VMID ' . $vmid . ': '
                . ($execRes['message'] ?? 'unknown'),
        ];
    }

    /** @param list<string> $warnings */
    private static function skipEnvInjectUsingTemplate(int $vmid, string $hostNode, array $warnings): array
    {
        FleetAuditLog::write(
            'provision_env_inject',
            'Skipped environment.conf injection for VMID ' . $vmid . ' — using golden template',
            'vmid',
            (string) $vmid,
            ['proxmox_node' => $hostNode, 'method' => 'skipped', 'warnings' => $warnings]
        );

        return ['ok' => true, 'method' => 'skipped', 'warnings' => $warnings];
    }

    private static function buildWorkerEnvironmentConf(int $vmid = 0): string
    {
        $token = Ms365EngineConfig::workerToken();
        if ($token === '') {
            throw new \RuntimeException('ms365_worker_token is not configured');
        }
        $apiBase = FleetSettings::workerApiBaseUrl();

        $lines = [
            '[Service]',
            'Environment=MS365_WORKER_TOKEN=' . $token,
            'Environment=MS365_WORKER_API_BASE=' . $apiBase,
        ];
        if ($vmid > 0) {
            $lines[] = 'Environment=PROXMOX_VMID=' . $vmid;
        }

        return implode("\n", $lines) . "\n";
    }

    private static function proxmoxShellTarget(): string
    {
        $target = trim(Ms365EngineConfig::moduleSettingPublic('proxmox_ssh_target', ''));
        if ($target !== '') {
            return $target;
        }

        return trim(Ms365EngineConfig::moduleSettingPublic('proxmox_shell_target', ''));
    }

    /** Proxmox storage pool for LXC full-clone rootfs (shared storage enables cross-node scale-up). */
    public static function lxcCloneStorage(): string
    {
        $storage = trim(Ms365EngineConfig::moduleSettingPublic('proxmox_lxc_storage', ''));
        if ($storage === '') {
            return 'local-lvm';
        }

        return $storage;
    }

    private static function proxmoxSshIdentity(): string
    {
        $identity = trim(Ms365EngineConfig::moduleSettingPublic('proxmox_ssh_identity', ''));
        if ($identity !== '') {
            return $identity;
        }

        $identity = trim(Ms365EngineConfig::moduleSettingPublic('proxmox_shell_identity', ''));
        if ($identity !== '') {
            return $identity;
        }

        foreach ([
            '/var/www/.ssh/ms365_proxmox_ed25519',
            '/var/www/.ssh/id_rsa',
            '/var/www/.ssh/id_ed25519',
        ] as $candidate) {
            if (is_readable($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    /** @return array{ok: bool, message?: string, output?: string} */
    private static function runSshCommand(string $target, string $remoteCommand): array
    {
        $identity = self::proxmoxSshIdentity();
        $cmd = ['ssh', '-o', 'BatchMode=yes', '-o', 'StrictHostKeyChecking=accept-new', '-o', 'ConnectTimeout=15'];
        if ($identity !== '') {
            $cmd[] = '-i';
            $cmd[] = $identity;
        }
        $cmd[] = $target;
        $cmd[] = $remoteCommand;
        $descriptorSpec = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = proc_open($cmd, $descriptorSpec, $pipes);
        if (!is_resource($proc)) {
            return ['ok' => false, 'message' => 'ssh proc_open failed'];
        }
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($proc);
        $output = trim((string) $stdout);
        if ($exit === 0) {
            return ['ok' => true, 'output' => $output];
        }
        $detail = trim($stderr !== '' ? $stderr : $output);
        if ($detail !== '' && str_contains($detail, 'Permission denied')) {
            $hint = $identity !== ''
                ? ' (identity: ' . $identity . ')'
                : ' (no SSH identity configured; set proxmox_ssh_identity or install /var/www/.ssh/ms365_proxmox_ed25519)';
            if (!str_contains($detail, $hint)) {
                $detail .= $hint;
            }
        }

        return ['ok' => false, 'message' => $detail !== '' ? $detail : 'ssh exit ' . $exit, 'output' => $output];
    }

    /** @return array{ok: bool, message?: string, output?: string} */
    private static function execLxcShell(
        string $apiUrl,
        string $hostNode,
        int $vmid,
        string $shell,
        string $tokenId,
        string $tokenSecret
    ): array {
        $path = '/nodes/' . rawurlencode($hostNode) . '/lxc/' . $vmid . '/exec';
        $res = self::apiPost($apiUrl, $path, [
            'command' => ['/bin/sh', '-c', $shell],
        ], $tokenId, $tokenSecret);
        if (($res['status'] ?? '') !== 'success') {
            return ['ok' => false, 'message' => (string) ($res['message'] ?? 'exec request failed')];
        }
        $decoded = json_decode((string) ($res['raw'] ?? ''), true);
        $pid = (int) ($decoded['data']['pid'] ?? 0);
        if ($pid <= 0) {
            return ['ok' => false, 'message' => 'exec returned no pid'];
        }

        $deadline = time() + 30;
        $output = '';
        while (time() < $deadline) {
            usleep(300000);
            $statusPath = '/nodes/' . rawurlencode($hostNode) . '/lxc/' . $vmid . '/exec-status?pid=' . $pid;
            $statusRes = self::apiGet($apiUrl, $statusPath, $tokenId, $tokenSecret);
            if (($statusRes['status'] ?? '') !== 'success') {
                continue;
            }
            $statusData = json_decode((string) ($statusRes['raw'] ?? ''), true);
            $data = $statusData['data'] ?? [];
            if (!is_array($data)) {
                continue;
            }
            if (!empty($data['out-data'])) {
                $output .= (string) $data['out-data'];
            }
            if (!empty($data['err-data'])) {
                $output .= (string) $data['err-data'];
            }
            if (($data['exited'] ?? 0) == 1) {
                $exitCode = (int) ($data['exitcode'] ?? 1);
                if ($exitCode === 0) {
                    return ['ok' => true, 'output' => $output];
                }
                $err = trim((string) ($data['err-data'] ?? ''));

                return [
                    'ok' => false,
                    'message' => $err !== '' ? $err : 'exec exit ' . $exitCode,
                    'output' => $output,
                ];
            }
        }

        return ['ok' => false, 'message' => 'exec timed out waiting for pid ' . $pid, 'output' => $output];
    }

    /** @param array{ok: bool, message?: string} $execRes */
    private static function isLxcExecUnavailable(array $execRes): bool
    {
        $msg = strtolower((string) ($execRes['message'] ?? ''));

        return str_contains($msg, 'not implemented')
            || str_contains($msg, 'no pid')
            || str_contains($msg, 'http 501')
            || preg_match('/\b501\b/', $msg) === 1;
    }

    private static function registerFailureHint(
        int $vmid,
        string $hostNode,
        string $apiUrl,
        string $tokenId,
        string $tokenSecret
    ): string {
        $journal = self::fetchWorkerJournal($vmid, '-3 min', 'now', 'register');
        if ($journal !== '' && (str_contains($journal, 'register.php http 404') || str_contains($journal, 'ms365_worker_register.php http 404'))) {
            return 'Worker journal shows register HTTP 404 — MS365_WORKER_API_BASE is likely missing '
                . '/modules/addons/cloudstorage/api (expected ' . FleetSettings::workerApiBaseUrl() . '). '
                . 'Patch environment.conf on the Proxmox host and restart ms365-backup-worker, or set proxmox_ssh_target for post-clone injection.';
        }
        $expected = FleetSettings::workerApiBaseUrl();
        $wrongBase = preg_replace('#/modules/addons/cloudstorage/api$#', '', $expected);
        if ($wrongBase !== '' && $wrongBase !== $expected) {
            return 'If the worker cannot reach ' . $expected . ', check MS365_WORKER_API_BASE in environment.conf '
                . '(common mistake: ' . $wrongBase . ' without /modules/addons/cloudstorage/api).';
        }

        return '';
    }
}
