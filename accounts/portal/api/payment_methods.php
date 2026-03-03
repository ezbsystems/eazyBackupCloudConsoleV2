<?php

require_once __DIR__ . '/../auth.php';

use Illuminate\Database\Capsule\Manager as Capsule;

$session = portal_require_auth_json();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    portal_json(['status' => 'fail', 'message' => 'Invalid method'], 405);
}

$tenantId = (int) ($session['tenant_id'] ?? 0);
if ($tenantId <= 0) {
    portal_json(['status' => 'fail', 'message' => 'Invalid session'], 401);
}

$customer = Capsule::table('eb_customers')
    ->where('tenant_id', $tenantId)
    ->first(['id']);

if (!$customer) {
    portal_json(['status' => 'success', 'data' => ['payment_methods' => []]]);
}

$rows = Capsule::table('eb_payment_cache')
    ->where('customer_id', (int) $customer->id)
    ->orderBy('created', 'desc')
    ->limit(50)
    ->get();

$paymentMethods = [];
foreach ($rows as $payment) {
    $paymentMethods[] = [
        'stripe_payment_intent_id' => (string) ($payment->stripe_payment_intent_id ?? ''),
        'status' => (string) ($payment->status ?? ''),
        'currency' => strtoupper((string) ($payment->currency ?? 'USD')),
        'amount' => (int) ($payment->amount ?? 0),
        'created' => (int) ($payment->created ?? 0),
    ];
}

portal_json([
    'status' => 'success',
    'data' => [
        'payment_methods' => $paymentMethods,
    ],
]);
