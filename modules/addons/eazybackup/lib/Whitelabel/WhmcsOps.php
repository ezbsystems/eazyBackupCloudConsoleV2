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

    public function cloneProduct(int $templatePid, int $groupId, string $name): int
    {
        $tpl = Capsule::table('tblproducts')->where('id', $templatePid)->first();
        if (!$tpl) { return 0; }
        $row = (array)$tpl; unset($row['id']);
        $row['gid'] = $groupId; $row['name'] = $name;
        return (int)Capsule::table('tblproducts')->insertGetId($row);
    }
}


