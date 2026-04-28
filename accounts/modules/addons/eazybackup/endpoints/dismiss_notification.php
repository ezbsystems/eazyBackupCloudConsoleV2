<?php

use WHMCS\Database\Capsule;

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../eazybackup.php';

header('Content-Type: application/json');

try {
    $userId = null;
    try {
        if (class_exists('\\WHMCS\\User\\User')) {
            $u = \WHMCS\User\User::fromSession();
            if ($u && $u->id) { $userId = (int)$u->id; }
        }
    } catch (\Throwable $e) { /* ignore */ }
    $clientId = isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : 0;
    if (!$userId && !$clientId) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
        exit;
    }

    $token = $_POST['token'] ?? $_POST['csrf'] ?? $_POST['token_csrf'] ?? '';
    $valid = false;
    try {
        if (class_exists('\\WHMCS\\Security\\Token')) {
            $valid = \WHMCS\Security\Token::isValid($token);
        }
    } catch (\Throwable $e) { $valid = false; }
    if (!$valid && function_exists('check_token')) {
        try { $valid = check_token('plain', $token); } catch (\Throwable $e) { $valid = false; }
    }
    if (!$valid) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    $nid = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;
    if ($nid <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid notification id']);
        exit;
    }

    $row = Capsule::table('eb_notifications')->where('id', $nid)->first(['id','status']);
    if (!$row || $row->status !== 'published') {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Notification not found']);
        exit;
    }

    $key = 'notif:' . $nid;
    $now = date('Y-m-d H:i:s');
    try {
        if ($userId) {
            Capsule::statement(
                "INSERT INTO mod_eazybackup_dismissals (user_id, client_id, announcement_key, dismissed_at)\n                 VALUES (?, NULL, ?, ?)\n                 ON DUPLICATE KEY UPDATE dismissed_at = VALUES(dismissed_at)",
                [$userId, $key, $now]
            );
        }
        if ($clientId) {
            Capsule::statement(
                "INSERT INTO mod_eazybackup_dismissals (user_id, client_id, announcement_key, dismissed_at)\n                 VALUES (NULL, ?, ?, ?)\n                 ON DUPLICATE KEY UPDATE dismissed_at = VALUES(dismissed_at)",
                [$clientId, $key, $now]
            );
        }
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Database error']);
        exit;
    }

    echo json_encode(['ok' => true, 'id' => $nid]);
    exit;
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error']);
    exit;
}
