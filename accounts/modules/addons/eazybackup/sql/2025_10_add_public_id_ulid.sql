-- Add public ULID to eb_whitelabel_tenants for customer-facing URLs
ALTER TABLE `eb_whitelabel_tenants` ADD COLUMN `public_id` CHAR(26) NULL AFTER `last_build_id`;
CREATE UNIQUE INDEX `idx_eb_wl_tenants_public_id` ON `eb_whitelabel_tenants` (`public_id`);

-- Optional backfill can be performed by application code; this script intentionally
-- does not attempt to generate ULIDs in SQL. After backfill, you may enforce NOT NULL:
-- ALTER TABLE `eb_whitelabel_tenants` MODIFY `public_id` CHAR(26) NOT NULL;

