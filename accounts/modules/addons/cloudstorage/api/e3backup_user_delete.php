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

$token = (string) ($_POST['token'] ?? '');
if ($token === '' || !function_exists('check_token')) {
    userDeleteFail('CSRF validation failed.', 400);
}
try {
    if (!check_token('plain', $token)) {
        userDeleteFail('CSRF validation failed.', 400);
    }
} catch (\Throwable $e) {
    userDeleteFail('CSRF validation failed.', 400);
}

$clientId = $ca->getUserID();
$isMsp = MspController::isMspClient($clientId);
$tenantTable = MspController::getTenantTableName();
$mspId = MspController::getMspIdForClient($clientId);
$tenantOwnerId = ($tenantTable === 'eb_tenants') ? (int)($mspId ?? 0) : (int)$clientId;
$tenantOwnerSelect = $tenantTable === 'eb_tenants' ? 't.msp_id as tenant_owner_id' : 't.client_id as tenant_owner_id';
$userIdRaw = trim((string) ($_POST['user_id'] ?? ''));
$hasPublicIdCol = Capsule::schema()->hasColumn('s3_backup_users', 'public_id');

if ($userIdRaw === '') {
    userDeleteFail('Invalid user ID.', 400, ['user_id' => 'Invalid user ID']);
}

$deleteLookup = Capsule::table('s3_backup_users as u')
    ->leftJoin($tenantTable . ' as t', 'u.tenant_id', '=', 't.id')
    ->where('u.client_id', $clientId);
if ($hasPublicIdCol && !ctype_digit($userIdRaw)) {
    $deleteLookup->where('u.public_id', $userIdRaw);
} else {
    $deleteLookup->where('u.id', (int) $userIdRaw);
}
$user = $deleteLookup->select([
    'u.id',
    'u.tenant_id',
    Capsule::raw($tenantOwnerSelect),
    't.status as tenant_status',
])->first();

if (!$user) {
    userDeleteFail('User not found.', 404);
}
$userId = (int) $user->id;

if (!$isMsp && !empty($user->tenant_id)) {
    userDeleteFail('User not found.', 404);
}

if ($isMsp && !empty($user->tenant_id)) {
    $tenantClientId = (int) ($user->tenant_owner_id ?? 0);
    $tenantStatus = strtolower((string) ($user->tenant_status ?? ''));
    if ($tenantClientId !== $tenantOwnerId || $tenantStatus === 'deleted') {
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

