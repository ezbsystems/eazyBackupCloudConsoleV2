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

$description = trim($_POST['description'] ?? '');
$tenantId = isset($_POST['tenant_id']) && $_POST['tenant_id'] !== '' ? (int)$_POST['tenant_id'] : null;
$maxUses = (int)($_POST['max_uses'] ?? 0);
$expiresIn = $_POST['expires_in'] ?? '';

// Validate tenant ownership if specified
if ($tenantId !== null && $tenantId > 0) {
    if (!$isMsp) {
        (new JsonResponse(['status' => 'fail', 'message' => 'Only MSPs can scope tokens to tenants'], 403))->send();
        exit;
    }
    
    $tenant = MspController::getTenant($tenantId, $clientId);
    if (!$tenant) {
        (new JsonResponse(['status' => 'fail', 'message' => 'Tenant not found'], 404))->send();
        exit;
    }
}

// Generate token
$token = bin2hex(random_bytes(20)); // 40 hex chars

// Calculate expiration
$expiresAt = null;
if ($expiresIn) {
    $now = new DateTime();
    switch ($expiresIn) {
        case '24h':
            $expiresAt = $now->modify('+24 hours')->format('Y-m-d H:i:s');
            break;
        case '7d':
            $expiresAt = $now->modify('+7 days')->format('Y-m-d H:i:s');
            break;
        case '30d':
            $expiresAt = $now->modify('+30 days')->format('Y-m-d H:i:s');
            break;
        case '90d':
            $expiresAt = $now->modify('+90 days')->format('Y-m-d H:i:s');
            break;
        case '1y':
            $expiresAt = $now->modify('+1 year')->format('Y-m-d H:i:s');
            break;
    }
}

$tokenId = Capsule::table('s3_agent_enrollment_tokens')->insertGetId([
    'client_id' => $clientId,
    'tenant_id' => $tenantId ?: null,
    'token' => $token,
    'description' => $description ?: null,
    'max_uses' => $maxUses,
    'use_count' => 0,
    'expires_at' => $expiresAt,
    'created_at' => Capsule::raw('NOW()'),
]);

(new JsonResponse([
    'status' => 'success', 
    'token_id' => $tokenId,
    'token' => $token,
    'message' => 'Token created successfully'
], 200))->send();
exit;

