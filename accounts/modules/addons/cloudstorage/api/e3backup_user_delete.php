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

function userDeleteFail(string $message, int $httpCode = 400, array $errors = []): void
{
    $payload = ['status' => 'fail', 'message' => $message];
    if (!empty($errors)) {
        $payload['errors'] = $errors;
    }
    (new JsonResponse($payload, $httpCode))->send();
    exit;
}

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    userDeleteFail('Session timeout', 200);
}

$clientId = $ca->getUserID();
$isMsp = MspController::isMspClient($clientId);
$userId = (int) ($_POST['user_id'] ?? 0);

if ($userId <= 0) {
    userDeleteFail('Invalid user ID.', 400, ['user_id' => 'Invalid user ID']);
}

$user = Capsule::table('s3_backup_users as u')
    ->leftJoin('s3_backup_tenants as t', 'u.tenant_id', '=', 't.id')
    ->where('u.id', $userId)
    ->where('u.client_id', $clientId)
    ->select([
        'u.id',
        'u.tenant_id',
        't.client_id as tenant_client_id',
        't.status as tenant_status',
    ])
    ->first();

if (!$user) {
    userDeleteFail('User not found.', 404);
}

if (!$isMsp && !empty($user->tenant_id)) {
    userDeleteFail('User not found.', 404);
}

if ($isMsp && !empty($user->tenant_id)) {
    $tenantClientId = (int) ($user->tenant_client_id ?? 0);
    $tenantStatus = strtolower((string) ($user->tenant_status ?? ''));
    if ($tenantClientId !== (int) $clientId || $tenantStatus === 'deleted') {
        userDeleteFail('User not found.', 404);
    }
}

try {
    Capsule::table('s3_backup_users')
        ->where('id', $userId)
        ->where('client_id', $clientId)
        ->delete();
} catch (\Throwable $e) {
    userDeleteFail('Failed to delete user.', 500);
}

(new JsonResponse([
    'status' => 'success',
    'message' => 'User deleted successfully.',
], 200))->send();
exit;

