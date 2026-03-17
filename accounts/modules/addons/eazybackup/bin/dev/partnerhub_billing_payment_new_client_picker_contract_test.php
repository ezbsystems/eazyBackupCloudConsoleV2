<?php

declare(strict_types=1);

/**
 * Contract test: Partner Hub standalone payment page should use a searchable
 * Alpine client picker instead of a native select element.
 *
 * Run:
 * php accounts/modules/addons/eazybackup/bin/dev/partnerhub_billing_payment_new_client_picker_contract_test.php
 */

$moduleRoot = dirname(__DIR__, 2);
$templateFile = $moduleRoot . '/templates/whitelabel/billing-payment-new.tpl';
$source = @file_get_contents($templateFile);

if ($source === false) {
    echo "FAIL: unable to read billing payment new template" . PHP_EOL;
    exit(1);
}

$requiredMarkers = [
    'alpine client picker state' => 'tenantSearch: ""',
    'tenant data json binding' => 'tenants: {$tenants|json_encode|escape:"html"}',
    'tenant public id lookup marker' => 'tenant.public_id',
    'tenant search placeholder' => 'Start typing a tenant name or email',
    'contact email filter marker' => 'tenant.contact_email',
    'hidden tenant input' => '<input id="np-tenant" type="hidden"',
    'single-line trigger marker' => 'truncate whitespace-nowrap',
];

$forbiddenMarkers = [
    'legacy native select' => '<select id="np-tenant"',
    'stacked closed-trigger helper text' => 'Search by tenant name or contact email.',
    'legacy numeric tenant lookup marker' => 'String(tenant.id)',
    'legacy numeric tenant selection marker' => 'tenant.id || ""',
];

$failures = [];

foreach ($requiredMarkers as $name => $needle) {
    if (strpos($source, $needle) === false) {
        $failures[] = "FAIL: missing {$name}";
    }
}

foreach ($forbiddenMarkers as $name => $needle) {
    if (strpos($source, $needle) !== false) {
        $failures[] = "FAIL: found {$name}";
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        echo $failure . PHP_EOL;
    }
    exit(1);
}

echo "partnerhub-billing-payment-new-client-picker-contract-ok\n";
exit(0);
