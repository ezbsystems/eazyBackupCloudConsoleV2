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

$tokenId = (int)($_POST['token_id'] ?? 0);

if ($tokenId <= 0) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Invalid token ID'], 400))->send();
    exit;
}

// Verify ownership
$token = Capsule::table('s3_agent_enrollment_tokens')
    ->where('id', $tokenId)
    ->where('client_id', $clientId)
    ->first();

if (!$token) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Token not found'], 404))->send();
    exit;
}

if ($token->revoked_at) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Token already revoked'], 400))->send();
    exit;
}

Capsule::table('s3_agent_enrollment_tokens')
    ->where('id', $tokenId)
    ->update(['revoked_at' => Capsule::raw('NOW()')]);

(new JsonResponse(['status' => 'success', 'message' => 'Token revoked successfully'], 200))->send();
exit;

