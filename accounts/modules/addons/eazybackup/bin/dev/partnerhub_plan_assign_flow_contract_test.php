<?php

declare(strict_types=1);

/**
 * Contract test: Partner Hub assign plan flow supports the approved
 * Comet-user and E3-storage assignment modes.
 *
 * Run:
 * php accounts/modules/addons/eazybackup/bin/dev/partnerhub_plan_assign_flow_contract_test.php
 */

$moduleRoot = dirname(__DIR__, 2);

$targets = [
    'catalog plans controller' => [
        'path' => $moduleRoot . '/pages/partnerhub/CatalogPlansController.php',
        'markers' => [
            'service links fallback marker' => "Capsule::table('eb_service_links as sl')",
            's3 user post marker' => "\$s3UserId = (int)(\$_POST['s3_user_id'] ?? 0);",
            'e3 assignment key marker' => "\$cometUserId = 'e3:' . \$s3UserId;",
            'conditional comet validation marker' => "if (\$assignmentMode['requires_comet_user'] && \$cometUserId === '')",
        ],
    ],
    'tenant shared helpers' => [
        'path' => $moduleRoot . '/pages/partnerhub/TenantsController.php',
        'markers' => [
            'assignment mode helper marker' => 'function eb_ph_plan_assignment_mode(',
            'e3 mode marker' => "'mode' => \$isE3StorageOnly ? 'e3_storage' : 'comet_user'",
            'requires s3 user marker' => "'requires_s3_user' => \$isE3StorageOnly",
        ],
    ],
    'catalog plans script' => [
        'path' => $moduleRoot . '/assets/js/catalog-plans.js',
        'markers' => [
            'assign plan meta parser marker' => "var assignPlans = parseJsonScript('eb-assign-plans-json');",
            'assign requires user helper marker' => 'assignPlanRequiresCometUser(){',
            'assign requires s3 helper marker' => 'assignPlanRequiresS3User(){',
            'tenant selection validation marker' => 'if (!this.assignData.tenant_id) {',
            'conditional assign validation marker' => "if (this.assignPlanRequiresCometUser() && !this.assignData.comet_user_id) {",
            'conditional s3 validation marker' => "if (this.assignPlanRequiresS3User() && !this.selectedS3UserId) {",
            'assign s3 payload marker' => "body.set('s3_user_id', String(this.selectedS3UserId));",
        ],
    ],
    'catalog plans template' => [
        'path' => $moduleRoot . '/templates/whitelabel/catalog-plans.tpl',
        'markers' => [
            'assign plans json marker' => 'id="eb-assign-plans-json"',
            'e3 helper text marker' => 'This plan requires an MSP-owned S3 user instead of an eazyBackup user.',
            's3 user label marker' => '<span class="eb-field-label">S3 user</span>',
            's3 helper text marker' => 'Choose the MSP-owned S3 user that should back this storage subscription.',
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
