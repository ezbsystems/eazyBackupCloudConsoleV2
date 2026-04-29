<?php

use WHMCS\Database\Capsule;

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../eazybackup.php';

header('Content-Type: application/json');

try {
    $clientId = isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : 0;
    if ($clientId <= 0) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
        exit;
    }

    $token = $_POST['token'] ?? $_POST['csrf'] ?? '';
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

    $mid = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;
    if ($mid <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid message id']);
        exit;
    }

    $row = Capsule::table('eb_client_messages')
        ->where('id', $mid)->where('client_id', $clientId)->first(['id','deleted_at']);
    if (!$row || !empty($row->deleted_at)) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Message not found']);
        exit;
    }

    $now = date('Y-m-d H:i:s');
    Capsule::table('eb_client_messages')
        ->where('id', $mid)->where('client_id', $clientId)
        ->whereNull('viewed_at')
        ->update(['viewed_at' => $now, 'updated_at' => $now]);

    echo json_encode(['ok' => true, 'id' => $mid]);
    exit;
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error']);
    exit;
}
