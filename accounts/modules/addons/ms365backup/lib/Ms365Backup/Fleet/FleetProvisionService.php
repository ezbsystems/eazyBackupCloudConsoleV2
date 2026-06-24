<?php
declare(strict_types=1);

namespace Ms365Backup\Fleet;

use Ms365Backup\ProxmoxProvisioner;
use Ms365Backup\WorkerNodeRepository;

/**
 * Pre-create registering worker rows and allocate VMIDs before Proxmox clone.
 */
final class FleetProvisionService
{
    /** @return list<array{node_id: string, vmid: int, hostname: string, proxmox_node: string}> */
    public static function prepareSlots(string $proxmoxNode, int $count, string $hostnamePrefix = 'ms365-worker-', array $blockedVmids = []): array
    {
        $proxmoxNode = trim($proxmoxNode);
        $count = max(1, min(20, $count));
        if ($proxmoxNode === '') {
            throw new \RuntimeException('proxmox_node required');
        }

        $prepared = [];
        $localBlocked = $blockedVmids;
        for ($i = 0; $i < $count; $i++) {
            $vmid = ProxmoxProvisioner::allocateNextVmid($proxmoxNode, $localBlocked);
            $hostname = $hostnamePrefix . $vmid;
            $nodeId = WorkerNodeRepository::registerProvisioning($hostname, $vmid, $proxmoxNode);
            $prepared[] = [
                'node_id' => $nodeId,
                'vmid' => $vmid,
                'hostname' => $hostname,
                'proxmox_node' => $proxmoxNode,
            ];
            $localBlocked[$vmid] = $vmid;
        }

        return $prepared;
    }

    public static function abandonSlot(?string $nodeId, int $vmid): void
    {
        WorkerNodeRepository::abandonProvisioning($nodeId, $vmid);
    }
}
