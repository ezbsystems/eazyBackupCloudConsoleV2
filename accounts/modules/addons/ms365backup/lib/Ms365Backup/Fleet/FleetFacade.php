<?php
declare(strict_types=1);

namespace Ms365Backup\Fleet;

use Ms365Backup\ProxmoxProvisioner;
use Ms365Backup\WorkerClaimService;
use Ms365Backup\WorkerNodeRepository;

/**
 * Routes fleet admin operations to local services or production remote API.
 */
final class FleetFacade
{
    public static function resolveFleetFromRequest(?string $requestFleet = null): string
    {
        if ($requestFleet !== null && trim($requestFleet) !== '' && FleetContext::isDevelopmentServer()) {
            FleetContext::setActiveFleet(trim($requestFleet));
        }

        return FleetContext::activeFleet();
    }

    /** @return array<string, mixed> */
    public static function summary(?string $fleet = null): array
    {
        $fleet = FleetContext::normalizeFleet($fleet ?? FleetContext::activeFleet());
        if (FleetContext::isRemoteFleet($fleet)) {
            $res = FleetRemoteClient::get('fleet_summary');

            return is_array($res['summary'] ?? null) ? $res['summary'] : $res;
        }

        return FleetSummaryService::summary();
    }

    /** @return list<array<string, mixed>> */
    public static function nodes(?string $statusCsv = null, ?string $fleet = null): array
    {
        $fleet = FleetContext::normalizeFleet($fleet ?? FleetContext::activeFleet());
        if (FleetContext::isRemoteFleet($fleet)) {
            $params = [];
            if ($statusCsv !== null && trim($statusCsv) !== '') {
                $params['status'] = trim($statusCsv);
            }
            $res = FleetRemoteClient::get('fleet_nodes', $params);

            return is_array($res['nodes'] ?? null) ? $res['nodes'] : [];
        }

        $statuses = $statusCsv !== null && trim($statusCsv) !== ''
            ? array_map('trim', explode(',', $statusCsv))
            : [];

        return WorkerNodeRepository::listNodes($statuses);
    }

    /** @return list<array<string, mixed>> */
    public static function audit(int $limit = 50, ?string $fleet = null): array
    {
        $fleet = FleetContext::normalizeFleet($fleet ?? FleetContext::activeFleet());
        if (FleetContext::isRemoteFleet($fleet)) {
            $res = FleetRemoteClient::get('fleet_audit', ['limit' => $limit]);

            return is_array($res['entries'] ?? null) ? $res['entries'] : [];
        }

        return FleetAuditLog::recent($limit);
    }

    /** @return array<string, mixed> */
    public static function nodeTelemetry(string $nodeId, int $limit = 96, ?string $fleet = null): array
    {
        $fleet = FleetContext::normalizeFleet($fleet ?? FleetContext::activeFleet());
        if (FleetContext::isRemoteFleet($fleet)) {
            return FleetRemoteClient::get('fleet_node_telemetry', [
                'node_id' => $nodeId,
                'limit' => $limit,
            ]);
        }

        return [
            'ok' => true,
            'node' => WorkerNodeRepository::get($nodeId),
            'history' => WorkerNodeRepository::telemetryHistory($nodeId, $limit),
        ];
    }

    public static function nodeDrain(string $nodeId, ?string $fleet = null): void
    {
        self::mutateNode('fleet_node_drain', $nodeId, $fleet, static function () use ($nodeId): void {
            WorkerNodeRepository::drain($nodeId);
            FleetAuditLog::write('node_drain', 'Node set to draining', 'node', $nodeId);
        });
    }

    public static function nodeActivate(string $nodeId, ?string $fleet = null): void
    {
        self::mutateNode('fleet_node_activate', $nodeId, $fleet, static function () use ($nodeId): void {
            WorkerNodeRepository::activate($nodeId);
            FleetAuditLog::write('node_activate', 'Node reactivated from draining', 'node', $nodeId);
        });
    }

    public static function nodeRetire(string $nodeId, ?string $fleet = null): void
    {
        self::mutateNode('fleet_node_retire', $nodeId, $fleet, static function () use ($nodeId): void {
            WorkerNodeRepository::retire($nodeId);
            FleetAuditLog::write('node_retire', 'Node retired', 'node', $nodeId);
        });
    }

    public static function nodeDelete(string $nodeId, ?string $fleet = null): void
    {
        $fleet = FleetContext::normalizeFleet($fleet ?? FleetContext::activeFleet());
        if (FleetContext::isRemoteFleet($fleet)) {
            FleetRemoteClient::post('fleet_node_delete', ['node_id' => $nodeId]);

            return;
        }
        if (!WorkerNodeRepository::deleteRetired($nodeId)) {
            throw new \RuntimeException('Only retired nodes can be deleted');
        }
        FleetAuditLog::write('node_delete', 'Retired node removed', 'node', $nodeId);
    }

    public static function nodeSetVmid(string $nodeId, int $vmid, ?string $fleet = null): void
    {
        self::mutateNode('fleet_node_set_vmid', $nodeId, $fleet, static function () use ($nodeId, $vmid): void {
            WorkerNodeRepository::setProxmoxVmid($nodeId, $vmid);
            FleetAuditLog::write('node_set_vmid', 'Set Proxmox VMID to ' . $vmid, 'node', $nodeId);
        }, ['proxmox_vmid' => $vmid]);
    }

    public static function nodeStop(string $nodeId, ?string $fleet = null): void
    {
        $fleet = FleetContext::normalizeFleet($fleet ?? FleetContext::activeFleet());
        if (FleetContext::isRemoteFleet($fleet)) {
            $node = self::requireNode($nodeId, $fleet);
            ProxmoxProvisioner::stopLxcByVmid(
                (int) ($node['proxmox_vmid'] ?? 0),
                (string) ($node['proxmox_node'] ?? '')
            );
            FleetRemoteClient::post('fleet_node_stop', ['node_id' => $nodeId]);

            return;
        }
        ProxmoxProvisioner::stopWorker($nodeId);
        FleetAuditLog::write('node_stop', 'Worker container stopped', 'node', $nodeId);
    }

    public static function nodeStart(string $nodeId, ?string $fleet = null): void
    {
        $fleet = FleetContext::normalizeFleet($fleet ?? FleetContext::activeFleet());
        if (FleetContext::isRemoteFleet($fleet)) {
            $node = self::requireNode($nodeId, $fleet);
            ProxmoxProvisioner::startLxcByVmid(
                (int) ($node['proxmox_vmid'] ?? 0),
                (string) ($node['proxmox_node'] ?? '')
            );
            FleetRemoteClient::post('fleet_node_start', ['node_id' => $nodeId]);

            return;
        }
        ProxmoxProvisioner::startWorker($nodeId);
        FleetAuditLog::write('node_start', 'Worker container started', 'node', $nodeId);
    }

    /** @return array{released: int, recovered: int, orphans_requeued: int} */
    public static function releaseLeases(?string $fleet = null): array
    {
        $fleet = FleetContext::normalizeFleet($fleet ?? FleetContext::activeFleet());
        if (FleetContext::isRemoteFleet($fleet)) {
            $res = FleetRemoteClient::post('fleet_release_leases');

            return [
                'released' => (int) ($res['released'] ?? 0),
                'recovered' => (int) ($res['recovered'] ?? 0),
                'orphans_requeued' => (int) ($res['orphans_requeued'] ?? 0),
            ];
        }

        return [
            'released' => WorkerClaimService::releaseExpiredLeases(),
            'recovered' => WorkerClaimService::recoverStaleRunning(),
            'orphans_requeued' => WorkerClaimService::releaseOrphanedClaimsForAllNodes(120),
        ];
    }

    /** @return array<string, string> */
    public static function settingsGet(?string $fleet = null): array
    {
        $fleet = FleetContext::normalizeFleet($fleet ?? FleetContext::activeFleet());
        $config = FleetSettings::publicConfig($fleet);
        if (FleetContext::isRemoteFleet($fleet)) {
            try {
                $res = FleetRemoteClient::get('fleet_settings_get');
                if (is_array($res['settings'] ?? null)) {
                    $config = array_merge($config, $res['settings']);
                }
            } catch (\Throwable $e) {
                $config['remote_settings_error'] = $e->getMessage();
            }
        }

        return $config;
    }

    /** @return array<string, mixed> */
    public static function scaleUp(string $proxmoxNode, int $count, ?string $fleet = null): array
    {
        $fleet = FleetContext::normalizeFleet($fleet ?? FleetContext::activeFleet());

        return ProxmoxProvisioner::scaleUp($proxmoxNode, $count, $fleet);
    }

    /** @return array<string, mixed> */
    public static function configGet(?string $fleet = null): array
    {
        $fleet = FleetContext::normalizeFleet($fleet ?? FleetContext::activeFleet());
        if (FleetContext::isRemoteFleet($fleet)) {
            $res = FleetRemoteClient::get('fleet_config_get');

            return [
                'version' => (int) ($res['version'] ?? 0),
                'sha256' => (string) ($res['sha256'] ?? ''),
                'yaml' => (string) ($res['yaml'] ?? ''),
                'status' => is_array($res['status'] ?? null) ? $res['status'] : WorkerConfigService::statusSummary(),
            ];
        }

        $current = WorkerConfigService::current();
        $yaml = $current ? (string) ($current['yaml'] ?? '') : WorkerConfigService::templateYaml();

        return [
            'version' => $current ? (int) $current['version'] : 0,
            'sha256' => $current ? (string) ($current['sha256'] ?? '') : '',
            'yaml' => $yaml,
            'status' => WorkerConfigService::statusSummary(),
        ];
    }

    /** @return array<string, mixed> */
    public static function configSave(string $yaml, bool $validateOnly, ?int $adminId, ?string $fleet = null): array
    {
        $fleet = FleetContext::normalizeFleet($fleet ?? FleetContext::activeFleet());
        if (FleetContext::isRemoteFleet($fleet)) {
            $res = FleetRemoteClient::post('fleet_config_save', [
                'yaml' => $yaml,
                'validate_only' => $validateOnly ? '1' : '0',
                'admin_id' => $adminId,
            ]);
            if ($validateOnly) {
                return ['ok' => true, 'valid' => true, 'yaml' => (string) ($res['yaml'] ?? $yaml)];
            }

            return ['ok' => true, 'version' => (int) ($res['version'] ?? 0), 'sha256' => (string) ($res['sha256'] ?? '')];
        }

        $validated = WorkerConfigService::validateYaml($yaml);
        if ($validated['errors'] !== []) {
            return ['ok' => false, 'errors' => $validated['errors'], 'yaml' => $validated['yaml']];
        }
        if ($validateOnly) {
            return ['ok' => true, 'valid' => true, 'yaml' => $validated['yaml']];
        }
        $saved = WorkerConfigService::saveNewVersion($validated['yaml'], $adminId);

        return ['ok' => true, 'version' => $saved['version'], 'sha256' => $saved['sha256']];
    }

    /** @param list<string> $nodeIds */
    public static function configRollout(int $version, array $nodeIds, string $strategy, ?string $fleet = null): array
    {
        $fleet = FleetContext::normalizeFleet($fleet ?? FleetContext::activeFleet());
        if (FleetContext::isRemoteFleet($fleet)) {
            $res = FleetRemoteClient::post('fleet_config_rollout', [
                'config_version' => $version,
                'strategy' => $strategy,
                'node_ids' => implode(',', $nodeIds),
            ]);

            return is_array($res) ? $res : ['ok' => true];
        }

        return WorkerConfigService::rollout($version, $nodeIds, $strategy) + ['ok' => true];
    }

    /** @return array<string, mixed> */
    public static function configStatus(?string $fleet = null): array
    {
        $fleet = FleetContext::normalizeFleet($fleet ?? FleetContext::activeFleet());
        if (FleetContext::isRemoteFleet($fleet)) {
            $res = FleetRemoteClient::get('fleet_config_status');

            return is_array($res['status'] ?? null) ? $res['status'] : [];
        }

        return WorkerConfigService::statusSummary();
    }

    /** @return list<array<string, mixed>> */
    public static function releaseList(?string $fleet = null): array
    {
        $fleet = FleetContext::normalizeFleet($fleet ?? FleetContext::activeFleet());
        if (FleetContext::isRemoteFleet($fleet)) {
            $res = FleetRemoteClient::get('fleet_release_list');

            return is_array($res['releases'] ?? null) ? $res['releases'] : [];
        }

        return ReleaseRepository::listRecent(25);
    }

    /** @return array<string, mixed> */
    public static function deployCreate(
        int $releaseId,
        string $strategy,
        bool $force,
        ?string $canaryNodeId,
        ?int $adminId,
        ?string $fleet = null
    ): array {
        $fleet = FleetContext::normalizeFleet($fleet ?? FleetContext::activeFleet());
        if (FleetContext::isRemoteFleet($fleet)) {
            $params = [
                'release_id' => $releaseId,
                'strategy' => $strategy,
                'force_deploy' => $force ? '1' : '0',
                'admin_id' => $adminId,
            ];
            if ($canaryNodeId !== null && $canaryNodeId !== '') {
                $params['canary_node_id'] = $canaryNodeId;
            }
            $res = FleetRemoteClient::post('fleet_deploy_create', $params);

            return $res;
        }

        return ['ok' => true] + DeployService::startDeploy($releaseId, $strategy, $force, $canaryNodeId, $adminId);
    }

    /** @return list<array<string, mixed>> */
    public static function deployList(?string $fleet = null): array
    {
        $fleet = FleetContext::normalizeFleet($fleet ?? FleetContext::activeFleet());
        if (FleetContext::isRemoteFleet($fleet)) {
            $res = FleetRemoteClient::get('fleet_deploy_list');

            return is_array($res['jobs'] ?? null) ? $res['jobs'] : [];
        }

        return DeployService::listDeployJobs(25);
    }

    /** @return array<string, mixed> */
    public static function releaseSyncToProduction(int $releaseId): array
    {
        return ReleaseSyncService::publishToProduction($releaseId);
    }

    /** @param array<string, scalar|null> $extra */
    private static function mutateNode(string $remoteOp, string $nodeId, ?string $fleet, callable $local, array $extra = []): void
    {
        $fleet = FleetContext::normalizeFleet($fleet ?? FleetContext::activeFleet());
        if (FleetContext::isRemoteFleet($fleet)) {
            FleetRemoteClient::post($remoteOp, array_merge(['node_id' => $nodeId], $extra));

            return;
        }
        $local();
    }

    /** @return array<string, mixed> */
    private static function requireNode(string $nodeId, string $fleet): array
    {
        foreach (self::nodes('', $fleet) as $node) {
            if ((string) ($node['node_id'] ?? '') === $nodeId) {
                return $node;
            }
        }
        throw new \RuntimeException('Node not found on production fleet');
    }
}
