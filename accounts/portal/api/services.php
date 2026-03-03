<?php

require_once __DIR__ . '/../auth.php';

use Illuminate\Database\Capsule\Manager as Capsule;

$session = portal_require_auth_json();
$tenantId = (int) ($session['tenant_id'] ?? 0);

if ($tenantId <= 0) {
    portal_json(['status' => 'fail', 'message' => 'auth'], 401);
}

$customer = Capsule::table('eb_customers')
    ->where('tenant_id', $tenantId)
    ->first(['id']);
$customerId = (int) ($customer->id ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!portal_validate_csrf()) {
        portal_json(['status' => 'fail', 'message' => 'CSRF validation failed'], 401);
    }

    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'cancel_request') {
        if ($customerId <= 0) {
            portal_json(['status' => 'fail', 'message' => 'Service not found'], 404);
        }

        $subscriptionId = (int) ($_POST['subscription_id'] ?? 0);
        if ($subscriptionId <= 0) {
            portal_json(['status' => 'fail', 'message' => 'subscription_id required'], 400);
        }

        $sub = Capsule::table('eb_subscriptions')
            ->where('id', $subscriptionId)
            ->where('customer_id', $customerId)
            ->first(['id']);
        if (!$sub) {
            portal_json(['status' => 'fail', 'message' => 'Service not found'], 404);
        }

        Capsule::table('eb_subscriptions')
            ->where('id', $subscriptionId)
            ->update([
                'cancel_at_period_end' => 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        portal_json(['status' => 'success', 'message' => 'Cancellation requested']);
    }

    portal_json(['status' => 'fail', 'message' => 'Unsupported action'], 400);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    portal_json(['status' => 'fail', 'message' => 'Invalid method'], 405);
}

$services = [];
if ($customerId > 0) {
    $serviceRows = Capsule::table('eb_subscriptions as s')
        ->leftJoin('eb_plans as p', 'p.id', '=', 's.plan_id')
        ->leftJoin('eb_plan_prices as pp', 'pp.id', '=', 's.current_price_id')
        ->where('s.customer_id', $customerId)
        ->orderByDesc('s.created_at')
        ->get([
            's.id',
            's.stripe_subscription_id',
            's.stripe_status',
            's.started_at',
            's.cancel_at',
            's.cancel_at_period_end',
            'p.name as plan_name',
            'pp.currency',
            'pp.unit_amount',
            'pp.interval',
            'pp.interval_count',
            'pp.is_metered',
        ]);

    foreach ($serviceRows as $row) {
        $services[] = [
            'id' => (int) ($row->id ?? 0),
            'name' => (string) ($row->plan_name ?: $row->stripe_subscription_id ?: 'Service'),
            'stripe_subscription_id' => (string) ($row->stripe_subscription_id ?? ''),
            'status' => (string) ($row->stripe_status ?? ''),
            'started_at' => (string) ($row->started_at ?? ''),
            'cancel_at' => (string) ($row->cancel_at ?? ''),
            'cancel_at_period_end' => (int) ($row->cancel_at_period_end ?? 0),
            'currency' => (string) ($row->currency ?? ''),
            'unit_amount' => (int) ($row->unit_amount ?? 0),
            'interval' => (string) ($row->interval ?? ''),
            'interval_count' => (int) ($row->interval_count ?? 0),
            'is_metered' => (int) ($row->is_metered ?? 0),
        ];
    }
}

$storageUsers = [];
$linkRows = Capsule::table('eb_tenant_storage_links as tsl')
    ->where('tsl.tenant_id', $tenantId)
    ->where('tsl.link_status', 'active')
    ->get(['tsl.storage_identifier']);

$s3UserIds = [];
foreach ($linkRows as $row) {
    $identifier = (string) ($row->storage_identifier ?? '');
    if (preg_match('/^s3_backup_user:(\d+)$/', $identifier, $m)) {
        $s3UserIds[] = (int) $m[1];
    }
}
$s3UserIds = array_values(array_unique(array_filter($s3UserIds)));

if ($s3UserIds !== []) {
    $rows = Capsule::table('s3_backup_users')
        ->whereIn('id', $s3UserIds)
        ->orderBy('username')
        ->get(['id', 'username', 'email', 'status']);

    foreach ($rows as $row) {
        $storageUsers[] = [
            'id' => (int) ($row->id ?? 0),
            'username' => (string) ($row->username ?? ''),
            'email' => (string) ($row->email ?? ''),
            'status' => (string) ($row->status ?? ''),
            'access_keys_url' => 'index.php?m=cloudstorage&page=access_keys',
            'buckets_url' => 'index.php?m=cloudstorage&page=buckets',
        ];
    }
}

portal_json(['status' => 'success', 'data' => [
    'services' => $services,
    'storage_users' => $storageUsers,
]]);
