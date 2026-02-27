<?php

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) { require_once __DIR__ . '/../../../../../init.php'; }

function eb_dashboard_usage_metrics_assert_client(): int {
    if (!isset($_SESSION['uid']) || !$_SESSION['uid']) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
        exit;
    }
    return (int)$_SESSION['uid'];
}

function eb_dashboard_usage_metrics() {
    $clientId = eb_dashboard_usage_metrics_assert_client();
    header('Content-Type: application/json');

    try {
        $excludeProductgroupIds = [2, 11];
        $productIds = Capsule::table('tblproducts')
            ->select('id')
            ->whereNotIn('gid', $excludeProductgroupIds)
            ->pluck('id')
            ->toArray();

        if (empty($productIds)) {
            echo json_encode([
                'status' => 'success',
                'devices30d' => [],
                'storage30d' => [],
                'status24h' => ['success' => 0, 'error' => 0, 'warning' => 0, 'missed' => 0, 'running' => 0],
            ]);
            exit;
        }

        $activeUsernames = Capsule::table('tblhosting')
            ->select('username')
            ->where('domainstatus', 'Active')
            ->where('userid', $clientId)
            ->whereIn('packageid', $productIds)
            ->pluck('username')
            ->toArray();

        if (empty($activeUsernames)) {
            echo json_encode([
                'status' => 'success',
                'devices30d' => [],
                'storage30d' => [],
                'status24h' => ['success' => 0, 'error' => 0, 'warning' => 0, 'missed' => 0, 'running' => 0],
            ]);
            exit;
        }

        $startDate = date('Y-m-d', strtotime('-29 days'));
        $sinceTs = time() - 86400;

        $devicesRows = Capsule::table('eb_devices_client_daily')
            ->select('d', 'registered', 'online')
            ->where('client_id', $clientId)
            ->where('d', '>=', $startDate)
            ->orderBy('d', 'asc')
            ->get();

        $devices30d = [];
        foreach ($devicesRows as $r) {
            $devices30d[] = [
                'd' => (string)$r->d,
                'registered' => (int)$r->registered,
                'online' => (int)$r->online,
            ];
        }

        $storageRows = Capsule::table('eb_storage_daily')
            ->select('d', Capsule::raw('SUM(bytes_total) AS bytes_total'))
            ->where('client_id', $clientId)
            ->whereIn('username', $activeUsernames)
            ->where('d', '>=', $startDate)
            ->groupBy('d')
            ->orderBy('d', 'asc')
            ->get();

        $storage30d = [];
        foreach ($storageRows as $r) {
            $storage30d[] = [
                'd' => (string)$r->d,
                'bytes_total' => (int)$r->bytes_total,
            ];
        }

        $status24h = [
            'success' => 0,
            'error' => 0,
            'warning' => 0,
            'missed' => 0,
            'running' => 0,
        ];

        $completedRows = Capsule::table('eb_jobs_recent_24h')
            ->select('status', Capsule::raw('COUNT(*) AS c'))
            ->where('ended_at', '>=', $sinceTs)
            ->whereIn('username', $activeUsernames)
            ->groupBy('status')
            ->get();

        foreach ($completedRows as $row) {
            $key = strtolower((string)($row->status ?? ''));
            if (array_key_exists($key, $status24h)) {
                $status24h[$key] = (int)$row->c;
            }
        }

        $status24h['running'] = (int) Capsule::table('eb_jobs_live')
            ->whereIn('username', $activeUsernames)
            ->count();

        echo json_encode([
            'status' => 'success',
            'devices30d' => $devices30d,
            'storage30d' => $storage30d,
            'status24h' => $status24h,
        ]);
    } catch (\Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => 'Query failed']);
    }

    exit;
}

