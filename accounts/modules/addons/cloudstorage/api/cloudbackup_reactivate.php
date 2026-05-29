<?php
/**
 * Customer-facing reactivation endpoint.
 *
 * Called after the customer adds a payment method on a Cloud Storage page.
 * Triggers an immediate trial-state evaluation for the current client; if a
 * card is now on file and a trial state row exists, transitions the trial
 * into "converted" and lifts the suspension on both products.
 *
 * Returns JSON: { status: success|error, new_state: string }
 */

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Provision/E3CloudBackupProductBootstrap.php';
require_once __DIR__ . '/../lib/Admin/E3CloudBackupTrial.php';

use WHMCS\ClientArea;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Admin\E3CloudBackupTrial;

header('Content-Type: application/json');

try {
    $ca = new ClientArea();
    if (!$ca->isLoggedIn()) {
        echo json_encode(['status' => 'error', 'message' => 'auth']);
        exit;
    }
    $clientId = (int) $ca->getUserID();
    if ($clientId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'auth']);
        exit;
    }

    $rows = Capsule::table('s3_cloudbackup_trial_state')
        ->where('client_id', $clientId)
        ->whereIn('status', ['trialing', 'suspended_no_payment'])
        ->get();

    if (count($rows) === 0) {
        echo json_encode(['status' => 'success', 'new_state' => 'no_trial_active']);
        exit;
    }

    $results = [];
    foreach ($rows as $row) {
        $newState = E3CloudBackupTrial::evaluateService((int) $row->service_id);
        $results[(int) $row->service_id] = $newState;
    }

    echo json_encode([
        'status' => 'success',
        'results' => $results,
    ]);
} catch (\Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
