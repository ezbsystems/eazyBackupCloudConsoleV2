<?php
/**
 * e3 Cloud Backup - Onboarding status endpoint.
 *
 * GET or POST (no body required). Returns the 4-step onboarding state for
 * the logged-in client. Polled by the Getting Started page and used to
 * decide whether to auto-start the driver.js tour.
 */

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/OnboardingState.php';

use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Client\OnboardingState;

header('Content-Type: application/json');

try {
    $ca = new ClientArea();
    if (!$ca->isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'auth']);
        exit;
    }
    $clientId = (int) $ca->getUserID();
    if ($clientId <= 0) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'auth']);
        exit;
    }

    $payload = OnboardingState::compute($clientId);
    echo json_encode(['status' => 'success'] + $payload);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
