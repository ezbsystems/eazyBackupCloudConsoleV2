ALTER TABLE `ms365_backup_runs`
  ADD COLUMN `resource_id` VARCHAR(191) NOT NULL DEFAULT '' AFTER `user_display_name`,
  ADD COLUMN `resource_type` VARCHAR(64) NOT NULL DEFAULT '' AFTER `resource_id`,
  ADD COLUMN `graph_id` VARCHAR(128) NOT NULL DEFAULT '' AFTER `resource_type`,
  ADD COLUMN `physical_key` VARCHAR(191) NOT NULL DEFAULT '' AFTER `graph_id`,
  ADD COLUMN `scope_json` TEXT NULL AFTER `backup_calendar`,
  ADD COLUMN `logical_sources_json` TEXT NULL AFTER `scope_json`;

ALTER TABLE `ms365_backup_runs`
  ADD KEY `idx_ms365_runs_physical` (`physical_key`, `created_at`),
  ADD KEY `idx_ms365_runs_resource_type` (`resource_type`, `status`);
