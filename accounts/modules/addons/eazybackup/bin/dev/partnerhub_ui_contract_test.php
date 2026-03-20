<?php

declare(strict_types=1);

/**
 * Contract test: Partner Hub shared-shell markers.
 *
 * Run:
 * php accounts/modules/addons/eazybackup/bin/dev/partnerhub_ui_contract_test.php
 */

$root = dirname(__DIR__, 2);

$internalShellTargets = [
    'overview.tpl' => 'overview',
    'tenants.tpl' => 'tenants',
    'tenant-detail.tpl' => 'tenants',
    'client-view.tpl' => 'tenants',
    'branding.tpl' => 'branding',
    'branding-list.tpl' => 'branding-list',
    'catalog-products.tpl' => 'catalog-products',
    'catalog-products-list.tpl' => 'catalog-products',
    'catalog-plans.tpl' => 'catalog-plans',
    'plans.tpl' => 'catalog-plans',
    'billing-subscriptions.tpl' => 'billing-subscriptions',
    'billing-invoices.tpl' => 'billing-invoices',
    'billing-payments.tpl' => 'billing-payments',
    'billing-payment-new.tpl' => 'billing-payments',
    'subscriptions-new.tpl' => 'billing-subscriptions',
    'money-balance.tpl' => 'money-balance',
    'money-disputes.tpl' => 'money-disputes',
    'money-payouts.tpl' => 'money-payouts',
    'stripe-connect.tpl' => 'stripe-connect',
    'stripe-manage.tpl' => 'stripe-manage',
    'settings-checkout.tpl' => 'settings-checkout',
    'settings-email.tpl' => 'settings-email',
    'email-templates.tpl' => 'settings-email',
    'email-template-edit.tpl' => 'settings-email',
    'settings-tax.tpl' => 'settings-tax',
    'signup-approvals.tpl' => 'signup-approvals',
    'signup-settings.tpl' => 'signup-settings',
];

$standaloneTargets = [
    'loader.tpl' => [
        'markers' => [
            'ui token include' => '_ui-tokens.tpl',
            'shared page shell' => 'page-shell.tpl',
        ],
    ],
    'public-download.tpl' => [
        'markers' => [
            'shared auth shell' => 'auth-shell.tpl',
        ],
    ],
    'public-invalid-host.tpl' => [
        'markers' => [
            'shared auth shell' => 'auth-shell.tpl',
        ],
    ],
    'public-signup.tpl' => [
        'markers' => [
            'shared auth shell' => 'auth-shell.tpl',
        ],
    ],
];

$legacyShellNeedles = [
    'min-h-screen bg-slate-950 text-gray-100 overflow-x-hidden',
    'bg-[rgb(var(--bg-page))] text-[rgb(var(--text-primary))]',
    'container mx-auto max-w-full px-4 pb-8 pt-6',
];

$failures = [];

foreach ($internalShellTargets as $name => $sidebarPage) {
    $path = $root . '/templates/whitelabel/' . $name;
    $source = @file_get_contents($path);
    if ($source === false) {
        $failures[] = "FAIL: unable to read {$name}";
        continue;
    }

    $required = [
        'ui token include' => '_ui-tokens.tpl',
        'sidebar page marker' => "ebPhSidebarPage='{$sidebarPage}'",
    ];

    foreach ($required as $label => $needle) {
        if (strpos($source, $needle) === false) {
            $failures[] = "FAIL: {$name} missing {$label}";
        }
    }

    $sharedShellMatched = false;
    foreach ([
        'partner_hub_shell.tpl',
        'class="eb-panel !p-0"',
    ] as $needle) {
        if (strpos($source, $needle) !== false) {
            $sharedShellMatched = true;
            break;
        }
    }
    if (!$sharedShellMatched) {
        $failures[] = "FAIL: {$name} missing shared shell marker";
    }

    foreach ($legacyShellNeedles as $needle) {
        if (strpos($source, $needle) !== false) {
            $failures[] = "FAIL: {$name} still contains legacy shell marker {$needle}";
        }
    }
}

foreach ($standaloneTargets as $name => $config) {
    $path = $root . '/templates/whitelabel/' . $name;
    $source = @file_get_contents($path);
    if ($source === false) {
        $failures[] = "FAIL: unable to read {$name}";
        continue;
    }

    foreach ($config['markers'] as $label => $needle) {
        if (strpos($source, $needle) === false) {
            $failures[] = "FAIL: {$name} missing {$label}";
        }
    }

    foreach ($legacyShellNeedles as $needle) {
        if (strpos($source, $needle) !== false) {
            $failures[] = "FAIL: {$name} still contains legacy shell marker {$needle}";
        }
    }
}

foreach (glob($root . '/templates/whitelabel/*.tpl') ?: [] as $path) {
    $name = basename($path);
    if ($name === 'catalog-product.tpl') {
        continue;
    }

    $source = @file_get_contents($path);
    if ($source === false) {
        $failures[] = "FAIL: unable to read {$name}";
        continue;
    }

    foreach ($legacyShellNeedles as $needle) {
        if (strpos($source, $needle) !== false) {
            $failures[] = "FAIL: {$name} still contains legacy shell marker {$needle}";
        }
    }
}

$modalPath = $root . '/templates/whitelabel/partials/billing-payment-modal.tpl';
$modalSource = @file_get_contents($modalPath);
if ($modalSource === false) {
    $failures[] = 'FAIL: unable to read partials/billing-payment-modal.tpl';
} else {
    foreach ([
        'shared modal shell' => 'eb-modal',
        'shared button class' => 'eb-btn',
        'shared field class' => 'eb-input',
    ] as $label => $needle) {
        if (strpos($modalSource, $needle) === false) {
            $failures[] = "FAIL: partials/billing-payment-modal.tpl missing {$label}";
        }
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        echo $failure . PHP_EOL;
    }
    exit(1);
}

echo "partnerhub-ui-contract-ok\n";
exit(0);
