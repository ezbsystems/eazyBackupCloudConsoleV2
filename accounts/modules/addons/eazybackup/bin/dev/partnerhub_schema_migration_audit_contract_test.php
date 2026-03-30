<?php

declare(strict_types=1);

/**
 * Contract test: Partner Hub schema migration covers canonical tenant tables and required columns.
 *
 * Run:
 * php accounts/modules/addons/eazybackup/bin/dev/partnerhub_schema_migration_audit_contract_test.php
 */

$moduleRoot = dirname(__DIR__, 2);
$moduleFile = $moduleRoot . '/eazybackup.php';
$source = @file_get_contents($moduleFile);

if ($source === false) {
    fwrite(STDERR, "FAIL: unable to read {$moduleFile}\n");
    exit(1);
}

$markers = [
    'eb_tenants create block marker' => "if (!\$schema->hasTable('eb_tenants')) {",
    'eb_tenants public_id create marker' => "\$t->char('public_id',26)->nullable();",
    'eb_tenants contact_name create marker' => "\$t->string('contact_name', 191)->nullable();",
    'eb_tenants stripe_customer_id create marker' => "\$t->string('stripe_customer_id', 255)->nullable()->index();",
    'eb_tenants contact_name migration marker' => "eb_add_column_if_missing('eb_tenants','contact_name'",
    'eb_tenants stripe_customer_id migration marker' => "eb_add_column_if_missing('eb_tenants','stripe_customer_id'",
    'eb_tenant_users create block marker' => "if (!\$schema->hasTable('eb_tenant_users')) {",
    'eb_tenant_users password hash marker' => "\$t->string('password_hash', 255)->nullable();",
    'eb_tenant_users role marker' => "\$t->string('role', 32)->default('user');",
    'eb_tenant_users unique email marker' => "\$t->unique(['tenant_id','email'], 'uq_eb_tenant_users_tenant_email');",
    'eb_tenant_comet_accounts create block marker' => "if (!\$schema->hasTable('eb_tenant_comet_accounts')) {",
    'eb_tenant_comet_accounts comet username marker' => "\$t->string('comet_username', 255)->nullable();",
    'eb_tenant_comet_accounts unique comet marker' => "\$t->unique('comet_user_id', 'uq_eb_tenant_comet_accounts_comet_user_id');",
    'eb_service_links tenant_id create marker' => "\$t->bigInteger('tenant_id')->nullable();",
    'eb_service_links tenant_id migration marker' => "eb_add_column_if_missing('eb_service_links','tenant_id'",
    'eb_subscriptions tenant_id create marker' => "\$t->bigInteger('tenant_id')->nullable()->index();",
    'eb_subscriptions tenant_id migration marker' => "eb_add_column_if_missing('eb_subscriptions','tenant_id'",
    'eb_whitelabel_tenants canonical tenant create marker' => "\$t->bigInteger('canonical_tenant_id')->nullable()->index();",
    'eb_whitelabel_tenants public id create marker' => "\$t->char('public_id',26)->nullable();",
    'eb_whitelabel_tenants canonical tenant migration marker' => "eb_add_column_if_missing('eb_whitelabel_tenants','canonical_tenant_id'",
    'eb_msp_accounts default currency create marker' => "\$t->char('default_currency',3)->nullable();",
    'eb_invoice_cache tenant_id create marker' => "\$t->bigInteger('tenant_id')->nullable()->index();",
    'eb_payment_cache tenant_id create marker' => "\$t->bigInteger('tenant_id')->nullable()->index();",
    'eb_catalog_products base metric create marker' => "\$t->enum('base_metric_code',[ 'STORAGE_TB','DEVICE_COUNT','DISK_IMAGE','HYPERV_VM','PROXMOX_VM','VMWARE_VM','M365_USER','GENERIC','E3_STORAGE_GIB' ])->nullable();",
    'eb_catalog_products product template create marker' => "\$t->string('product_template', 50)->nullable();",
    'eb_catalog_products attributes create marker' => "\$t->text('attributes_json')->nullable();",
    'eb_catalog_products product template migration marker' => "eb_add_column_if_missing('eb_catalog_products','product_template'",
    'eb_catalog_products attributes migration marker' => "eb_add_column_if_missing('eb_catalog_products','attributes_json'",
    'eb_plan_components metric code create marker' => "\$t->enum('metric_code', ['STORAGE_TB','DEVICE_COUNT','DISK_IMAGE','HYPERV_VM','PROXMOX_VM','VMWARE_VM','M365_USER','GENERIC','E3_STORAGE_GIB']);",
    'eb_plan_components metric code enum alter marker' => "ALTER TABLE eb_plan_components MODIFY COLUMN metric_code ENUM('STORAGE_TB','DEVICE_COUNT','DISK_IMAGE','HYPERV_VM','PROXMOX_VM','VMWARE_VM','M365_USER','GENERIC','E3_STORAGE_GIB') NOT NULL",
    'eb_catalog_prices pricing scheme create marker' => "\$t->string('pricing_scheme', 20)->default('per_unit');",
    'eb_catalog_prices tiers mode create marker' => "\$t->string('tiers_mode', 20)->nullable();",
    'eb_catalog_prices tiers json create marker' => "\$t->text('tiers_json')->nullable();",
    'eb_catalog_prices pricing scheme migration marker' => "eb_add_column_if_missing('eb_catalog_prices','pricing_scheme'",
    'eb_catalog_prices tiers mode migration marker' => "eb_add_column_if_missing('eb_catalog_prices','tiers_mode'",
    'eb_catalog_prices tiers json migration marker' => "eb_add_column_if_missing('eb_catalog_prices','tiers_json'",
    'eb_plan_templates billing interval create marker' => "\$t->string('billing_interval', 10)->default('month');",
    'eb_plan_templates currency create marker' => "\$t->string('currency', 3)->default('CAD');",
    'eb_plan_templates status create marker' => "\$t->string('status', 20)->default('active');",
    'eb_plan_templates metadata create marker' => "\$t->text('metadata_json')->nullable();",
    'eb_plan_templates billing interval migration marker' => "eb_add_column_if_missing('eb_plan_templates','billing_interval'",
    'eb_plan_templates currency migration marker' => "eb_add_column_if_missing('eb_plan_templates','currency'",
    'eb_plan_templates status migration marker' => "eb_add_column_if_missing('eb_plan_templates','status'",
    'eb_plan_templates metadata migration marker' => "eb_add_column_if_missing('eb_plan_templates','metadata_json'",
    'eb_plan_instances tenant_id create marker' => "\$t->bigInteger('tenant_id')->nullable()->index();",
    'eb_plan_instances cancelled_at create marker' => "\$t->dateTime('cancelled_at')->nullable();",
    'eb_plan_instances cancel_reason create marker' => "\$t->string('cancel_reason', 255)->nullable();",
    'eb_plan_instances tenant_id migration marker' => "eb_add_column_if_missing('eb_plan_instances','tenant_id'",
    'eb_plan_instances cancelled_at migration marker' => "eb_add_column_if_missing('eb_plan_instances','cancelled_at'",
    'eb_plan_instances cancel_reason migration marker' => "eb_add_column_if_missing('eb_plan_instances','cancel_reason'",
    'eb_plan_instance_items metric code create marker' => "\$t->enum('metric_code', ['STORAGE_TB','DEVICE_COUNT','DISK_IMAGE','HYPERV_VM','PROXMOX_VM','VMWARE_VM','M365_USER','GENERIC','E3_STORAGE_GIB']);",
    'eb_plan_instance_items metric code enum alter marker' => "ALTER TABLE eb_plan_instance_items MODIFY COLUMN metric_code ENUM('STORAGE_TB','DEVICE_COUNT','DISK_IMAGE','HYPERV_VM','PROXMOX_VM','VMWARE_VM','M365_USER','GENERIC','E3_STORAGE_GIB') NOT NULL",
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

echo "partnerhub-schema-migration-audit-contract-ok\n";
exit(0);
