<?php

require_once __DIR__ . '/bootstrap.php';

use Illuminate\Database\Capsule\Manager as Capsule;

function portal_login(string $email, string $password): array
{
    $user = Capsule::table('s3_backup_tenant_users')
        ->whereRaw('LOWER(email) = LOWER(?)', [$email])
        ->where('status', 'active')
        ->first();

    if (!$user || !password_verify($password, $user->password_hash)) {
        return ['status' => 'fail', 'message' => 'Invalid credentials'];
    }

    $tenant = Capsule::table('s3_backup_tenants')
        ->where('id', $user->tenant_id)
        ->where('status', 'active')
        ->first();

    if (!$tenant) {
        return ['status' => 'fail', 'message' => 'Tenant unavailable'];
    }

    $branding = portal_detect_branding();

    $_SESSION['portal_user'] = [
        'user_id' => (int) $user->id,
        'tenant_id' => (int) $user->tenant_id,
        'client_id' => (int) $tenant->client_id,
        'email' => $user->email,
        'name' => $user->name,
        'role' => $user->role,
        'tenant_name' => $tenant->name,
        'branding' => $branding,
        'logged_in_at' => time(),
    ];

    Capsule::table('s3_backup_tenant_users')
        ->where('id', $user->id)
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
