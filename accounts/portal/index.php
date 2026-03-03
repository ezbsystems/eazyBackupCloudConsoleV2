<?php

require_once __DIR__ . '/auth.php';

$api = $_GET['api'] ?? '';
$apiRoutes = [
    'profile_update' => __DIR__ . '/api/profile_update.php',
    'change_password' => __DIR__ . '/api/change_password.php',
];

if ($api !== '' && isset($apiRoutes[$api])) {
    require_once $apiRoutes[$api];
    exit;
}

$page = $_GET['page'] ?? 'dashboard';
$session = portal_session();

// Public pages
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
    case 'password_reset':
        $template = 'password_reset.tpl';
        break;
    case 'password_reset_confirm':
        $template = 'password_reset_confirm.tpl';
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
