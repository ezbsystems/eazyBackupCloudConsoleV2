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
    'strict index helper marker' => 'function eb_require_index(',
    'eb_customers.tenant_id column marker' => "eb_add_column_if_missing('eb_customers','tenant_id'",
    'eb_customers.tenant_id unique marker' => "eb_require_index('eb_customers', 'uq_eb_customers_tenant_id'",
    'eb_tenant_storage_links table marker' => "if (!\$schema->hasTable('eb_tenant_storage_links')) {",
    'eb_tenant_storage_links unique index marker' => "eb_require_index('eb_tenant_storage_links', 'uq_tenant_storage_link'",
    'eb_tenant_storage_links tenant index marker' => "eb_require_index('eb_tenant_storage_links', 'idx_tenant_storage_link_tenant'",
    'eb_usage_ledger.tenant_id create marker' => "\$t->bigInteger('tenant_id')->nullable();",
    'eb_usage_ledger.tenant_id backfill marker' => "eb_add_column_if_missing('eb_usage_ledger','tenant_id'",
    'eb_usage_ledger.tenant_id index marker' => "eb_require_index('eb_usage_ledger', 'idx_usage_ledger_tenant_id'",
    'eb_tenants.public_id column marker' => "eb_add_column_if_missing('eb_tenants','public_id'",
    'eb_tenants.public_id index marker' => "eb_require_index(\n            'eb_tenants',\n            'idx_eb_tenants_public_id'",
    'eb_tenants.public_id backfill marker' => "whereNull('public_id')",
    'eb_whitelabel_signup_events approved_by marker' => "eb_add_column_if_missing('eb_whitelabel_signup_events','approved_by_admin_id'",
    'eb_whitelabel_signup_events approved_at marker' => "eb_add_column_if_missing('eb_whitelabel_signup_events','approved_at'",
    'eb_whitelabel_signup_events approval_notes marker' => "eb_add_column_if_missing('eb_whitelabel_signup_events','approval_notes'",
    'eb_whitelabel_signup_events approved_by index marker' => "eb_require_index('eb_whitelabel_signup_events', 'idx_wlse_approved_by'",
    'eb_whitelabel_signup_events approved_at index marker' => "eb_require_index('eb_whitelabel_signup_events', 'idx_wlse_approved_at'",
];

$forbidden = [
    'legacy helper used for eb_customers tenant index' => "eb_add_index_if_missing('eb_customers'",
    'legacy helper used for eb_tenant_storage_links unique index' => "eb_add_index_if_missing('eb_tenant_storage_links', \"CREATE UNIQUE INDEX",
    'legacy helper used for eb_tenant_storage_links tenant index' => "eb_add_index_if_missing('eb_tenant_storage_links', \"CREATE INDEX",
    'legacy helper used for eb_usage_ledger tenant index' => "eb_add_index_if_missing('eb_usage_ledger'",
    'legacy helper used for eb_whitelabel_signup_events approval indexes' => "eb_add_index_if_missing('eb_whitelabel_signup_events'",
];

$missing = [];
foreach ($markers as $name => $needle) {
    if (strpos($source, $needle) === false) {
        $missing[] = $name;
    }
}

foreach ($forbidden as $name => $needle) {
    if (strpos($source, $needle) !== false) {
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
