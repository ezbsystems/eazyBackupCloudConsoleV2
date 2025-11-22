<?php

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) { require_once __DIR__ . '/../../../../../init.php'; }

function eb_storage_history_assert_client(): int {
    if (!isset($_SESSION['uid']) || !$_SESSION['uid']) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
        exit;
    }
    return (int)$_SESSION['uid'];
}

function eb_storage_history() {
    $clientId = eb_storage_history_assert_client();
    header('Content-Type: application/json');

    $username = isset($_REQUEST['username']) ? trim((string)$_REQUEST['username']) : '';
    $days = isset($_REQUEST['days']) ? (int)$_REQUEST['days'] : 180;
    if ($days <= 0 || $days > 3650) { $days = 180; }
    if ($username === '') {
        echo json_encode(['status' => 'error', 'message' => 'Missing username']);
        exit;
    }

    try {
        // Verify the username belongs to this client and is active (defensive)
        $isOwned = Capsule::table('tblhosting')
            ->where('userid', $clientId)
            ->where('username', $username)
            ->where('domainstatus', 'Active')
            ->exists();
        if (!$isOwned) {
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized username']);
            exit;
        }

        $rows = Capsule::table('eb_storage_daily')
            ->select('d', 'bytes_total', 'bytes_t1000', 'bytes_t1003')
            ->where('client_id', $clientId)
            ->where('username', $username)
            ->where('d', '>=', Capsule::raw("DATE_SUB(CURRENT_DATE(), INTERVAL {$days} DAY)"))
            ->orderBy('d', 'asc')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'd' => (string)$r->d,
                'total' => (int)$r->bytes_total,
                't1000' => (int)$r->bytes_t1000,
                't1003' => (int)$r->bytes_t1003,
            ];
        }

        echo json_encode(['status' => 'success', 'username' => $username, 'days' => $days, 'data' => $out]);
    } catch (\Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => 'Query failed']);
    }
    exit;
}


