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

$agentUuid = trim((string) ($_POST['agent_uuid'] ?? ''));
$status = $_POST['status'] ?? '';

if ($agentUuid === '') {
    (new JsonResponse(['status' => 'fail', 'message' => 'Invalid agent UUID'], 400))->send();
    exit;
}

if (!in_array($status, ['active', 'disabled'])) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Invalid status'], 400))->send();
    exit;
}

// Verify ownership
$agent = Capsule::table('s3_cloudbackup_agents')
    ->where('agent_uuid', $agentUuid)
    ->where('client_id', $clientId)
    ->first();

if (!$agent) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Agent not found'], 404))->send();
    exit;
}

Capsule::table('s3_cloudbackup_agents')
    ->where('agent_uuid', $agentUuid)
    ->update([
        'status' => $status,
        'updated_at' => Capsule::raw('NOW()'),
    ]);

(new JsonResponse([
    'status' => 'success',
    'message' => 'Agent status updated',
    'agent_uuid' => $agentUuid,
], 200))->send();
exit;

