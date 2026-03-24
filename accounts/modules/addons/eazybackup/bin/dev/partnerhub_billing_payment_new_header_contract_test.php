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
    'shared shell include' => 'partials/partner_hub_shell.tpl',
    'header title' => "ebPhTitle='New One-time Payment'",
    'header helper text' => 'Charge a saved card for setup fees, project work, or ad-hoc adjustments.',
    'header back action' => '&a=ph-billing-payments',
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
