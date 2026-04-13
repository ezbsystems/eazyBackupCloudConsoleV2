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

function userResetFail(string $message, int $httpCode = 400, array $errors = []): void
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
    userResetFail('Session timeout', 200);
}

$token = (string) ($_POST['token'] ?? '');
if ($token === '' || !function_exists('check_token')) {
    userResetFail('CSRF validation failed.', 400);
}
try {
    if (!check_token('plain', $token)) {
        userResetFail('CSRF validation failed.', 400);
    }
} catch (\Throwable $e) {
    userResetFail('CSRF validation failed.', 400);
}

$clientId = $ca->getUserID();
$isMsp = MspController::isMspClient($clientId);
$tenantTable = MspController::getTenantTableName();
$mspId = MspController::getMspIdForClient($clientId);
$tenantOwnerId = ($tenantTable === 'eb_tenants') ? (int)($mspId ?? 0) : (int)$clientId;
$tenantOwnerSelect = $tenantTable === 'eb_tenants' ? 't.msp_id as tenant_owner_id' : 't.client_id as tenant_owner_id';
$userIdRaw = trim((string) ($_POST['user_id'] ?? ''));
$hasPublicIdCol = Capsule::schema()->hasColumn('s3_backup_users', 'public_id');
$password = (string) ($_POST['password'] ?? '');
$passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

if ($userIdRaw === '') {
    userResetFail('Invalid user ID.', 400, ['user_id' => 'Invalid user ID']);
}

if ($password === '') {
    userResetFail('Please provide a new password.', 400, ['password' => 'Password is required.']);
}
if (strlen($password) < 8) {
    userResetFail('Password must be at least 8 characters.', 400, ['password' => 'Password must be at least 8 characters.']);
}
if ($passwordConfirm !== '' && $password !== $passwordConfirm) {
    userResetFail('Password confirmation does not match.', 400, ['password_confirm' => 'Password confirmation does not match.']);
}

$resetLookup = Capsule::table('s3_backup_users as u')
    ->leftJoin($tenantTable . ' as t', 'u.tenant_id', '=', 't.id')
    ->where('u.client_id', $clientId);
if ($hasPublicIdCol && !ctype_digit($userIdRaw)) {
    $resetLookup->where('u.public_id', $userIdRaw);
} else {
    $resetLookup->where('u.id', (int) $userIdRaw);
}
$user = $resetLookup->select([
    'u.id',
    'u.tenant_id',
    Capsule::raw($tenantOwnerSelect),
    't.status as tenant_status',
])->first();

if (!$user) {
    userResetFail('User not found.', 404);
}
$userId = (int) $user->id;

if (!$isMsp && !empty($user->tenant_id)) {
    userResetFail('User not found.', 404);
}

if ($isMsp && !empty($user->tenant_id)) {
    $tenantClientId = (int) ($user->tenant_owner_id ?? 0);
    $tenantStatus = strtolower((string) ($user->tenant_status ?? ''));
    if ($tenantClientId !== $tenantOwnerId || $tenantStatus === 'deleted') {
        userResetFail('User not found.', 404);
    }
}

try {
    Capsule::table('s3_backup_users')
        ->where('id', $userId)
        ->where('client_id', $clientId)
        ->update([
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'updated_at' => Capsule::raw('NOW()'),
        ]);
} catch (\Throwable $e) {
    userResetFail('Failed to update password.', 500);
}

(new JsonResponse([
    'status' => 'success',
    'message' => 'Password updated successfully.',
], 200))->send();
exit;

