<?php

require_once __DIR__ . '/bootstrap.php';

use Illuminate\Database\Capsule\Manager as Capsule;

function portal_login(string $email, string $password): array
{
    if (portal_msp_context_is_invalid()) {
        return ['status' => 'fail', 'message' => 'Invalid credentials'];
    }

    $tenantContextId = portal_resolve_tenant_context();
    $clientContextId = portal_resolve_client_context();

    $query = Capsule::table('eb_tenant_users as u')
        ->join('eb_tenants as t', 'u.tenant_id', '=', 't.id')
        ->whereRaw('LOWER(u.email) = LOWER(?)', [$email])
        ->where('u.status', 'active')
        ->where('t.status', 'active');
    if ($tenantContextId !== null && $tenantContextId > 0) {
        $query->where('u.tenant_id', $tenantContextId);
    } elseif ($clientContextId !== null && $clientContextId > 0) {
        $mspId = portal_resolve_msp_id_for_client($clientContextId);
        if ($mspId !== null) {
            $query->where('t.msp_id', $mspId);
        }
    }
    $matches = $query->orderBy('u.id', 'asc')->get([
        'u.id as user_id',
        'u.tenant_id',
        'u.password_hash',
        'u.email',
        'u.name',
        'u.role',
        't.msp_id as owner_id',
        't.name as tenant_name',
    ]);

    if (!$matches || count($matches) < 1) {
        return ['status' => 'fail', 'message' => 'Invalid credentials'];
    }
    if (count($matches) > 1) {
        return ['status' => 'fail', 'message' => 'Invalid credentials'];
    }

    $user = (object) $matches[0];
    if (!password_verify($password, (string) ($user->password_hash ?? ''))) {
        return ['status' => 'fail', 'message' => 'Invalid credentials'];
    }

    $tenantId = (int) ($user->tenant_id ?? 0);
    $ownerId = (int) ($user->owner_id ?? 0);
    if ($tenantId <= 0 || $ownerId <= 0) {
        return ['status' => 'fail', 'message' => 'Tenant unavailable'];
    }

    $clientId = $ownerId;
    if ($ownerId > 0) {
        $msp = Capsule::table('eb_msp_accounts')->where('id', $ownerId)->first(['whmcs_client_id']);
        $clientId = $msp ? (int) $msp->whmcs_client_id : 0;
    }

    $branding = portal_detect_branding();
    session_regenerate_id(true);

    $_SESSION['portal_user'] = [
        'user_id' => (int) $user->user_id,
        'tenant_id' => $tenantId,
        'client_id' => $clientId,
        'email' => (string) ($user->email ?? ''),
        'name' => (string) ($user->name ?? ''),
        'role' => (string) ($user->role ?? ''),
        'tenant_name' => (string) ($user->tenant_name ?? ''),
        'branding' => $branding,
        'logged_in_at' => time(),
    ];

    Capsule::table('eb_tenant_users')
        ->where('id', (int) $user->user_id)
        ->update([
            'last_login_at' => Capsule::raw('NOW()'),
            'updated_at' => Capsule::raw('NOW()'),
        ]);

    return ['status' => 'success'];
}

function portal_validate_csrf(): bool
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return true;
    }
    $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $token = $_SESSION['portal_csrf'] ?? '';
    return $token !== '' && hash_equals($token, $header);
}

function portal_issue_csrf(): string
{
    if (empty($_SESSION['portal_csrf'])) {
        $_SESSION['portal_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['portal_csrf'];
}
