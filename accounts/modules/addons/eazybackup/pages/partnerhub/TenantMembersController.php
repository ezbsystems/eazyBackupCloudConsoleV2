<?php

use WHMCS\Database\Capsule;

require_once __DIR__ . '/TenantsController.php';

function eb_ph_tenant_members(array $vars)
{
    [$clientId, $msp, $tenantId, $tenant] = eb_ph_tenant_require_owned($vars);

    $rows = [];
    $error = '';
    try {
        if (!Capsule::schema()->hasTable('eb_tenant_users')) {
            $error = 'tenant_members_table_missing';
        } else {
            $result = Capsule::table('eb_tenant_users')
                ->where('tenant_id', $tenantId)
                ->orderByRaw("CASE WHEN role = 'admin' THEN 0 ELSE 1 END")
                ->orderBy('name', 'asc')
                ->orderBy('id', 'asc')
                ->get([
                    'id',
                    'email',
                    'name',
                    'role',
                    'status',
                    'last_login_at',
                    'created_at',
                    'updated_at',
                ]);
            foreach ($result as $row) {
                $member = (array)$row;
                $role = strtolower(trim((string)($member['role'] ?? 'user')));
                $status = strtolower(trim((string)($member['status'] ?? 'disabled')));
                $member['role'] = in_array($role, ['admin', 'user'], true) ? $role : 'user';
                $member['status'] = in_array($status, ['active', 'disabled'], true) ? $status : 'disabled';
                $rows[] = $member;
            }
        }
    } catch (\Throwable $__) {
        $error = 'tenant_members_query_failed';
    }

    return eb_ph_tenant_shell_response($vars, (array)$msp, (array)$tenant, 'members', [
        'members' => $rows,
        'members_error' => $error,
    ]);
}
