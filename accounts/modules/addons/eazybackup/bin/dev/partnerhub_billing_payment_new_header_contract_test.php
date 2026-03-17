<?php

declare(strict_types=1);

/**
 * Contract test: Partner Hub standalone payment page should use the shared
 * page header pattern with the page-level Back to Payments action.
 *
 * Run:
 * php accounts/modules/addons/eazybackup/bin/dev/partnerhub_billing_payment_new_header_contract_test.php
 */

$moduleRoot = dirname(__DIR__, 2);
$templateFile = $moduleRoot . '/templates/whitelabel/billing-payment-new.tpl';
$source = @file_get_contents($templateFile);

if ($source === false) {
    echo "FAIL: unable to read billing payment new template" . PHP_EOL;
    exit(1);
}

$requiredMarkers = [
    'shared page header' => 'class="flex items-center justify-between border-b border-slate-800/60 px-6 py-4"',
    'header title' => '<h1 class="text-2xl font-semibold tracking-tight">New One-time Payment</h1>',
    'header helper text' => 'Charge a saved card for setup fees, project work, or ad-hoc adjustments.',
    'header back action' => 'href="{$modulelink}&a=ph-billing-payments"',
];

$forbiddenMarkers = [
    'legacy in-card heading wrapper' => '<div>' . "\r\n" . '      <h1 class="text-xl font-semibold text-slate-50 tracking-tight">New One-time Payment</h1>',
    'legacy helper text copy with non-ascii hyphen' => 'ad‑hoc adjustments.',
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

echo "partnerhub-billing-payment-new-header-contract-ok\n";
exit(0);
