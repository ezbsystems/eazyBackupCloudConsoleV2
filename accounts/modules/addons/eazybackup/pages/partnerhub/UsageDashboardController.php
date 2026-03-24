<?php

use WHMCS\Database\Capsule;
use PartnerHub\StripeService;
use function PartnerHub\computeBillableMeteredUsage;

require_once __DIR__ . '/TenantsController.php';
require_once __DIR__ . '/../../lib/PartnerHub/MeteredUsage.php';

function eb_ph_usage_dashboard_metric_options(): array
{
    return [
        '' => 'All Metrics',
        'STORAGE_TB' => 'Storage',
        'DEVICE_COUNT' => 'Devices',
        'DISK_IMAGE' => 'Disk Images',
        'HYPERV_VM' => 'Hyper-V VMs',
        'PROXMOX_VM' => 'Proxmox VMs',
        'VMWARE_VM' => 'VMware VMs',
        'M365_USER' => 'M365 Users',
        'GENERIC' => 'Generic',
    ];
}

function eb_ph_usage_dashboard_metric_label(string $metricCode): string
{
    $options = eb_ph_usage_dashboard_metric_options();
    return $options[$metricCode] ?? ($metricCode !== '' ? $metricCode : 'Unknown');
}

function eb_ph_usage_dashboard_status_label(int $usedQty, int $includedQty, string $overageMode): string
{
    if ($usedQty > $includedQty && strtolower($overageMode) === 'cap_at_default') {
        return 'capped';
    }
    if ($includedQty <= 0) {
        return $usedQty > 0 ? 'over_quota' : 'healthy';
    }
    if ($usedQty >= $includedQty) {
        return 'over_quota';
    }
    if ($usedQty >= (int)ceil($includedQty * 0.8)) {
        return 'at_risk';
    }
    return 'healthy';
}

function eb_ph_usage_dashboard_status_badge(string $status): string
{
    return [
        'over_quota' => 'Over Quota',
        'at_risk' => 'At Risk',
        'capped' => 'Capped',
        'healthy' => 'Healthy',
    ][$status] ?? 'Healthy';
}

function eb_ph_usage_dashboard_rows(int $mspId, string $metricFilter, string $query): array
{
    $rows = Capsule::table('eb_plan_instance_items as pii')
        ->join('eb_plan_instances as pi', 'pi.id', '=', 'pii.plan_instance_id')
        ->join('eb_tenants as t', 't.id', '=', 'pi.tenant_id')
        ->leftJoin('eb_plan_templates as pt', 'pt.id', '=', 'pi.plan_id')
        ->leftJoin('eb_plan_components as pc', 'pc.id', '=', 'pii.plan_component_id')
        ->where('pi.msp_id', $mspId)
        ->whereIn('pi.status', ['active', 'trialing', 'past_due', 'paused'])
        ->where('t.status', '!=', 'deleted');

    if ($metricFilter !== '') {
        $rows->where('pii.metric_code', $metricFilter);
    }
    if ($query !== '') {
        $rows->where(function ($where) use ($query): void {
            $like = '%' . $query . '%';
            $where->where('t.name', 'like', $like)
                ->orWhere('pi.comet_user_id', 'like', $like)
                ->orWhere('pt.name', 'like', $like)
                ->orWhere('pii.metric_code', 'like', $like);
        });
    }

    $result = $rows
        ->orderBy('t.name', 'asc')
        ->orderBy('pii.metric_code', 'asc')
        ->get([
            'pii.id as plan_instance_item_id',
            'pii.metric_code',
            'pii.last_qty',
            'pii.stripe_subscription_item_id',
            'pi.id as plan_instance_id',
            'pi.comet_user_id',
            'pi.status as plan_instance_status',
            'pi.stripe_account_id',
            't.id as tenant_id',
            't.name as tenant_name',
            't.public_id as tenant_public_id',
            'pt.name as plan_name',
            'pc.default_qty',
            'pc.overage_mode',
        ]);

    $rowsArr = [];
    foreach ($result as $row) {
        $rowsArr[] = (array)$row;
    }
    return $rowsArr;
}

function eb_ph_usage_dashboard_logs(array $itemIds): array
{
    $logs = [];
    if ($itemIds === [] || !Capsule::schema()->hasTable('eb_usage_ledger')) {
        return $logs;
    }

    $rows = Capsule::table('eb_usage_ledger')
        ->whereIn('plan_instance_item_id', $itemIds)
        ->orderBy('id', 'desc')
        ->get([
            'id',
            'plan_instance_item_id',
            'metric',
            'qty',
            'period_start',
            'period_end',
            'source',
            'pushed_to_stripe_at',
            'created_at',
        ]);

    foreach ($rows as $row) {
        $itemId = (int)($row->plan_instance_item_id ?? 0);
        if ($itemId <= 0) {
            continue;
        }
        if (!isset($logs[$itemId])) {
            $logs[$itemId] = [];
        }
        if (count($logs[$itemId]) >= 10) {
            continue;
        }
        $logs[$itemId][] = (array)$row;
    }

    return $logs;
}

function eb_ph_usage_dashboard_latest_rows(array $itemIds): array
{
    $latest = [];
    if ($itemIds === [] || !Capsule::schema()->hasTable('eb_usage_ledger')) {
        return $latest;
    }

    $rows = Capsule::table('eb_usage_ledger')
        ->whereIn('id', function ($query) use ($itemIds): void {
            $query->from('eb_usage_ledger')
                ->selectRaw('MAX(id)')
                ->whereIn('plan_instance_item_id', $itemIds)
                ->groupBy('plan_instance_item_id');
        })
        ->get([
            'id',
            'plan_instance_item_id',
            'qty',
            'period_end',
            'pushed_to_stripe_at',
            'created_at',
        ]);

    foreach ($rows as $row) {
        $latest[(int)($row->plan_instance_item_id ?? 0)] = (array)$row;
    }

    return $latest;
}

function eb_ph_usage_dashboard(array $vars)
{
    [$clientId, $msp] = eb_ph_tenants_require_context($vars);
    unset($clientId);

    try { if (function_exists('eazybackup_migrate_schema')) { @eazybackup_migrate_schema(); } } catch (\Throwable $__) {}

    $metricOptions = eb_ph_usage_dashboard_metric_options();
    $metric = strtoupper(trim((string)($_GET['metric'] ?? '')));
    if (!array_key_exists($metric, $metricOptions)) {
        $metric = '';
    }

    $status = strtolower(trim((string)($_GET['status'] ?? '')));
    if (!in_array($status, ['', 'over_quota', 'at_risk', 'capped', 'healthy'], true)) {
        $status = '';
    }

    $q = trim((string)($_GET['q'] ?? ''));
    $sort = strtolower(trim((string)($_GET['sort'] ?? 'last_push')));
    if (!in_array($sort, ['tenant', 'plan', 'metric', 'included', 'used', 'status', 'last_push'], true)) {
        $sort = 'last_push';
    }
    $dir = strtolower(trim((string)($_GET['dir'] ?? 'desc'))) === 'asc' ? 'asc' : 'desc';

    $baseRows = eb_ph_usage_dashboard_rows((int)$msp->id, $metric, $q);
    $itemIds = array_values(array_filter(array_map(static fn(array $row): int => (int)($row['plan_instance_item_id'] ?? 0), $baseRows)));
    $latestRows = eb_ph_usage_dashboard_latest_rows($itemIds);
    $logsByItem = eb_ph_usage_dashboard_logs($itemIds);

    $usageRows = [];
    $tenantOverQuota = [];
    $lastUsagePush = '';

    foreach ($baseRows as $row) {
        $itemId = (int)($row['plan_instance_item_id'] ?? 0);
        $latest = $latestRows[$itemId] ?? [];
        $includedQty = max(0, (int)($row['default_qty'] ?? 0));
        $usedQty = isset($latest['qty']) ? max(0, (int)$latest['qty']) : max(0, (int)($row['last_qty'] ?? 0));
        $overageMode = (string)($row['overage_mode'] ?? 'bill_all');
        $statusKey = eb_ph_usage_dashboard_status_label($usedQty, $includedQty, $overageMode);
        if ($status !== '' && $statusKey !== $status) {
            continue;
        }

        $overageQty = max(0, $usedQty - $includedQty);
        $usagePct = $includedQty > 0 ? min(999, (int)round(($usedQty / max(1, $includedQty)) * 100)) : ($usedQty > 0 ? 100 : 0);
        if ($overageQty > 0) {
            $tenantOverQuota[(string)($row['tenant_public_id'] ?? '')] = true;
        }
        $lastPushAt = (string)($latest['pushed_to_stripe_at'] ?? '');
        if ($lastPushAt !== '' && ($lastUsagePush === '' || strcmp($lastPushAt, $lastUsagePush) > 0)) {
            $lastUsagePush = $lastPushAt;
        }

        $usageRows[] = [
            'plan_instance_item_id' => $itemId,
            'plan_instance_id' => (int)($row['plan_instance_id'] ?? 0),
            'tenant_id' => (int)($row['tenant_id'] ?? 0),
            'tenant_name' => (string)($row['tenant_name'] ?? 'Unknown Tenant'),
            'tenant_public_id' => (string)($row['tenant_public_id'] ?? ''),
            'tenant_url' => eb_ph_tenants_base_link($vars) . '&a=ph-tenant&id=' . rawurlencode((string)($row['tenant_public_id'] ?? '')) . '&tab=billing',
            'plan_name' => (string)($row['plan_name'] ?? 'Unknown Plan'),
            'plan_url' => eb_ph_tenants_base_link($vars) . '&a=ph-catalog-plans',
            'metric_code' => (string)($row['metric_code'] ?? ''),
            'metric_label' => eb_ph_usage_dashboard_metric_label((string)($row['metric_code'] ?? '')),
            'comet_user_id' => (string)($row['comet_user_id'] ?? ''),
            'included_qty' => $includedQty,
            'used_qty' => $usedQty,
            'overage_qty' => $overageQty,
            'usage_pct' => $usagePct,
            'overage_mode' => $overageMode,
            'status' => $statusKey,
            'status_label' => eb_ph_usage_dashboard_status_badge($statusKey),
            'last_push_at' => $lastPushAt,
            'stripe_subscription_item_id' => (string)($row['stripe_subscription_item_id'] ?? ''),
            'detail_logs' => $logsByItem[$itemId] ?? [],
        ];
    }

    usort($usageRows, static function (array $left, array $right) use ($sort, $dir): int {
        $sortValue = static function (array $row) use ($sort): string {
            return match ($sort) {
                'tenant' => strtolower((string)($row['tenant_name'] ?? '')),
                'plan' => strtolower((string)($row['plan_name'] ?? '')),
                'metric' => strtolower((string)($row['metric_label'] ?? '')),
                'included' => str_pad((string)($row['included_qty'] ?? 0), 12, '0', STR_PAD_LEFT),
                'used' => str_pad((string)($row['used_qty'] ?? 0), 12, '0', STR_PAD_LEFT),
                'status' => strtolower((string)($row['status_label'] ?? '')),
                default => strtolower((string)($row['last_push_at'] ?? '')),
            };
        };
        $a = $sortValue($left);
        $b = $sortValue($right);
        if ($a === $b) {
            return 0;
        }
        if ($dir === 'asc') {
            return $a <=> $b;
        }
        return $b <=> $a;
    });

    $stalePush = false;
    if ($lastUsagePush !== '') {
        $stalePush = (time() - strtotime($lastUsagePush)) > (25 * 3600);
    }

    return [
        'pagetitle' => 'Usage Dashboard',
        'templatefile' => 'whitelabel/usage-dashboard',
        'breadcrumb' => ['index.php?m=eazybackup' => 'eazyBackup'],
        'requirelogin' => true,
        'forcessl' => true,
        'vars' => [
            'modulelink' => $vars['modulelink'] ?? 'index.php?m=eazybackup',
            'msp' => $msp,
            'metric' => $metric,
            'metric_options' => $metricOptions,
            'status' => $status,
            'q' => $q,
            'sort' => $sort,
            'dir' => $dir,
            'token' => function_exists('generate_token') ? generate_token('plain') : '',
            'active_metered_subscriptions' => count($usageRows),
            'tenants_over_included_quota' => count($tenantOverQuota),
            'last_usage_push' => $lastUsagePush,
            'last_usage_push_stale' => $stalePush,
            'usage_rows' => $usageRows,
        ],
    ];
}

function eb_ph_usage_dashboard_stripe_live(array $vars): void
{
    header('Content-Type: application/json');
    [$clientId, $msp] = eb_ph_tenants_require_context($vars);
    unset($clientId);

    if (!eb_ph_tenants_require_csrf_or_json_error((string)($_REQUEST['token'] ?? ''))) { return; }

    $itemId = (int)($_REQUEST['plan_instance_item_id'] ?? 0);
    if ($itemId <= 0) { echo json_encode(['status' => 'error', 'message' => 'item']); return; }

    $row = Capsule::table('eb_plan_instance_items as pii')
        ->join('eb_plan_instances as pi', 'pi.id', '=', 'pii.plan_instance_id')
        ->where('pi.msp_id', (int)$msp->id)
        ->where('pii.id', $itemId)
        ->first([
            'pii.stripe_subscription_item_id',
            'pi.stripe_account_id',
        ]);

    if (!$row) { echo json_encode(['status' => 'error', 'message' => 'scope']); return; }

    $subscriptionItemId = trim((string)($row->stripe_subscription_item_id ?? ''));
    if ($subscriptionItemId === '') { echo json_encode(['status' => 'error', 'message' => 'no_subscription_item']); return; }

    try {
        $svc = new StripeService();
        $summaries = $svc->listUsageRecordSummaries($subscriptionItemId, 12, trim((string)($row->stripe_account_id ?? '')) ?: null);
        echo json_encode(['status' => 'success', 'summaries' => $summaries['data'] ?? []]);
        return;
    } catch (\Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        return;
    }
}

function eb_ph_usage_dashboard_push_now(array $vars): void
{
    header('Content-Type: application/json');
    [$clientId, $msp] = eb_ph_tenants_require_context($vars);
    unset($clientId);

    if (!eb_ph_tenants_require_csrf_or_json_error((string)($_POST['token'] ?? ''))) { return; }

    $itemId = (int)($_POST['plan_instance_item_id'] ?? 0);
    if ($itemId <= 0) { echo json_encode(['status' => 'error', 'message' => 'item']); return; }

    $row = Capsule::table('eb_plan_instance_items as pii')
        ->join('eb_plan_instances as pi', 'pi.id', '=', 'pii.plan_instance_id')
        ->leftJoin('eb_plan_components as pc', 'pc.id', '=', 'pii.plan_component_id')
        ->where('pi.msp_id', (int)$msp->id)
        ->where('pii.id', $itemId)
        ->first([
            'pii.id as plan_instance_item_id',
            'pii.metric_code',
            'pii.last_qty',
            'pii.stripe_subscription_item_id',
            'pi.tenant_id',
            'pi.stripe_account_id',
            'pc.default_qty',
            'pc.overage_mode',
        ]);

    if (!$row) { echo json_encode(['status' => 'error', 'message' => 'scope']); return; }

    $subscriptionItemId = trim((string)($row->stripe_subscription_item_id ?? ''));
    if ($subscriptionItemId === '') { echo json_encode(['status' => 'error', 'message' => 'no_subscription_item']); return; }

    $rawQty = max(0, (int)($row->last_qty ?? 0));
    $billableQty = computeBillableMeteredUsage($rawQty, max(0, (int)($row->default_qty ?? 0)), (string)($row->overage_mode ?? 'bill_all'));
    $periodStartTs = strtotime(gmdate('Y-m-01 00:00:00'));
    $periodEndTs = time();
    $idempotencyKey = sha1('usage-dashboard|' . $itemId . '|' . $periodStartTs . '|' . $periodEndTs . '|' . $rawQty);

    try {
        $svc = new StripeService();
        $svc->createUsageRecord($subscriptionItemId, $billableQty, max(1, $periodEndTs - 1), trim((string)($row->stripe_account_id ?? '')) ?: null, $idempotencyKey);
        Capsule::table('eb_usage_ledger')->updateOrInsert(
            ['idempotency_key' => $idempotencyKey],
            [
                'tenant_id' => (int)($row->tenant_id ?? 0),
                'plan_instance_item_id' => (int)($row->plan_instance_item_id ?? 0),
                'metric' => (string)($row->metric_code ?? ''),
                'qty' => $billableQty,
                'period_start' => gmdate('Y-m-d H:i:s', $periodStartTs),
                'period_end' => gmdate('Y-m-d H:i:s', $periodEndTs),
                'source' => 'dashboard',
                'pushed_to_stripe_at' => gmdate('Y-m-d H:i:s'),
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]
        );
        echo json_encode(['status' => 'success', 'raw_qty' => $rawQty, 'billable_qty' => $billableQty]);
        return;
    } catch (\Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        return;
    }
}
