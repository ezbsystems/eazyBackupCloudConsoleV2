-- Phase 20: MS365 restore archive export mode

ALTER TABLE `ms365_restore_runs`
  ADD COLUMN `restore_mode` VARCHAR(16) NOT NULL DEFAULT 'tenant' AFTER `conflict_policy`,
  ADD COLUMN `archive_object_key` VARCHAR(512) NULL AFTER `restore_mode`,
  ADD COLUMN `archive_bucket` VARCHAR(255) NULL AFTER `archive_object_key`,
  ADD COLUMN `archive_size_bytes` BIGINT UNSIGNED NULL AFTER `archive_bucket`,
  ADD COLUMN `archive_expires_at` INT UNSIGNED NULL AFTER `archive_size_bytes`;
