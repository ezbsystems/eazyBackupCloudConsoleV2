CREATE TABLE IF NOT EXISTS `ms365_tenant_records` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `whmcs_client_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `label` VARCHAR(255) NOT NULL DEFAULT '',
  `region` VARCHAR(64) NOT NULL DEFAULT 'GlobalPublicCloud',
  `tenant_id` VARCHAR(64) NOT NULL DEFAULT '',
  `client_id` VARCHAR(64) NOT NULL DEFAULT '',
  `app_secret_enc` TEXT NULL,
  `s3_endpoint` VARCHAR(512) NOT NULL DEFAULT '',
  `s3_bucket` VARCHAR(255) NOT NULL DEFAULT '',
  `s3_region` VARCHAR(64) NOT NULL DEFAULT 'us-east-1',
  `s3_access_key_enc` TEXT NULL,
  `s3_secret_key_enc` TEXT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` INT UNSIGNED NOT NULL,
  `updated_at` INT UNSIGNED NOT NULL,
  KEY `idx_ms365_tenant_records_client` (`whmcs_client_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `ms365_tenant_config`
  ADD COLUMN `s3_endpoint` VARCHAR(512) NOT NULL DEFAULT '' AFTER `app_secret_enc`,
  ADD COLUMN `s3_bucket` VARCHAR(255) NOT NULL DEFAULT '' AFTER `s3_endpoint`,
  ADD COLUMN `s3_region` VARCHAR(64) NOT NULL DEFAULT 'us-east-1' AFTER `s3_bucket`,
  ADD COLUMN `s3_access_key_enc` TEXT NULL AFTER `s3_region`,
  ADD COLUMN `s3_secret_key_enc` TEXT NULL AFTER `s3_access_key_enc`;

ALTER TABLE `ms365_backup_runs`
  ADD COLUMN `tenant_record_id` INT UNSIGNED NULL DEFAULT NULL AFTER `id`,
  ADD COLUMN `whmcs_client_id` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `tenant_record_id`;
