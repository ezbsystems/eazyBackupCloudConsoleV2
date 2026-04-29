<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../eazybackup.php';

header('Content-Type: application/json');

try {
    if (empty($_SESSION['adminid']) || (int)$_SESSION['adminid'] <= 0) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Not authorized']);
        exit;
    }

    $valid = false;
    try {
        if (function_exists('check_token')) {
            $valid = check_token('WHMCS.admin.default');
        }
    } catch (\Throwable $e) { $valid = false; }
    if (!$valid) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    $audienceType = (string)($_POST['audience_type'] ?? 'all');
    if (!in_array($audienceType, ['all','filtered'], true)) $audienceType = 'all';

    $products = isset($_POST['target_products']) && is_array($_POST['target_products'])
        ? array_map('intval', $_POST['target_products']) : [];
    $groups   = isset($_POST['target_groups']) && is_array($_POST['target_groups'])
        ? array_map('intval', $_POST['target_groups']) : [];

    $clientsRaw = (string)($_POST['target_clients'] ?? '');
    $clients = [];
    foreach (preg_split('/[\s,;]+/', $clientsRaw) as $tok) {
        $tok = trim($tok);
        if ($tok !== '' && ctype_digit($tok)) $clients[] = (int)$tok;
    }

    $count = function_exists('eb_count_notification_audience')
        ? eb_count_notification_audience($audienceType, $products, $groups, $clients)
        : 0;

    echo json_encode([
        'ok' => true,
        'audience_type' => $audienceType,
        'count' => (int)$count,
        'computed_at' => date('Y-m-d H:i:s'),
    ]);
    exit;
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error']);
    exit;
}
