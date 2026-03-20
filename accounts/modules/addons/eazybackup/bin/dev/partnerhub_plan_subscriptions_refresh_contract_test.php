<?php

declare(strict_types=1);

/**
 * Contract test: canceling a subscription refreshes the current plan-scoped subscriptions list.
 *
 * Run:
 * php accounts/modules/addons/eazybackup/bin/dev/partnerhub_plan_subscriptions_refresh_contract_test.php
 */

$moduleRoot = dirname(__DIR__, 2);
$jsFile = $moduleRoot . '/assets/js/catalog-plans.js';

$source = @file_get_contents($jsFile);
if ($source === false) {
    echo "FAIL: unable to read JS file at {$jsFile}\n";
    exit(1);
}

$markers = [
    'subscriptions plan state marker' => 'subsPlanId: null,',
    'openSubs function marker' => 'async openSubs(planId){',
    'openSubs state assignment marker' => 'this.subsPlanId = planId;',
    'cancel subscription function marker' => 'async cancelSubscription(instanceId){',
    'cancel refresh marker' => 'this.openSubs(this.subsPlanId || 0);',
];

$forbidden = [
    'legacy assignPlanId refresh marker' => 'this.openSubs(this.assignPlanId || 0);',
];

$failures = [];
foreach ($markers as $markerName => $needle) {
    if (strpos($source, $needle) === false) {
        $failures[] = "FAIL: missing {$markerName}";
    }
}

foreach ($forbidden as $markerName => $needle) {
    if (strpos($source, $needle) !== false) {
        $failures[] = "FAIL: forbidden {$markerName}";
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        echo $failure . PHP_EOL;
    }
    exit(1);
}

echo "partnerhub-plan-subscriptions-refresh-contract-ok\n";
exit(0);
