<?php

require_once __DIR__ . '/../auth.php';

use Illuminate\Database\Capsule\Manager as Capsule;

$session = portal_require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    portal_json(['status' => 'fail', 'message' => 'Invalid method'], 405);
}

if (!portal_validate_csrf()) {
    portal_json(['status' => 'fail', 'message' => 'CSRF validation failed'], 401);
}

$name = trim((string) ($_POST['name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));

if ($name === '' || $email === '') {
    portal_json(['status' => 'fail', 'message' => 'Name and email are required'], 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    portal_json(['status' => 'fail', 'message' => 'Invalid email address'], 400);
}

$tenantId = (int) ($session['tenant_id'] ?? 0);
$userId = (int) ($session['user_id'] ?? 0);

if ($tenantId <= 0 || $userId <= 0) {
    portal_json(['status' => 'fail', 'message' => 'Invalid session'], 401);
}

$updated = Capsule::table('s3_backup_tenant_users')
    ->where('id', $userId)
    ->where('tenant_id', $tenantId)
    ->update([
        'name' => $name,
        'email' => $email,
        'updated_at' => Capsule::raw('NOW()'),
    ]);

if ($updated < 1) {
    portal_json(['status' => 'fail', 'message' => 'Profile update failed'], 400);
}

$_SESSION['portal_user']['name'] = $name;
$_SESSION['portal_user']['email'] = $email;

portal_json([
    'status' => 'success',
    'data' => [
        'name' => $name,
        'email' => $email,
    ],
]);
