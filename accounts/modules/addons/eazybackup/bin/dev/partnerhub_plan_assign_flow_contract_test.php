<?php

declare(strict_types=1);

/**
 * Contract test: Partner Hub assign plan flow supports storage-only plans
 * without requiring an eazyBackup user and falls back to service links for
 * user discovery.
 *
 * Run:
 * php accounts/modules/addons/eazybackup/bin/dev/partnerhub_plan_assign_flow_contract_test.php
 */

$moduleRoot = dirname(__DIR__, 2);

$targets = [
    'catalog plans controller' => [
        'path' => $moduleRoot . '/pages/partnerhub/CatalogPlansController.php',
        'markers' => [
            'assignment mode helper marker' => 'function eb_ph_plan_assignment_mode(',
            'service links fallback marker' => "Capsule::table('eb_service_links as sl')",
            'storage assignment key marker' => 'function eb_ph_plan_storage_assignment_key(',
            'conditional comet validation marker' => "if (\$assignmentMode['requires_comet_user'] && \$cometUserId === '')",
        ],
    ],
    'catalog plans script' => [
        'path' => $moduleRoot . '/assets/js/catalog-plans.js',
        'markers' => [
            'assign plan meta parser marker' => "var assignPlans = parseJsonScript('eb-assign-plans-json');",
            'assign requires user helper marker' => 'assignPlanRequiresCometUser(){',
            'conditional assign validation marker' => "if (!this.assignData.tenant_id || (this.assignPlanRequiresCometUser() && !this.assignData.comet_user_id)) {",
        ],
    ],
    'catalog plans template' => [
        'path' => $moduleRoot . '/templates/whitelabel/catalog-plans.tpl',
        'markers' => [
            'assign plans json marker' => 'id="eb-assign-plans-json"',
            'conditional user label marker' => "x-text=\"assignPlanRequiresCometUser() ? 'eazyBackup user' : 'Storage assignment'\"",
            'storage assignment helper text marker' => 'Storage-based plans bill at the tenant level and do not require an eazyBackup user.',
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

echo "partnerhub-plan-assign-flow-contract-ok\n";
