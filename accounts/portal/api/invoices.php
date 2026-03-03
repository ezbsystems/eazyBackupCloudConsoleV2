<?php

require_once __DIR__ . '/../auth.php';

use Illuminate\Database\Capsule\Manager as Capsule;

$session = portal_require_auth();

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
    portal_json(['status' => 'success', 'data' => ['invoices' => []]]);
}

$rows = Capsule::table('eb_invoice_cache')
    ->where('customer_id', (int) $customer->id)
    ->orderBy('created', 'desc')
    ->limit(50)
    ->get();

$invoices = [];
foreach ($rows as $invoice) {
    $invoices[] = [
        'stripe_invoice_id' => (string) ($invoice->stripe_invoice_id ?? ''),
        'amount_total' => (int) ($invoice->amount_total ?? 0),
        'amount_tax' => (int) ($invoice->amount_tax ?? 0),
        'status' => (string) ($invoice->status ?? ''),
        'currency' => strtoupper((string) ($invoice->currency ?? 'USD')),
        'created' => (int) ($invoice->created ?? 0),
        'hosted_invoice_url' => (string) ($invoice->hosted_invoice_url ?? ''),
        'send_url' => (string) ($invoice->hosted_invoice_url ?? ''),
        'download_url' => (string) ($invoice->hosted_invoice_url ?? ''),
    ];
}

portal_json([
    'status' => 'success',
    'data' => [
        'invoices' => $invoices,
    ],
]);
