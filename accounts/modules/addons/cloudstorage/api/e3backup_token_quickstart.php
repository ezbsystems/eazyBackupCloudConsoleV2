<?php
/**
 * e3 Cloud Backup - Quick-enroll token API.
 *
 * Generates a short-lived, single-use enrollment token bound to the
 * specified s3_backup_users row, and returns the token plus the base URL
 * to be used in the install snippets.
 *
 * Auth: client area session OR admin SSO. Token is scoped: a customer can
 * only request a token for a backup user that belongs to their client_id.
 *
 * Request:
 *   POST user_id=<s3_backup_users.id>
 *
 * Response (JSON):
 *   { status: success, token: "...", expires_at: "...", download_base: "https://..." }
 */

require_once __DIR__ . '/../../../../init.php';

use WHMCS\ClientArea;
use WHMCS\Database\Capsule;

header('Content-Type: application/json');

try {
    $ca = new ClientArea();
    $loggedIn = $ca->isLoggedIn();
    $clientId = $loggedIn ? (int) $ca->getUserID() : 0;

    // Admin sessions also allowed for the quick-enroll flow.
    $isAdmin = !empty($_SESSION['adminid']);

    if (!$loggedIn && !$isAdmin) {
        http_response_code(401);
        echo json_encode(['status' => 'fail', 'message' => 'auth']);
        exit;
    }

    $backupUserId = (int) ($_REQUEST['user_id'] ?? 0);
    if ($backupUserId <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'fail', 'message' => 'missing_user_id']);
        exit;
    }

    $row = Capsule::table('s3_backup_users')->where('id', $backupUserId)->first();
    if (!$row) {
        http_response_code(404);
        echo json_encode(['status' => 'fail', 'message' => 'backup_user_not_found']);
        exit;
    }
    // Customers may only request tokens for their own users.
    if (!$isAdmin && (int) $row->client_id !== $clientId) {
        http_response_code(403);
        echo json_encode(['status' => 'fail', 'message' => 'forbidden']);
        exit;
    }

    // Mint a fresh token. Single-use, valid for 60 minutes.
    $token = strtoupper(bin2hex(random_bytes(16)));
    $expires = date('Y-m-d H:i:s', strtotime('+60 minutes'));
    $rowClientId = (int) $row->client_id;
    $rowTenantId = $row->tenant_id !== null ? (int) $row->tenant_id : null;

    $insertId = (int) Capsule::table('s3_agent_enrollment_tokens')->insertGetId([
        'client_id'      => $rowClientId,
        'tenant_id'      => $rowTenantId,
        'backup_user_id' => $backupUserId,
        'token'          => $token,
        'description'    => 'Quick-enroll snippet',
        'max_uses'       => 1,
        'use_count'      => 0,
        'expires_at'     => $expires,
        'created_at'     => date('Y-m-d H:i:s'),
    ]);

    // Build a sensible download base. Use the current host so it matches what
    // the admin/customer sees in the browser.
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    $base = $scheme . '://' . $host;

    echo json_encode([
        'status'        => 'success',
        'token'         => $token,
        'token_id'      => $insertId,
        'expires_at'    => $expires,
        'download_base' => $base,
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'fail', 'message' => $e->getMessage()]);
}
