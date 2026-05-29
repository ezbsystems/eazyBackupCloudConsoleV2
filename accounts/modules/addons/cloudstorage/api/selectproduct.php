<?php

require_once __DIR__ . '/../../../../init.php';

use WHMCS\Database\Capsule;
use WHMCS\ClientArea;

header('Content-Type: application/json');

try {
    $ca = new ClientArea();
    if (!$ca->isLoggedIn()) {
        echo json_encode(['status' => 'error', 'message' => 'auth']);
        exit;
    }

    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    // Resolve the client id robustly. In the welcome / verifytrial SSO session
    // $ca->getUserID() frequently returns 0 even though isLoggedIn() is true,
    // so mirror the resolution chain used by set_portal_password.php and
    // setpassword_and_provision.php: Auth::user() -> tblusers_clients ->
    // getUserID() -> $_SESSION['uid'].
    $userId = 0;
    try {
        if (class_exists('\\WHMCS\\Authentication\\Auth') && method_exists('\\WHMCS\\Authentication\\Auth', 'user')) {
            $authUser = \WHMCS\Authentication\Auth::user();
            if ($authUser && isset($authUser->id)) {
                $userId = (int) $authUser->id;
            }
        }
    } catch (\Throwable $e) {}

    $clientId = 0;
    try {
        if ($userId) {
            $link = Capsule::table('tblusers_clients')
                ->where('userid', $userId)
                ->orderBy('owner', 'desc')
                ->first();
            if ($link && isset($link->clientid)) {
                $clientId = (int) $link->clientid;
            }
        }
    } catch (\Throwable $e) {}
    if ($clientId <= 0) {
        try { $clientId = (int) ($ca->getUserID() ?? 0); } catch (\Throwable $e) {}
    }
    if ($clientId <= 0 && isset($_SESSION['uid'])) {
        $clientId = (int) $_SESSION['uid'];
    }
    if ($clientId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'auth']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => 'error', 'message' => 'method']);
        exit;
    }

    $choice = isset($_POST['product_choice']) ? strtolower(trim((string)$_POST['product_choice'])) : '';
    $valid = ['backup','cloudbackup','storage','cloudstorage','ms365','m365','cloud2cloud','cloud-to-cloud','e3backup','e3_backup','e3-backup','cloudbackup_e3'];
    if ($choice === '' || !in_array($choice, $valid, true)) {
        echo json_encode(['status' => 'error', 'message' => 'invalid_choice']);
        exit;
    }

    // Normalize choice
    if (in_array($choice, ['backup','cloudbackup'], true)) $choice = 'backup';
    if (in_array($choice, ['storage','cloudstorage'], true)) $choice = 'storage';
    if (in_array($choice, ['ms365','m365'], true)) $choice = 'ms365';
    if (in_array($choice, ['cloud2cloud','cloud-to-cloud'], true)) $choice = 'cloud2cloud';
    if (in_array($choice, ['e3backup','e3_backup','e3-backup','cloudbackup_e3'], true)) $choice = 'e3backup';

    // Gate e3backup behind the beta gate.
    if ($choice === 'e3backup') {
        require_once __DIR__ . '/../lib/Beta/BetaGate.php';
        if (!\WHMCS\Module\Addon\CloudStorage\Beta\BetaGate::isE3BackupVisible($clientId)) {
            echo json_encode(['status' => 'error', 'message' => 'beta_unavailable']);
            exit;
        }
    }

    // Persist selection to a table if available; otherwise, fall back to trial verifications meta
    $persisted = false;
    try {
        if (Capsule::schema()->hasTable('cloudstorage_trial_selection')) {
            $now = date('Y-m-d H:i:s');
            $data = [
                'client_id'      => $clientId,
                'product_choice' => $choice,
                'meta'           => json_encode([], JSON_UNESCAPED_SLASHES),
                'updated_at'     => $now,
            ];
            // Upsert by client_id
            $exists = Capsule::table('cloudstorage_trial_selection')->where('client_id', $clientId)->first();
            if ($exists) {
                Capsule::table('cloudstorage_trial_selection')->where('client_id', $clientId)->update($data);
            } else {
                $data['created_at'] = $now;
                Capsule::table('cloudstorage_trial_selection')->insert($data);
            }
            $persisted = true;
        }
    } catch (\Throwable $e) {
        $persisted = false;
    }

    if (!$persisted) {
        // Try to write into most recent unconsumed verification meta
        try {
            $row = Capsule::table('cloudstorage_trial_verifications')
                ->where('client_id', $clientId)
                ->whereNull('consumed_at')
                ->orderBy('id', 'desc')
                ->first();
            if ($row) {
                $meta = [];
                if (!empty($row->meta)) {
                    $dec = json_decode($row->meta, true);
                    if (is_array($dec)) $meta = $dec;
                }
                $meta['product_choice'] = $choice;
                Capsule::table('cloudstorage_trial_verifications')->where('id', $row->id)->update([
                    'meta' => json_encode($meta, JSON_UNESCAPED_SLASHES),
                ]);
                $persisted = true;
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    echo json_encode([
        'status' => 'success',
        'product_choice' => $choice,
        'persisted' => $persisted,
    ]);
} catch (\Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'server']);
}


