<?php
/**
 * Admin SSO helper: impersonate a client and land directly on the Cloud
 * Storage Welcome page so admin can verify the customer's onboarding flow
 * end-to-end in a single click.
 *
 * Requires an authenticated WHMCS admin session.
 */

require_once __DIR__ . '/../../../../../init.php';

use WHMCS\Database\Capsule;

// Verify admin session.
$isAdmin = false;
try {
    if (!empty($_SESSION['adminid'])) {
        $isAdmin = true;
    }
} catch (\Throwable $e) {
}
if (!$isAdmin) {
    http_response_code(403);
    echo 'Admin session required.';
    exit;
}

$clientId = (int) ($_GET['client_id'] ?? 0);
if ($clientId <= 0) {
    http_response_code(400);
    echo 'Missing client_id.';
    exit;
}

try {
    $sso = localAPI('CreateSsoToken', [
        'client_id'         => $clientId,
        'destination'       => 'sso:custom_redirect',
        'sso_redirect_path' => 'index.php?m=cloudstorage&page=welcome&eb_beta=1',
    ], 'API');
    if (($sso['result'] ?? '') === 'success' && !empty($sso['redirect_url'])) {
        header('Location: ' . $sso['redirect_url']);
        exit;
    }
    echo 'Unable to create SSO token: ' . htmlspecialchars($sso['message'] ?? 'unknown');
} catch (\Throwable $e) {
    http_response_code(500);
    echo 'Impersonation failed: ' . htmlspecialchars($e->getMessage());
}
