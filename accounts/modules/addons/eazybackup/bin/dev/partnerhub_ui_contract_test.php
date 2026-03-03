<?php

declare(strict_types=1);

/**
 * Contract test: Partner Hub UI harmonization markers.
 *
 * Run:
 * php accounts/modules/addons/eazybackup/bin/dev/partnerhub_ui_contract_test.php
 */

$root = dirname(__DIR__, 2);

$targets = [
    'billing-subscriptions.tpl' => [
        'path' => $root . '/templates/whitelabel/billing-subscriptions.tpl',
        'markers' => [
            'ui token include' => "{include file=\"modules/addons/eazybackup/templates/partials/_ui-tokens.tpl\"}",
            'page shell marker' => 'min-h-screen eb-bg-page eb-text-primary',
            'card style marker' => 'rounded-2xl eb-bg-card ring-1 ring-white/10',
        ],
    ],
    'billing-invoices.tpl' => [
        'path' => $root . '/templates/whitelabel/billing-invoices.tpl',
        'markers' => [
            'ui token include' => "{include file=\"modules/addons/eazybackup/templates/partials/_ui-tokens.tpl\"}",
            'page shell marker' => 'min-h-screen eb-bg-page eb-text-primary',
            'card style marker' => 'rounded-2xl eb-bg-card ring-1 ring-white/10',
        ],
    ],
    'stripe-connect.tpl' => [
        'path' => $root . '/templates/whitelabel/stripe-connect.tpl',
        'markers' => [
            'ui token include' => "{include file=\"modules/addons/eazybackup/templates/partials/_ui-tokens.tpl\"}",
            'page shell marker' => 'min-h-screen eb-bg-page eb-text-primary',
            'card style marker' => 'rounded-2xl eb-bg-card ring-1 ring-white/10',
        ],
    ],
    'clients.tpl' => [
        'path' => $root . '/templates/whitelabel/clients.tpl',
        'markers' => [
            'ui token include' => "{include file=\"modules/addons/eazybackup/templates/partials/_ui-tokens.tpl\"}",
            'page shell marker' => 'min-h-screen eb-bg-page',
            'card style marker' => 'rounded-2xl eb-bg-card',
        ],
    ],
    'client-view.tpl' => [
        'path' => $root . '/templates/whitelabel/client-view.tpl',
        'markers' => [
            'ui token include' => "{include file=\"modules/addons/eazybackup/templates/partials/_ui-tokens.tpl\"}",
            'page shell marker' => 'min-h-screen eb-bg-page',
            'card style marker' => 'rounded-2xl eb-bg-card',
        ],
    ],
];

$failures = [];
foreach ($targets as $name => $target) {
    $source = @file_get_contents($target['path']);
    if ($source === false) {
        $failures[] = "FAIL: unable to read {$name}";
        continue;
    }
    foreach ($target['markers'] as $markerName => $needle) {
        if (strpos($source, $needle) === false) {
            $failures[] = "FAIL: {$name} missing {$markerName}";
        }
    }
}

if ($failures !== []) {
    foreach ($failures as $f) {
        echo $f . PHP_EOL;
    }
    exit(1);
}

echo "partnerhub-ui-contract-ok\n";
exit(0);
