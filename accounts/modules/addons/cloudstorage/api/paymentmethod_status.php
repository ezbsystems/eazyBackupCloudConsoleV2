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

function cloudstorage_get_stripe_publishable_key(): string
{
    $stripePublishableKey = '';
    if (!function_exists('getGatewayVariables')) {
        $gwInc = __DIR__ . '/../../../../includes/gatewayfunctions.php';
        if (is_file($gwInc)) {
            require_once $gwInc;
        }
    }
    try {
        if (Capsule::schema()->hasTable('tblpaymentgateways')) {
            $candidate = (string) (Capsule::table('tblpaymentgateways')
                ->where('gateway', 'stripe')
                ->whereIn('setting', ['publishableKey', 'publishablekey'])
                ->value('value') ?? '');
            if ($candidate === '' || strpos($candidate, 'pk_') !== 0) {
                $fallback = Capsule::table('tblpaymentgateways')
                    ->where('gateway', 'stripe')
                    ->where('value', 'like', 'pk\_%')
                    ->orderBy('setting')
                    ->value('value');
                if (is_string($fallback) && $fallback !== '') {
                    $candidate = $fallback;
                }
            }
            if (strpos((string) $candidate, 'pk_') === 0) {
                $stripePublishableKey = (string) $candidate;
            }
        }
        if ($stripePublishableKey === '' && function_exists('getGatewayVariables')) {
            $gw = getGatewayVariables('stripe');
            if (is_array($gw)) {
                $cand2 = (string) ($gw['publishableKey'] ?? $gw['publishablekey'] ?? $gw['publishable_key'] ?? $gw['public_key'] ?? '');
                if (strpos($cand2, 'pk_') === 0) {
                    $stripePublishableKey = $cand2;
                }
            }
        }
    } catch (\Throwable $e) {
        $stripePublishableKey = '';
    }

    return $stripePublishableKey;
}

function cloudstorage_client_has_stripe_card(int $clientId): bool
{
    if ($clientId <= 0) {
        return false;
    }

    $hasCard = false;
    try {
        if (class_exists('\\WHMCS\\Payment\\PayMethod\\PayMethod')) {
            $pmQuery = \WHMCS\Payment\PayMethod\PayMethod::where('userid', $clientId)
                ->whereNull('deleted_at')
                ->whereIn('payment_type', ['CreditCard', 'RemoteCreditCard']);
            $payMethods = $pmQuery->get();
            foreach ($payMethods as $pm) {
                $hasCard = true;
                break;
            }
        }
    } catch (\Throwable $e) {
        $hasCard = false;
    }

    if (!$hasCard) {
        try {
            if (Capsule::schema()->hasTable('tblpaymethods')) {
                $q = Capsule::table('tblpaymethods')
                    ->where('userid', $clientId)
                    ->whereNull('deleted_at')
                    ->whereIn('payment_type', ['CreditCard', 'RemoteCreditCard']);
                $hasCard = $q->exists();
            }
        } catch (\Throwable $e) {
            $hasCard = false;
        }
    }

    if (!$hasCard) {
        try {
            $resp = localAPI('GetPayMethods', ['clientid' => $clientId]);
            if (($resp['result'] ?? '') === 'success' && !empty($resp['paymethods']) && is_array($resp['paymethods'])) {
                foreach ($resp['paymethods'] as $pm) {
                    $ptype = strtolower((string) ($pm['payment_type'] ?? ''));
                    if ($ptype === 'creditcard' || $ptype === 'remotecreditcard') {
                        $hasCard = true;
                        break;
                    }
                }
            }
        } catch (\Throwable $e) {
            $hasCard = false;
        }
    }

    return $hasCard;
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

    $billing = [
        'billing_name' => '',
        'billing_address_1' => '',
        'billing_address_2' => '',
        'billing_city' => '',
        'billing_state' => '',
        'billing_postcode' => '',
        'billing_country' => '',
    ];

    try {
        $client = Capsule::table('tblclients')->where('id', $clientId)->first();
        if ($client) {
            $name = trim(((string) ($client->firstname ?? '')) . ' ' . ((string) ($client->lastname ?? '')));
            $billing['billing_name'] = $name;
            $billing['billing_address_1'] = (string) ($client->address1 ?? '');
            $billing['billing_address_2'] = (string) ($client->address2 ?? '');
            $billing['billing_city'] = (string) ($client->city ?? '');
            $billing['billing_state'] = (string) ($client->state ?? '');
            $billing['billing_postcode'] = (string) ($client->postcode ?? '');
            $billing['billing_country'] = (string) ($client->country ?? '');
        }
    } catch (\Throwable $e) {
        // non-fatal
    }

    $currencyCode = 'USD';
    try {
        $currencyId = (int) (Capsule::table('tblclients')->where('id', $clientId)->value('currency') ?? 0);
        if ($currencyId > 0) {
            $code = (string) (Capsule::table('tblcurrencies')->where('id', $currencyId)->value('code') ?? '');
            if ($code !== '') { $currencyCode = strtoupper($code); }
        } else {
            $code = (string) (Capsule::table('tblcurrencies')->where('default', 1)->value('code') ?? '');
            if ($code !== '') { $currencyCode = strtoupper($code); }
        }
    } catch (\Throwable $e) {
        $currencyCode = 'USD';
    }

    echo json_encode([
        'status' => 'success',
        'has_card' => cloudstorage_client_has_stripe_card($clientId),
        'stripe_publishable_key' => cloudstorage_get_stripe_publishable_key(),
        'currency_code' => $currencyCode,
        'billing' => $billing,
    ]);
} catch (\Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'server']);
}
