<?php

require_once __DIR__ . '/../../../../init.php';

use WHMCS\ClientArea;
use WHMCS\Database\Capsule;
use WHMCS\Authentication\Auth;

header('Content-Type: application/json');

if (!defined('WHMCS')) {
    echo json_encode(['status' => 'error', 'message' => 'invalid_request']);
    exit;
}

function cloudstorage_resolve_client_id(ClientArea $ca): int
{
    $userId = 0;
    try {
        if (class_exists('\\WHMCS\\Authentication\\Auth') && method_exists('\\WHMCS\\Authentication\\Auth', 'user')) {
            $authUser = Auth::user();
            if ($authUser && isset($authUser->id)) {
                $userId = (int) $authUser->id;
            }
        }
    } catch (\Throwable $e) {
        $userId = 0;
    }

    $clientId = 0;
    if ($userId) {
        try {
            $link = Capsule::table('tblusers_clients')
                ->where('userid', $userId)
                ->orderBy('owner', 'desc')
                ->first();
            if ($link && isset($link->clientid)) {
                $clientId = (int) $link->clientid;
            }
        } catch (\Throwable $e) {
            $clientId = 0;
        }
    }

    if ($clientId <= 0) {
        try { $clientId = (int) ($ca->getUserID() ?? 0); } catch (\Throwable $e) { $clientId = 0; }
    }
    if ($clientId <= 0 && isset($_SESSION['uid'])) {
        $clientId = (int) $_SESSION['uid'];
    }

    return $clientId;
}

try {
    $ca = new ClientArea();
    if (!$ca->isLoggedIn()) {
        echo json_encode(['status' => 'error', 'message' => 'auth']);
        exit;
    }

    $clientId = cloudstorage_resolve_client_id($ca);
    if ($clientId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'auth']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => 'error', 'message' => 'method']);
        exit;
    }

    $paymentMethodId = (string) ($_POST['payment_method_id'] ?? '');
    $remoteToken = (string) ($_POST['remote_storage_token'] ?? $_POST['gateway_token'] ?? $_POST['remoteStorageToken'] ?? $_POST['token'] ?? '');
    if ($paymentMethodId === '' && $remoteToken === '') {
        logModuleCall('cloudstorage', 'add_paymentmethod', $_POST, 'missing_payment_method', '', []);
        echo json_encode(['status' => 'error', 'message' => 'missing_payment_method']);
        exit;
    }

    // Extract card details from POST (sent by frontend after retrieving from Stripe)
    $cardLast4 = (string) ($_POST['card_last_four'] ?? $_POST['card_last4'] ?? '');
    $cardExpMonth = (string) ($_POST['card_exp_month'] ?? '');
    $cardExpYear = (string) ($_POST['card_exp_year'] ?? '');
    $cardBrand = (string) ($_POST['card_brand'] ?? '');

    // Build card expiry in MMYY format for WHMCS
    $cardExpiry = '';
    if ($cardExpMonth !== '' && $cardExpYear !== '') {
        $month = str_pad($cardExpMonth, 2, '0', STR_PAD_LEFT);
        $year = strlen($cardExpYear) === 4 ? substr($cardExpYear, 2, 2) : $cardExpYear;
        $cardExpiry = $month . $year;
    }

    // Use placeholders if card details weren't provided
    if ($cardLast4 === '') {
        $cardLast4 = '0000';
    }
    if ($cardExpiry === '') {
        // Use a future date as placeholder
        $cardExpiry = '1230'; // December 2030
    }

    $tokenValue = $remoteToken !== '' ? $remoteToken : $paymentMethodId;
    
    // Build description with card brand if available
    $description = 'Primary Card';
    if ($cardBrand !== '') {
        $description = ucfirst($cardBrand) . ' ending in ' . $cardLast4;
    }

    // Log the attempt for debugging
    logModuleCall('cloudstorage', 'add_paymentmethod_attempt', [
        'clientid' => $clientId,
        'has_pm_id' => $paymentMethodId !== '',
        'has_remote_token' => $remoteToken !== '',
        'card_last4' => $cardLast4,
        'card_expiry' => $cardExpiry,
        'card_brand' => $cardBrand,
        'token_prefix' => substr($tokenValue, 0, 20) . '...',
    ], '', '', ['token_value']);

    $payMethodId = null;

    // Skip Method 1 (WHMCS classes) - go directly to database insert for reliability
    // The WHMCS PayMethod class approach has proven unreliable across versions

    // Direct database insert with transaction for atomicity
    if ($payMethodId === null) {
        Capsule::connection()->beginTransaction();
        try {
            // Build expiry date for database
            $expiryMonth = (int) ($cardExpMonth ?: 12);
            $expiryYear = (int) ($cardExpYear ?: 2030);
            if ($expiryYear < 100) {
                $expiryYear += 2000;
            }
            $expiryDate = sprintf('%04d-%02d-01 00:00:00', $expiryYear, $expiryMonth);

            // Create the remote token JSON - format expected by WHMCS Stripe module
            $remoteTokenJson = json_encode([
                'customer' => '',  // Will be empty initially, Stripe should work with just the method
                'method' => $tokenValue,
            ]);

            // Insert into tblcreditcards first (this is the adapter/payment details table)
            $adapterId = Capsule::table('tblcreditcards')->insertGetId([
                'pay_method_id' => 0, // Will update after creating paymethods entry
                'card_type' => $cardBrand ?: 'Visa', // Default to Visa if unknown
                'last_four' => $cardLast4,
                'expiry_date' => $expiryDate,
                'card_data' => $remoteTokenJson, // Remote token stored in card_data blob
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            if (!$adapterId) {
                throw new \Exception('Failed to insert credit card record');
            }

            // Insert into tblpaymethods (the main payment method record)
            $payMethodId = Capsule::table('tblpaymethods')->insertGetId([
                'userid' => $clientId,
                'description' => $description,
                'gateway_name' => 'stripe',
                'payment_type' => 'RemoteCreditCard',
                'payment_id' => $adapterId,
                'contact_id' => $clientId,
                'contact_type' => 'Client',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            if (!$payMethodId) {
                throw new \Exception('Failed to insert payment method record');
            }

            // Update the adapter with the correct pay_method_id for bidirectional link
            Capsule::table('tblcreditcards')
                ->where('id', $adapterId)
                ->update(['pay_method_id' => $payMethodId]);

            Capsule::connection()->commit();

            logModuleCall('cloudstorage', 'add_paymentmethod_success', [
                'pay_method_id' => $payMethodId,
                'adapter_id' => $adapterId,
                'card_last4' => $cardLast4,
                'card_brand' => $cardBrand,
            ], 'success', '', []);

        } catch (\Throwable $e) {
            Capsule::connection()->rollBack();
            logModuleCall('cloudstorage', 'add_paymentmethod_error', [
                'error' => $e->getMessage(),
            ], '', '', []);
            $payMethodId = null;
        }
    }

    if ($payMethodId) {
        echo json_encode(['status' => 'success', 'paymethodid' => $payMethodId]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to save payment method. Please try again.']);
    }
} catch (\Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
