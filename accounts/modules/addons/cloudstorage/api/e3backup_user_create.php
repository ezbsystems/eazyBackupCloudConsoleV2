<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/MspController.php';
require_once __DIR__ . '/../../eazybackup/pages/partnerhub/TenantStorageLinksController.php';

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

$token = (string) ($_POST['token'] ?? '');
if ($token === '' || !function_exists('check_token')) {
    userCreateFail('CSRF validation failed.', 400);
}
try {
    if (!check_token('plain', $token)) {
        userCreateFail('CSRF validation failed.', 400);
    }
} catch (\Throwable $e) {
    userCreateFail('CSRF validation failed.', 400);
}

$clientId = $ca->getUserID();
$isMsp = MspController::isMspClient($clientId);

$username = normalizeUsername((string) ($_POST['username'] ?? ''));
$email = strtolower(trim((string) ($_POST['email'] ?? '')));
$password = (string) ($_POST['password'] ?? '');
$passwordConfirm = (string) ($_POST['password_confirm'] ?? '');
$status = strtolower(trim((string) ($_POST['status'] ?? 'active')));
$tenantIdRaw = trim((string) ($_POST['tenant_id'] ?? ''));
$tenantId = null;
$errors = [];
$canonicalTenantProvided = array_key_exists('canonical_tenant_id', $_POST);
$canonicalTenantIdRaw = trim((string) ($_POST['canonical_tenant_id'] ?? ''));
$canonicalTenantId = null;

if ($tenantIdRaw !== '' && $tenantIdRaw !== 'direct') {
    $tenant = MspController::getTenantByPublicId($tenantIdRaw, $clientId);
    if ($tenant) {
        $tenantId = (int) $tenant->id;
    } else {
        $errors['tenant_id'] = 'Invalid tenant selection.';
    }
}

if ($canonicalTenantProvided) {
    if ($canonicalTenantIdRaw === '' || $canonicalTenantIdRaw === 'direct') {
        $canonicalTenantId = null;
        if ($isMsp) {
            $tenantId = null;
        }
    } else {
        $canonicalTenant = eb_tenant_storage_links_resolve_tenant_for_client_by_public_id((int) $clientId, $canonicalTenantIdRaw);
        if ($canonicalTenant) {
            $canonicalTenantId = (int) $canonicalTenant->id;
        } else {
            $errors['canonical_tenant_id'] = 'Invalid canonical tenant selection.';
        }
    }
}

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

if ($isMsp && $tenantId !== null && !$canonicalTenantProvided) {
    $tenant = MspController::getTenant($tenantId, $clientId);
    if (!$tenant) {
        $errors['tenant_id'] = 'Selected tenant does not belong to your account.';
    } elseif (strtolower((string) ($tenant->status ?? '')) === 'deleted') {
        $errors['tenant_id'] = 'Selected tenant is no longer available.';
    }
}

if (!$isMsp && $canonicalTenantId !== null) {
    $errors['canonical_tenant_id'] = 'Direct accounts cannot assign canonical tenants.';
}

if ($isMsp && $canonicalTenantId !== null) {
    $canonicalTenant = eb_tenant_storage_links_resolve_tenant_for_client((int) $clientId, $canonicalTenantId);
    if (!$canonicalTenant) {
        $errors['canonical_tenant_id'] = 'Selected canonical tenant does not belong to your account.';
    } else {
        try {
            $tenantId = eb_tenant_storage_links_resolve_or_create_storage_tenant_id((int) $clientId, $canonicalTenantId);
        } catch (\Throwable $e) {
            $errors['canonical_tenant_id'] = 'Unable to map canonical tenant to storage scope.';
        }
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
    $userId = Capsule::connection()->transaction(function () use ($clientId, $tenantId, $username, $password, $email, $status, $isMsp, $canonicalTenantId) {
        $userId = (int) Capsule::table('s3_backup_users')->insertGetId([
            'client_id' => $clientId,
            'tenant_id' => $tenantId,
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'email' => $email,
            'status' => $status,
            'created_at' => Capsule::raw('NOW()'),
            'updated_at' => Capsule::raw('NOW()'),
        ]);

        if ($isMsp) {
            $storageIdentifier = eb_tenant_storage_identifier_for_user((int) $userId);
            $linkResult = eb_tenant_storage_links_upsert_for_client((int) $clientId, $storageIdentifier, $canonicalTenantId);
            if (empty($linkResult['ok'])) {
                throw new \RuntimeException((string) ($linkResult['message'] ?? 'tenant_storage_link_failed'));
            }
        }

        return $userId;
    });
} catch (\Throwable $e) {
    userCreateFail('Failed to create user.', 500);
}

(new JsonResponse([
    'status' => 'success',
    'user_id' => (int) $userId,
    'message' => 'User created successfully.',
], 200))->send();
exit;

