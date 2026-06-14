<?php
declare(strict_types=1);

use Ms365Backup\PlatformEntraConfig;
use Ms365Backup\Seeder\SeederOAuthService;

// Customer platform admin-consent must never be handled here (separate Entra app + callback).
$isPlatformAdminConsent = isset($_GET['admin_consent'])
    || (isset($_GET['tenant']) && trim((string) $_GET['tenant']) !== '' && !isset($_GET['code']));
if ($isPlatformAdminConsent) {
    $qs = (string) ($_SERVER['QUERY_STRING'] ?? '');
    $target = PlatformEntraConfig::customerConnectCallbackUrl();
    header('Location: ' . $target . ($qs !== '' ? '?' . $qs : ''));
    exit;
}

if (!isset($_SESSION['adminid']) || (int) $_SESSION['adminid'] <= 0) {
    echo '<div class="alert alert-danger">Admin login required.</div>';
    return;
}

$baseUrl = 'addonmodules.php?module=ms365backup&action=seeder';
$error = '';
$success = '';

try {
    SeederOAuthService::handleCallback($_GET);
    $success = 'Seed user connected successfully.';
} catch (\Throwable $e) {
    $error = $e->getMessage();
}

if ($error !== '') {
    header('Location: ' . $baseUrl . '&oauth_error=' . rawurlencode($error));
    exit;
}

header('Location: ' . $baseUrl . '&oauth_ok=1');
exit;
