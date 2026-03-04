-- Tenant restructuring (eb_tenants): drop obsolete tables.
-- Run only on dev / when no production data exists in these tables.
-- After running, activate eazybackup addon or call eazybackup_migrate_schema() to ensure new tables exist.

-- eazybackup: replaced by eb_tenants
DROP TABLE IF EXISTS eb_customer_user_links;
DROP TABLE IF EXISTS eb_customers;

-- eazybackup: eb_customer_comet_accounts replaced by eb_tenant_comet_accounts (if old table exists)
DROP TABLE IF EXISTS eb_customer_comet_accounts;

-- cloudstorage: replaced by eb_tenants / eb_tenant_users when eazybackup is active
-- Only drop if not using cloudstorage tenant APIs or after migrating
-- DROP TABLE IF EXISTS s3_backup_tenant_users;
-- DROP TABLE IF EXISTS s3_backup_tenants;
