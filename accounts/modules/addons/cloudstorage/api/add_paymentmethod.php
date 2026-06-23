<?php

require_once __DIR__ . '/../../../../init.php';

use WHMCS\ClientArea;
use WHMCS\Database\Capsule;
use WHMCS\Authentication\Auth;
use WHMCS\Payment\PayMethod\Adapter\RemoteCreditCard;
use WHMCS\Payment\PayMethod\Model as PayMethodModel;
use WHMCS\User\Client as ClientModel;

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

function cloudstorage_normalize_card_brand(string $brand): string
{
    $brand = trim($brand);
    if ($brand === '') {
        return 'Visa';
    }

    $normalized = strtolower(str_replace(['_', '-'], ' ', $brand));
    $map = [
        'visa' => 'Visa',
        'mastercard' => 'MasterCard',
        'master card' => 'MasterCard',
        'amex' => 'American Express',
        'american express' => 'American Express',
        'discover' => 'Discover',
        'diners' => 'Diners Club',
        'diners club' => 'Diners Club',
        'jcb' => 'JCB',
        'unionpay' => 'UnionPay',
    ];

    return $map[$normalized] ?? ucwords($normalized);
}

function cloudstorage_create_paymethod_from_request(
    int $clientId,
    string $tokenValue,
    string $description,
    array $billing = []
): ?int {
    if (!class_exists('\\WHMCS\\Http\\Message\\ServerRequest')
        || !class_exists('\\WHMCS\\Payment\\PayMethod\\Model')
        || !method_exists(PayMethodModel::class, 'factoryFromRequest')
    ) {
        return null;
    }

    $postBackup = $_POST;
    $serverMethodBackup = $_SERVER['REQUEST_METHOD'] ?? null;

    $_POST = array_merge($billing, [
        'type' => 'token_stripe',
        'paymentmethod' => 'stripe',
        'description' => $description,
        'remoteStorageToken' => $tokenValue,
        'billingcontact' => (string) $clientId,
    ]);
    $_SERVER['REQUEST_METHOD'] = 'POST';

    try {
        $request = \WHMCS\Http\Message\ServerRequest::fromGlobals();
        $payMethod = PayMethodModel::factoryFromRequest($request);
        if ($payMethod && isset($payMethod->id) && (int) $payMethod->id > 0) {
            return (int) $payMethod->id;
        }
    } catch (\Throwable $e) {
        logModuleCall('cloudstorage', 'add_paymentmethod_factory_request_error', [
            'clientid' => $clientId,
            'error' => $e->getMessage(),
        ], '', '', []);
    } finally {
        $_POST = $postBackup;
        if ($serverMethodBackup !== null) {
            $_SERVER['REQUEST_METHOD'] = $serverMethodBackup;
        } else {
            unset($_SERVER['REQUEST_METHOD']);
        }
    }

    return null;
}

function cloudstorage_create_paymethod_via_adapter(
    ClientModel $client,
    string $tokenValue,
    string $description,
    string $cardLast4,
    string $cardExpiry,
    string $cardBrand
): ?int {
    $month = (int) substr($cardExpiry, 0, 2);
    $year = 2000 + (int) substr($cardExpiry, 2, 2);
    if ($month < 1 || $month > 12 || $year < 2000) {
        throw new \InvalidArgumentException('Invalid card expiry');
    }

    $payMethod = RemoteCreditCard::factoryPayMethod($client, null, $description);
    $payMethod->gateway_name = 'stripe';
    $payMethod->save();

    $adapter = $payMethod->payment;
    if (!$adapter instanceof RemoteCreditCard) {
        throw new \RuntimeException('Remote credit card adapter missing');
    }

    $adapter->setRemoteToken($tokenValue);
    $adapter->setLastFour($cardLast4);
    $adapter->setCardType(cloudstorage_normalize_card_brand($cardBrand));
    $adapter->setExpiryDate(\WHMCS\Carbon::createFromDate($year, $month, 1));

    try {
        $adapter->createRemote();
    } catch (\Throwable $e) {
        logModuleCall('cloudstorage', 'add_paymentmethod_create_remote_warning', [
            'clientid' => (int) $client->id,
            'pay_method_id' => (int) $payMethod->id,
            'error' => $e->getMessage(),
        ], '', '', []);
    }

    $adapter->save();

    return (int) $payMethod->id;
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

    $cardLast4 = (string) ($_POST['card_last_four'] ?? $_POST['card_last4'] ?? '');
    $cardExpMonth = (string) ($_POST['card_exp_month'] ?? '');
    $cardExpYear = (string) ($_POST['card_exp_year'] ?? '');
    $cardBrand = (string) ($_POST['card_brand'] ?? '');

    $cardExpiry = '';
    if ($cardExpMonth !== '' && $cardExpYear !== '') {
        $month = str_pad($cardExpMonth, 2, '0', STR_PAD_LEFT);
        $year = strlen($cardExpYear) === 4 ? substr($cardExpYear, 2, 2) : $cardExpYear;
        $cardExpiry = $month . $year;
    }

    if ($cardLast4 === '' || $cardExpiry === '') {
        logModuleCall('cloudstorage', 'add_paymentmethod', [
            'clientid' => $clientId,
            'card_last4' => $cardLast4,
            'card_expiry' => $cardExpiry,
        ], 'missing_card_metadata', '', []);
        echo json_encode(['status' => 'error', 'message' => 'missing_card_metadata']);
        exit;
    }

    if ($remoteToken === '') {
        echo json_encode(['status' => 'error', 'message' => 'missing_payment_method']);
        exit;
    }

    $tokenValue = $remoteToken;

    $description = 'Primary Card';
    if ($cardBrand !== '') {
        $description = cloudstorage_normalize_card_brand($cardBrand) . ' ending in ' . $cardLast4;
    }

    $billing = [
        'billing_name' => (string) ($_POST['billing_name'] ?? ''),
        'billing_address_1' => (string) ($_POST['billing_address_1'] ?? ''),
        'billing_address_2' => (string) ($_POST['billing_address_2'] ?? ''),
        'billing_city' => (string) ($_POST['billing_city'] ?? ''),
        'billing_state' => (string) ($_POST['billing_state'] ?? ''),
        'billing_postcode' => (string) ($_POST['billing_postcode'] ?? ''),
        'billing_country' => (string) ($_POST['billing_country'] ?? ''),
    ];
    if ($billing['billing_name'] === '') {
        try {
            $clientRow = Capsule::table('tblclients')->where('id', $clientId)->first();
            if ($clientRow) {
                $billing['billing_name'] = trim(((string) ($clientRow->firstname ?? '')) . ' ' . ((string) ($clientRow->lastname ?? '')));
                if ($billing['billing_address_1'] === '') {
                    $billing['billing_address_1'] = (string) ($clientRow->address1 ?? '');
                }
                if ($billing['billing_city'] === '') {
                    $billing['billing_city'] = (string) ($clientRow->city ?? '');
                }
                if ($billing['billing_state'] === '') {
                    $billing['billing_state'] = (string) ($clientRow->state ?? '');
                }
                if ($billing['billing_postcode'] === '') {
                    $billing['billing_postcode'] = (string) ($clientRow->postcode ?? '');
                }
                if ($billing['billing_country'] === '') {
                    $billing['billing_country'] = (string) ($clientRow->country ?? '');
                }
            }
        } catch (\Throwable $e) {
            // non-fatal
        }
    }

    logModuleCall('cloudstorage', 'add_paymentmethod_attempt', [
        'clientid' => $clientId,
        'has_pm_id' => $paymentMethodId !== '',
        'has_remote_token' => $remoteToken !== '',
        'card_last4' => $cardLast4,
        'card_expiry' => $cardExpiry,
        'card_brand' => $cardBrand,
        'token_prefix' => substr($tokenValue, 0, 20) . '...',
    ], '', '', ['token_value']);

    $payMethodId = cloudstorage_create_paymethod_from_request(
        $clientId,
        $tokenValue,
        $description,
        $billing
    );

    if ($payMethodId === null) {
        $client = ClientModel::find($clientId);
        if (!$client) {
            echo json_encode(['status' => 'error', 'message' => 'auth']);
            exit;
        }

        try {
            $payMethodId = cloudstorage_create_paymethod_via_adapter(
                $client,
                $tokenValue,
                $description,
                $cardLast4,
                $cardExpiry,
                $cardBrand
            );
        } catch (\Throwable $e) {
            logModuleCall('cloudstorage', 'add_paymentmethod_error', [
                'clientid' => $clientId,
                'error' => $e->getMessage(),
            ], '', '', []);
            $payMethodId = null;
        }
    }

    if ($payMethodId) {
        logModuleCall('cloudstorage', 'add_paymentmethod_success', [
            'pay_method_id' => $payMethodId,
            'card_last4' => $cardLast4,
            'card_brand' => $cardBrand,
        ], 'success', '', []);

        echo json_encode(['status' => 'success', 'paymethodid' => $payMethodId]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to save payment method. Please try again.']);
    }
} catch (\Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
