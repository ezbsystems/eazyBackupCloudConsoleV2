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

    $whmcsCometUsernames = eb_ph_discover_msp_comet_usernames($clientId);
    if ($whmcsCometUsernames !== []) {
        $assignedCometIds = array_map(
            static fn(array $r): string => (string)($r['comet_user_id'] ?? ''),
            $assignedRows
        );
        $existingUnassignedIds = array_map(
            static fn(array $r): string => (string)($r['comet_user_id'] ?? ''),
            $unassignedRows
        );
        $knownIds = array_merge($assignedCometIds, $existingUnassignedIds);

        foreach ($whmcsCometUsernames as $username) {
            if (in_array($username, $knownIds, true)) {
                continue;
            }
            if ($q !== '' && stripos($username, $q) === false) {
                continue;
            }
            $unassignedRows[] = [
                'comet_user_id' => $username,
                'comet_username' => $username,
                'tenant_name' => '',
                'tenant_public_id' => '',
            ];
        }
    }

    eb_ph_user_assignments_sort_rows($assignedRows, $sort, $dir);
    eb_ph_user_assignments_sort_rows($unassignedRows, $sort, $dir);

    $baseLink = eb_ph_tenants_base_link($vars);
    foreach ($assignedRows as &$row) {
        $row['tenant_url'] = $baseLink . '&a=ph-tenant&id=' . rawurlencode((string)($row['tenant_public_id'] ?? ''));
        $row['plans_url'] = $baseLink . '&a=ph-catalog-plans';
    }
    unset($row);

    foreach ($unassignedRows as &$row) {
        $row['tenant_url'] = $baseLink . '&a=ph-tenant&id=' . rawurlencode((string)($row['tenant_public_id'] ?? ''));
        $row['plans_url'] = $baseLink . '&a=ph-catalog-plans';
    }
    unset($row);

    $mspId = (int)($msp->id ?? 0);
    $assignTenants = [];
    try {
        $assignTenants = Capsule::table('eb_tenants')
            ->where('msp_id', $mspId)
            ->where('status', '!=', 'deleted')
            ->orderBy('name', 'asc')
            ->get(['public_id', 'name'])
            ->map(fn($r) => (array)$r)
            ->toArray();
    } catch (\Throwable $__) {}

    $assignPlans = [];
    try {
        if (Capsule::schema()->hasTable('eb_plan_templates')) {
            $planRows = Capsule::table('eb_plan_templates')
                ->where('msp_id', $mspId)
                ->where('status', 'active')
                ->orderBy('name', 'asc')
                ->get(['id', 'name', 'description'])
                ->map(fn($r) => (array)$r)
                ->toArray();

            $metricsByPlan = [];
            if ($planRows !== [] && Capsule::schema()->hasTable('eb_plan_components')) {
                $rows = Capsule::table('eb_plan_components as pc')
                    ->leftJoin('eb_catalog_prices as pr', 'pr.id', '=', 'pc.price_id')
                    ->leftJoin('eb_catalog_products as p', 'p.id', '=', 'pr.product_id')
                    ->whereIn('pc.plan_id', array_map(static fn(array $r): int => (int)($r['id'] ?? 0), $planRows))
                    ->get(['pc.plan_id', 'pr.metric_code as price_metric', 'p.base_metric_code as product_base_metric'])
                    ->map(fn($r) => (array)$r)
                    ->toArray();
                foreach ($rows as $c) {
                    $pid = (int)($c['plan_id'] ?? 0);
                    $metric = strtoupper(trim((string)($c['price_metric'] ?? $c['product_base_metric'] ?? '')));
                    if ($pid > 0 && $metric !== '') {
                        $metricsByPlan[$pid][$metric] = true;
                    }
                }
            }

            foreach ($planRows as $planRow) {
                $pid = (int)($planRow['id'] ?? 0);
                $metrics = array_keys($metricsByPlan[$pid] ?? []);
                $nonStorage = array_filter($metrics, static fn(string $m): bool => $m !== 'STORAGE_TB');
                $planRow['requires_comet_user'] = count($metrics) === 0 ? true : count($nonStorage) > 0;
                $assignPlans[] = $planRow;
            }
        }
    } catch (\Throwable $__) {}

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
            'assign_tenants' => $assignTenants,
            'assign_plans' => $assignPlans,
            'token' => function_exists('generate_token') ? generate_token('plain') : '',
        ],
    ];
}
