<?php

declare(strict_types=1);

/**
 * Contract test: Partner Hub subscription modification MVP wiring.
 *
 * Run:
 * php accounts/modules/addons/eazybackup/bin/dev/partnerhub_subscription_modification_contract_test.php
 */

$moduleRoot = dirname(__DIR__, 2);

$targets = [
    'stripe service file' => [
        'path' => $moduleRoot . '/lib/PartnerHub/StripeService.php',
        'markers' => [
            'update subscription method marker' => 'public function updateSubscription(string $subscriptionId, array $params, ?string $stripeAccount = null): array',
            'pause subscription method marker' => 'public function pauseSubscription(string $subscriptionId, ?string $stripeAccount = null): array',
            'resume subscription method marker' => 'public function resumeSubscription(string $subscriptionId, ?string $stripeAccount = null): array',
            'preview upcoming invoice method marker' => 'public function previewUpcomingInvoice(array $params, ?string $stripeAccount = null): array',
        ],
    ],
    'addon route file' => [
        'path' => $moduleRoot . '/eazybackup.php',
        'markers' => [
            'subscription detail route marker' => "'ph-plan-subscription-detail'",
            'subscription preview route marker' => "'ph-plan-subscription-preview'",
            'subscription update route marker' => "'ph-plan-subscription-update'",
            'subscription pause route marker' => "'ph-plan-subscription-pause'",
            'subscription resume route marker' => "'ph-plan-subscription-resume'",
        ],
    ],
    'catalog plans controller file' => [
        'path' => $moduleRoot . '/pages/partnerhub/CatalogPlansController.php',
        'markers' => [
            'subscription detail handler marker' => 'function eb_ph_plan_subscription_detail(array $vars): void',
            'subscription preview handler marker' => 'function eb_ph_plan_subscription_preview(array $vars): void',
            'subscription update handler marker' => 'function eb_ph_plan_subscription_update(array $vars): void',
            'subscription pause handler marker' => 'function eb_ph_plan_subscription_pause(array $vars): void',
            'subscription resume handler marker' => 'function eb_ph_plan_subscription_resume(array $vars): void',
        ],
    ],
    'catalog plans template file' => [
        'path' => $moduleRoot . '/templates/whitelabel/catalog-plans.tpl',
        'markers' => [
            'edit subscription action marker' => '@click="openSubscriptionEditor(sub.id)"',
            'pause subscription action marker' => '@click="pauseSubscription(sub.id)"',
            'resume subscription action marker' => '@click="resumeSubscription(sub.id)"',
            'subscription editor token card marker' => 'eb-card-raised',
            'subscription editor token button marker' => 'eb-btn eb-btn-primary',
            'subscription editor token input marker' => 'eb-input',
        ],
    ],
    'catalog plans js file' => [
        'path' => $moduleRoot . '/assets/js/catalog-plans.js',
        'markers' => [
            'subscription editor state marker' => 'subscriptionEditor: {',
            'open subscription editor method marker' => 'async openSubscriptionEditor(instanceId){',
            'preview subscription changes method marker' => 'async previewSubscriptionChanges(){',
            'save subscription changes method marker' => 'async saveSubscriptionChanges(){',
            'pause subscription method marker' => 'async pauseSubscription(instanceId){',
            'resume subscription method marker' => 'async resumeSubscription(instanceId){',
            'detail endpoint marker' => "modulelink + '&a=ph-plan-subscription-detail&instance_id='",
            'preview endpoint marker' => "modulelink + '&a=ph-plan-subscription-preview'",
            'update endpoint marker' => "modulelink + '&a=ph-plan-subscription-update'",
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

echo "partnerhub-subscription-modification-contract-ok\n";
exit(0);
