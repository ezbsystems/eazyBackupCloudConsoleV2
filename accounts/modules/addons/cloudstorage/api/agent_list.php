<?php

require_once __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Session timeout'], 200))->send();
    exit;
}
$clientId = $ca->getUserID();

$agents = Capsule::table('s3_cloudbackup_agents')
    ->where('client_id', $clientId)
    ->get(['id', 'client_id', 'hostname', 'status', 'last_seen_at', 'created_at', 'updated_at']);

(new JsonResponse(['status' => 'success', 'agents' => $agents], 200))->send();
exit;

