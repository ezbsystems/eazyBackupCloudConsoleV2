<?php

declare(strict_types=1);

/**
 * Contract test: Partner Hub enable reuses unlinked client WL tenants.
 *
 * Run:
 * php accounts/modules/addons/eazybackup/bin/dev/whitelabel_enable_reuse_contract_test.php
 */

$moduleRoot = dirname(__DIR__, 2);
$buildControllerFile = $moduleRoot . '/pages/whitelabel/BuildController.php';

$targets = [
    'BuildController' => [
        'path' => $buildControllerFile,
        'markers' => [
            'finish enable helper' => 'function eazybackup_whitelabel_finish_enable_existing_tenant(',
            'reusable lookup helper' => 'function eazybackup_whitelabel_find_reusable_unlinked_tenant(',
            'unlinked canonical null check' => 'whereNull(\'canonical_tenant_id\')',
            'reuse status filter' => '->whereIn(\'status\', [\'queued\', \'building\', \'active\'])',
            'reuse oldest wins' => '->orderBy(\'id\', \'asc\')',
            'link canonical on reuse' => '\'canonical_tenant_id\' => $canonicalTenantId',
            'reuse before insert' => 'eazybackup_whitelabel_find_reusable_unlinked_tenant($clientId)',
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

echo "whitelabel-enable-reuse-contract-ok\n";
exit(0);
