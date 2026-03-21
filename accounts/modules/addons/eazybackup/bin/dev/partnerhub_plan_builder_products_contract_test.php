<?php

declare(strict_types=1);

/**
 * Contract test: plan builder product bootstrap must iterate
 * Capsule collections directly, not via collection-to-array casts.
 *
 * Run:
 * php accounts/modules/addons/eazybackup/bin/dev/partnerhub_plan_builder_products_contract_test.php
 */

$moduleRoot = dirname(__DIR__, 2);
$path = $moduleRoot . '/pages/partnerhub/CatalogPlansController.php';
$source = @file_get_contents($path);

if ($source === false) {
    fwrite(STDERR, "FAIL: unable to read CatalogPlansController.php\n");
    exit(1);
}

$required = [
    'plans iteration marker' => 'foreach ($plans as $r)',
    'components iteration marker' => 'foreach ($components as $r)',
    'prices iteration marker' => 'foreach ($prices as $r)',
    'tenants iteration marker' => 'foreach ($tenants as $r)',
    'products iteration marker' => 'foreach ($products as $row)',
];

$forbidden = [
    'forbidden plans collection cast' => 'foreach ((array)$plans as $r)',
    'forbidden components collection cast' => 'foreach ((array)$components as $r)',
    'forbidden prices collection cast' => 'foreach ((array)$prices as $r)',
    'forbidden tenants collection cast' => 'foreach ((array)$tenants as $r)',
    'forbidden products collection cast' => 'foreach ((array)$products as $row)',
];

$failures = [];
foreach ($required as $name => $needle) {
    if (strpos($source, $needle) === false) {
        $failures[] = "FAIL: missing {$name}";
    }
}
foreach ($forbidden as $name => $needle) {
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

echo "partnerhub-plan-builder-products-contract-ok\n";
