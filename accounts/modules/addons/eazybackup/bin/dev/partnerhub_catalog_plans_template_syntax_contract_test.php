<?php

declare(strict_types=1);

/**
 * Contract test: catalog plans template must avoid raw JSON object braces
 * inside Smarty loops, or Smarty will parse them as template tags.
 *
 * Run:
 * php accounts/modules/addons/eazybackup/bin/dev/partnerhub_catalog_plans_template_syntax_contract_test.php
 */

$moduleRoot = dirname(__DIR__, 2);
$path = $moduleRoot . '/templates/whitelabel/catalog-plans.tpl';
$source = @file_get_contents($path);

if ($source === false) {
    fwrite(STDERR, "FAIL: unable to read catalog-plans.tpl\n");
    exit(1);
}

$required = [
    'comet accounts smarty data script marker' => '<script type="application/json" id="eb-comet-accounts-smarty-data">',
    'safe opening brace marker' => '{ldelim}"tenant_public_id":"{$ca.tenant_public_id|escape:\'javascript\'}"',
    'safe closing brace marker' => '"tenant_name":"{$ca.tenant_name|escape:\'javascript\'}"{rdelim}{if !$smarty.foreach.cometAccounts.last},{/if}',
];

$forbidden = [
    'raw json object inside smarty loop marker' => '{"tenant_public_id":"{$ca.tenant_public_id|escape:\'javascript\'}","comet_user_id":"{$ca.comet_user_id|escape:\'javascript\'}","tenant_name":"{$ca.tenant_name|escape:\'javascript\'}"}{if !$smarty.foreach.cometAccounts.last},{/if}',
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

echo "partnerhub-catalog-plans-template-syntax-contract-ok\n";
exit(0);
