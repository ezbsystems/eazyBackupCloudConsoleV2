<?php

declare(strict_types=1);

/**
 * Contract test: tenant detail billing Alpine bootstrap must HTML-escape
 * embedded JSON when used inside a quoted x-data attribute.
 *
 * Run:
 * php accounts/modules/addons/eazybackup/bin/dev/partnerhub_tenant_detail_billing_alpine_contract_test.php
 */

$moduleRoot = dirname(__DIR__, 2);
$path = $moduleRoot . '/templates/whitelabel/tenant-detail.tpl';
$source = @file_get_contents($path);

if ($source === false) {
    fwrite(STDERR, "FAIL: unable to read tenant-detail.tpl\n");
    exit(1);
}

$required = [
    'billing x-data marker' => '<div x-data="{',
    'plans json escaped for html marker' => 'plans: {$billing_assignable_plans|default:array()|@json_encode|escape:\'html\'}',
    'comet users json escaped for html marker' => 'cometUsers: {$billing_tenant_comet_users|default:array()|@json_encode|escape:\'html\'}',
];

$forbidden = [
    'plans json nofilter in x-data marker' => 'plans: {$billing_assignable_plans|default:array()|@json_encode nofilter}',
    'comet users json nofilter in x-data marker' => 'cometUsers: {$billing_tenant_comet_users|default:array()|@json_encode nofilter}',
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

echo "partnerhub-tenant-detail-billing-alpine-contract-ok\n";
exit(0);
