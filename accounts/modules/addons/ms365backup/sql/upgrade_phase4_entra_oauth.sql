ALTER TABLE `ms365_tenant_records`
  ADD COLUMN `azure_tenant_id` VARCHAR(64) NOT NULL DEFAULT '' AFTER `tenant_id`,
  ADD COLUMN `connection_status` VARCHAR(32) NOT NULL DEFAULT 'pending' AFTER `azure_tenant_id`,
  ADD COLUMN `consent_granted_at` INT UNSIGNED NULL DEFAULT NULL AFTER `connection_status`,
  ADD COLUMN `consent_granted_by_upn` VARCHAR(255) NOT NULL DEFAULT '' AFTER `consent_granted_at`,
  ADD COLUMN `platform_app_id` VARCHAR(64) NOT NULL DEFAULT '' AFTER `consent_granted_by_upn`,
  ADD COLUMN `last_health_check_at` INT UNSIGNED NULL DEFAULT NULL AFTER `platform_app_id`,
  ADD COLUMN `health_error` TEXT NULL AFTER `last_health_check_at`,
  ADD COLUMN `whmcs_service_id` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `whmcs_client_id`,
  ADD COLUMN `s3_bucket_id` INT UNSIGNED NULL DEFAULT NULL AFTER `s3_secret_key_enc`,
  ADD COLUMN `s3_bucket_name` VARCHAR(255) NOT NULL DEFAULT '' AFTER `s3_bucket_id`,
  ADD COLUMN `s3_user_id` INT UNSIGNED NULL DEFAULT NULL AFTER `s3_bucket_name`;

ALTER TABLE `ms365_tenant_records`
  ADD KEY `idx_ms365_tenant_azure` (`azure_tenant_id`),
  ADD KEY `idx_ms365_tenant_connection` (`connection_status`, `whmcs_client_id`);
