<?php

use WHMCS\Database\Capsule;

require_once __DIR__ . '/TenantsController.php';

function eb_ph_user_assignments_sort_rows(array &$rows, string $sort, string $dir): void
{
    usort($rows, static function (array $left, array $right) use ($sort, $dir): int {
        $value = static function (array $row) use ($sort): string {
            return match ($sort) {
                'tenant' => strtolower((string)($row['tenant_name'] ?? '')),
                'plan' => strtolower((string)($row['plan_name'] ?? '')),
                'status' => strtolower((string)($row['status'] ?? '')),
                'since' => strtolower((string)($row['created_at'] ?? '')),
                default => strtolower((string)($row['comet_user_id'] ?? '')),
            };
        };
        $a = $value($left);
        $b = $value($right);
        if ($a === $b) {
            return 0;
        }
        return $dir === 'asc' ? ($a <=> $b) : ($b <=> $a);
    });
}

function eb_ph_user_assignments(array $vars)
{
    [$clientId, $msp] = eb_ph_tenants_require_context($vars);
    unset($clientId);

    $q = trim((string)($_GET['q'] ?? ''));
    $sort = strtolower(trim((string)($_GET['sort'] ?? 'username')));
    if (!in_array($sort, ['username', 'tenant', 'plan', 'status', 'since'], true)) {
        $sort = 'username';
    }
    $dir = strtolower(trim((string)($_GET['dir'] ?? 'asc'))) === 'desc' ? 'desc' : 'asc';

    $assignedQuery = Capsule::table('eb_plan_instances as pi')
        ->join('eb_tenants as t', 't.id', '=', 'pi.tenant_id')
        ->leftJoin('eb_plan_templates as pt', 'pt.id', '=', 'pi.plan_id')
        ->where('pi.msp_id', (int)$msp->id)
        ->whereIn('pi.status', ['active', 'trialing', 'past_due', 'paused'])
        ->where('t.status', '!=', 'deleted');

    $unassignedQuery = Capsule::table('eb_tenant_comet_accounts as tca')
        ->join('eb_tenants as t', 't.id', '=', 'tca.tenant_id')
        ->leftJoin('eb_plan_instances as pi', function ($join): void {
            $join->on('pi.comet_user_id', '=', 'tca.comet_user_id')
                ->whereIn('pi.status', ['active', 'trialing', 'past_due', 'paused']);
        })
        ->where('t.msp_id', (int)$msp->id)
        ->where('t.status', '!=', 'deleted')
        ->whereNull('pi.id');

    if ($q !== '') {
        $assignedQuery->where(function ($where) use ($q): void {
            $like = '%' . $q . '%';
            $where->where('pi.comet_user_id', 'like', $like)
                ->orWhere('t.name', 'like', $like)
                ->orWhere('pt.name', 'like', $like);
        });
        $unassignedQuery->where(function ($where) use ($q): void {
            $like = '%' . $q . '%';
            $where->where('tca.comet_user_id', 'like', $like)
                ->orWhere('tca.comet_username', 'like', $like)
                ->orWhere('t.name', 'like', $like);
        });
    }

    $assignedRows = [];
    foreach ($assignedQuery->get([
        'pi.id as plan_instance_id',
        'pi.comet_user_id',
        'pi.status',
        'pi.created_at',
        't.name as tenant_name',
        't.public_id as tenant_public_id',
        'pt.name as plan_name',
    ]) as $row) {
        $assignedRows[] = (array)$row;
    }

    $unassignedRows = [];
    foreach ($unassignedQuery->get([
        'tca.comet_user_id',
        'tca.comet_username',
        't.name as tenant_name',
        't.public_id as tenant_public_id',
    ]) as $row) {
        $unassignedRows[] = (array)$row;
    }

    eb_ph_user_assignments_sort_rows($assignedRows, $sort, $dir);
    eb_ph_user_assignments_sort_rows($unassignedRows, $sort, $dir);

    foreach ($assignedRows as &$row) {
        $row['tenant_url'] = eb_ph_tenants_base_link($vars) . '&a=ph-tenant&id=' . rawurlencode((string)($row['tenant_public_id'] ?? ''));
        $row['plans_url'] = eb_ph_tenants_base_link($vars) . '&a=ph-catalog-plans';
    }
    unset($row);

    foreach ($unassignedRows as &$row) {
        $row['tenant_url'] = eb_ph_tenants_base_link($vars) . '&a=ph-tenant&id=' . rawurlencode((string)($row['tenant_public_id'] ?? ''));
        $row['plans_url'] = eb_ph_tenants_base_link($vars) . '&a=ph-catalog-plans';
    }
    unset($row);

    return [
        'pagetitle' => 'User Assignments',
        'templatefile' => 'whitelabel/user-assignments',
        'breadcrumb' => ['index.php?m=eazybackup' => 'eazyBackup'],
        'requirelogin' => true,
        'forcessl' => true,
        'vars' => [
            'modulelink' => $vars['modulelink'] ?? 'index.php?m=eazybackup',
            'msp' => $msp,
            'q' => $q,
            'sort' => $sort,
            'dir' => $dir,
            'assigned_rows' => $assignedRows,
            'unassigned_rows' => $unassignedRows,
        ],
    ];
}
