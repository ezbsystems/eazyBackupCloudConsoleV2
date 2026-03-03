<?php

require_once __DIR__ . '/../auth.php';

use Illuminate\Database\Capsule\Manager as Capsule;

$session = portal_require_auth_json();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    portal_json(['status' => 'fail', 'message' => 'Invalid method'], 405);
}

if (!portal_validate_csrf()) {
    portal_json(['status' => 'fail', 'message' => 'CSRF validation failed'], 401);
}

$currentPassword = (string) ($_POST['current_password'] ?? '');
$newPassword = (string) ($_POST['new_password'] ?? '');
$confirmPassword = (string) ($_POST['confirm_password'] ?? '');

if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
    portal_json(['status' => 'fail', 'message' => 'All password fields are required'], 400);
}

if (strlen($newPassword) < 8) {
    portal_json(['status' => 'fail', 'message' => 'Password must be at least 8 characters'], 400);
}

if ($newPassword !== $confirmPassword) {
    portal_json(['status' => 'fail', 'message' => 'Passwords do not match'], 400);
}

$tenantId = (int) ($session['tenant_id'] ?? 0);
$userId = (int) ($session['user_id'] ?? 0);

if ($tenantId <= 0 || $userId <= 0) {
    portal_json(['status' => 'fail', 'message' => 'Invalid session'], 401);
}

$user = Capsule::table('s3_backup_tenant_users')
    ->where('id', $userId)
    ->where('tenant_id', $tenantId)
    ->first();

if (!$user || !password_verify($currentPassword, (string) ($user->password_hash ?? ''))) {
    portal_json(['status' => 'fail', 'message' => 'Current password is incorrect'], 400);
}

$updated = Capsule::table('s3_backup_tenant_users')
    ->where('id', $userId)
    ->where('tenant_id', $tenantId)
    ->update([
        'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
        'updated_at' => Capsule::raw('NOW()'),
    ]);

if ($updated < 1) {
    portal_json(['status' => 'fail', 'message' => 'Password change failed'], 400);
}

portal_json(['status' => 'success']);
