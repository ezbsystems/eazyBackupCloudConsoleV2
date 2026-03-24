<?php

declare(strict_types=1);

/**
 * Contract test: Partner Hub plan-assignment and plan-builder menus
 * must use Alpine-powered searchable menu components instead of native selects.
 *
 * Run:
 * php accounts/modules/addons/eazybackup/bin/dev/partnerhub_alpine_menu_upgrade_contract_test.php
 */

$moduleRoot = dirname(__DIR__, 2);
$tenantDetailTemplate = $moduleRoot . '/templates/whitelabel/tenant-detail.tpl';
$catalogPlansTemplate = $moduleRoot . '/templates/whitelabel/catalog-plans.tpl';

$targets = [
    'tenant detail template' => [
        'path' => $tenantDetailTemplate,
        'required' => [
            'assign plan picker placeholder marker' => 'Search plans...',
            'assign backup user picker placeholder marker' => 'Search backup users...',
            'assign plan picker button marker' => "@click=\"assignPlanOpen = !assignPlanOpen",
            'assign backup user picker dropdown marker' => 'x-show="assignCometUserOpen"',
        ],
        'forbidden' => [
            'native assign plan select marker' => '<select class="eb-input" x-model="selectedPlanId">',
            'native assign backup user select marker' => '<select class="eb-input" x-model="selectedCometUserId">',
        ],
    ],
    'catalog plans template' => [
        'path' => $catalogPlansTemplate,
        'required' => [
            'billing interval search marker' => 'Search billing intervals...',
            'currency search marker' => 'Search currencies...',
            'status search marker' => 'Search statuses...',
            'catalog type search marker' => 'Search product types...',
            'overage search marker' => 'Search overage rules...',
        ],
        'forbidden' => [
            'native billing interval select marker' => '<select x-model="planData.billing_interval" class="eb-select mt-2">',
            'native currency select marker' => '<select x-model="planData.currency" class="eb-select mt-2">',
            'native status select marker' => '<select x-model="planData.status" class="eb-select mt-2">',
            'native catalog type select marker' => '<select x-model="catalogTypeFilter" class="eb-select w-full rounded-full md:w-56">',
            'native overage select marker' => '<select x-model="comp.overage_mode" class="eb-select mt-1 !text-xs">',
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

    foreach ($target['required'] as $markerName => $needle) {
        if (strpos($source, $needle) === false) {
            $failures[] = "FAIL: missing {$markerName}";
        }
    }

    foreach ($target['forbidden'] as $markerName => $needle) {
        if (strpos($source, $needle) !== false) {
            $failures[] = "FAIL: found {$markerName}";
        }
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        echo $failure . PHP_EOL;
    }
    exit(1);
}

echo "partnerhub-alpine-menu-upgrade-contract-ok\n";
exit(0);
