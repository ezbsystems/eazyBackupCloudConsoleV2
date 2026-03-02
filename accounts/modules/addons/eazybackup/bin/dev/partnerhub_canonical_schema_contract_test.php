<?php

declare(strict_types=1);

/**
 * Contract test: Partner Hub canonical tenant billing schema intent markers.
 *
 * Run:
 * php accounts/modules/addons/eazybackup/bin/dev/partnerhub_canonical_schema_contract_test.php
 */

$moduleFile = dirname(__DIR__, 2) . '/eazybackup.php';
$source = @file_get_contents($moduleFile);

if ($source === false) {
    fwrite(STDERR, "FAIL: unable to read {$moduleFile}\n");
    exit(1);
}

$markers = [
    'eb_customers.tenant_id column marker' => "eb_add_column_if_missing('eb_customers','tenant_id'",
    'eb_customers.tenant_id unique marker' => "CREATE UNIQUE INDEX IF NOT EXISTS uq_eb_customers_tenant_id ON eb_customers (tenant_id)",
    'eb_tenant_storage_links table marker' => "if (!\$schema->hasTable('eb_tenant_storage_links')) {",
    'eb_usage_ledger.tenant_id create marker' => "\$t->bigInteger('tenant_id')->nullable()->index();",
    'eb_usage_ledger.tenant_id backfill marker' => "eb_add_column_if_missing('eb_usage_ledger','tenant_id'",
    'eb_whitelabel_signup_events approved_by marker' => "eb_add_column_if_missing('eb_whitelabel_signup_events','approved_by_admin_id'",
    'eb_whitelabel_signup_events approved_at marker' => "eb_add_column_if_missing('eb_whitelabel_signup_events','approved_at'",
    'eb_whitelabel_signup_events approval_notes marker' => "eb_add_column_if_missing('eb_whitelabel_signup_events','approval_notes'",
];

$missing = [];
foreach ($markers as $name => $needle) {
    if (strpos($source, $needle) === false) {
        $missing[] = $name;
    }
}

if ($missing !== []) {
    foreach ($missing as $name) {
        echo "FAIL: missing {$name}\n";
    }
    exit(1);
}

echo "partnerhub-canonical-schema-contract-ok\n";
exit(0);
