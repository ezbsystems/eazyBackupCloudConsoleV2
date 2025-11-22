<?php

require_once __DIR__ . '/../../../../init.php';

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Database\Capsule;

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    $response = new JsonResponse(['status' => 'fail', 'message' => 'Session timeout.'], 200);
    $response->send();
    exit;
}

try {
    $clientId = (int) $ca->getUserID();
    $provider = $_GET['provider'] ?? $_POST['provider'] ?? 'google_drive';
    $statusFilter = $_GET['status'] ?? $_POST['status'] ?? 'active';

    $query = Capsule::table('s3_cloudbackup_sources')
        ->where('client_id', $clientId)
        ->where('provider', $provider);

    if ($statusFilter !== 'all') {
        $query->where('status', $statusFilter);
    }

    $rows = $query
        ->orderBy('updated_at', 'desc')
        ->get(['id', 'display_name', 'account_email', 'status']);

    $sources = [];
    foreach ($rows as $r) {
        $sources[] = [
            'id' => (int) $r->id,
            'display_name' => $r->display_name,
            'account_email' => $r->account_email,
            'status' => $r->status,
        ];
    }

    $response = new JsonResponse(['status' => 'success', 'sources' => $sources], 200);
    $response->send();
    exit;
} catch (\Exception $e) {
    $response = new JsonResponse(['status' => 'fail', 'message' => 'Failed to list sources.'], 200);
    $response->send();
    exit;
}


