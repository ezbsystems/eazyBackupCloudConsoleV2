<?php

declare(strict_types=1);

/**
 * Contract test: Partner Hub missing Stripe webhook events + trial notice wiring.
 *
 * Run:
 * php accounts/modules/addons/eazybackup/bin/dev/partnerhub_missing_webhooks_contract_test.php
 */

$moduleRoot = dirname(__DIR__, 2);

$targets = [
    'webhook controller file' => [
        'path' => $moduleRoot . '/pages/partnerhub/StripeWebhookController.php',
        'markers' => [
            'trial will end webhook marker' => "case 'customer.subscription.trial_will_end':",
            'invoice voided webhook marker' => "case 'invoice.voided':",
            'connect deauthorized webhook marker' => "case 'account.application.deauthorized':",
            'payment method attached webhook marker' => "case 'payment_method.attached':",
            'payment method detached webhook marker' => "case 'payment_method.detached':",
            'customer deleted webhook marker' => "case 'customer.deleted':",
            'trial notice persistence marker' => 'eb_ph_store_trial_notice(',
        ],
    ],
    'schema file' => [
        'path' => $moduleRoot . '/eazybackup.php',
        'markers' => [
            'partner hub notice table marker' => 'eb_partnerhub_notices',
            'partner hub notice notice_key marker' => 'notice_key',
            'partner hub notice dismissed_at marker' => 'dismissed_at',
        ],
    ],
    'overview controller file' => [
        'path' => $moduleRoot . '/pages/partnerhub/OverviewController.php',
        'markers' => [
            'overview notices query marker' => "Capsule::table('eb_partnerhub_notices')",
            'overview notices vars marker' => "'partnerhub_notices' => \$partnerHubNotices,",
        ],
    ],
    'overview template file' => [
        'path' => $moduleRoot . '/templates/whitelabel/overview.tpl',
        'markers' => [
            'overview notices loop marker' => '{if isset($partnerhub_notices) && $partnerhub_notices|@count > 0}',
            'overview trial notice banner marker' => 'Trial ending soon',
            'overview dismiss notice marker' => 'data-eb-notice-dismiss',
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

echo "partnerhub-missing-webhooks-contract-ok\n";
exit(0);
