<?php

declare(strict_types=1);

/**
 * Contract test: Partner Hub billing payments page uses the standalone
 * one-time payment template route instead of the inline modal.
 *
 * Run:
 * php accounts/modules/addons/eazybackup/bin/dev/partnerhub_billing_payment_modal_contract_test.php
 */

$moduleRoot = dirname(__DIR__, 2);
$paymentsTemplateFile = $moduleRoot . '/templates/whitelabel/billing-payments.tpl';

$targets = [
    'billing payments template file' => [
        'path' => $paymentsTemplateFile,
        'markers' => [
            'new payment page link marker' => 'href="{$modulelink}&a=ph-billing-payment-new"',
            'empty state page link marker' => 'href="{$modulelink}&a=ph-billing-payment-new"',
        ],
    ],
];

$failures = [];
foreach ($targets as $targetName => $target) {
    $source = @file_get_contents($target['path']);
    if ($source === false) {
        $failures[] = "FAIL: unable to read {$targetName}";
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

if (strpos((string)@file_get_contents($paymentsTemplateFile), 'billing-payment-modal.tpl') !== false) {
    echo "FAIL: modal partial include should be removed from billing payments template" . PHP_EOL;
    exit(1);
}

if (strpos((string)@file_get_contents($paymentsTemplateFile), 'paymentModalOpen') !== false) {
    echo "FAIL: modal state should be removed from billing payments template" . PHP_EOL;
    exit(1);
}

echo "partnerhub-billing-payment-page-contract-ok\n";
exit(0);
