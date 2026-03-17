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

$token = (string) ($_POST['token'] ?? '');
if ($token === '' || !function_exists('check_token')) {
    userUpdateFail('CSRF validation failed.', 400);
}
try {
    if (!check_token('plain', $token)) {
        userUpdateFail('CSRF validation failed.', 400);
    }
} catch (\Throwable $e) {
    userUpdateFail('CSRF validation failed.', 400);
}

$clientId = $ca->getUserID();
$isMsp = MspController::isMspClient($clientId);
$tenantTable = MspController::getTenantTableName();
$mspId = MspController::getMspIdForClient($clientId);
$tenantOwnerId = ($tenantTable === 'eb_tenants') ? (int)($mspId ?? 0) : (int)$clientId;
$tenantOwnerSelect = $tenantTable === 'eb_tenants' ? 't.msp_id as tenant_owner_id' : 't.client_id as tenant_owner_id';
$userId = (int) ($_POST['user_id'] ?? 0);

if ($userId <= 0) {
    userUpdateFail('Invalid user ID.', 400, ['user_id' => 'Invalid user ID']);
}

$currentUser = Capsule::table('s3_backup_users as u')
    ->leftJoin($tenantTable . ' as t', 'u.tenant_id', '=', 't.id')
    ->where('u.id', $userId)
    ->where('u.client_id', $clientId)
    ->select([
        'u.id',
        'u.client_id',
        'u.tenant_id',
        'u.username',
        'u.email',
        'u.status',
        Capsule::raw($tenantOwnerSelect),
        't.status as tenant_status',
    ])
    ->first();

if (!$currentUser) {
    userUpdateFail('User not found.', 404);
}

if (!$isMsp && !empty($currentUser->tenant_id)) {
    userUpdateFail('User not found.', 404);
}

$tenantClientId = (int) ($currentUser->tenant_owner_id ?? 0);
$tenantStatus = strtolower((string) ($currentUser->tenant_status ?? ''));
if ($isMsp && !empty($currentUser->tenant_id) && ($tenantClientId !== $tenantOwnerId || $tenantStatus === 'deleted')) {
    userUpdateFail('User not found.', 404);
}

$storageIdentifier = eb_tenant_storage_identifier_for_user((int) $userId);
$currentCanonicalLink = null;
if ($isMsp) {
    $currentCanonicalLink = eb_tenant_storage_links_get_current_link_for_identifier((int) $clientId, $storageIdentifier);
}

$username = normalizeUserNameForUpdate((string) ($_POST['username'] ?? $currentUser->username));
$email = strtolower(trim((string) ($_POST['email'] ?? $currentUser->email)));
$status = strtolower(trim((string) ($_POST['status'] ?? $currentUser->status)));
$tenantIdRaw = array_key_exists('tenant_id', $_POST) ? trim((string) $_POST['tenant_id']) : null;
$currentTenantId = $currentUser->tenant_id !== null ? (int) $currentUser->tenant_id : null;
$tenantId = $currentTenantId;
$canonicalTenantProvided = array_key_exists('canonical_tenant_id', $_POST);
$canonicalTenantIdRaw = trim((string) ($_POST['canonical_tenant_id'] ?? ''));
$canonicalTenantId = null;

if ($tenantIdRaw !== null) {
    if ($tenantIdRaw === '' || $tenantIdRaw === 'direct') {
        $tenantId = null;
    } else {
        $tenant = MspController::getTenantByPublicId($tenantIdRaw, $clientId);
        if ($tenant) {
            $tenantId = (int) $tenant->id;
        } else {
            userUpdateFail('Invalid tenant selection.', 400, ['tenant_id' => 'Invalid tenant selection']);
        }
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
            userUpdateFail('Invalid canonical tenant selection.', 400, ['canonical_tenant_id' => 'Invalid canonical tenant selection']);
        }
    }
}

$errors = [];

if ($isMsp && !$canonicalTenantProvided && $currentCanonicalLink && $tenantIdRaw !== null && $tenantId !== $currentTenantId) {
    if ($tenantId === null) {
        $canonicalTenantProvided = true;
        $canonicalTenantId = null;
    } else {
        $inferredCanonicalTenantId = eb_tenant_storage_links_infer_canonical_tenant_id_from_storage_tenant_id((int) $clientId, (int) $tenantId);
        if ($inferredCanonicalTenantId !== null) {
            $canonicalTenantProvided = true;
            $canonicalTenantId = $inferredCanonicalTenantId;
        } else {
            $errors['tenant_id'] = 'Canonical-managed users require canonical_tenant_id for scope changes.';
            $tenantId = $currentTenantId;
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
    } elseif (strtolower((string) ($tenant->status ?? '')) === 'deleted') {
        $errors['tenant_id'] = 'Selected tenant is no longer available.';
    }
}

if (!$isMsp && $canonicalTenantProvided && $canonicalTenantId !== null) {
    $errors['canonical_tenant_id'] = 'Direct accounts cannot assign canonical tenants.';
}

if ($isMsp && $canonicalTenantProvided && $canonicalTenantId !== null) {
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
    Capsule::connection()->transaction(function () use ($userId, $clientId, $tenantId, $username, $email, $status, $isMsp, $canonicalTenantProvided, $canonicalTenantId, $storageIdentifier) {
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

        if ($isMsp && $canonicalTenantProvided) {
            $linkResult = eb_tenant_storage_links_upsert_for_client((int) $clientId, $storageIdentifier, $canonicalTenantId);
            if (empty($linkResult['ok'])) {
                throw new \RuntimeException((string) ($linkResult['message'] ?? 'tenant_storage_link_failed'));
            }
        }
    });
} catch (\Throwable $e) {
    userUpdateFail('Failed to update user.', 500);
}

(new JsonResponse([
    'status' => 'success',
    'message' => 'User updated successfully.',
], 200))->send();
exit;

