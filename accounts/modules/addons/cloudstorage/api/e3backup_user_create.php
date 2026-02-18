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

function userCreateFail(string $message, int $httpCode = 400, array $errors = []): void
{
    $payload = ['status' => 'fail', 'message' => $message];
    if (!empty($errors)) {
        $payload['errors'] = $errors;
    }
    (new JsonResponse($payload, $httpCode))->send();
    exit;
}

function normalizeUsername(string $value): string
{
    return trim($value);
}

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    userCreateFail('Session timeout', 200);
}

$clientId = $ca->getUserID();
$isMsp = MspController::isMspClient($clientId);

$username = normalizeUsername((string) ($_POST['username'] ?? ''));
$email = strtolower(trim((string) ($_POST['email'] ?? '')));
$password = (string) ($_POST['password'] ?? '');
$passwordConfirm = (string) ($_POST['password_confirm'] ?? '');
$status = strtolower(trim((string) ($_POST['status'] ?? 'active')));
$tenantIdRaw = $_POST['tenant_id'] ?? '';
$tenantId = null;

if ($tenantIdRaw !== null && $tenantIdRaw !== '' && (int) $tenantIdRaw > 0) {
    $tenantId = (int) $tenantIdRaw;
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

if ($password === '') {
    $errors['password'] = 'Password is required.';
} elseif (strlen($password) < 8) {
    $errors['password'] = 'Password must be at least 8 characters.';
}

if ($passwordConfirm === '') {
    $errors['password_confirm'] = 'Please confirm your password.';
} elseif ($password !== $passwordConfirm) {
    $errors['password_confirm'] = 'Password confirmation does not match.';
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
    userCreateFail('Please correct the highlighted fields.', 400, $errors);
}

$existingQuery = Capsule::table('s3_backup_users')
    ->where('client_id', $clientId)
    ->where('username', $username);

if ($tenantId === null) {
    $existingQuery->whereNull('tenant_id');
} else {
    $existingQuery->where('tenant_id', $tenantId);
}

$existing = $existingQuery->first();
if ($existing) {
    userCreateFail('A user with this username already exists in this scope.', 400, [
        'username' => 'Username already exists for this account scope.',
    ]);
}

try {
    $userId = Capsule::table('s3_backup_users')->insertGetId([
        'client_id' => $clientId,
        'tenant_id' => $tenantId,
        'username' => $username,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'email' => $email,
        'status' => $status,
        'created_at' => Capsule::raw('NOW()'),
        'updated_at' => Capsule::raw('NOW()'),
    ]);
} catch (\Throwable $e) {
    userCreateFail('Failed to create user.', 500);
}

(new JsonResponse([
    'status' => 'success',
    'user_id' => (int) $userId,
    'message' => 'User created successfully.',
], 200))->send();
exit;

