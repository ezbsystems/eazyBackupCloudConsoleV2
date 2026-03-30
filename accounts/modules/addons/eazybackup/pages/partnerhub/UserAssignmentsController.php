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
                default => strtolower((string)($row['username_sort'] ?? $row['comet_user_display'] ?? $row['comet_user_id'] ?? $row['comet_username'] ?? '')),
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

function eb_ph_user_assignments_display_label(string $cometUserId, array $s3UsersById): string
{
    $cometUserId = trim($cometUserId);
    if ($cometUserId === '') {
        return '';
    }

    if (preg_match('/^e3:(\d+)$/', $cometUserId, $matches)) {
        $s3UserId = (int)($matches[1] ?? 0);
        $displayLabel = trim((string)($s3UsersById[$s3UserId]['display_label'] ?? ''));
        return $displayLabel !== '' ? 'S3: ' . $displayLabel : $cometUserId;
    }

    if (strpos($cometUserId, 'storage:') === 0) {
        return 'Tenant-level (legacy)';
    }

    return $cometUserId;
}

function eb_ph_user_assignments_row_key(array $row): string
{
    $tenantPublicId = trim((string)($row['tenant_public_id'] ?? ''));
    $identifier = trim((string)($row['comet_user_id'] ?? $row['comet_username'] ?? ''));
    return $tenantPublicId . '|' . $identifier;
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

    $assignTenants = [];
    foreach (Capsule::table('eb_tenants')
        ->where('msp_id', (int)$msp->id)
        ->where('status', '!=', 'deleted')
        ->orderBy('name', 'asc')
        ->get(['public_id', 'name']) as $tenantRow) {
        $tenantRow = (array)$tenantRow;
        $assignTenants[] = [
            'public_id' => (string)($tenantRow['public_id'] ?? ''),
            'name' => (string)($tenantRow['name'] ?? ''),
        ];
    }

    $assignPlans = [];
    foreach (Capsule::table('eb_plan_templates')
        ->where('msp_id', (int)$msp->id)
        ->where('status', 'active')
        ->orderBy('name', 'asc')
        ->get(['id', 'name']) as $planRow) {
        $planRow = (array)$planRow;
        $planId = (int)($planRow['id'] ?? 0);
        if ($planId <= 0) {
            continue;
        }

        $assignPlans[] = [
            'id' => $planId,
            'name' => (string)($planRow['name'] ?? ''),
            'assignment_mode' => eb_ph_plan_assignment_mode((int)$planRow['id']),
        ];
    }

    $s3Users = eb_ph_discover_msp_s3_users($clientId);
    $s3UsersById = [];
    foreach ($s3Users as $s3User) {
        $s3UserId = (int)($s3User['id'] ?? 0);
        if ($s3UserId > 0) {
            $s3UsersById[$s3UserId] = $s3User;
        }
    }

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

    $rowIndex = [];
    foreach ($assignedRows as $row) {
        $rowKey = eb_ph_user_assignments_row_key($row);
        if ($rowKey !== '|') {
            $rowIndex[$rowKey] = true;
        }
    }
    foreach ($unassignedRows as $row) {
        $rowKey = eb_ph_user_assignments_row_key($row);
        if ($rowKey !== '|') {
            $rowIndex[$rowKey] = true;
        }
    }

    try {
        if (Capsule::schema()->hasTable('eb_service_links')) {
            $serviceLinksQuery = Capsule::table('eb_service_links as sl')
                ->join('eb_tenants as t', 't.id', '=', 'sl.tenant_id')
                ->leftJoin('eb_plan_instances as pi', function ($join): void {
                    $join->on('pi.tenant_id', '=', 'sl.tenant_id')
                        ->on('pi.comet_user_id', '=', 'sl.comet_user_id')
                        ->whereIn('pi.status', ['active', 'trialing', 'past_due', 'paused']);
                })
                ->where('sl.msp_id', (int)$msp->id)
                ->where('t.status', '!=', 'deleted')
                ->whereNotNull('sl.comet_user_id')
                ->where('sl.comet_user_id', '!=', '')
                ->whereNull('pi.id');

            if ($q !== '') {
                $serviceLinksQuery->where(function ($where) use ($q): void {
                    $like = '%' . $q . '%';
                    $where->where('sl.comet_user_id', 'like', $like)
                        ->orWhere('t.name', 'like', $like);
                });
            }

            foreach ($serviceLinksQuery->get([
                'sl.comet_user_id',
                't.name as tenant_name',
                't.public_id as tenant_public_id',
            ]) as $row) {
                $candidate = (array)$row;
                $candidate['comet_username'] = (string)($candidate['comet_user_id'] ?? '');
                $rowKey = eb_ph_user_assignments_row_key($candidate);
                if ($rowKey === '|' || isset($rowIndex[$rowKey])) {
                    continue;
                }
                $unassignedRows[] = $candidate;
                $rowIndex[$rowKey] = true;
            }
        }
    } catch (\Throwable $__) {}

    $whmcsCometUsernames = eb_ph_discover_msp_comet_usernames($clientId);
    foreach ($whmcsCometUsernames as $username) {
        $rowKey = '|' . $username;
        if (isset($rowIndex[$rowKey])) {
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
        $rowIndex[$rowKey] = true;
    }

    foreach ($assignedRows as &$row) {
        $row['comet_user_display'] = eb_ph_user_assignments_display_label((string)($row['comet_user_id'] ?? ''), $s3UsersById);
        $row['username_sort'] = $row['comet_user_display'];
        $row['tenant_url'] = eb_ph_tenants_base_link($vars) . '&a=ph-tenant&id=' . rawurlencode((string)($row['tenant_public_id'] ?? ''));
        $row['plans_url'] = eb_ph_tenants_base_link($vars) . '&a=ph-catalog-plans';
    }
    unset($row);

    foreach ($unassignedRows as &$row) {
        $row['comet_user_display'] = eb_ph_user_assignments_display_label(
            (string)($row['comet_user_id'] ?? $row['comet_username'] ?? ''),
            $s3UsersById
        );
        $row['username_sort'] = (string)(
            ($row['comet_user_id'] ?? '') !== ''
                ? $row['comet_user_id']
                : ($row['comet_username'] ?? '')
        );
        $row['tenant_url'] = eb_ph_tenants_base_link($vars) . '&a=ph-tenant&id=' . rawurlencode((string)($row['tenant_public_id'] ?? ''));
        $row['plans_url'] = eb_ph_tenants_base_link($vars) . '&a=ph-catalog-plans';
    }
    unset($row);

    eb_ph_user_assignments_sort_rows($assignedRows, $sort, $dir);
    eb_ph_user_assignments_sort_rows($unassignedRows, $sort, $dir);

    return [
        'pagetitle' => 'User Assignments',
        'templatefile' => 'whitelabel/user-assignments',
        'breadcrumb' => ['index.php?m=eazybackup' => 'eazyBackup'],
        'requirelogin' => true,
        'forcessl' => true,
        'vars' => [
            'modulelink' => $vars['modulelink'] ?? 'index.php?m=eazybackup',
            'token' => function_exists('generate_token') ? generate_token('plain') : '',
            'msp' => $msp,
            'q' => $q,
            'sort' => $sort,
            'dir' => $dir,
            'assign_tenants' => $assignTenants,
            'assign_tenants_json' => json_encode($assignTenants, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
            'assign_plans' => $assignPlans,
            'assign_plans_json' => json_encode($assignPlans, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
            'assigned_rows' => $assignedRows,
            'unassigned_rows' => $unassignedRows,
            's3_users' => $s3Users,
            's3_users_json' => json_encode($s3Users, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
        ],
    ];
}
