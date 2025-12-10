<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/MspController.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Client\MspController;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Session timeout'], 200))->send();
    exit;
}
$clientId = $ca->getUserID();

$isMsp = MspController::isMspClient($clientId);

// Build query based on MSP status
$query = Capsule::table('s3_agent_enrollment_tokens as t')
    ->where('t.client_id', $clientId)
    ->select([
        't.id',
        't.token',
        't.description',
        't.tenant_id',
        't.max_uses',
        't.use_count',
        't.expires_at',
        't.revoked_at',
        't.created_at',
    ]);

if ($isMsp) {
    $query->leftJoin('s3_backup_tenants as tn', 't.tenant_id', '=', 'tn.id')
          ->addSelect('tn.name as tenant_name');
}

$tokens = $query->orderByDesc('t.created_at')->get();

// Add computed is_valid field
$now = new DateTime();
foreach ($tokens as $token) {
    $expired = $token->expires_at && new DateTime($token->expires_at) < $now;
    $maxedOut = $token->max_uses > 0 && $token->use_count >= $token->max_uses;
    $token->is_valid = !$token->revoked_at && !$expired && !$maxedOut;
}

(new JsonResponse(['status' => 'success', 'tokens' => $tokens], 200))->send();
exit;

