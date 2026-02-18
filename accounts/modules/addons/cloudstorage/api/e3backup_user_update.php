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

function userUpdateFail(string $message, int $httpCode = 400, array $errors = []): void
{
    $payload = ['status' => 'fail', 'message' => $message];
    if (!empty($errors)) {
        $payload['errors'] = $errors;
    }
    (new JsonResponse($payload, $httpCode))->send();
    exit;
}

function normalizeUserNameForUpdate(string $value): string
{
    return trim($value);
}

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    userUpdateFail('Session timeout', 200);
}

$clientId = $ca->getUserID();
$isMsp = MspController::isMspClient($clientId);
$userId = (int) ($_POST['user_id'] ?? 0);

if ($userId <= 0) {
    userUpdateFail('Invalid user ID.', 400, ['user_id' => 'Invalid user ID']);
}

$currentUser = Capsule::table('s3_backup_users as u')
    ->leftJoin('s3_backup_tenants as t', 'u.tenant_id', '=', 't.id')
    ->where('u.id', $userId)
    ->where('u.client_id', $clientId)
    ->select([
        'u.id',
        'u.client_id',
        'u.tenant_id',
        'u.username',
        'u.email',
        'u.status',
        't.client_id as tenant_client_id',
        't.status as tenant_status',
    ])
    ->first();

if (!$currentUser) {
    userUpdateFail('User not found.', 404);
}

if (!$isMsp && !empty($currentUser->tenant_id)) {
    userUpdateFail('User not found.', 404);
}

$tenantClientId = (int) ($currentUser->tenant_client_id ?? 0);
$tenantStatus = strtolower((string) ($currentUser->tenant_status ?? ''));
if ($isMsp && !empty($currentUser->tenant_id) && ($tenantClientId !== (int) $clientId || $tenantStatus === 'deleted')) {
    userUpdateFail('User not found.', 404);
}

$username = normalizeUserNameForUpdate((string) ($_POST['username'] ?? $currentUser->username));
$email = strtolower(trim((string) ($_POST['email'] ?? $currentUser->email)));
$status = strtolower(trim((string) ($_POST['status'] ?? $currentUser->status)));
$tenantIdRaw = $_POST['tenant_id'] ?? null;
$tenantId = $currentUser->tenant_id !== null ? (int) $currentUser->tenant_id : null;

if ($tenantIdRaw !== null) {
    if ($tenantIdRaw === '' || $tenantIdRaw === 'direct') {
        $tenantId = null;
    } elseif ((int) $tenantIdRaw > 0) {
        $tenantId = (int) $tenantIdRaw;
    } else {
        userUpdateFail('Invalid tenant selection.', 400, ['tenant_id' => 'Invalid tenant selection']);
    }
}

$errors = [];

if ($username === '') {
    $errors['username'] = 'Username is required.';
} elseif (!preg_match('/^[A-Za-z0-9._-]{3,64}$/', $username)) {
    $errors['username'] = 'Username must be 3-64 characters and use letters, numbers, dots, underscores, or hyphens.';
}

if ($email === '') {
    $errors['email'] = 'Email is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid email address.';
}

if (!in_array($status, ['active', 'disabled'], true)) {
    $errors['status'] = 'Invalid status.';
}

if (!$isMsp && $tenantId !== null) {
    $errors['tenant_id'] = 'Direct accounts cannot assign tenants.';
}

if ($isMsp && $tenantId !== null) {
    $tenant = MspController::getTenant($tenantId, $clientId);
    if (!$tenant) {
        $errors['tenant_id'] = 'Selected tenant does not belong to your account.';
    }
}

if (!empty($errors)) {
    userUpdateFail('Please correct the highlighted fields.', 400, $errors);
}

$existingQuery = Capsule::table('s3_backup_users')
    ->where('client_id', $clientId)
    ->where('username', $username)
    ->where('id', '!=', $userId);

if ($tenantId === null) {
    $existingQuery->whereNull('tenant_id');
} else {
    $existingQuery->where('tenant_id', $tenantId);
}

$existing = $existingQuery->first();
if ($existing) {
    userUpdateFail('A user with this username already exists in this scope.', 400, [
        'username' => 'Username already exists for this account scope.',
    ]);
}

try {
    Capsule::table('s3_backup_users')
        ->where('id', $userId)
        ->where('client_id', $clientId)
        ->update([
            'tenant_id' => $tenantId,
            'username' => $username,
            'email' => $email,
            'status' => $status,
            'updated_at' => Capsule::raw('NOW()'),
        ]);
} catch (\Throwable $e) {
    userUpdateFail('Failed to update user.', 500);
}

(new JsonResponse([
    'status' => 'success',
    'message' => 'User updated successfully.',
], 200))->send();
exit;

