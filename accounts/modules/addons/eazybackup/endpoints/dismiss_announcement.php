<?php

use WHMCS\Database\Capsule;

// WHMCS bootstrap
require_once __DIR__ . '/../../../../init.php';

// Module main file (ensures autoloaders, helpers, constants)
require_once __DIR__ . '/../eazybackup.php';
require_once __DIR__ . '/../lib/constants.php';

header('Content-Type: application/json');

// Enforce authentication: allow WHMCS User or Client session
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

    // CSRF validation
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

    // Validate announcement key
    $key = isset($_POST['announcement_key']) ? (string)$_POST['announcement_key'] : '';
    if ($key === '' || !defined('ANNOUNCEMENT_KEY') || $key !== ANNOUNCEMENT_KEY) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid announcement key']);
        exit;
    }

    // Upsert dismissal by user_id when available, and also by client_id as fallback
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

    echo json_encode(['ok' => true]);
    exit;
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error']);
    exit;
}


