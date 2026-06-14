ALTER TABLE `ms365_tenant_records`
  ADD COLUMN `backup_user_id` INT UNSIGNED NULL DEFAULT NULL AFTER `whmcs_client_id`;

ALTER TABLE `ms365_tenant_records`
  ADD KEY `idx_ms365_tenant_backup_user` (`whmcs_client_id`, `backup_user_id`, `is_active`);
