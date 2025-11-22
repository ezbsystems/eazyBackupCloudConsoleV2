<?php

namespace EazyBackup\Whitelabel;

use WHMCS\Database\Capsule;

class WhmcsOps
{
    public function addServerAndGroup(string $name, string $hostname, string $module = 'comet'): array
    {
        $sid = Capsule::table('tblservers')->insertGetId([
            'name' => $name,
            'hostname' => $hostname,
            'ipaddress' => '',
            'type' => $module,
            'active' => 1,
        ]);
        $gid = Capsule::table('tblservergroups')->insertGetId(['name' => $name . ' Group']);
        Capsule::table('tblservergroupsrel')->insert(['groupid' => $gid, 'serverid' => $sid]);
        return ['server_id' => (int)$sid, 'servergroup_id' => (int)$gid];
    }

    /**
     * Ensure there is exactly one product group mapped for the given client and return its ID.
     * - Canonical source of truth is tbl_client_productgroup_map (by client_id).
     * - Never associate by matching names.
     * - If mapping exists but points to a missing group, create a new group and update mapping.
     * - If mapping does not exist, create a new group (named by company or fallback to client ID) and insert mapping.
     */
    public function ensureClientProductGroup(int $clientId): int
    {
        try {
            $map = Capsule::table('tbl_client_productgroup_map')
                ->where('client_id', $clientId)
                ->first();

            if ($map) {
                $gid = (int)($map->product_group_id ?? 0);
                if ($gid > 0 && Capsule::table('tblproductgroups')->where('id', $gid)->exists()) {
                    return $gid;
                }
                // Mapping exists but group missing → create a fresh group and update mapping
                $newGid = $this->createProductGroupForClient($clientId);
                if ($newGid > 0) {
                    Capsule::table('tbl_client_productgroup_map')
                        ->where('id', (int)$map->id)
                        ->update([
                            'product_group_id' => (int)$newGid,
                            'created_at' => date('Y-m-d H:i:s'),
                        ]);
                }
                return (int)$newGid;
            }

            // No mapping → create a new group and insert mapping
            $newGid = $this->createProductGroupForClient($clientId);
            if ($newGid > 0) {
                Capsule::table('tbl_client_productgroup_map')->updateOrInsert(
                    ['client_id' => (int)$clientId],
                    [
                        'product_group_id' => (int)$newGid,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]
                );
            }
            return (int)$newGid;

        } catch (\Throwable $e) {
            try { logModuleCall('eazybackup', 'ensure_client_product_group_exception', ['client_id' => $clientId], $e->getMessage()); } catch (\Throwable $__) {}
            return 0;
        }
    }

    /**
     * Create a product group for the client. Name by company, else fallback to "Client #<id>".
     */
    private function createProductGroupForClient(int $clientId): int
    {
        $client = Capsule::table('tblclients')
            ->select('companyname')
            ->where('id', $clientId)
            ->first();

        $company = $client ? trim((string)($client->companyname ?? '')) : '';
        $groupName = $company !== '' ? $company : (string)$clientId;

        return (int)Capsule::table('tblproductgroups')->insertGetId(['name' => $groupName]);
    }

    public function cloneProduct(int $templatePid, int $groupId, string $name): int
    {
        // Clone core product row
        $tpl = Capsule::table('tblproducts')->where('id', $templatePid)->first();
        if (!$tpl) {
            return 0;
        }

        $row = (array) $tpl;
        unset($row['id']);
        $row['gid']  = $groupId;
        $row['name'] = $name;

        $newPid = (int) Capsule::table('tblproducts')->insertGetId($row);
        if ($newPid <= 0) {
            return 0;
        }

        // Clone product pricing rows (monthly/annual/etc.) so tenant plans inherit
        // the same enabled cycles and amounts as the template product.
        try {
            $prices = Capsule::table('tblpricing')
                ->where('type', 'product')
                ->where('relid', $templatePid)
                ->get();

            foreach ($prices as $p) {
                $pr = (array) $p;
                unset($pr['id']);
                $pr['relid'] = $newPid;
                Capsule::table('tblpricing')->insert($pr);
            }
        } catch (\Throwable $e) {
            try {
                logModuleCall(
                    'eazybackup',
                    'clone_product_pricing_exception',
                    ['templatePid' => $templatePid, 'newPid' => $newPid],
                    $e->getMessage()
                );
            } catch (\Throwable $__) {
                // ignore logging failures
            }
        }

        return $newPid;
    }
}


