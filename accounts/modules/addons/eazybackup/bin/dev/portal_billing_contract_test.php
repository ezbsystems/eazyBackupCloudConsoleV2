<?php

declare(strict_types=1);

/**
 * Contract test: portal billing route + APIs + template wiring.
 *
 * Run:
 * php accounts/modules/addons/eazybackup/bin/dev/portal_billing_contract_test.php
 */

$moduleRoot = dirname(__DIR__, 2);
$repoRoot = dirname($moduleRoot, 4);

$indexFile = $repoRoot . '/accounts/portal/index.php';
$layoutTemplateFile = $repoRoot . '/accounts/portal/templates/layout.tpl';
$billingTemplateFile = $repoRoot . '/accounts/portal/templates/billing.tpl';
$invoicesApiFile = $repoRoot . '/accounts/portal/api/invoices.php';
$paymentMethodsApiFile = $repoRoot . '/accounts/portal/api/payment_methods.php';

$targets = [
    'portal index route file' => [
        'path' => $indexFile,
        'markers' => [
            'invoices api route marker' => "'invoices' => __DIR__ . '/api/invoices.php'",
            'payment methods api route marker' => "'payment_methods' => __DIR__ . '/api/payment_methods.php'",
            'billing page switch marker' => "case 'billing':",
            'billing template marker' => "\$template = 'billing.tpl';",
        ],
    ],
    'portal layout template file' => [
        'path' => $layoutTemplateFile,
        'markers' => [
            'billing nav href marker' => 'href="index.php?page=billing"',
            'billing nav label marker' => '>Billing<',
        ],
    ],
    'portal billing template file' => [
        'path' => $billingTemplateFile,
        'markers' => [
            'invoices api endpoint marker' => "index.php?api=invoices",
            'payment methods api endpoint marker' => "index.php?api=payment_methods",
            'invoice send link marker' => 'Send invoice',
            'invoice download link marker' => 'Download',
            'hosted invoice field marker' => 'invoice.hosted_invoice_url',
            'invoice table body marker' => 'id="billing-invoices-body"',
            'payment methods table body marker' => 'id="billing-payment-methods-body"',
        ],
    ],
    'portal invoices api file' => [
        'path' => $invoicesApiFile,
        'markers' => [
            'auth include marker' => "require_once __DIR__ . '/../auth.php';",
            'auth required marker' => '$session = portal_require_auth_json();',
            'method guard marker' => "\$_SERVER['REQUEST_METHOD'] !== 'GET'",
            'tenant id marker' => '$tenantId = (int) ($session[\'tenant_id\'] ?? 0);',
            'tenant scoped customer lookup marker' => "->where('tenant_id', \$tenantId)",
            'invoice cache query marker' => "Capsule::table('eb_invoice_cache')",
            'hosted invoice url response marker' => "'hosted_invoice_url' => (string) (\$invoice->hosted_invoice_url ?? ''),",
            'send link response marker' => "'send_url' => (string) (\$invoice->hosted_invoice_url ?? ''),",
            'download link response marker' => "'download_url' => (string) (\$invoice->hosted_invoice_url ?? ''),",
        ],
    ],
    'portal payment methods api file' => [
        'path' => $paymentMethodsApiFile,
        'markers' => [
            'auth include marker' => "require_once __DIR__ . '/../auth.php';",
            'auth required marker' => '$session = portal_require_auth_json();',
            'method guard marker' => "\$_SERVER['REQUEST_METHOD'] !== 'GET'",
            'tenant id marker' => '$tenantId = (int) ($session[\'tenant_id\'] ?? 0);',
            'tenant scoped customer lookup marker' => "->where('tenant_id', \$tenantId)",
            'payment cache query marker' => "Capsule::table('eb_payment_cache')",
            'payment intent marker' => "'stripe_payment_intent_id' => (string) (\$payment->stripe_payment_intent_id ?? ''),",
            'payment status marker' => "'status' => (string) (\$payment->status ?? ''),",
        ],
    ],
];

$failures = [];
foreach ($targets as $targetName => $target) {
    $path = $target['path'];
    $source = @file_get_contents($path);
    if ($source === false) {
        $failures[] = "FAIL: unable to read {$targetName} at {$path}";
        continue;
    }

    foreach ($target['markers'] as $markerName => $needle) {
        if (strpos($source, $needle) === false) {
            $failures[] = "FAIL: missing {$markerName}";
        }
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        echo $failure . PHP_EOL;
    }
    exit(1);
}

echo "portal-billing-contract-ok\n";
exit(0);
