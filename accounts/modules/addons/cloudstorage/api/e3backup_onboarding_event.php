<?php
/**
 * e3 Cloud Backup - Onboarding event recorder.
 *
 * POST event=<download_clicked|tour_started|tour_completed|tour_dismissed>
 *
 * Records a click event into s3_e3backup_onboarding_state for the
 * logged-in client. Events are write-once: re-firing the same event does
 * NOT overwrite the original timestamp.
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

    $event = (string) ($_REQUEST['event'] ?? '');
    $allowed = [
        OnboardingState::EVENT_DOWNLOAD_CLICKED,
        OnboardingState::EVENT_TOUR_STARTED,
        OnboardingState::EVENT_TOUR_COMPLETED,
        OnboardingState::EVENT_TOUR_DISMISSED,
        OnboardingState::EVENT_FIRST_JOB_TOUR_STARTED,
        OnboardingState::EVENT_FIRST_JOB_TOUR_COMPLETED,
        OnboardingState::EVENT_FIRST_JOB_TOUR_DISMISSED,
    ];
    if (!in_array($event, $allowed, true)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'invalid_event']);
        exit;
    }

    $ok = OnboardingState::recordEvent($clientId, $event);
    echo json_encode([
        'status' => $ok ? 'success' : 'error',
        'event'  => $event,
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
