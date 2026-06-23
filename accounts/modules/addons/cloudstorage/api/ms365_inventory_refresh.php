<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/Ms365E3Controller.php';

use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Client\Ms365E3Controller;
use Symfony\Component\HttpFoundation\JsonResponse;

header('Content-Type: application/json');

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    (new JsonResponse(['status' => 'fail', 'message' => 'auth'], 401))->send();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    (new JsonResponse(['status' => 'fail', 'message' => 'POST required'], 405))->send();
    exit;
}

$clientId = (int) $ca->getUserID();
$userId = trim((string) ($_POST['user_id'] ?? ''));
if ($userId === '') {
    (new JsonResponse(['status' => 'fail', 'message' => 'user_id is required'], 400))->send();
    exit;
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

// #region agent log
$__invRefreshStart = microtime(true);
register_shutdown_function(static function () use ($clientId, $__invRefreshStart): void {
    $err = error_get_last();
    if (!is_array($err)) {
        return;
    }
    $fatalTypes = [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE];
    if (!in_array((int) ($err['type'] ?? 0), $fatalTypes, true)) {
        return;
    }
    Ms365Backup\Ms365AgentDebugLog::write(
        'ms365_inventory_refresh.php:shutdown',
        'inventory refresh fatal shutdown',
        [
            'client_id' => $clientId,
            'duration_ms' => (int) round((microtime(true) - $__invRefreshStart) * 1000),
            'error_type' => (int) ($err['type'] ?? 0),
            'error_message' => (string) ($err['message'] ?? ''),
            'peak_memory_mb' => (int) round(memory_get_peak_usage(true) / 1048576),
        ],
        'D',
    );
});
Ms365Backup\Ms365AgentDebugLog::write(
    'ms365_inventory_refresh.php:entry',
    'inventory refresh request started',
    [
        'client_id' => $clientId,
        'user_id' => $userId,
        'session_status' => function_exists('session_status') ? session_status() : -1,
        'session_id_set' => session_id() !== '',
        'max_execution_time' => (int) ini_get('max_execution_time'),
        'memory_limit' => (string) ini_get('memory_limit'),
        'async' => true,
    ],
    'A',
);
// #endregion

try {
    $result = Ms365E3Controller::refreshInventory($clientId, $userId);
    // #region agent log
    Ms365Backup\Ms365AgentDebugLog::write(
        'ms365_inventory_refresh.php:accepted',
        'inventory refresh dispatched to background worker',
        [
            'client_id' => $clientId,
            'duration_ms' => (int) round((microtime(true) - $__invRefreshStart) * 1000),
            'refresh_in_progress' => (bool) ($result['refresh_in_progress'] ?? true),
        ],
        'B',
    );
    // #endregion
    (new JsonResponse([
        'status' => (string) ($result['status'] ?? 'accepted'),
        'refresh_in_progress' => (bool) ($result['refresh_in_progress'] ?? true),
        'message' => (string) ($result['message'] ?? 'Inventory refresh started.'),
        'inventory' => $result,
    ]))->send();
} catch (\Throwable $e) {
    // #region agent log
    Ms365Backup\Ms365AgentDebugLog::write(
        'ms365_inventory_refresh.php:error',
        'inventory refresh request failed',
        [
            'client_id' => $clientId,
            'duration_ms' => (int) round((microtime(true) - $__invRefreshStart) * 1000),
            'error_class' => $e::class,
            'error_message' => $e->getMessage(),
        ],
        'D',
    );
    // #endregion
    Ms365E3Controller::apiErrorResponse($e, 'ms365_inventory_refresh')->send();
}
