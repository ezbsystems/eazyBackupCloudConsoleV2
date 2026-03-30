<?php

declare(strict_types=1);

$moduleRoot = dirname(__DIR__, 2);
$target = $moduleRoot . '/bin/partnerhub_usage_job.php';

$source = @file_get_contents($target);
if ($source === false) {
    fwrite(STDERR, "FAIL: unable to read partnerhub usage job\n");
    exit(1);
}

$requiredMarkers = [
    'local scoped resolver helper marker' => 'function partnerhub_usage_job_resolve_plan_instance_metered_item(int $planInstanceId, string $metricCode): ?array',
    'plan instance item query marker' => "->where('pii.plan_instance_id', \$planInstanceId)",
    'storage branch scoped resolver marker' => "\$meteredItem = partnerhub_usage_job_resolve_plan_instance_metered_item((int) \$pi->id, 'STORAGE_TB');",
    'e3 branch scoped resolver marker' => "\$meteredItem = partnerhub_usage_job_resolve_plan_instance_metered_item((int) \$pi->id, 'E3_STORAGE_GIB');",
];

$failures = [];
foreach ($requiredMarkers as $label => $needle) {
    if (strpos($source, $needle) === false) {
        $failures[] = "FAIL: missing {$label}";
    }
}

$forbiddenMarkers = [
    "resolveActivePlanInstanceMeteredItem(\$tenantId, 'STORAGE_TB')",
    "resolveActivePlanInstanceMeteredItem(\$tenantId, 'E3_STORAGE_GIB')",
];

foreach ($forbiddenMarkers as $needle) {
    if (strpos($source, $needle) !== false) {
        $failures[] = "FAIL: found tenant-scoped metered resolver usage {$needle}";
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        echo $failure . PHP_EOL;
    }
    exit(1);
}

echo "partnerhub-usage-job-contract-ok\n";
