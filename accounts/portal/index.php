<?php

require_once __DIR__ . '/auth.php';

use Illuminate\Database\Capsule\Manager as Capsule;

if (!empty($_GET['impersonate'])) {
    $rawToken = (string)$_GET['impersonate'];
    $hashedToken = hash('sha256', $rawToken);
    try {
        $tokenRow = Capsule::table('eb_tenant_portal_tokens')
            ->where('token', $hashedToken)
            ->whereNull('used_at')
            ->where('expires_at', '>', date('Y-m-d H:i:s'))
            ->first();
        if ($tokenRow) {
            Capsule::table('eb_tenant_portal_tokens')->where('id', (int)$tokenRow->id)->update(['used_at' => date('Y-m-d H:i:s')]);

            $ut = portal_tenant_users_table();
            $tt = portal_tenant_table();
            $user = Capsule::table($ut)->where('id', (int)$tokenRow->tenant_user_id)->where('status', 'active')->first();
            if ($user) {
                $tenant = Capsule::table($tt)->where('id', (int)$user->tenant_id)->where('status', 'active')->first();
                if ($tenant) {
                    $msp = Capsule::table('eb_msp_accounts')->where('id', (int)($tenant->msp_id ?? 0))->first(['whmcs_client_id']);
                    $clientId = $msp ? (int)$msp->whmcs_client_id : 0;

                    session_regenerate_id(true);
                    $_SESSION['portal_user'] = [
                        'user_id' => (int)$user->id,
                        'tenant_id' => (int)$user->tenant_id,
                        'client_id' => $clientId,
                        'email' => (string)($user->email ?? ''),
                        'name' => (string)($user->name ?? ''),
                        'role' => (string)($user->role ?? 'admin'),
                        'tenant_name' => (string)($tenant->name ?? ''),
                        'logged_in_at' => time(),
                    ];
                    $_SESSION['portal_impersonated_by'] = (int)$tokenRow->msp_client_id;
                    $_SESSION['portal_impersonate_return'] = '/index.php?m=eazybackup&a=ph-tenants-manage';
                    header('Location: /portal/index.php?page=dashboard');
                    exit;
                }
            }
        }
    } catch (\Throwable $e) {}
    header('Location: /portal/index.php?page=login&impersonate_error=1');
    exit;
}

$api = $_GET['api'] ?? '';
$apiRoutes = [
    'login' => __DIR__ . '/api/login.php',
    'profile_update' => __DIR__ . '/api/profile_update.php',
    'change_password' => __DIR__ . '/api/change_password.php',
    'invoices' => __DIR__ . '/api/invoices.php',
    'payment_methods' => __DIR__ . '/api/payment_methods.php',
    'services' => __DIR__ . '/api/services.php',
    'password_reset' => __DIR__ . '/api/password_reset.php',
    'password_reset_confirm' => __DIR__ . '/api/password_reset_confirm.php',
];

if ($api !== '' && isset($apiRoutes[$api])) {
    require_once $apiRoutes[$api];
    exit;
}

$page = $_GET['page'] ?? 'dashboard';
$session = portal_session();

$public = ['login', 'password_reset', 'password_reset_confirm'];

if (!in_array($page, $public, true)) {
    $session = portal_require_auth();
}

$branding = portal_detect_branding();
$csrf = portal_issue_csrf();

switch ($page) {
    case 'login':
        $template = 'login.tpl';
        break;
    case 'dashboard':
        $template = 'dashboard.tpl';
        break;
    case 'devices':
        $template = 'devices.tpl';
        break;
    case 'jobs':
        $template = 'jobs.tpl';
        break;
    case 'restore':
        $template = 'restore.tpl';
        break;
    case 'billing':
        $template = 'billing.tpl';
        break;
    case 'services':
        $template = 'services.tpl';
        break;
    case 'cloud_storage':
        $template = 'cloud_storage.tpl';
        break;
    case 'password_reset':
        $template = 'password_reset.tpl';
        break;
    case 'password_reset_confirm':
        $template = 'password_reset_confirm.tpl';
        break;
    case 'settings':
    default:
        $template = 'settings.tpl';
        break;
}

portal_render($template, [
    'branding' => $branding,
    'session'  => $session,
    'csrf'     => $csrf,
    'page'     => $page,
]);
