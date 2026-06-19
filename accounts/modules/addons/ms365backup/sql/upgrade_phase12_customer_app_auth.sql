ALTER TABLE `ms365_tenant_records`
  ADD COLUMN `connection_auth_mode` VARCHAR(32) NOT NULL DEFAULT 'platform_consent' AFTER `connection_status`;
