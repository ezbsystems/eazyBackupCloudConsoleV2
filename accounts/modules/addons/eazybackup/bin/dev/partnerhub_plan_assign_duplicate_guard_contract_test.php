<?php

declare(strict_types=1);

/**
 * Contract test: plan assignment rejects duplicate active tenant+plan+backup-user assignments.
 *
 * Run:
 * php accounts/modules/addons/eazybackup/bin/dev/partnerhub_plan_assign_duplicate_guard_contract_test.php
 */

$moduleRoot = dirname(__DIR__, 2);
$controllerFile = $moduleRoot . '/pages/partnerhub/CatalogPlansController.php';

$source = @file_get_contents($controllerFile);
if ($source === false) {
    echo "FAIL: unable to read controller at {$controllerFile}\n";
    exit(1);
}

$markers = [
    'plan assign function marker' => 'function eb_ph_plan_assign(array $vars): void',
    'duplicate instance query marker' => "Capsule::table('eb_plan_instances')",
    'tenant duplicate scope marker' => "->where('tenant_id', \$tenantId)",
    'plan duplicate scope marker' => "->where('plan_id', (int)\$plan->id)",
    'backup user duplicate scope marker' => "->where('comet_user_id', \$cometUserId)",
    'active statuses marker' => "->whereIn('status', ['active', 'trialing', 'past_due', 'paused'])",
    'duplicate error message marker' => "This plan is already assigned to this tenant with this backup user.",
];

$failures = [];
foreach ($markers as $markerName => $needle) {
    if (strpos($source, $needle) === false) {
        $failures[] = "FAIL: missing {$markerName}";
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        echo $failure . PHP_EOL;
    }
    exit(1);
}

echo "partnerhub-plan-assign-duplicate-guard-contract-ok\n";
exit(0);
